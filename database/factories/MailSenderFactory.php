<?php

namespace Noerd\Communication\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Communication\Models\MailSender;
use Noerd\Models\Tenant;

class MailSenderFactory extends Factory
{
    protected $model = MailSender::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'name' => $this->faker->company(),
            'from_email' => $this->faker->safeEmail(),
            'reply_email' => null,
            'smtp_host' => null,
            'smtp_port' => null,
            'smtp_encryption' => null,
            'smtp_username' => null,
            'smtp_password' => null,
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function withSmtp(string $host = 'smtp.example.com', string $username = 'user@example.com'): static
    {
        return $this->state(fn(): array => [
            'smtp_host' => $host,
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'smtp_username' => $username,
            'smtp_password' => 'secret',
        ]);
    }

    public function default(): static
    {
        return $this->state(fn(): array => [
            'is_default' => true,
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn(): array => [
            'is_active' => false,
            'is_default' => false,
        ]);
    }
}
