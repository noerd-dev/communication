<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates `communications` with the module's final schema. Existing installations skip
 * this and are brought up to the same shape by add_contact_to_communications.
 *
 * The table carries two independent polymorphic links and no foreign key to any domain
 * table, so the module stays independent of whatever a host app models:
 *   model_type/model_id     — the source record the mail was generated from
 *   contact_type/contact_id — the record the mail concerns
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('communications')) {
            return;
        }

        Schema::create('communications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()
                ->constrained('tenants')->cascadeOnDelete();
            $table->string('contact_type', 255)->nullable();
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('model_type', 255)->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('type', 32)->default('email');
            $table->string('status', 32)->default('sent');
            $table->string('from', 255)->nullable();
            $table->string('to', 1024);
            $table->string('subject', 512)->nullable();
            $table->longText('body')->nullable();
            $table->string('mailable_class', 255)->nullable();
            $table->string('message_id', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sent_at']);
            $table->index(['contact_type', 'contact_id']);
            $table->index(['model_type', 'model_id']);
            $table->index(['type', 'status']);
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
