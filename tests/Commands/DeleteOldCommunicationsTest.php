<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Noerd\Communication\Models\Communication;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

function zzCommunicationAged(int $tenantId, int $daysAgo): Communication
{
    $communication = Communication::factory()->create(['tenant_id' => $tenantId]);
    // created_at is set by the model, so the age is stamped afterwards.
    $communication->forceFill(['created_at' => now()->subDays($daysAgo)])->saveQuietly();

    return $communication;
}

it('deletes communications older than the retention period across every tenant', function (): void {
    $first = Tenant::factory()->create();
    $second = Tenant::factory()->create();

    $oldFirst = zzCommunicationAged($first->id, 40);
    $oldSecond = zzCommunicationAged($second->id, 31);
    $recentFirst = zzCommunicationAged($first->id, 29);
    $recentSecond = zzCommunicationAged($second->id, 1);

    $exit = Artisan::call('communication:delete-old-communications');

    expect($exit)->toBe(0);
    expect(Artisan::output())->toContain('Deleted 2 communications older than 30 days.');

    $remaining = Communication::withoutGlobalScopes()->pluck('id')->all();

    expect($remaining)->toContain($recentFirst->id)
        ->toContain($recentSecond->id)
        ->not->toContain($oldFirst->id)
        ->not->toContain($oldSecond->id);
});

it('honours a custom retention period', function (): void {
    $tenant = Tenant::factory()->create();

    $old = zzCommunicationAged($tenant->id, 10);
    $recent = zzCommunicationAged($tenant->id, 3);

    expect(Artisan::call('communication:delete-old-communications', ['--days' => 7]))->toBe(0);

    $remaining = Communication::withoutGlobalScopes()->pluck('id')->all();

    expect($remaining)->toContain($recent->id)->not->toContain($old->id);
});
