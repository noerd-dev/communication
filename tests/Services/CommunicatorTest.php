<?php

declare(strict_types=1);

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Models\Communication;
use Noerd\Communication\Services\Communicator;
use Noerd\Communication\Services\TenantSmtpResolver;
use Noerd\Customer\Models\Customer;
use Noerd\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

class CommunicatorTestMail extends Mailable
{
    public function __construct(public string $bodyText = 'Hello world') {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test Subject');
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>' . e($this->bodyText) . '</p>');
    }
}

it('sends a mail and persists a communication row', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: 'foo@example.com',
        customer: $customer,
    );

    expect($communication)->toBeInstanceOf(Communication::class);
    expect($communication->type)->toBe(CommunicationType::Email);
    expect($communication->status)->toBe(CommunicationStatus::Sent);
    expect($communication->customer_id)->toBe($customer->id);
    expect($communication->tenant_id)->toBe($tenant->id);
    expect($communication->to)->toBe('foo@example.com');
    expect($communication->mailable_class)->toBe(CommunicatorTestMail::class);

    Mail::assertSent(CommunicatorTestMail::class, fn($mail) => $mail->hasTo('foo@example.com'));
});

it('accepts a Customer as recipient and auto-resolves customer_id', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $customer = Customer::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'auto@example.com',
    ]);

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: $customer,
    );

    expect($communication->customer_id)->toBe($customer->id);
    expect($communication->to)->toBe('auto@example.com');
});

it('persists status=failed and rethrows when the mailer throws', function (): void {
    $mailer = Mockery::mock(Mailer::class);
    $pendingMail = Mockery::mock(PendingMail::class);
    $mailer->shouldReceive('to')->andReturn($pendingMail);
    $pendingMail->shouldReceive('send')->andThrow(new RuntimeException('SMTP boom'));

    $resolver = Mockery::mock(TenantSmtpResolver::class);
    $resolver->shouldReceive('resolve')->andReturn($mailer);
    app()->instance(TenantSmtpResolver::class, $resolver);

    $tenant = Tenant::factory()->create();

    expect(function () use ($tenant): void {
        app(Communicator::class)->send(
            mailable: new CommunicatorTestMail(),
            to: 'broken@example.com',
            tenantSettings: ['tenant_id' => $tenant->id],
        );
    })->toThrow(RuntimeException::class, 'SMTP boom');

    $communication = Communication::withoutGlobalScopes()->latest('id')->first();
    expect($communication->status)->toBe(CommunicationStatus::Failed);
    expect($communication->error_message)->toBe('SMTP boom');
});

it('accepts an array of recipients', function (): void {
    Mail::fake();

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: ['a@example.com', 'b@example.com'],
    );

    expect($communication->to)->toBe('a@example.com, b@example.com');
});

it('allows nullable customer', function (): void {
    Mail::fake();

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: 'anon@example.com',
    );

    expect($communication->customer_id)->toBeNull();
});
