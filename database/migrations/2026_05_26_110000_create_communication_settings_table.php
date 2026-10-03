<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per tenant. The sender addresses and SMTP credentials live on
 * `communication_mail_senders`; `from_email` is kept for the record but not
 * consulted when a mail is sent (SPF/DKIM).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('communication_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->unique()
                ->constrained('tenants')->cascadeOnDelete();
            $table->string('from_email')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_settings');
    }
};
