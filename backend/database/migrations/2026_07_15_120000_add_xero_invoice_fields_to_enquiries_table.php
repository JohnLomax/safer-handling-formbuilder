<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('xero_invoice_id')->nullable()->after('xero_quote_number');
            $table->string('xero_invoice_number')->nullable()->after('xero_invoice_id');
            $table->timestamp('xero_invoice_created_at')->nullable()->after('xero_quote_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropColumn([
                'xero_invoice_id',
                'xero_invoice_number',
                'xero_invoice_created_at',
            ]);
        });
    }
};
