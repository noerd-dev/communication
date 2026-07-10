<?php

namespace Noerd\Communication\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Communication\Models\CommunicationSetting;
use Noerd\Models\Tenant;

class CommunicationSettingFactory extends Factory
{
    protected $model = CommunicationSetting::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'from_email' => $this->faker->safeEmail(),
            'reply_email' => null,
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_encryption' => null,
            'smtp_username' => null,
            'smtp_password' => null,
            'use_custom_smtp' => false,
        ];
    }

    public function withSmtp(string $host = 'smtp.example.com', string $username = 'user@example.com'): static
    {
        return $this->state(fn () => [
            'smtp_host' => $host,
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => $username,
            'smtp_password' => 'secret',
            'use_custom_smtp' => true,
        ]);
    }
}
