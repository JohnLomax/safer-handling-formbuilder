<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->text('booking_details_json')->nullable()->after('form_data_json');
            $table->timestamp('booking_email_sent_at')->nullable()->after('xero_quote_sent_at');
            $table->timestamp('booking_submitted_at')->nullable()->after('booking_email_sent_at');
            $table->timestamp('terms_accepted_at')->nullable()->after('booking_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn([
                'booking_details_json',
                'booking_email_sent_at',
                'booking_submitted_at',
                'terms_accepted_at',
            ]);
        });
    }
};
