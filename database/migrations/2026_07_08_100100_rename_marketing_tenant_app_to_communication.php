<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('tenant_apps')) {
            return;
        }

        DB::table('tenant_apps')->where('name', 'MARKETING')->update([
            'name' => 'COMMUNICATION',
            'title' => 'Communication',
            'icon' => 'communication::icons.app',
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenant_apps')) {
            return;
        }

        DB::table('tenant_apps')->where('name', 'COMMUNICATION')->update([
            'name' => 'MARKETING',
            'title' => 'Marketing',
            'icon' => 'marketing::icons.app',
        ]);
    }
};
