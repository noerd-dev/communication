<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * reply_email leaves the communication settings.
 *
 * Despite the name it never was a reply-to — the reply-to of outgoing mail comes from the
 * sender account (MailSender::$reply_email, read by resolvedReplyEmail()). Its only readers
 * were Liefertool, which used it as the tenant's notification address; that concept now lives
 * on liefertool_settings.notification_emails, where the preceding liefertool migration copied
 * the values.
 *
 * The address also survives on the sender account: the 100100 migration copied it into
 * communication_mail_senders.reply_email before this drop.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('communication_settings') || ! Schema::hasColumn('communication_settings', 'reply_email')) {
            return;
        }

        Schema::table('communication_settings', function (Blueprint $table): void {
            $table->dropColumn('reply_email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('communication_settings') || Schema::hasColumn('communication_settings', 'reply_email')) {
            return;
        }

        Schema::table('communication_settings', function (Blueprint $table): void {
            $table->string('reply_email')->nullable();
        });
    }
};
