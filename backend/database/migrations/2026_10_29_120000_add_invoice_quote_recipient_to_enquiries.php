<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table): void {
            if (! Schema::hasColumn('enquiries', 'invoice_quote_name')) {
                $table->string('invoice_quote_name', 200)->nullable()->after('email');
            }
            if (! Schema::hasColumn('enquiries', 'invoice_quote_email')) {
                $table->string('invoice_quote_email', 200)->nullable()->after('invoice_quote_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table): void {
            if (Schema::hasColumn('enquiries', 'invoice_quote_email')) {
                $table->dropColumn('invoice_quote_email');
            }
            if (Schema::hasColumn('enquiries', 'invoice_quote_name')) {
                $table->dropColumn('invoice_quote_name');
            }
        });
    }
};
