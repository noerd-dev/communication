<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketing_settings') || Schema::hasTable('communication_settings')) {
            return;
        }

        Schema::rename('marketing_settings', 'communication_settings');
    }

    public function down(): void
    {
        if (! Schema::hasTable('communication_settings') || Schema::hasTable('marketing_settings')) {
            return;
        }

        Schema::rename('communication_settings', 'marketing_settings');
    }
};
