<?php

namespace Noerd\Marketing\Services;

use Illuminate\Mail\Mailable;
use Noerd\Customer\Models\Customer;
use Noerd\Marketing\Enums\CommunicationStatus;
use Noerd\Marketing\Enums\CommunicationType;
use Noerd\Marketing\Models\Communication;
use Symfony\Component\Mime\Email;
use Throwable;

class Communicator
{
    public const COMMUNICATION_HEADER = 'X-Marketing-Communication-Id';

    public function __construct(
        private TenantSmtpResolver $smtpResolver,
    ) {}

    /**
     * Send a Mailable through the central marketing channel and log it to the
     * communications table. Re-throws send failures after logging them with
     * status=failed, so existing job retry logic stays intact.
     *
     * Subject, from and body are filled by the LogMessageSentFallback listener
     * once the framework dispatches the MessageSent event, so this method does
     * not require the Mailable to expose envelope() (legacy build() works too).
     *
     * @param  string|array<int,string>|Customer|null  $to          Email, list of emails, Customer (extracts email), or null to skip sending
     * @param  Customer|int|null                       $customer    Explicit customer link; falls back to $to if Customer
     * @param  object|array|null                       $tenantSettings  Forwarded to TenantSmtpResolver
     * @param  array<string,mixed>                     $metadata    Extra data persisted as JSON (cc, bcc, headers, ...)
     */
    public function send(
        Mailable $mailable,
        string|array|Customer|null $to,
        Customer|int|null $customer = null,
        mixed $tenantSettings = null,
        array $metadata = [],
        bool $queue = false,
    ): ?Communication {
        $recipients = $this->resolveRecipients($to);

        if ($recipients === []) {
            return null;
        }

        $resolvedCustomer = $this->resolveCustomer($customer, $to);
        $tenantId = $this->resolveTenantId($tenantSettings, $resolvedCustomer);

        $communication = Communication::create([
            'tenant_id' => $tenantId,
            'customer_id' => $resolvedCustomer instanceof Customer ? $resolvedCustomer->id : $resolvedCustomer,
            'type' => CommunicationType::Email,
            'status' => $queue ? CommunicationStatus::Queued : CommunicationStatus::Sent,
            'to' => implode(', ', $recipients),
            'mailable_class' => $mailable::class,
            'metadata' => $metadata ?: null,
            'sent_at' => $queue ? null : now(),
        ]);

        $this->tagMailable($mailable, $communication->id);

        try {
            $mailer = $this->smtpResolver->resolve($tenantSettings);
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
    private function resolveRecipients(string|array|Customer|null $to): array
    {
        if ($to === null) {
            return [];
        }

        if ($to instanceof Customer) {
            return array_values(array_filter([$to->email]));
        }

        if (is_array($to)) {
            return array_values(array_filter($to));
        }

        return $to === '' ? [] : [$to];
    }

    private function resolveCustomer(Customer|int|null $customer, string|array|Customer|null $to): Customer|int|null
    {
        if ($customer !== null) {
            return $customer;
        }

        return $to instanceof Customer ? $to : null;
    }

    private function resolveTenantId(mixed $tenantSettings, Customer|int|null $customer): ?int
    {
        if (is_array($tenantSettings) && isset($tenantSettings['tenant_id'])) {
            return (int) $tenantSettings['tenant_id'];
        }

        if (is_object($tenantSettings) && isset($tenantSettings->tenant_id)) {
            return (int) $tenantSettings->tenant_id;
        }

        if ($customer instanceof Customer && $customer->tenant_id) {
            return (int) $customer->tenant_id;
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
