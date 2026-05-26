<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('mail_logs')) {
            return;
        }

        DB::table('mail_logs')->orderBy('id')->chunkById(500, function ($rows): void {
            $payload = [];

            foreach ($rows as $row) {
                $payload[] = [
                    'tenant_id' => $row->tenant_id,
                    'customer_id' => null,
                    'type' => 'email',
                    'status' => 'sent',
                    'from' => $row->from,
                    'to' => $row->to,
                    'subject' => $row->title,
                    'body' => $row->body,
                    'mailable_class' => null,
                    'error_message' => null,
                    'metadata' => null,
                    'sent_at' => $row->created_at,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ];
            }

            if ($payload !== []) {
                DB::table('communications')->insert($payload);
            }
        });

        Schema::drop('mail_logs');
    }

    public function down(): void
    {
        if (Schema::hasTable('mail_logs')) {
            return;
        }

        Schema::create('mail_logs', function ($table): void {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants');
            $table->string('from');
            $table->string('to');
            $table->string('title');
            $table->longText('body');
            $table->timestamps();
        });
    }
};
