<?php

namespace Noerd\Marketing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Marketing\Enums\CommunicationStatus;
use Noerd\Marketing\Enums\CommunicationType;
use Noerd\Marketing\Models\Communication;
use Noerd\Models\Tenant;

class CommunicationFactory extends Factory
{
    protected $model = Communication::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'customer_id' => null,
            'type' => CommunicationType::Email,
            'status' => CommunicationStatus::Sent,
            'from' => $this->faker->safeEmail(),
            'to' => $this->faker->safeEmail(),
            'subject' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'mailable_class' => null,
            'message_id' => null,
            'error_message' => null,
            'metadata' => null,
            'sent_at' => now(),
        ];
    }

    public function failed(string $error = 'Test failure'): static
    {
        return $this->state(fn() => [
            'status' => CommunicationStatus::Failed,
            'error_message' => $error,
            'sent_at' => null,
        ]);
    }
}
