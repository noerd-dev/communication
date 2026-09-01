<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Noerd\Communication\Models\MailSender;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('makes the first account of a tenant the default even when not asked to', function (): void {
    $tenant = Tenant::factory()->create();

    $sender = MailSender::factory()->create([
        'tenant_id' => $tenant->id,
        'is_default' => false,
    ]);

    expect($sender->fresh()->is_default)->toBeTrue()
        ->and($sender->fresh()->is_active)->toBeTrue();
});

it('does not make every later account the default', function (): void {
    $tenant = Tenant::factory()->create();

    $first = MailSender::factory()->create(['tenant_id' => $tenant->id]);
    $second = MailSender::factory()->create(['tenant_id' => $tenant->id, 'is_default' => false]);

    expect($first->fresh()->is_default)->toBeTrue()
        ->and($second->fresh()->is_default)->toBeFalse();
});

it('demotes the previous default when another account is promoted', function (): void {
    $tenant = Tenant::factory()->create();
    $first = MailSender::factory()->create(['tenant_id' => $tenant->id]);
    $second = MailSender::factory()->create(['tenant_id' => $tenant->id]);

    $second->update(['is_default' => true]);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

it('activates an inactive account that is marked as default', function (): void {
    $tenant = Tenant::factory()->create();
    MailSender::factory()->create(['tenant_id' => $tenant->id]);
    $inactive = MailSender::factory()->inactive()->create(['tenant_id' => $tenant->id]);

    $inactive->update(['is_default' => true]);

    expect($inactive->fresh()->is_active)->toBeTrue()
        ->and($inactive->fresh()->is_default)->toBeTrue();
});

it('promotes another active account when the default is deactivated', function (): void {
    $tenant = Tenant::factory()->create();
    $default = MailSender::factory()->create(['tenant_id' => $tenant->id]);
    $other = MailSender::factory()->create(['tenant_id' => $tenant->id]);

    $default->update(['is_active' => false]);

    expect($default->fresh()->is_default)->toBeFalse()
        ->and($other->fresh()->is_default)->toBeTrue();
});

it('promotes another active account when the default is deleted', function (): void {
    $tenant = Tenant::factory()->create();
    $default = MailSender::factory()->create(['tenant_id' => $tenant->id]);
    $other = MailSender::factory()->create(['tenant_id' => $tenant->id]);

    $default->delete();

    expect($other->fresh()->is_default)->toBeTrue();
});

it('leaves the tenant without a default when every account is deactivated', function (): void {
    $tenant = Tenant::factory()->create();
    $only = MailSender::factory()->create(['tenant_id' => $tenant->id]);

    $only->update(['is_active' => false]);

    expect(MailSender::defaultForTenant($tenant->id))->toBeNull();
});

it('never resolves a default across tenants', function (): void {
    $one = Tenant::factory()->create();
    $two = Tenant::factory()->create();
    $senderOne = MailSender::factory()->create(['tenant_id' => $one->id]);
    $senderTwo = MailSender::factory()->create(['tenant_id' => $two->id]);

    expect(MailSender::defaultForTenant($one->id)->id)->toBe($senderOne->id)
        ->and(MailSender::defaultForTenant($two->id)->id)->toBe($senderTwo->id);
});

it('stores the smtp password encrypted but reads it back in clear', function (): void {
    $sender = MailSender::factory()->create(['smtp_password' => 'super-secret']);

    $raw = DB::table('communication_mail_senders')->where('id', $sender->id)->value('smtp_password');

    expect($raw)->not->toBe('super-secret')
        ->and($sender->fresh()->smtp_password)->toBe('super-secret');
});

it('reports whether an account brings its own smtp server', function (): void {
    expect(MailSender::factory()->withSmtp()->create()->usesCustomSmtp())->toBeTrue()
        ->and(MailSender::factory()->create()->usesCustomSmtp())->toBeFalse();
});
