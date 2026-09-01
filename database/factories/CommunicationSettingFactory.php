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
        ];
    }
}
