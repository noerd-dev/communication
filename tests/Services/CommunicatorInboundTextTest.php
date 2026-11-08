<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Noerd\Communication\Enums\CommunicationDirection;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Events\TextMessageReceived;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Services\Communicator;
use Noerd\Models\Tenant;
use Noerd\Support\TenantContext;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

it('logs a reply in the tenant of the latest message to the sender and fires the event', function (): void {
    Event::fake([TextMessageReceived::class]);
    $earlierTenant = Tenant::factory()->create();
    $tenant = Tenant::factory()->create();
    Communication::factory()->text()->create(['tenant_id' => $earlierTenant->id, 'to' => '+491711234567']);
    $original = Communication::factory()->text()->create([
        'tenant_id' => $tenant->id,
        'to' => '+491711234567',
        'contact_type' => 'zz_contact',
        'contact_id' => 7,
        'model_type' => 'zz_model',
        'model_id' => 9,
    ]);

    $reply = app(Communicator::class)->receiveText(CommunicationType::Sms, '0171 1234567', '+4930000000', 'Passt, danke!', 'SM-in-1', 'zz', ['num_media' => 0]);

    expect($reply->tenant_id)->toBe($tenant->id)
        ->and($reply->direction)->toBe(CommunicationDirection::Inbound)
        ->and($reply->status)->toBe(CommunicationStatus::Received)
        ->and($reply->from)->toBe('+491711234567')
        ->and($reply->body)->toBe('Passt, danke!')
        ->and($reply->in_reply_to_id)->toBe($original->id)
        ->and($reply->contact_type)->toBe('zz_contact')
        ->and($reply->contact_id)->toBe(7)
        ->and($reply->model_id)->toBe(9)
        ->and($reply->metadata)->toBe(['num_media' => 0, 'provider' => 'zz']);

    Event::assertDispatched(TextMessageReceived::class, fn(TextMessageReceived $event): bool => $event->communication->is($reply));
});

it('fires the event inside the tenant context of the reply', function (): void {
    $tenant = Tenant::factory()->create();
    Communication::factory()->text()->create(['tenant_id' => $tenant->id]);
    $contextSeen = null;
    Event::listen(TextMessageReceived::class, function () use (&$contextSeen): void {
        $contextSeen = TenantContext::current();
    });

    app(Communicator::class)->receiveText(CommunicationType::Sms, '+491711234567', null, 'Hallo', 'SM-in-1', 'zz');

    expect($contextSeen)->toBe($tenant->id);
});

it('prefers a message of the same channel over a later one of another channel', function (): void {
    $smsTenant = Tenant::factory()->create();
    $whatsAppTenant = Tenant::factory()->create();
    $sms = Communication::factory()->text(CommunicationType::Sms)->create(['tenant_id' => $smsTenant->id]);
    Communication::factory()->text(CommunicationType::WhatsApp)->create(['tenant_id' => $whatsAppTenant->id]);

    $reply = app(Communicator::class)->receiveText(CommunicationType::Sms, '+491711234567', null, 'Ja', 'SM-in-1', 'zz');

    expect($reply->in_reply_to_id)->toBe($sms->id)
        ->and($reply->tenant_id)->toBe($smsTenant->id);
});

it('falls back to a message of another text channel', function (): void {
    $tenant = Tenant::factory()->create();
    $sms = Communication::factory()->text(CommunicationType::Sms)->create(['tenant_id' => $tenant->id]);

    $reply = app(Communicator::class)->receiveText(CommunicationType::WhatsApp, '+491711234567', null, 'Ja', 'SM-in-1', 'zz');

    expect($reply->type)->toBe(CommunicationType::WhatsApp)
        ->and($reply->in_reply_to_id)->toBe($sms->id)
        ->and($reply->tenant_id)->toBe($tenant->id);
});

it('never answers an inbound message or a mail to the same address', function (): void {
    $tenant = Tenant::factory()->create();
    Communication::factory()->inbound()->create(['tenant_id' => $tenant->id, 'to' => '+491711234567']);
    Communication::factory()->create(['tenant_id' => $tenant->id, 'to' => '+491711234567']);

    $reply = app(Communicator::class)->receiveText(CommunicationType::Sms, '+491711234567', null, 'Hallo?', 'SM-in-1', 'zz');

    expect($reply->tenant_id)->toBeNull()
        ->and($reply->in_reply_to_id)->toBeNull();
});

it('logs a message of an unknown sender without a tenant', function (): void {
    $reply = app(Communicator::class)->receiveText(CommunicationType::Sms, '+491719999999', null, 'Wer ist da?', 'SM-in-1', 'zz');

    expect($reply->tenant_id)->toBeNull()
        ->and($reply->contact_id)->toBeNull()
        ->and(Communication::acrossTenants()->count())->toBe(1);
});

it('logs a message the provider delivers twice only once', function (): void {
    Event::fake([TextMessageReceived::class]);
    $tenant = Tenant::factory()->create();
    Communication::factory()->text()->create(['tenant_id' => $tenant->id]);

    $first = app(Communicator::class)->receiveText(CommunicationType::Sms, '+491711234567', null, 'Ja', 'SM-in-1', 'zz');
    $second = app(Communicator::class)->receiveText(CommunicationType::Sms, '+491711234567', null, 'Ja', 'SM-in-1', 'zz');

    expect($second->is($first))->toBeTrue()
        ->and(Communication::acrossTenants()->where('direction', CommunicationDirection::Inbound)->count())->toBe(1);
    Event::assertDispatchedTimes(TextMessageReceived::class, 1);
});

it('ignores a sender without a valid number', function (): void {
    expect(app(Communicator::class)->receiveText(CommunicationType::Sms, 'Anonymous', null, 'Hallo', 'SM-in-1', 'zz'))->toBeNull()
        ->and(Communication::acrossTenants()->count())->toBe(0);
});

it('marks every message the platform sends as outbound', function (): void {
    $tenant = Tenant::factory()->create();
    app(\Noerd\Communication\Support\TextMessageChannelRegistry::class)->register(new \Noerd\Communication\Tests\Fixtures\ZzTextDriver());

    $communication = app(Communicator::class)->sendText(CommunicationType::Sms, '+491711234567', 'Hallo', tenantId: $tenant->id);

    expect($communication->direction)->toBe(CommunicationDirection::Outbound);
});

it('lets a module assign a message of an unknown sender to the record it recognised', function (): void {
    $tenant = Tenant::factory()->create();
    $contact = Tenant::factory()->create();
    $contact->setAttribute('tenant_id', $tenant->id);
    $reply = app(Communicator::class)->receiveText(CommunicationType::Sms, '+491719999999', null, 'Neue Nummer', 'SM-in-1', 'zz');
    $events = [];
    Event::listen(TextMessageReceived::class, function (TextMessageReceived $event) use (&$events): void {
        $events[] = [$event->communication->tenant_id, TenantContext::current()];
    });

    app(Communicator::class)->assignInbound($reply, $contact);
    app(Communicator::class)->assignInbound($reply->fresh(), $contact);

    expect($reply->fresh()->tenant_id)->toBe($tenant->id)
        ->and($reply->fresh()->contact_id)->toBe($contact->id)
        ->and($events)->toBe([[$tenant->id, $tenant->id]]);
});

it('gives a message of an unknown sender no tenant even while a user is signed in', function (): void {
    $user = \Noerd\Models\NoerdUser::factory()->create(['selected_tenant_id' => Tenant::factory()->create()->id]);
    $this->actingAs($user, 'noerd');

    $reply = app(Communicator::class)->receiveText(CommunicationType::Sms, '+491719999999', null, 'Hallo', 'SM-in-1', 'zz');

    expect($reply->fresh()->tenant_id)->toBeNull();
});
