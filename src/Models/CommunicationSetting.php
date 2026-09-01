<?php

namespace Noerd\Communication\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Noerd\Communication\Database\Factories\CommunicationSettingFactory;
use Noerd\Traits\BelongsToTenant;

class CommunicationSetting extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $guarded = ['id'];

    private ?MailSender $defaultSender = null;

    private bool $defaultSenderLoaded = false;

    public static function forTenant(int $tenantId): ?self
    {
        return self::withoutGlobalScopes()->firstWhere('tenant_id', $tenantId);
    }

    /**
     * The tenant's default sender account, or null when the tenant runs on the platform
     * mailer. Memoized: the mailables call the two resolvers below in pairs.
     */
    public function defaultSender(): ?MailSender
    {
        if (! $this->defaultSenderLoaded) {
            $this->defaultSender = $this->tenant_id
                ? MailSender::defaultForTenant((int) $this->tenant_id)
                : null;
            $this->defaultSenderLoaded = true;
        }

        return $this->defaultSender;
    }

    /**
     * The from address for outgoing mail: the tenant's default sender account, otherwise
     * MAIL_FROM_ADDRESS.
     *
     * This row's own from_email is deliberately NOT consulted. It was only ever honored behind
     * the removed use_custom_smtp flag, and most tenants that store one were running on the
     * platform mailer — reading it here would silently move their envelope From onto a domain
     * the platform mail server may not be SPF/DKIM authorized for.
     */
    public function resolvedFromEmail(): string
    {
        return $this->defaultSender()?->from_email
            ?: (string) config('mail.from.address');
    }

    /**
     * The reply-to address, resolved the same way. Null means: apply none.
     */
    public function resolvedReplyEmail(): ?string
    {
        return $this->defaultSender()?->reply_email ?: null;
    }

    protected static function newFactory(): CommunicationSettingFactory
    {
        return CommunicationSettingFactory::new();
    }
}
