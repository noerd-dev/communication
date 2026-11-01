<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Communication\Models\Communication;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

it('never deletes a logged communication from the detail', function (): void {
    $user = createNoerdUserForApp('COMMUNICATION');
    $this->actingAs($user);

    $communication = Communication::factory()->create(['tenant_id' => $user->selected_tenant_id]);

    Livewire::test('communication::communication-detail', ['modelId' => $communication->id])
        ->call('delete');

    expect(Communication::withoutGlobalScopes()->find($communication->id))->not->toBeNull();
});
