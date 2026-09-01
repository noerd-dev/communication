<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Noerd\Communication\Models\MailSender;
use Noerd\Communication\Tests\Traits\CreatesCommunicationUser;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);
uses(CreatesCommunicationUser::class);

it('renders the mail-sender detail route', function (): void {
    $user = $this->withCommunicationModule();
    $this->actingAs($user);

    $sender = MailSender::factory()->create(['tenant_id' => $user->selected_tenant_id]);

    $this->get('/mail-sender/' . $sender->id)->assertStatus(200);
});

it('persists a sender on save', function (): void {
    $user = $this->withCommunicationModule();
    $this->actingAs($user);

    $component = Livewire::test('communication::mail-sender-detail')
        ->set('detailData', validDetailPayload(MailSender::class, [
            'name' => 'Billing',
            'from_email' => 'billing@example.com',
            'smtp_host' => 'smtp.example.com',
            'smtp_username' => 'billing',
            'smtp_password' => 'secret',
        ]))
        ->call('store')
        ->assertHasNoErrors();

    $saved = MailSender::where('name', 'Billing')->first();

    expect($saved)->not->toBeNull()
        ->and($saved->tenant_id)->toBe($user->selected_tenant_id)
        ->and($saved->from_email)->toBe('billing@example.com')
        ->and($saved->smtp_password)->toBe('secret');

    unset($component);
});

it('reports the fields the layout marks as required', function (): void {
    $user = $this->withCommunicationModule();
    $this->actingAs($user);

    $component = Livewire::test('communication::mail-sender-detail')
        ->set('detailData', [])
        ->call('store');

    $component->assertHasErrors(requiredLayoutFields($component));
});

it('refuses a test email before the sender is saved', function (): void {
    $user = $this->withCommunicationModule();
    $this->actingAs($user);

    Livewire::test('communication::mail-sender-detail')
        ->call('sendTestEmail')
        ->assertSet('testEmailError', __('Save the sender before sending a test email.'))
        ->assertSet('testEmailMessage', null);
});

it('sends a test email through the sender to the logged-in user', function (): void {
    config(['mail.default' => 'array']);
    $user = $this->withCommunicationModule();
    $this->actingAs($user);
    app('mail.manager')->forgetMailers();
    Cache::flush();

    $sender = MailSender::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'from_email' => 'sender@example.com',
    ]);

    Livewire::test('communication::mail-sender-detail', ['modelId' => $sender->id])
        ->call('sendTestEmail')
        ->assertSet('testEmailError', null)
        ->assertSet('testEmailMessage', __('Test email sent to :email', ['email' => $user->email]));

    $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->getEnvelope()->getRecipients()[0]->getAddress())->toBe($user->email);
});

/**
 * The cooldown moved from the tenant to the sender when the module gained multiple accounts —
 * a busy tenant must still be able to verify each account.
 */
it('rate-limits the test email per sender, not per tenant', function (): void {
    config(['mail.default' => 'array']);
    $user = $this->withCommunicationModule();
    $this->actingAs($user);
    app('mail.manager')->forgetMailers();
    Cache::flush();

    $first = MailSender::factory()->create(['tenant_id' => $user->selected_tenant_id]);
    $second = MailSender::factory()->create(['tenant_id' => $user->selected_tenant_id]);

    Livewire::test('communication::mail-sender-detail', ['modelId' => $first->id])
        ->call('sendTestEmail')
        ->assertSet('testEmailMessage', __('Test email sent to :email', ['email' => $user->email]));

    Livewire::test('communication::mail-sender-detail', ['modelId' => $first->id])
        ->call('sendTestEmail')
        ->assertSet('testEmailError', __('Send test email (only possible once per minute)'));

    Livewire::test('communication::mail-sender-detail', ['modelId' => $second->id])
        ->call('sendTestEmail')
        ->assertSet('testEmailMessage', __('Test email sent to :email', ['email' => $user->email]));
});
