<?php

namespace Noerd\Communication\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Communication\Database\Factories\MailSenderFactory;
use Noerd\Traits\BelongsToTenant;

/**
 * One outgoing sender account.
 *
 * A tenant owns any number of them; exactly one active account carries is_default, and that
 * one is used whenever a caller does not name a specific sender. No default — or no account
 * at all — means the .env mailer. The fallback is implicit; there is no toggle.
 */
class MailSender extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'communication_mail_senders';

    protected $guarded = ['id'];

    /**
     * The account outgoing mail is sent through when no explicit sender is named.
     * Unscoped: queue workers and console commands carry no tenant context.
     */
    public static function defaultForTenant(int $tenantId): ?self
    {
        return self::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Promote the first active account when a tenant has no default left — after a demotion,
     * a deactivation or a delete. saveQuietly() so the saved hook that called us cannot
     * re-enter.
     */
    public static function electDefaultForTenant(int $tenantId): void
    {
        $query = self::withoutGlobalScopes()->where('tenant_id', $tenantId);

        if ((clone $query)->where('is_default', true)->where('is_active', true)->exists()) {
            return;
        }

        $candidate = (clone $query)->where('is_active', true)->orderBy('id')->first();

        $candidate?->forceFill(['is_default' => true])->saveQuietly();
    }

    /**
     * Whether this account sends through its own SMTP server. Replaces the removed
     * use_custom_smtp flag with the condition the resolver always actually applied.
     * An account without credentials is still valid — it relays through the .env mailer.
     */
    public function usesCustomSmtp(): bool
    {
        return filled($this->smtp_host) && filled($this->smtp_username);
    }

    protected static function newFactory(): MailSenderFactory
    {
        return MailSenderFactory::new();
    }

    protected static function booted(): void
    {
        // Deliberately `creating`, not `saving`: Eloquent fires saving BEFORE creating, and
        // BelongsToTenant stamps tenant_id in creating. A first-row check inside saving would
        // count against tenant_id = null and make EVERY new account the default.
        static::creating(function (self $sender): void {
            if (self::withoutGlobalScopes()->where('tenant_id', $sender->tenant_id)->doesntExist()) {
                $sender->is_default = true;
                $sender->is_active = true;
            }
        });

        // is_default and is_active can never contradict each other. Promoting wins: marking an
        // inactive account as default activates it; deactivating an account demotes it.
        static::saving(function (self $sender): void {
            if ($sender->is_default && $sender->isDirty('is_default')) {
                $sender->is_active = true;

                return;
            }

            if (! $sender->is_active) {
                $sender->is_default = false;
            }
        });

        static::saved(function (self $sender): void {
            if ($sender->is_default) {
                // Mass update through the query builder: fires no model events, so no recursion.
                self::withoutGlobalScopes()
                    ->where('tenant_id', $sender->tenant_id)
                    ->whereKeyNot($sender->getKey())
                    ->where('is_default', true)
                    ->update(['is_default' => false]);

                return;
            }

            self::electDefaultForTenant((int) $sender->tenant_id);
        });

        static::deleted(function (self $sender): void {
            if ($sender->is_default) {
                self::electDefaultForTenant((int) $sender->tenant_id);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'smtp_port' => 'integer',
            'smtp_password' => 'encrypted',
        ];
    }
}
