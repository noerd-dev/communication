<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Copies each tenant's configured custom SMTP user into its first mail sender account.
 *
 * Only rows that ACTUALLY sent through custom SMTP are migrated: use_custom_smtp = 1 AND a
 * non-empty host AND a non-empty username — the exact condition the old TenantSmtpResolver
 * required before it built a tenant mailer. Rows with the flag off, or with the flag on but
 * incomplete credentials, already sent through the .env mailer and keep doing so with no
 * sender row at all.
 *
 * from_email / reply_email are copied along because resolvedFromEmail() reads only the sender
 * from now on — a tenant whose From address comes from the settings row today must carry it
 * into the sender, or its From would change.
 *
 * Raw queries on purpose: the Eloquent model must not run here. Its tenant global scope yields
 * nothing without a resolved tenant, its boot hooks would fight the explicit is_default, and the
 * columns read below disappear one migration later. The password is therefore encrypted
 * explicitly — Crypt::encryptString() produces exactly what the `encrypted` cast reads back.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('communication_mail_senders') || ! Schema::hasTable('communication_settings')) {
            return;
        }

        // A legacy install reached this table through the marketing_settings rename, so never
        // assume a column exists.
        foreach (['use_custom_smtp', 'smtp_host', 'smtp_username'] as $column) {
            if (! Schema::hasColumn('communication_settings', $column)) {
                return;
            }
        }

        $hasPort = Schema::hasColumn('communication_settings', 'smtp_port');
        $hasEncryption = Schema::hasColumn('communication_settings', 'smtp_encryption');
        $hasPassword = Schema::hasColumn('communication_settings', 'smtp_password');
        $now = now();

        DB::table('communication_settings')
            ->where('use_custom_smtp', true)
            ->whereNotNull('smtp_host')->where('smtp_host', '<>', '')
            ->whereNotNull('smtp_username')->where('smtp_username', '<>', '')
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($hasPort, $hasEncryption, $hasPassword, $now): void {
                foreach ($rows as $row) {
                    // Re-runnable: a tenant that already owns a sender is skipped.
                    $alreadyMigrated = DB::table('communication_mail_senders')
                        ->where('tenant_id', $row->tenant_id)
                        ->exists();

                    if ($alreadyMigrated) {
                        continue;
                    }

                    // Values were entered by hand and at least one carries a leading space.
                    $host = mb_trim((string) $row->smtp_host);
                    $username = mb_trim((string) $row->smtp_username);
                    $fromEmail = mb_trim((string) ($row->from_email ?? ''));
                    $replyEmail = mb_trim((string) ($row->reply_email ?? ''));
                    $password = $hasPassword ? (string) ($row->smtp_password ?? '') : '';

                    DB::table('communication_mail_senders')->insert([
                        'tenant_id' => $row->tenant_id,
                        'name' => $fromEmail ?: $host,
                        'from_email' => $fromEmail ?: null,
                        'reply_email' => $replyEmail ?: null,
                        'smtp_host' => $host,
                        'smtp_port' => $hasPort ? $row->smtp_port : null,
                        'smtp_encryption' => $hasEncryption ? (mb_trim((string) ($row->smtp_encryption ?? '')) ?: null) : null,
                        'smtp_username' => $username,
                        'smtp_password' => $password === '' ? null : Crypt::encryptString($password),
                        'is_default' => true,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    public function down(): void
    {
        // Irreversible by design: the next migration drops the source columns, so there is
        // nothing to copy back into. Rolling this back means restoring a dump.
    }
};
