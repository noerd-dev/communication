<?php

namespace Noerd\Communication\Services;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;
use Noerd\Communication\Models\MailSender;

/**
 * Resolves the Mailer outgoing mail goes through.
 *
 * Resolution order: an explicit MailSender -> the tenant's default MailSender -> the .env
 * default mailer. There is no toggle; a tenant without an active default sender account simply
 * sends through the platform mailer.
 */
class TenantSmtpResolver
{
    /**
     * Backward-compatible entry point. Accepts a MailSender, anything carrying a tenant_id
     * (the CommunicationSetting model, an array) or null.
     */
    public function resolve(mixed $settings = null): Mailer
    {
        if ($settings instanceof MailSender) {
            return $this->resolveForSender($settings);
        }

        return $this->resolveForTenant($this->extractTenantId($settings));
    }

    public function resolveForTenant(?int $tenantId): Mailer
    {
        return $this->resolveForSender(
            $tenantId ? MailSender::defaultForTenant($tenantId) : null,
        );
    }

    /**
     * A sender without its own SMTP credentials is still a valid account — it carries
     * from/reply and relays through the .env mailer.
     */
    public function resolveForSender(?MailSender $sender): Mailer
    {
        if (! $sender || ! $sender->usesCustomSmtp()) {
            return Mail::mailer();
        }

        $name = $this->mailerName($sender);

        config()->set("mail.mailers.{$name}", $this->mailerConfig($sender));

        return Mail::mailer($name);
    }

    /**
     * Per-account AND per-credential-revision. Laravel's MailManager caches resolved mailers by
     * name with no way to forget a single one, so a name keyed only by tenant would collide
     * across that tenant's accounts, and one keyed only by id would keep serving a stale
     * transport after credentials are edited — fatal in a long-lived queue worker.
     */
    public function mailerName(MailSender $sender): string
    {
        $fingerprint = mb_substr(sha1((string) json_encode($this->mailerConfig($sender))), 0, 8);

        return 'communication_sender_' . ($sender->getKey() ?? 'new') . '_' . $fingerprint;
    }

    /**
     * @return array<string, mixed>
     */
    private function mailerConfig(MailSender $sender): array
    {
        $config = [
            'transport' => 'smtp',
            'host' => $sender->smtp_host,
            'port' => (int) ($sender->smtp_port ?: 587),
            'username' => $sender->smtp_username,
            'password' => $sender->smtp_password,
            'timeout' => 30,
        ];

        // Laravel 12 derives the SMTP scheme from `scheme`, falling back to
        // port == 465 ? smtps : smtp — the `encryption` key is ignored entirely. Force smtps
        // for an explicit ssl account; leave tls to the port heuristic so an existing
        // tls-on-465 account is not downgraded.
        if ($sender->smtp_encryption === 'ssl') {
            $config['scheme'] = 'smtps';
        }

        return $config;
    }

    private function extractTenantId(mixed $settings): ?int
    {
        if (is_array($settings)) {
            return isset($settings['tenant_id']) ? (int) $settings['tenant_id'] : null;
        }

        if (is_object($settings)) {
            return isset($settings->tenant_id) ? (int) $settings->tenant_id : null;
        }

        return null;
    }
}
