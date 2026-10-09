<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Jobs\SendTextMessage;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Models\CommunicationUsage;
use Noerd\Communication\Services\Communicator;
use Noerd\Communication\Support\TextMessageChannelRegistry;
use Noerd\Communication\Tests\Fixtures\ZzTextDriver;
use Noerd\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

class ZzTextRecipient extends Model
{
    protected $guarded = [];
}

function zzRegisterTextDriver(ZzTextDriver $driver): ZzTextDriver
{
    app(TextMessageChannelRegistry::class)->register($driver);

    return $driver;
}

it('sends a text message, logs it and records the usage', function (): void {
    $driver = zzRegisterTextDriver(new ZzTextDriver(CommunicationType::WhatsApp));
    $tenant = Tenant::factory()->create();

    $communication = app(Communicator::class)->sendText(
        type: CommunicationType::WhatsApp,
        to: '0171 1234567',
        body: 'New order from Jane',
        template: 'zz.order_placed',
        variables: ['1' => 'Jane'],
        tenantId: $tenant->id,
    );

    expect($driver->sent)->toHaveCount(1)
        ->and($driver->sent[0]->to)->toBe('+491711234567')
        ->and($driver->sent[0]->template)->toBe('zz.order_placed')
        ->and($driver->sent[0]->variables)->toBe(['1' => 'Jane'])
        ->and($driver->sent[0]->tenantId)->toBe($tenant->id)
        ->and($communication->type)->toBe(CommunicationType::WhatsApp)
        ->and($communication->status)->toBe(CommunicationStatus::Sent)
        ->and($communication->message_id)->toBe('zz-1')
        ->and($communication->from)->toBe('+4930000000')
        ->and($communication->body)->toBe('New order from Jane')
        ->and($communication->metadata['provider'])->toBe('zz');

    $usage = CommunicationUsage::withoutGlobalScopes()->sole();

    expect($usage->tenant_id)->toBe($tenant->id)
        ->and($usage->communication_id)->toBe($communication->id)
        ->and($usage->type)->toBe(CommunicationType::WhatsApp)
        ->and($usage->provider)->toBe('zz')
        ->and($usage->units)->toBe(2)
        ->and((float) $usage->cost)->toBe(0.075)
        ->and($usage->currency)->toBe('EUR');
});

it('reads the phone number and the contact from a record', function (): void {
    $driver = zzRegisterTextDriver(new ZzTextDriver());
    $tenant = Tenant::factory()->create();
    $recipient = new ZzTextRecipient(['id' => 7, 'tenant_id' => $tenant->id, 'phone' => '+49 30 1234567']);

    $communication = app(Communicator::class)->sendText(CommunicationType::Sms, $recipient, 'Hello');

    expect($driver->sent[0]->to)->toBe('+49301234567')
        ->and($communication->tenant_id)->toBe($tenant->id)
        ->and($communication->contact_type)->toBe($recipient->getMorphClass())
        ->and($communication->contact_id)->toBe(7);
});

it('marks the message failed, records no usage and rethrows', function (): void {
    zzRegisterTextDriver(new ZzTextDriver(failWith: 'provider down'));
    $tenant = Tenant::factory()->create();

    expect(fn() => app(Communicator::class)->sendText(CommunicationType::Sms, '+491711234567', 'Hi', tenantId: $tenant->id))
        ->toThrow(RuntimeException::class, 'provider down');

    $communication = Communication::withoutGlobalScopes()->sole();

    expect($communication->status)->toBe(CommunicationStatus::Failed)
        ->and($communication->error_message)->toBe('provider down')
        ->and(CommunicationUsage::withoutGlobalScopes()->count())->toBe(0);
});

it('sends nothing without a valid number or an available driver', function (): void {
    $communicator = app(Communicator::class);

    expect($communicator->sendText(CommunicationType::Sms, '+491711234567', 'Hi'))->toBeNull();

    zzRegisterTextDriver(new ZzTextDriver(available: false));
    expect($communicator->sendText(CommunicationType::Sms, '+491711234567', 'Hi'))->toBeNull();

    zzRegisterTextDriver(new ZzTextDriver());
    expect($communicator->sendText(CommunicationType::Sms, null, 'Hi'))->toBeNull()
        ->and($communicator->sendText(CommunicationType::Sms, 'abc', 'Hi'))->toBeNull()
        ->and(Communication::withoutGlobalScopes()->count())->toBe(0);
});

it('queues the delivery and the job sends it once', function (): void {
    Queue::fake();
    $driver = zzRegisterTextDriver(new ZzTextDriver());
    $tenant = Tenant::factory()->create();

    $communication = app(Communicator::class)->sendText(CommunicationType::Sms, '+491711234567', 'Hi', tenantId: $tenant->id, queue: true);

    expect($communication->status)->toBe(CommunicationStatus::Queued)
        ->and($driver->sent)->toBe([]);

    Queue::assertPushed(SendTextMessage::class, fn(SendTextMessage $job) => $job->communicationId === $communication->id);

    (new SendTextMessage($communication->id))->handle(app(Communicator::class));
    (new SendTextMessage($communication->id))->handle(app(Communicator::class));

    expect($driver->sent)->toHaveCount(1)
        ->and($communication->refresh()->status)->toBe(CommunicationStatus::Sent)
        ->and(CommunicationUsage::withoutGlobalScopes()->count())->toBe(1);
});

it('refuses email as a text channel', function (): void {
    app(Communicator::class)->sendText(CommunicationType::Email, '+491711234567', 'Hi');
})->throws(RuntimeException::class);
