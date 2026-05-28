<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('communications')) {
            return;
        }

        if (Schema::hasColumn('communications', 'message_id')) {
            return;
        }

        Schema::table('communications', function (Blueprint $table): void {
            $table->string('message_id', 255)->nullable()->after('mailable_class');
            $table->index('message_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('communications')) {
            return;
        }

        if (! Schema::hasColumn('communications', 'message_id')) {
            return;
        }

        Schema::table('communications', function (Blueprint $table): void {
            $table->dropIndex(['message_id']);
            $table->dropColumn('message_id');
        });
    }
};
