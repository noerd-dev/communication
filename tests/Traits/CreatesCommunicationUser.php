<?php

declare(strict_types=1);

namespace Noerd\Communication\Tests\Traits;

use Noerd\Helpers\TenantHelper;
use Noerd\Models\NoerdUser;
use Noerd\Models\Tenant;
use Noerd\Models\TenantApp;

trait CreatesCommunicationUser
{
    protected function withCommunicationModule(): NoerdUser
    {
        $tenant = Tenant::factory()->create();
        $user = NoerdUser::factory()->create(['selected_tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id);
        TenantHelper::setSelectedTenantId($tenant->id);
        TenantHelper::setSelectedApp('COMMUNICATION');

        $app = TenantApp::firstOrCreate(
            ['name' => 'COMMUNICATION'],
            [
                'title' => 'Communication',
                'icon' => 'communication::icons.app',
                'route' => 'communications',
                'is_active' => true,
            ],
        );
        $tenant->tenantApps()->syncWithoutDetaching([$app->id]);

        return $user;
    }
}
