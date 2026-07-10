<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Noerd\Communication\Models\CommunicationSetting;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);

function actingAsCommunicationUser(): NoerdUser
{
    $tenant = Tenant::factory()->create();
    $user = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);
    $tenant->users()->attach($user->id);
    test()->actingAs($user);

    return $user;
}

it('renders the communication-settings route', function (): void {
    actingAsCommunicationUser();

    $this->get('/communication-settings')->assertStatus(200);
});

it('persists communication settings on save', function (): void {
    $user = actingAsCommunicationUser();

    Livewire::test('communication::communication-settings-detail')
        ->set('settingsData.from_email', 'from@example.com')
        ->set('settingsData.reply_email', 'reply@example.com')
        ->set('settingsData.use_custom_smtp', true)
        ->set('settingsData.smtp_host', 'smtp.example.com')
        ->set('settingsData.smtp_port', 587)
        ->set('settingsData.smtp_encryption', 'tls')
        ->set('settingsData.smtp_username', 'user@example.com')
        ->set('settingsData.smtp_password', 'secret')
        ->call('store')
        ->assertSet('showSuccessIndicator', true);

    $saved = CommunicationSetting::forTenant($user->selected_tenant_id);
    expect($saved)->not->toBeNull();
    expect($saved->from_email)->toBe('from@example.com');
    expect($saved->use_custom_smtp)->toBeTrue();
    expect($saved->smtp_host)->toBe('smtp.example.com');
    expect($saved->smtp_port)->toBe(587);
});

it('sends a test email to the logged-in user', function (): void {
    config(['mail.default' => 'array']);
    $user = actingAsCommunicationUser();
    app('mail.manager')->forgetMailers();
    Cache::flush();

    Livewire::test('communication::communication-settings-detail')
        ->call('sendTestEmail')
        ->assertSet('testEmailError', null)
        ->assertSet('testEmailMessage', __('Test email sent to :email', ['email' => $user->email]));

    $messages = app('mail.manager')->mailer()->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1);
    expect($messages->first()->getEnvelope()->getRecipients()[0]->getAddress())->toBe($user->email);
});

it('rate-limits the test email to once per minute', function (): void {
    $user = actingAsCommunicationUser();
    app('mail.manager')->forgetMailers();
    Cache::flush();

    $component = Livewire::test('communication::communication-settings-detail');
    $component->call('sendTestEmail')
        ->assertSet('testEmailMessage', __('Test email sent to :email', ['email' => $user->email]));
    $component->call('sendTestEmail')
        ->assertSet('testEmailError', __('Send test email (only possible once per minute)'));
});
