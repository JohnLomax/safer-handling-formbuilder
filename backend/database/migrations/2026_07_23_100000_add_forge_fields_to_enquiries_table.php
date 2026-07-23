<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('enquiries', 'forge_synced_at')) {
                $table->timestamp('forge_synced_at')->nullable()->after('xero_invoice_created_at');
            }
            if (! Schema::hasColumn('enquiries', 'forge_event_id')) {
                $table->string('forge_event_id')->nullable()->after('forge_synced_at');
            }
            if (! Schema::hasColumn('enquiries', 'forge_last_action')) {
                $table->string('forge_last_action')->nullable()->after('forge_event_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('enquiries', 'forge_synced_at') ? 'forge_synced_at' : null,
                Schema::hasColumn('enquiries', 'forge_event_id') ? 'forge_event_id' : null,
                Schema::hasColumn('enquiries', 'forge_last_action') ? 'forge_last_action' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
