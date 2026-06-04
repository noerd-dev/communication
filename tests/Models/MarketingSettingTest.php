<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Marketing\Models\MarketingSetting;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('uses the tenant from_email only when use_custom_smtp is active', function (): void {
    config(['mail.from.address' => 'env@example.com']);

    $setting = MarketingSetting::factory()->make([
        'use_custom_smtp' => true,
        'from_email' => 'tenant@example.com',
    ]);

    expect($setting->resolvedFromEmail())->toBe('tenant@example.com');
});

it('falls back to MAIL_FROM_ADDRESS when use_custom_smtp is inactive', function (): void {
    config(['mail.from.address' => 'env@example.com']);

    $setting = MarketingSetting::factory()->make([
        'use_custom_smtp' => false,
        'from_email' => 'tenant@example.com',
    ]);

    expect($setting->resolvedFromEmail())->toBe('env@example.com');
});

it('falls back to MAIL_FROM_ADDRESS when use_custom_smtp is active but from_email is empty', function (): void {
    config(['mail.from.address' => 'env@example.com']);

    $setting = MarketingSetting::factory()->make([
        'use_custom_smtp' => true,
        'from_email' => null,
    ]);

    expect($setting->resolvedFromEmail())->toBe('env@example.com');
});

it('returns the reply_email only when use_custom_smtp is active', function (): void {
    $custom = MarketingSetting::factory()->make([
        'use_custom_smtp' => true,
        'reply_email' => 'reply@example.com',
    ]);

    $default = MarketingSetting::factory()->make([
        'use_custom_smtp' => false,
        'reply_email' => 'reply@example.com',
    ]);

    expect($custom->resolvedReplyEmail())->toBe('reply@example.com');
    expect($default->resolvedReplyEmail())->toBeNull();
});
