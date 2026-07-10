<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\SentMessage;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Listeners\LogMessageSentFallback;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Services\Communicator;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

function buildMessageSentEvent(Email $email): MessageSent
{
    $envelope = new Envelope(
        new Address('sender@example.com'),
        [new Address('recipient@example.com')],
    );

    return new MessageSent(new SentMessage(new SymfonySentMessage($email, $envelope)));
}

it('writes the Symfony Message-ID into the existing communication row', function (): void {
    $communication = Communication::factory()->create([
        'message_id' => null,
    ]);

    $email = (new Email())
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Hello')
        ->html('<p>Hello</p>');

    $email->getHeaders()->addTextHeader(Communicator::COMMUNICATION_HEADER, (string) $communication->id);
    $email->getHeaders()->addIdHeader('Message-ID', 'abc123@mail.example.com');

    app(LogMessageSentFallback::class)->handle(buildMessageSentEvent($email));

    $communication->refresh();

    expect($communication->message_id)->toBe('abc123@mail.example.com');
    expect($communication->status)->toBe(CommunicationStatus::Sent);
    expect($communication->subject)->toBe('Hello');
});

it('captures the Message-ID for untracked messages too', function (): void {
    $email = (new Email())
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('Untracked')
        ->html('<p>Untracked</p>');

    $email->getHeaders()->addIdHeader('Message-ID', 'untracked@mail.example.com');

    app(LogMessageSentFallback::class)->handle(buildMessageSentEvent($email));

    $communication = Communication::withoutGlobalScopes()->latest('id')->first();

    expect($communication->message_id)->toBe('untracked@mail.example.com');
});

it('stores null when no Message-ID header is present', function (): void {
    $communication = Communication::factory()->create([
        'message_id' => null,
    ]);

    $email = (new Email())
        ->from('sender@example.com')
        ->to('recipient@example.com')
        ->subject('No header')
        ->text('No header');

    $email->getHeaders()->addTextHeader(Communicator::COMMUNICATION_HEADER, (string) $communication->id);

    app(LogMessageSentFallback::class)->handle(buildMessageSentEvent($email));

    expect($communication->refresh()->message_id)->toBeNull();
});
