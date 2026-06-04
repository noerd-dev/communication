<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('marketing_settings') || Schema::hasColumn('marketing_settings', 'use_custom_smtp')) {
            return;
        }

        Schema::table('marketing_settings', function (Blueprint $table): void {
            $table->boolean('use_custom_smtp')->default(false)->after('smtp_password');
        });

        DB::table('marketing_settings')
            ->whereNotNull('smtp_host')
            ->where('smtp_host', '!=', '')
            ->update(['use_custom_smtp' => true]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketing_settings') || ! Schema::hasColumn('marketing_settings', 'use_custom_smtp')) {
            return;
        }

        Schema::table('marketing_settings', function (Blueprint $table): void {
            $table->dropColumn('use_custom_smtp');
        });
    }
};
