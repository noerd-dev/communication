<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates `communication_settings` with the module's final schema.
 *
 * The guard also covers the legacy table name: installations that still carry
 * `marketing_settings` must fall through to the rename migration, which preserves their
 * SMTP data. Creating an empty table here would orphan it, because this file sorts before
 * the rename.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('communication_settings') || Schema::hasTable('marketing_settings')) {
            return;
        }

        Schema::create('communication_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()
                ->constrained('tenants')->cascadeOnDelete();
            $table->string('from_email')->nullable();
            $table->string('reply_email')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 16)->nullable();
            $table->string('smtp_username')->nullable();
            $table->string('smtp_password')->nullable();
            $table->boolean('use_custom_smtp')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_settings');
    }
};
