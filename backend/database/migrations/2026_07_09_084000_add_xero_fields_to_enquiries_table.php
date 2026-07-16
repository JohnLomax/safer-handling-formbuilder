<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('xero_contact_id')->nullable()->after('monday_item_id');
            $table->string('xero_quote_id')->nullable()->after('xero_contact_id');
            $table->string('xero_quote_number')->nullable()->after('xero_quote_id');
            $table->timestamp('xero_quote_sent_at')->nullable()->after('quote_email_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn([
                'xero_contact_id',
                'xero_quote_id',
                'xero_quote_number',
                'xero_quote_sent_at',
            ]);
        });
    }
};
