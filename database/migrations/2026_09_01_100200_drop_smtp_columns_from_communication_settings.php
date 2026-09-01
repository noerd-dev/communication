<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The SMTP credentials now live per sender account (communication_mail_senders).
 *
 * A separate file from the data copy on purpose: MySQL DDL is not transactional, so a single
 * migration that copied rows and then dropped columns would leave an unrecoverable half-state
 * if the drop failed — the copy would not be recorded, and re-running would duplicate senders.
 *
 * from_email STAYS: TenantRegisterService still writes it. reply_email is dropped one
 * migration later, once liefertool has copied it to liefertool_settings.notification_emails.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('communication_settings')) {
            return;
        }

        $columns = array_values(array_filter(
            ['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'smtp_password', 'use_custom_smtp'],
            fn(string $column): bool => Schema::hasColumn('communication_settings', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('communication_settings', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('communication_settings') || Schema::hasColumn('communication_settings', 'smtp_host')) {
            return;
        }

        Schema::table('communication_settings', function (Blueprint $table): void {
            $table->string('smtp_host')->nullable();
            $table->unsignedSmallInteger('smtp_port')->nullable();
            $table->string('smtp_encryption', 16)->nullable();
            $table->string('smtp_username')->nullable();
            $table->string('smtp_password')->nullable();
            $table->boolean('use_custom_smtp')->default(false);
        });
    }
};
