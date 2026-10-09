<?php

namespace Noerd\Communication\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Jobs\SendTextMessage;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Models\CommunicationUsage;
use Noerd\Communication\Models\MailSender;
use Noerd\Communication\Support\PhoneNumber;
use Noerd\Communication\Support\TextMessage;
use Noerd\Communication\Support\TextMessageChannelRegistry;
use RuntimeException;
use Symfony\Component\Mime\Email;
use Throwable;

class Communicator
{
    public const COMMUNICATION_HEADER = 'X-Communication-Id';

    public function __construct(
        private TenantSmtpResolver $smtpResolver,
        private ?TextMessageChannelRegistry $textChannels = null,
    ) {
        $this->textChannels ??= app(TextMessageChannelRegistry::class);
    }

    /**
     * Send a Mailable through the central communication channel and log it to the
     * communications table. Re-throws send failures after logging them with
     * status=failed, so existing job retry logic stays intact.
     *
     * Subject, from and body are filled by the LogMessageSentFallback listener
     * once the framework dispatches the MessageSent event, so this method does
     * not require the Mailable to expose envelope() (legacy build() works too).
     *
     * @param  string|array<int,string>|Model|null  $to  Email, list of emails, a record carrying an `email` attribute, or null to skip sending
     * @param  Model|null  $contact  The record this mail concerns; falls back to $to when that is a record
     * @param  object|array|null  $tenantSettings  Forwarded to TenantSmtpResolver
     * @param  array<string,mixed>  $metadata  Extra data persisted as JSON (cc, bcc, headers, ...)
     * @param  Model|null  $model  Source record this mail belongs to (stored polymorphically)
     * @param  MailSender|null  $sender  Explicit sender account; wins over the tenant's default
     */
    public function send(
        Mailable $mailable,
        string|array|Model|null $to,
        ?Model $contact = null,
        mixed $tenantSettings = null,
        array $metadata = [],
        bool $queue = false,
        ?Model $model = null,
        ?MailSender $sender = null,
    ): ?Communication {
        $recipients = $this->resolveRecipients($to);

        if ($recipients === []) {
            return null;
        }

        $resolvedContact = $this->resolveContact($contact, $to);
        $tenantId = $sender?->tenant_id !== null
            ? (int) $sender->tenant_id
            : $this->resolveTenantId($tenantSettings, $resolvedContact);

        if ($sender) {
            $metadata['mail_sender_id'] = $sender->getKey();
        }

        $communication = Communication::create([
            'tenant_id' => $tenantId,
            'contact_type' => $resolvedContact?->getMorphClass(),
            'contact_id' => $resolvedContact?->getKey(),
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'type' => CommunicationType::Email,
            'status' => $queue ? CommunicationStatus::Queued : CommunicationStatus::Sent,
            'to' => implode(', ', $recipients),
            'mailable_class' => $mailable::class,
            'metadata' => $metadata ?: null,
            'sent_at' => $queue ? null : now(),
        ]);

        $this->tagMailable($mailable, $communication->id);

        try {
            // An explicit sender wins over the tenant's default.
            $mailer = $sender
                ? $this->smtpResolver->resolveForSender($sender)
                : $this->smtpResolver->resolve($tenantSettings);
            $pendingMail = $mailer->to($recipients);

            if ($queue) {
                $pendingMail->queue($mailable);
            } else {
                $pendingMail->send($mailable);
            }

            return $communication->refresh();
        } catch (Throwable $e) {
            $communication->forceFill([
                'status' => CommunicationStatus::Failed,
                'error_message' => $e->getMessage(),
            ])->save();

            throw $e;
        }
    }

    /**
     * Send a text message (SMS, WhatsApp, ...) through the driver registered for the channel
     * and log it to the communications table. Returns null — and sends nothing — when there
     * is no valid phone number or no available driver for the channel.
     *
     * The row is written first; the message is then delivered at once or, with `queue`,
     * by the SendTextMessage job. A delivery failure marks the row failed and re-throws.
     * Every accepted message is recorded as a CommunicationUsage for invoicing.
     *
     * Consent is the caller's business: a module messaging its customers must only call this
     * for recipients who opted in to the channel.
     *
     * @param  string|Model|null  $to  Phone number, a record carrying a `phone` attribute, or null to skip sending
     * @param  string|null  $template  Stable template key a driver may map to a pre-approved provider template
     * @param  array<string,string>  $variables  Values for the template placeholders
     * @param  Model|null  $contact  The record this message concerns; falls back to $to when that is a record
     * @param  Model|null  $model  Source record this message belongs to (stored polymorphically)
     * @param  array<string,mixed>  $metadata  Extra data persisted as JSON
     */
    public function sendText(
        CommunicationType $type,
        string|Model|null $to,
        string $body,
        ?string $template = null,
        array $variables = [],
        ?Model $contact = null,
        ?Model $model = null,
        ?int $tenantId = null,
        array $metadata = [],
        bool $queue = false,
    ): ?Communication {
        if (! $type->isTextMessage()) {
            throw new RuntimeException("{$type->value} is not a text message channel.");
        }

        $phone = PhoneNumber::normalize($to instanceof Model ? $to->getAttribute('phone') : $to);

        if ($phone === null) {
            return null;
        }

        $resolvedContact = $contact ?? ($to instanceof Model ? $to : null);
        $tenantId ??= $this->resolveTenantId(null, $resolvedContact ?? $model);
        $driver = $this->textChannels->driverFor($type);

        if ($driver === null || ! $driver->isAvailable($tenantId)) {
            return null;
        }

        $communication = Communication::create([
            'tenant_id' => $tenantId,
            'contact_type' => $resolvedContact?->getMorphClass(),
            'contact_id' => $resolvedContact?->getKey(),
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'type' => $type,
            'status' => CommunicationStatus::Queued,
            'to' => $phone,
            'body' => $body,
            'metadata' => array_merge($metadata, array_filter([
                'provider' => $driver->provider(),
                'template' => $template,
                'variables' => $variables ?: null,
            ])),
        ]);

        if ($queue) {
            SendTextMessage::dispatch($communication->id);

            return $communication;
        }

        $this->deliverText($communication);

        return $communication->refresh();
    }

    /**
     * Deliver a logged text message through its channel's driver. A message that was
     * already sent is never sent twice (a retried job after a lost acknowledgement).
     */
    public function deliverText(Communication $communication): void
    {
        if ($communication->status === CommunicationStatus::Sent) {
            return;
        }

        try {
            $driver = $this->textChannels->driverFor($communication->type);

            if ($driver === null) {
                throw new RuntimeException("No text message driver registered for {$communication->type->value}.");
            }

            $result = $driver->send(new TextMessage(
                to: $communication->to,
                body: (string) $communication->body,
                template: $communication->metadata['template'] ?? null,
                variables: $communication->metadata['variables'] ?? [],
                tenantId: $communication->tenant_id,
            ));
        } catch (Throwable $e) {
            $communication->forceFill([
                'status' => CommunicationStatus::Failed,
                'error_message' => $e->getMessage(),
            ])->save();

            throw $e;
        }

        $communication->forceFill([
            'status' => CommunicationStatus::Sent,
            'from' => $result->from,
            'message_id' => $result->messageId,
            'error_message' => null,
            'sent_at' => now(),
        ])->save();

        CommunicationUsage::create([
            'tenant_id' => $communication->tenant_id,
            'communication_id' => $communication->id,
            'type' => $communication->type,
            'provider' => $driver->provider(),
            'units' => max(1, $result->units),
            'cost' => $result->cost,
            'currency' => $result->currency,
            'occurred_at' => now(),
        ]);
    }

    /**
     * @return array<int,string>
     */
    private function resolveRecipients(string|array|Model|null $to): array
    {
        if ($to === null) {
            return [];
        }

        if ($to instanceof Model) {
            return array_values(array_filter([$to->getAttribute('email')]));
        }

        if (is_array($to)) {
            return array_values(array_filter($to));
        }

        return $to === '' ? [] : [$to];
    }

    private function resolveContact(?Model $contact, string|array|Model|null $to): ?Model
    {
        if ($contact !== null) {
            return $contact;
        }

        return $to instanceof Model ? $to : null;
    }

    private function resolveTenantId(mixed $tenantSettings, ?Model $contact): ?int
    {
        if (is_array($tenantSettings) && isset($tenantSettings['tenant_id'])) {
            return (int) $tenantSettings['tenant_id'];
        }

        if (is_object($tenantSettings) && isset($tenantSettings->tenant_id)) {
            return (int) $tenantSettings->tenant_id;
        }

        if ($contact?->getAttribute('tenant_id')) {
            return (int) $contact->getAttribute('tenant_id');
        }

        if (auth()->check() && (auth()->user()->selected_tenant_id ?? null)) {
            return (int) auth()->user()->selected_tenant_id;
        }

        return null;
    }

    private function tagMailable(Mailable $mailable, int $communicationId): void
    {
        $mailable->withSymfonyMessage(function (Email $message) use ($communicationId): void {
            $message->getHeaders()->addTextHeader(self::COMMUNICATION_HEADER, (string) $communicationId);
        });
    }
}
