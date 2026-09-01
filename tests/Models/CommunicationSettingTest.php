<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Communication\Models\CommunicationSetting;
use Noerd\Communication\Models\MailSender;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('uses the default senders from address', function (): void {
    config(['mail.from.address' => 'env@example.com']);

    $tenant = Tenant::factory()->create();
    MailSender::factory()->default()->create([
        'tenant_id' => $tenant->id,
        'from_email' => 'sender@example.com',
    ]);
    $setting = CommunicationSetting::factory()->create(['tenant_id' => $tenant->id]);

    expect($setting->resolvedFromEmail())->toBe('sender@example.com');
});

it('uses the default senders reply address', function (): void {
    $tenant = Tenant::factory()->create();
    MailSender::factory()->default()->create([
        'tenant_id' => $tenant->id,
        'reply_email' => 'reply@example.com',
    ]);
    $setting = CommunicationSetting::factory()->create(['tenant_id' => $tenant->id]);

    expect($setting->resolvedReplyEmail())->toBe('reply@example.com');
});

/**
 * Regression guard: the settings row's own from_email was only ever honored behind the
 * removed use_custom_smtp flag. Most tenants that store one run on the platform mailer, so
 * reading it here would silently move their envelope From onto a domain the platform mail
 * server may not be SPF/DKIM authorized for.
 */
it('ignores the settings row from address when the tenant has no sender', function (): void {
    config(['mail.from.address' => 'env@example.com']);

    $tenant = Tenant::factory()->create();
    $setting = CommunicationSetting::factory()->create([
        'tenant_id' => $tenant->id,
        'from_email' => 'stored@example.com',
    ]);

    expect($setting->resolvedFromEmail())->toBe('env@example.com')
        ->and($setting->resolvedReplyEmail())->toBeNull();
});

it('falls back to MAIL_FROM_ADDRESS when the default sender has no from address', function (): void {
    config(['mail.from.address' => 'env@example.com']);

    $tenant = Tenant::factory()->create();
    MailSender::factory()->default()->create([
        'tenant_id' => $tenant->id,
        'from_email' => null,
    ]);
    $setting = CommunicationSetting::factory()->create(['tenant_id' => $tenant->id]);

    expect($setting->resolvedFromEmail())->toBe('env@example.com');
});

it('ignores a deactivated sender', function (): void {
    config(['mail.from.address' => 'env@example.com']);

    $tenant = Tenant::factory()->create();
    // The first account of a tenant is always created active and default, so switch it off
    // afterwards — that is how a tenant goes back to the platform mailer.
    $sender = MailSender::factory()->create([
        'tenant_id' => $tenant->id,
        'from_email' => 'inactive@example.com',
    ]);
    $sender->update(['is_active' => false]);

    $setting = CommunicationSetting::factory()->create(['tenant_id' => $tenant->id]);

    expect($setting->resolvedFromEmail())->toBe('env@example.com');
});
