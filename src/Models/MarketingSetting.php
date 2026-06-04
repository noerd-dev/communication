<?php

namespace Noerd\Marketing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Marketing\Database\Factories\MarketingSettingFactory;
use Noerd\Traits\BelongsToTenant;

class MarketingSetting extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    public static function forTenant(int $tenantId): ?self
    {
        return self::withoutGlobalScopes()->firstWhere('tenant_id', $tenantId);
    }

    /**
     * The from address to use for outgoing mail. The tenant's own from_email is
     * only honored when use_custom_smtp is active; otherwise the .env default
     * (MAIL_FROM_ADDRESS) is used.
     */
    public function resolvedFromEmail(): string
    {
        if ($this->use_custom_smtp && $this->from_email) {
            return $this->from_email;
        }

        return (string) config('mail.from.address');
    }

    /**
     * The reply-to address to use for outgoing mail. Only honored when
     * use_custom_smtp is active; otherwise no custom reply-to is applied.
     */
    public function resolvedReplyEmail(): ?string
    {
        return $this->use_custom_smtp ? ($this->reply_email ?: null) : null;
    }

    protected static function newFactory(): MarketingSettingFactory
    {
        return MarketingSettingFactory::new();
    }

    protected function casts(): array
    {
        return [
            'use_custom_smtp' => 'boolean',
        ];
    }
}
