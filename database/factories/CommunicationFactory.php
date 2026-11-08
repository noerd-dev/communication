<?php

namespace Noerd\Communication\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Communication\Enums\CommunicationDirection;
use Noerd\Communication\Enums\CommunicationStatus;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Models\Communication;
use Noerd\Models\Tenant;

class CommunicationFactory extends Factory
{
    protected $model = Communication::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'type' => CommunicationType::Email,
            'direction' => CommunicationDirection::Outbound,
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

    /**
     * An outbound text message to a phone number.
     */
    public function text(CommunicationType $type = CommunicationType::Sms, string $to = '+491711234567'): static
    {
        return $this->state(fn() => [
            'type' => $type,
            'from' => '+4930000000',
            'to' => $to,
            'subject' => null,
        ]);
    }

    /**
     * An inbound text message from a phone number.
     */
    public function inbound(CommunicationType $type = CommunicationType::Sms, string $from = '+491711234567'): static
    {
        return $this->state(fn() => [
            'type' => $type,
            'direction' => CommunicationDirection::Inbound,
            'status' => CommunicationStatus::Received,
            'from' => $from,
            'to' => '+4930000000',
            'subject' => null,
        ]);
    }
}
