<?php

namespace Noerd\Communication\Services;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Mail;

class TenantSmtpResolver
{
    /**
     * Resolve a mailer instance for tenant-specific SMTP settings.
     * Returns the default mailer when settings are missing or incomplete.
     *
     * Expected settings object/array keys:
     * - use_custom_smtp (only custom SMTP is used when truthy, otherwise the default mailer)
     * - smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password
     * - tenant_id (for unique mailer name)
     */
    public function resolve(mixed $settings = null): Mailer
    {
        if ($settings === null) {
            return Mail::mailer();
        }

        if (! $this->extract($settings, 'use_custom_smtp')) {
            return Mail::mailer();
        }

        $host = $this->extract($settings, 'smtp_host');
        $username = $this->extract($settings, 'smtp_username');

        if (empty($host) || empty($username)) {
            return Mail::mailer();
        }

        $tenantId = $this->extract($settings, 'tenant_id') ?? 'default';
        $name = "communication_tenant_{$tenantId}";

        config()->set("mail.mailers.{$name}", [
            'transport' => 'smtp',
            'host' => $host,
            'port' => (int) ($this->extract($settings, 'smtp_port') ?: 587),
            'encryption' => $this->extract($settings, 'smtp_encryption') ?: 'tls',
            'username' => $username,
            'password' => $this->extract($settings, 'smtp_password'),
            'timeout' => 30,
        ]);

        return Mail::mailer($name);
    }

    private function extract(mixed $settings, string $key): mixed
    {
        if (is_array($settings)) {
            return $settings[$key] ?? null;
        }

        if (is_object($settings)) {
            return $settings->{$key} ?? null;
        }

        return null;
    }
}
