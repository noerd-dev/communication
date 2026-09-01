<?php

namespace Noerd\Communication\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Models\MailSender;
use Symfony\Component\Mime\Email;
use Throwable;

class Communicator
{
    public const COMMUNICATION_HEADER = 'X-Communication-Id';

    public function __construct(
        private TenantSmtpResolver $smtpResolver,
    ) {}

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
