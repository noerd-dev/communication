<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Noerd\Communication\Models\MailSender;
use Noerd\Communication\Tests\Traits\CreatesCommunicationUser;
use Noerd\Models\Tenant;

uses(Tests\TestCase::class);
uses(RefreshDatabase::class);
uses(CreatesCommunicationUser::class);

it('lists only the senders of the acting tenant', function (): void {
    $user = $this->withCommunicationModule();
    $this->actingAs($user);

    MailSender::factory()->create([
        'tenant_id' => $user->selected_tenant_id,
        'name' => 'Own Sender',
    ]);
    MailSender::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'name' => 'Foreign Sender',
    ]);

    Livewire::test('communication::mail-senders-list')
        ->assertSee('Own Sender')
        ->assertDontSee('Foreign Sender');
});
