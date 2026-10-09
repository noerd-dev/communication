<?php

namespace Noerd\Communication\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Noerd\Communication\Enums\CommunicationType;
use Noerd\Communication\Models\CommunicationUsage;
use Noerd\Models\Tenant;

class CommunicationUsageFactory extends Factory
{
    protected $model = CommunicationUsage::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'communication_id' => null,
            'type' => CommunicationType::Sms,
            'provider' => 'zz',
            'units' => 1,
            'cost' => null,
            'currency' => null,
            'occurred_at' => now(),
        ];
    }
}
