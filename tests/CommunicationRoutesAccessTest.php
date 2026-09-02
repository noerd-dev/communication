<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Noerd\Communication\Models\MailSender;
use Noerd\Communication\Tests\Traits\CreatesCommunicationUser;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);
uses(CreatesCommunicationUser::class);

it('redirects guests to the login', function (string $url): void {
    $this->get($url)->assertRedirect(route('noerd.login'));
})->with([
    'communications' => '/communications',
    'mail senders' => '/mail-senders',
]);

it('renders the module screens for an authenticated tenant', function (string $url, string $component): void {
    $this->actingAs($this->withCommunicationModule());

    $this->get($url)
        ->assertOk()
        ->assertSeeLivewire($component);
})->with([
    'communications' => ['/communications', 'communication::communications-list'],
    'mail senders' => ['/mail-senders', 'communication::mail-senders-list'],
]);

it('never discloses another tenant\'s SMTP credentials on the sender detail route', function (): void {
    $owner = $this->withCommunicationModule();
    $foreignSender = MailSender::factory()->create([
        'tenant_id' => $owner->selected_tenant_id,
        'name' => 'Foreign Sender',
        'smtp_host' => 'smtp.foreign-host.example',
        'smtp_username' => 'foreign-user',
        'smtp_password' => 'foreign-secret',
    ]);

    // A second tenant opens the URL of the first tenant's sender.
    $intruder = $this->withCommunicationModule();
    $this->actingAs($intruder);

    $this->get('/mail-sender/' . $foreignSender->id)
        ->assertOk()
        ->assertDontSee('smtp.foreign-host.example')
        ->assertDontSee('foreign-user')
        ->assertDontSee('foreign-secret');
});
