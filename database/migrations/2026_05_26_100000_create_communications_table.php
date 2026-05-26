<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('communications')) {
            return;
        }

        Schema::create('communications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()
                ->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->nullOnDelete();
            $table->string('type', 32)->default('email');
            $table->string('status', 32)->default('sent');
            $table->string('from', 255)->nullable();
            $table->string('to', 1024);
            $table->string('subject', 512)->nullable();
            $table->longText('body')->nullable();
            $table->string('mailable_class', 255)->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sent_at']);
            $table->index(['customer_id', 'sent_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communications');
    }
};
