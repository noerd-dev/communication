<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per billable message a provider accepted. Kept apart from `communications`
 * because the log is pruned by retention while usage must survive for invoicing —
 * the link to the log row is therefore nulled, never cascaded.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('communication_usages')) {
            return;
        }

        Schema::create('communication_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()
                ->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('communication_id')->nullable()
                ->constrained('communications')->nullOnDelete();
            $table->string('type', 32);
            $table->string('provider', 64);
            $table->unsignedInteger('units')->default(1);
            $table->decimal('cost', 10, 5)->nullable();
            $table->string('currency', 8)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['tenant_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_usages');
    }
};
