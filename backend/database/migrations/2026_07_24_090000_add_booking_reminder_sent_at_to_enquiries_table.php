<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('enquiries', 'booking_reminder_sent_at')) {
                $table->timestamp('booking_reminder_sent_at')->nullable()->after('booking_email_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            if (Schema::hasColumn('enquiries', 'booking_reminder_sent_at')) {
                $table->dropColumn('booking_reminder_sent_at');
            }
        });
    }
};
