<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('enquiries', 'kajabi_contact_id')) {
                $table->string('kajabi_contact_id')->nullable()->after('xero_invoice_sent_at');
            }
            if (! Schema::hasColumn('enquiries', 'kajabi_offer_id')) {
                $table->string('kajabi_offer_id')->nullable()->after('kajabi_contact_id');
            }
            if (! Schema::hasColumn('enquiries', 'kajabi_enrolled_at')) {
                $table->timestamp('kajabi_enrolled_at')->nullable()->after('kajabi_offer_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('enquiries', 'kajabi_contact_id') ? 'kajabi_contact_id' : null,
                Schema::hasColumn('enquiries', 'kajabi_offer_id') ? 'kajabi_offer_id' : null,
                Schema::hasColumn('enquiries', 'kajabi_enrolled_at') ? 'kajabi_enrolled_at' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
