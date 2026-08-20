<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brings an EXISTING `communications` table up to the module's current schema. Purely
 * additive and idempotent: it adds whatever columns and indexes are missing and never
 * touches data or drops anything.
 *
 * Fresh installations get the same shape straight from create_communications_table and
 * skip every branch here.
 *
 * Moving legacy `customer_id` values onto the contact link is deliberately NOT done here:
 * only the modules that wrote those rows know which record the id refers to, so they ship
 * their own backfill (see liefertool and booking-members).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('communications')) {
            return;
        }

        Schema::table('communications', function (Blueprint $table): void {
            if (! Schema::hasColumn('communications', 'contact_type')) {
                $table->string('contact_type', 255)->nullable();
            }

            if (! Schema::hasColumn('communications', 'contact_id')) {
                $table->unsignedBigInteger('contact_id')->nullable();
            }

            if (! Schema::hasColumn('communications', 'model_type')) {
                $table->string('model_type', 255)->nullable();
            }

            if (! Schema::hasColumn('communications', 'model_id')) {
                $table->unsignedBigInteger('model_id')->nullable();
            }

            if (! Schema::hasColumn('communications', 'message_id')) {
                $table->string('message_id', 255)->nullable();
            }
        });

        Schema::table('communications', function (Blueprint $table): void {
            if (! $this->hasIndexOn(['contact_type', 'contact_id'])) {
                $table->index(['contact_type', 'contact_id']);
            }

            if (! $this->hasIndexOn(['model_type', 'model_id'])) {
                $table->index(['model_type', 'model_id']);
            }

            if (! $this->hasIndexOn(['message_id'])) {
                $table->index('message_id');
            }
        });
    }

    /**
     * Only the contact link is reverted — model_type/model_id and message_id predate this
     * migration and may well have been added by an earlier one.
     */
    public function down(): void
    {
        if (! Schema::hasTable('communications') || ! Schema::hasColumn('communications', 'contact_type')) {
            return;
        }

        if ($this->hasIndexOn(['contact_type', 'contact_id'])) {
            Schema::table('communications', function (Blueprint $table): void {
                $table->dropIndex(['contact_type', 'contact_id']);
            });
        }

        Schema::table('communications', function (Blueprint $table): void {
            $table->dropColumn(['contact_type', 'contact_id']);
        });
    }

    /**
     * Matched on columns rather than on the generated index name, so an installation that
     * carries a hand-made index under a different name is not given a duplicate.
     *
     * @param  array<int,string>  $columns
     */
    private function hasIndexOn(array $columns): bool
    {
        foreach (Schema::getIndexes('communications') as $index) {
            if ($index['columns'] === $columns) {
                return true;
            }
        }

        return false;
    }
};
