<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_matrix_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('training_matrix_entries', 'enable_second_date')) {
                $table->boolean('enable_second_date')->default(false)->after('kajabi_enabled');
            }
        });

        Schema::table('enquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('enquiries', 'preferred_date_2')) {
                $table->string('preferred_date_2')->nullable()->after('preferred_date_time');
            }
            if (! Schema::hasColumn('enquiries', 'enable_second_date')) {
                $table->boolean('enable_second_date')->default(false)->after('preferred_date_2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            foreach (['enable_second_date', 'preferred_date_2'] as $column) {
                if (Schema::hasColumn('enquiries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('training_matrix_entries', function (Blueprint $table) {
            if (Schema::hasColumn('training_matrix_entries', 'enable_second_date')) {
                $table->dropColumn('enable_second_date');
            }
        });
    }
};
