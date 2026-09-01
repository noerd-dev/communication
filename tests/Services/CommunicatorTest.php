<?php

declare(strict_types=1);

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Database\Eloquent\Model;
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
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Tests\TestCase;

uses(TestCase::class);
uses(RefreshDatabase::class);

/**
 * Stand-in for whatever record a host app links a mail to. The Communicator must accept
 * any Eloquent model, so this fixture is never persisted and needs no table.
 */
class CommunicatorTestContact extends Model
{
    protected $guarded = [];
}

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
    $contact = new CommunicatorTestContact(['id' => 42, 'tenant_id' => $tenant->id]);

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: 'foo@example.com',
        contact: $contact,
    );

    expect($communication)->toBeInstanceOf(Communication::class);
    expect($communication->type)->toBe(CommunicationType::Email);
    expect($communication->status)->toBe(CommunicationStatus::Sent);
    expect($communication->contact_type)->toBe(CommunicatorTestContact::class);
    expect($communication->contact_id)->toBe(42);
    expect($communication->tenant_id)->toBe($tenant->id);
    expect($communication->to)->toBe('foo@example.com');
    expect($communication->mailable_class)->toBe(CommunicatorTestMail::class);

    Mail::assertSent(CommunicatorTestMail::class, fn($mail) => $mail->hasTo('foo@example.com'));
});

it('accepts any Eloquent model as recipient and auto-resolves the contact link', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $contact = new CommunicatorTestContact([
        'id' => 7,
        'tenant_id' => $tenant->id,
        'email' => 'auto@example.com',
    ]);

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: $contact,
    );

    expect($communication->contact_type)->toBe(CommunicatorTestContact::class);
    expect($communication->contact_id)->toBe(7);
    expect($communication->tenant_id)->toBe($tenant->id);
    expect($communication->to)->toBe('auto@example.com');
});

it('skips sending when the recipient model carries no email', function (): void {
    Mail::fake();

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: new CommunicatorTestContact(['id' => 9]),
    );

    expect($communication)->toBeNull();
    Mail::assertNothingSent();
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

it('allows a nullable contact', function (): void {
    Mail::fake();

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: 'anon@example.com',
    );

    expect($communication->contact_type)->toBeNull();
    expect($communication->contact_id)->toBeNull();
});

it('derives the tenant from the contact when no tenantSettings are given', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: 'foo@example.com',
        contact: new CommunicatorTestContact(['id' => 3, 'tenant_id' => $tenant->id]),
    );

    expect($communication->tenant_id)->toBe($tenant->id);
});

it('keeps the contact and model links independent', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $source = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: 'foo@example.com',
        contact: new CommunicatorTestContact(['id' => 5, 'tenant_id' => $tenant->id]),
        model: $source,
    );

    expect($communication->contact_type)->toBe(CommunicatorTestContact::class);
    expect($communication->contact_id)->toBe(5);
    expect($communication->model_type)->toBe($source->getMorphClass());
    expect($communication->model_id)->toBe($source->id);
});

it('stores a polymorphic model link when a model is passed', function (): void {
    Mail::fake();

    $tenant = Tenant::factory()->create();
    $linked = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: 'foo@example.com',
        tenantSettings: ['tenant_id' => $tenant->id],
        model: $linked,
    );

    expect($communication->model_type)->toBe($linked->getMorphClass());
    expect($communication->model_id)->toBe($linked->id);
    expect($communication->model->id)->toBe($linked->id);
});

it('leaves the model link empty when no model is passed', function (): void {
    Mail::fake();

    $communication = app(Communicator::class)->send(
        mailable: new CommunicatorTestMail(),
        to: 'foo@example.com',
    );

    expect($communication->model_type)->toBeNull();
    expect($communication->model_id)->toBeNull();
});

it('routes through an explicit sender account and stamps its tenant', function (): void {
    $tenant = Tenant::factory()->create();
    $sender = \Noerd\Communication\Models\MailSender::factory()->create(['tenant_id' => $tenant->id]);

    $resolver = Mockery::mock(TenantSmtpResolver::class);
    $resolver->shouldReceive('resolveForSender')->once()->with($sender)->andReturn(Mail::mailer());
    $resolver->shouldNotReceive('resolve');

    $communication = (new Communicator($resolver))->send(
        mailable: new CommunicatorTestMail(),
        to: 'someone@example.com',
        sender: $sender,
    );

    expect($communication->tenant_id)->toBe($tenant->id)
        ->and($communication->metadata['mail_sender_id'])->toBe($sender->id);
});
