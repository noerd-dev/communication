<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Services\Communicator;
use Noerd\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->message = Communication::factory()->text()->create(['tenant_id' => Tenant::factory()->create()->id, 'message_id' => 'SM1']);
});

it('records that a sent text message was delivered and read', function (): void {
    app(Communicator::class)->updateTextStatus('SM1', CommunicationStatus::Delivered);
    expect($this->message->fresh()->status)->toBe(CommunicationStatus::Delivered)
        ->and($this->message->fresh()->metadata)->toHaveKey('delivered_at');

    app(Communicator::class)->updateTextStatus('SM1', CommunicationStatus::Read);
    expect($this->message->fresh()->status)->toBe(CommunicationStatus::Read);
});

it('records why a sent text message could not be delivered', function (): void {
    app(Communicator::class)->updateTextStatus('SM1', CommunicationStatus::Failed, 'Outside the 24-hour window.');

    expect($this->message->fresh()->status)->toBe(CommunicationStatus::Failed)
        ->and($this->message->fresh()->error_message)->toBe('Outside the 24-hour window.');
});

it('never moves a message back when reports arrive out of order', function (): void {
    app(Communicator::class)->updateTextStatus('SM1', CommunicationStatus::Delivered);
    app(Communicator::class)->updateTextStatus('SM1', CommunicationStatus::Sent);
    app(Communicator::class)->updateTextStatus('SM1', CommunicationStatus::Failed, 'late');

    expect($this->message->fresh()->status)->toBe(CommunicationStatus::Delivered)
        ->and($this->message->fresh()->error_message)->toBeNull();
});

it('ignores a report for a message it does not know', function (): void {
    expect(app(Communicator::class)->updateTextStatus('SM-unknown', CommunicationStatus::Delivered))->toBeNull();
});

it('never delivers a message again that the provider already delivered', function (): void {
    $this->message->forceFill(['status' => CommunicationStatus::Delivered])->save();
    $driver = new \Noerd\Communication\Tests\Fixtures\ZzTextDriver();
    app(\Noerd\Communication\Support\TextMessageChannelRegistry::class)->register($driver);

    app(Communicator::class)->deliverText($this->message->fresh());

    expect($driver->sent)->toBe([]);
});
