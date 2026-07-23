<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('enquiries', 'xero_invoice_sent_at')) {
                $table->timestamp('xero_invoice_sent_at')->nullable()->after('xero_invoice_created_at');
            }
            if (! Schema::hasColumn('enquiries', 'monday_booking_item_id')) {
                $table->string('monday_booking_item_id')->nullable()->after('monday_item_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('enquiries', 'xero_invoice_sent_at') ? 'xero_invoice_sent_at' : null,
                Schema::hasColumn('enquiries', 'monday_booking_item_id') ? 'monday_booking_item_id' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
