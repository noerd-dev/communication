<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Tests\Traits\CreatesCommunicationUser;
use Noerd\Models\NoerdUser;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);
uses(CreatesCommunicationUser::class);

beforeEach(function (): void {
    $user = $this->withCommunicationModule();
    $this->actingAs($user);

    $this->linked = NoerdUser::factory()->create(['selected_tenant_id' => $user->selected_tenant_id]);

    Communication::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'subject' => 'Linked mail subject',
        'model_type' => $this->linked->getMorphClass(),
        'model_id' => $this->linked->id,
    ]);
    Communication::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'subject' => 'Unlinked mail subject',
    ]);
});

it('filters the list by the polymorphic model link', function (): void {
    Livewire::test('communication::communications-list', [
        'modelType' => $this->linked->getMorphClass(),
        'modelId' => $this->linked->id,
    ])
        ->assertSee('Linked mail subject')
        ->assertDontSee('Unlinked mail subject');
});

it('shows all rows when no model filter is set', function (): void {
    Livewire::test('communication::communications-list')
        ->assertSee('Linked mail subject')
        ->assertSee('Unlinked mail subject');
});
