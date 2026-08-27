<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('training_matrix_entries', 'kajabi_enabled')) {
            Schema::table('training_matrix_entries', function (Blueprint $table) {
                $table->boolean('kajabi_enabled')->default(true)->after('is_active');
            });
        }

        // Ensure every existing skill matrix row has Kajabi enabled by default.
        DB::table('training_matrix_entries')
            ->where(function ($query): void {
                $query->where('kajabi_enabled', false)
                    ->orWhereNull('kajabi_enabled');
            })
            ->update(['kajabi_enabled' => true]);
    }

    public function down(): void
    {
        // Intentionally left empty: do not turn Kajabi back off for existing rows.
    }
};
