<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Noerd\Marketing\Models\MarketingSetting;
use Noerd\Marketing\Services\TenantSmtpResolver;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('uses the default mailer when use_custom_smtp is false even with SMTP credentials', function (): void {
    $settings = MarketingSetting::factory()->withSmtp()->create([
        'use_custom_smtp' => false,
    ]);

    $mailer = app(TenantSmtpResolver::class)->resolve($settings);

    expect($mailer)->toBe(Mail::mailer());
    expect(config("mail.mailers.marketing_tenant_{$settings->tenant_id}"))->toBeNull();
});

it('builds a tenant-specific mailer when use_custom_smtp is true', function (): void {
    $settings = MarketingSetting::factory()
        ->withSmtp(host: 'smtp.tenant.test', username: 'tenant@example.com')
        ->create();

    $mailer = app(TenantSmtpResolver::class)->resolve($settings);

    $config = config("mail.mailers.marketing_tenant_{$settings->tenant_id}");
    expect($config)->not->toBeNull();
    expect($config['host'])->toBe('smtp.tenant.test');
    expect($config['username'])->toBe('tenant@example.com');
    expect($mailer)->not->toBe(Mail::mailer());
});

it('falls back to the default mailer when use_custom_smtp is true but host is missing', function (): void {
    $settings = MarketingSetting::factory()->create([
        'use_custom_smtp' => true,
        'smtp_host' => null,
        'smtp_username' => null,
    ]);

    $mailer = app(TenantSmtpResolver::class)->resolve($settings);

    expect($mailer)->toBe(Mail::mailer());
    expect(config("mail.mailers.marketing_tenant_{$settings->tenant_id}"))->toBeNull();
});
