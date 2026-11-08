<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound text messages: every row is outbound (sent by the platform) or inbound
 * (a reply a provider handed over). An inbound row points at the outbound
 * message it answers — without a foreign key, the log is pruned by retention.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('communications', 'direction')) {
            return;
        }

        Schema::table('communications', function (Blueprint $table): void {
            $table->string('direction', 16)->default('outbound')->after('type');
            $table->unsignedBigInteger('in_reply_to_id')->nullable()->after('message_id');

            $table->index(['tenant_id', 'type', 'direction']);
            $table->index('in_reply_to_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('communications', 'direction')) {
            return;
        }

        Schema::table('communications', function (Blueprint $table): void {
            $table->dropIndex(['tenant_id', 'type', 'direction']);
            $table->dropIndex(['in_reply_to_id']);
            $table->dropColumn(['direction', 'in_reply_to_id']);
        });
    }
};
