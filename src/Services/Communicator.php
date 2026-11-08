<?php

namespace Noerd\Communication\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Noerd\Communication\Enums\CommunicationDirection;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Events\TextMessageReceived;
use Noerd\Communication\Jobs\SendTextMessage;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Models\CommunicationUsage;
use Noerd\Communication\Models\MailSender;
use Noerd\Communication\Support\CommunicationHeader;
use Noerd\Communication\Support\PhoneNumber;
use Noerd\Communication\Support\TextMessage;
use Noerd\Communication\Support\TextMessageChannelRegistry;
use Noerd\Support\TenantContext;
use RuntimeException;
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

        $communication = $this->forTenant($tenantId, fn(): Communication => Communication::create([
            'tenant_id' => $tenantId,
            'contact_type' => $resolvedContact?->getMorphClass(),
            'contact_id' => $resolvedContact?->getKey(),
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'type' => CommunicationType::Email,
            'direction' => CommunicationDirection::Outbound,
            'status' => $queue ? CommunicationStatus::Queued : CommunicationStatus::Sent,
            'to' => implode(', ', $recipients),
            'mailable_class' => $mailable::class,
            'metadata' => $metadata ?: null,
            'sent_at' => $queue ? null : now(),
        ]));

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

        $communication = $this->forTenant($tenantId, fn(): Communication => Communication::create([
            'tenant_id' => $tenantId,
            'contact_type' => $resolvedContact?->getMorphClass(),
            'contact_id' => $resolvedContact?->getKey(),
            'model_type' => $model?->getMorphClass(),
            'model_id' => $model?->getKey(),
            'type' => $type,
            'direction' => CommunicationDirection::Outbound,
            'status' => CommunicationStatus::Queued,
            'to' => $phone,
            'body' => $body,
            'metadata' => array_merge($metadata, array_filter([
                'provider' => $driver->provider(),
                'template' => $template,
                'variables' => $variables ?: null,
            ])),
        ]));

        if ($queue) {
            SendTextMessage::dispatch($communication->id);

            return $communication;
        }

        $this->deliverText($communication);

        return $communication->refresh();
    }

    /**
     * Log a text message somebody sent to the platform (a reply) and fire
     * TextMessageReceived. A provider module calls this from its webhook; this
     * module never knows the provider.
     *
     * The message is matched to the latest outbound text message to the sender's
     * number — of the same channel first, then of any text channel, across all
     * tenants: that message's tenant, contact and model become the reply's, and
     * `in_reply_to_id` points at it. A sender nobody wrote to is logged without a
     * tenant (system mode) and reaches no tenant's screens.
     *
     * A provider retries a webhook it got no answer for: a message id that was
     * logged already returns the existing row and fires nothing.
     *
     * Returns null — and logs nothing — for a sender without a valid number.
     *
     * @param  array<string,mixed>  $metadata  Extra data persisted as JSON (media URLs, ...)
     */
    public function receiveText(
        CommunicationType $type,
        string $from,
        ?string $to,
        string $body,
        ?string $messageId = null,
        ?string $provider = null,
        array $metadata = [],
    ): ?Communication {
        if (! $type->isTextMessage()) {
            throw new RuntimeException("{$type->value} is not a text message channel.");
        }

        $phone = PhoneNumber::normalize($from);

        if ($phone === null) {
            return null;
        }

        if ($messageId !== null && $messageId !== '') {
            $existing = Communication::acrossTenants()
                ->where('direction', CommunicationDirection::Inbound)
                ->where('message_id', $messageId)
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $original = $this->latestOutboundTextTo($phone, $type);
        $tenantId = $original?->tenant_id !== null ? (int) $original->tenant_id : null;

        return $this->inTenant($tenantId, function () use ($tenantId, $original, $type, $phone, $to, $body, $messageId, $provider, $metadata): Communication {
            $communication = Communication::create([
                'tenant_id' => $tenantId,
                'contact_type' => $original?->contact_type,
                'contact_id' => $original?->contact_id,
                'model_type' => $original?->model_type,
                'model_id' => $original?->model_id,
                'type' => $type,
                'direction' => CommunicationDirection::Inbound,
                'status' => CommunicationStatus::Received,
                'from' => $phone,
                'to' => PhoneNumber::normalize($to) ?? (string) $to,
                'body' => $body,
                'message_id' => $messageId ?: null,
                'in_reply_to_id' => $original?->id,
                'metadata' => array_merge($metadata, array_filter(['provider' => $provider])) ?: null,
                'sent_at' => now(),
            ]);

            // A message nobody wrote to the sender of belongs to no tenant — not
            // even to the one a signed-in user happens to have selected (the
            // tenant trait stamps that on create).
            if ($tenantId === null && $communication->tenant_id !== null) {
                $communication->forceFill(['tenant_id' => null])->saveQuietly();
            }

            TextMessageReceived::dispatch($communication);

            return $communication;
        });
    }

    /**
     * Give an inbound text message nobody could match (no outbound message to
     * the sender's number — a new number) to the record a module recognised as
     * its sender: the message takes that record's tenant and becomes its
     * contact, then TextMessageReceived fires again inside that tenant. Only an
     * inbound message without tenant can be assigned; any other is returned
     * unchanged.
     */
    public function assignInbound(Communication $communication, Model $contact): Communication
    {
        $tenantId = $contact->getAttribute('tenant_id') !== null ? (int) $contact->getAttribute('tenant_id') : null;

        if (! $communication->isInbound() || $communication->tenant_id !== null || $tenantId === null) {
            return $communication;
        }

        return $this->inTenant($tenantId, function () use ($communication, $contact, $tenantId): Communication {
            $communication->forceFill([
                'tenant_id' => $tenantId,
                'contact_type' => $contact->getMorphClass(),
                'contact_id' => $contact->getKey(),
            ])->save();

            TextMessageReceived::dispatch($communication);

            return $communication;
        });
    }

    /**
     * Record what the provider reports about an outbound text message later
     * (its status callback): delivered, read or failed after all — with the
     * provider's reason. A report never moves a message back: a late "sent"
     * after "delivered" is ignored, and a delivered message cannot fail. A
     * message id this log does not know returns null.
     */
    public function updateTextStatus(string $messageId, CommunicationStatus $status, ?string $error = null): ?Communication
    {
        $communication = Communication::acrossTenants()
            ->where('direction', CommunicationDirection::Outbound)
            ->where('message_id', $messageId)
            ->first();

        if ($communication === null || $status === CommunicationStatus::Received || $status === CommunicationStatus::Queued) {
            return $communication;
        }

        if ($status->progress() <= $communication->status->progress()) {
            return $communication;
        }

        $tenantId = $communication->tenant_id !== null ? (int) $communication->tenant_id : null;

        return $this->inTenant($tenantId, function () use ($communication, $status, $error): Communication {
            $communication->forceFill([
                'status' => $status,
                'error_message' => $status === CommunicationStatus::Failed ? ($error ?: $communication->error_message) : null,
                'metadata' => array_merge($communication->metadata ?? [], [$status->value . '_at' => now()->toIso8601String()]),
            ])->save();

            return $communication;
        });
    }

    /**
     * Deliver a logged text message through its channel's driver. A message that was
     * already sent is never sent twice (a retried job after a lost acknowledgement).
     */
    public function deliverText(Communication $communication): void
    {
        if ($communication->status->wasSent()) {
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

        $usageTenantId = $communication->tenant_id !== null ? (int) $communication->tenant_id : null;

        $this->forTenant($usageTenantId, fn(): CommunicationUsage => CommunicationUsage::create([
            'tenant_id' => $usageTenantId,
            'communication_id' => $communication->id,
            'type' => $communication->type,
            'provider' => $driver->provider(),
            'units' => max(1, $result->units),
            'cost' => $result->cost,
            'currency' => $result->currency,
            'occurred_at' => now(),
        ]));
    }

    /**
     * The latest outbound text message to a number: of the given channel first,
     * then of any text channel (a WhatsApp reply to an SMS conversation).
     */
    private function latestOutboundTextTo(string $phone, CommunicationType $type): ?Communication
    {
        $textTypes = array_map(
            fn(CommunicationType $candidate): string => $candidate->value,
            array_values(array_filter(CommunicationType::cases(), fn(CommunicationType $candidate): bool => $candidate->isTextMessage())),
        );

        return Communication::acrossTenants()
            ->where('direction', CommunicationDirection::Outbound)
            ->where('to', $phone)
            ->whereIn('type', $textTypes)
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [$type->value])
            ->latest('id')
            ->first();
    }

    /**
     * Run inside the tenant's context, or in system mode without a tenant.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function inTenant(?int $tenantId, Closure $callback): mixed
    {
        return $tenantId === null ? TenantContext::system($callback) : TenantContext::run($tenantId, $callback);
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

        // A job, command or public frontend working in a tenant (the job's
        // dispatcher, TenantContext::run(), a shop middleware).
        if (TenantContext::current() !== null) {
            return TenantContext::current();
        }

        if (auth()->check() && (auth()->user()->selected_tenant_id ?? null)) {
            return (int) auth()->user()->selected_tenant_id;
        }

        return null;
    }

    /**
     * Write a log row of the given tenant. A message no tenant could be resolved
     * for (a platform mail to a plain address) is tenant-less data: its row is
     * written in system mode, so the missing tenant context is deliberate.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    private function forTenant(?int $tenantId, Closure $callback): mixed
    {
        return $tenantId === null ? TenantContext::system($callback) : $callback();
    }

    private function tagMailable(Mailable $mailable, int $communicationId): void
    {
        $mailable->withSymfonyMessage(new CommunicationHeader($communicationId));
    }
}
