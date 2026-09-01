<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Noerd\Communication\Models\CommunicationSetting;
use Noerd\Communication\Models\MailSender;
use Noerd\Communication\Services\TenantSmtpResolver;
use Noerd\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('uses the default mailer when the tenant has no sender', function (): void {
    $tenant = Tenant::factory()->create();

    $mailer = app(TenantSmtpResolver::class)->resolveForTenant($tenant->id);

    expect($mailer)->toBe(Mail::mailer());
});

it('uses the default mailer when the sender has no smtp credentials', function (): void {
    $sender = MailSender::factory()->default()->create();

    $mailer = app(TenantSmtpResolver::class)->resolveForSender($sender);

    expect($mailer)->toBe(Mail::mailer());
});

it('builds a sender-specific mailer from the stored credentials', function (): void {
    $resolver = app(TenantSmtpResolver::class);
    $sender = MailSender::factory()
        ->default()
        ->withSmtp(host: 'smtp.tenant.test', username: 'tenant@example.com')
        ->create();

    $mailer = $resolver->resolveForSender($sender);

    $config = config('mail.mailers.' . $resolver->mailerName($sender));
    expect($config)->not->toBeNull()
        ->and($config['host'])->toBe('smtp.tenant.test')
        ->and($config['username'])->toBe('tenant@example.com')
        ->and($config['password'])->toBe('secret')
        ->and($mailer)->not->toBe(Mail::mailer());
});

it('gives two senders of the same tenant distinct mailers', function (): void {
    $resolver = app(TenantSmtpResolver::class);
    $tenant = Tenant::factory()->create();

    $first = MailSender::factory()->withSmtp(host: 'first.test', username: 'first@example.com')
        ->create(['tenant_id' => $tenant->id]);
    $second = MailSender::factory()->withSmtp(host: 'second.test', username: 'second@example.com')
        ->create(['tenant_id' => $tenant->id]);

    $resolver->resolveForSender($first);
    $resolver->resolveForSender($second);

    expect($resolver->mailerName($first))->not->toBe($resolver->mailerName($second))
        ->and(config('mail.mailers.' . $resolver->mailerName($first))['host'])->toBe('first.test')
        ->and(config('mail.mailers.' . $resolver->mailerName($second))['host'])->toBe('second.test');
});

it('changes the mailer name when credentials change so the cached transport is not reused', function (): void {
    $resolver = app(TenantSmtpResolver::class);
    $sender = MailSender::factory()->withSmtp()->create();

    $before = $resolver->mailerName($sender);

    $sender->update(['smtp_password' => 'rotated']);

    expect($resolver->mailerName($sender->fresh()))->not->toBe($before);
});

it('forces the smtps scheme for an ssl sender and leaves tls to the port heuristic', function (): void {
    $resolver = app(TenantSmtpResolver::class);

    $ssl = MailSender::factory()->withSmtp()->create(['smtp_encryption' => 'ssl']);
    $tls = MailSender::factory()->withSmtp()->create(['smtp_encryption' => 'tls']);

    $resolver->resolveForSender($ssl);
    $resolver->resolveForSender($tls);

    expect(config('mail.mailers.' . $resolver->mailerName($ssl))['scheme'])->toBe('smtps')
        ->and(config('mail.mailers.' . $resolver->mailerName($tls)))->not->toHaveKey('scheme');
});

it('resolves a CommunicationSetting to the tenants default sender', function (): void {
    $resolver = app(TenantSmtpResolver::class);
    $tenant = Tenant::factory()->create();
    $settings = CommunicationSetting::factory()->create(['tenant_id' => $tenant->id]);
    $sender = MailSender::factory()->default()->withSmtp(host: 'via-settings.test')
        ->create(['tenant_id' => $tenant->id]);

    $resolver->resolve($settings);

    expect(config('mail.mailers.' . $resolver->mailerName($sender))['host'])->toBe('via-settings.test');
});

it('resolves a plain tenant_id array to the tenants default sender', function (): void {
    $resolver = app(TenantSmtpResolver::class);
    $tenant = Tenant::factory()->create();
    $sender = MailSender::factory()->default()->withSmtp(host: 'via-array.test')
        ->create(['tenant_id' => $tenant->id]);

    $resolver->resolve(['tenant_id' => $tenant->id]);

    expect(config('mail.mailers.' . $resolver->mailerName($sender))['host'])->toBe('via-array.test');
});

it('uses the default mailer when nothing is passed', function (): void {
    expect(app(TenantSmtpResolver::class)->resolve())->toBe(Mail::mailer());
});
