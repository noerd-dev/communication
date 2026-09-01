<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sender accounts: a tenant may keep any number of them, exactly one flagged as default.
 *
 * There is deliberately NO unique index on tenant_id — that singleton constraint on
 * communication_settings is exactly what this table replaces. smtp_password is `text`
 * because the model casts it to `encrypted`, and an encrypter payload for even a short
 * secret runs past varchar(255).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('communication_mail_senders')) {
            return;
        }

        Schema::create('communication_mail_senders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('from_email')->nullable();
            $table->string('reply_email')->nullable();
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 16)->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'is_default']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_mail_senders');
    }
};
