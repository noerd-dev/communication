<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('communications')) {
            return;
        }

        if (Schema::hasColumn('communications', 'model_type')) {
            return;
        }

        Schema::table('communications', function (Blueprint $table): void {
            $table->string('model_type', 255)->nullable()->after('customer_id');
            $table->unsignedBigInteger('model_id')->nullable()->after('model_type');
            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('communications')) {
            return;
        }

        if (!Schema::hasColumn('communications', 'model_type')) {
            return;
        }

        Schema::table('communications', function (Blueprint $table): void {
            $table->dropIndex(['model_type', 'model_id']);
            $table->dropColumn(['model_type', 'model_id']);
        });
    }
};
