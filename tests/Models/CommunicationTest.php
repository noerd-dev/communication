<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Models\Communication;
use Noerd\Customer\Models\Customer;
use Noerd\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('creates a communication via the factory', function (): void {
    $communication = Communication::factory()->create();

    expect($communication->type)->toBe(CommunicationType::Email);
    expect($communication->status)->toBe(CommunicationStatus::Sent);
});

it('supports the failed state', function (): void {
    $communication = Communication::factory()->failed('Bad host')->create();

    expect($communication->status)->toBe(CommunicationStatus::Failed);
    expect($communication->error_message)->toBe('Bad host');
    expect($communication->sent_at)->toBeNull();
});

it('relates to a customer (nullable)', function (): void {
    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $communication = Communication::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
    ]);

    expect($communication->customer)->toBeInstanceOf(Customer::class);
    expect($communication->customer->id)->toBe($customer->id);

    $withoutCustomer = Communication::factory()->create(['customer_id' => null]);
    expect($withoutCustomer->customer)->toBeNull();
});
