<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Models\Communication;
use Noerd\Models\NoerdUser;
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

it('resolves the contact link polymorphically (nullable)', function (): void {
    $tenant = Tenant::factory()->create();
    $contact = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);

    $communication = Communication::factory()->create([
        'tenant_id' => $tenant->id,
        'contact_type' => $contact->getMorphClass(),
        'contact_id' => $contact->id,
    ]);

    expect($communication->contact)->toBeInstanceOf(NoerdUser::class);
    expect($communication->contact->id)->toBe($contact->id);

    $withoutContact = Communication::factory()->create();
    expect($withoutContact->contact)->toBeNull();
});

it('resolves the linked record via the polymorphic model relation', function (): void {
    $tenant = Tenant::factory()->create();
    $source = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);

    $communication = Communication::factory()->create([
        'tenant_id' => $tenant->id,
        'model_type' => $source->getMorphClass(),
        'model_id' => $source->id,
    ]);

    expect($communication->model)->toBeInstanceOf(NoerdUser::class);
    expect($communication->model->id)->toBe($source->id);

    $unlinked = Communication::factory()->create();
    expect($unlinked->model)->toBeNull();
});

it('keeps the model and contact links independent of each other', function (): void {
    $tenant = Tenant::factory()->create();
    $source = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);
    $contact = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);

    $communication = Communication::factory()->create([
        'tenant_id' => $tenant->id,
        'model_type' => $source->getMorphClass(),
        'model_id' => $source->id,
        'contact_type' => $contact->getMorphClass(),
        'contact_id' => $contact->id,
    ]);

    expect($communication->model->id)->toBe($source->id);
    expect($communication->contact->id)->toBe($contact->id);
    expect($communication->model->id)->not->toBe($communication->contact->id);
});
