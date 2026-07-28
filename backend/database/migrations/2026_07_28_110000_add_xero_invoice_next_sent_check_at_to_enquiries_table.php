<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('enquiries', 'xero_invoice_next_sent_check_at')) {
                $table->timestamp('xero_invoice_next_sent_check_at')->nullable()->after('xero_invoice_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            if (Schema::hasColumn('enquiries', 'xero_invoice_next_sent_check_at')) {
                $table->dropColumn('xero_invoice_next_sent_check_at');
            }
        });
    }
};
