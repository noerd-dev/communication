<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Communication\Models\Communication;
use Noerd\Customer\Models\Customer;
use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

function actingAsCommunicationsListUser(): NoerdUser
{
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user->id);

    TenantHelper::setSelectedTenantId($tenant->id);
    TenantHelper::setSelectedApp('COMMUNICATION');

    $app = TenantApp::firstOrCreate(
        ['name' => 'COMMUNICATION'],
        [
            'title' => 'Communication',
            'icon' => 'communication::icons.app',
            'route' => 'communications',
            'is_active' => true,
        ],
    );
    $tenant->tenantApps()->syncWithoutDetaching([$app->id]);

    test()->actingAs($user);

    return $user;
}

it('filters the list by the polymorphic model link', function (): void {
    $user = actingAsCommunicationsListUser();
    $customer = Customer::factory()->create(['tenant_id' => $user->selected_tenant_id]);

    Communication::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'subject' => 'Linked mail subject',
        'model_type' => $customer->getMorphClass(),
        'model_id' => $customer->id,
    ]);
    Communication::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'subject' => 'Unlinked mail subject',
    ]);

    Livewire::test('communication::communications-list', [
        'modelType' => $customer->getMorphClass(),
        'modelId' => $customer->id,
    ])
        ->assertSee('Linked mail subject')
        ->assertDontSee('Unlinked mail subject');
});

it('shows all rows when no model filter is set', function (): void {
    $user = actingAsCommunicationsListUser();
    $customer = Customer::factory()->create(['tenant_id' => $user->selected_tenant_id]);

    Communication::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'subject' => 'Linked mail subject',
        'model_type' => $customer->getMorphClass(),
        'model_id' => $customer->id,
    ]);
    Communication::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'subject' => 'Unlinked mail subject',
    ]);

    Livewire::test('communication::communications-list')
        ->assertSee('Linked mail subject')
        ->assertSee('Unlinked mail subject');
});
