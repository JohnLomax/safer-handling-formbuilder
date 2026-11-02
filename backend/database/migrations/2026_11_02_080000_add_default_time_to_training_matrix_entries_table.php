<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_matrix_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('training_matrix_entries', 'default_time')) {
                $table->string('default_time', 5)->nullable()->after('enable_second_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_matrix_entries', function (Blueprint $table) {
            if (Schema::hasColumn('training_matrix_entries', 'default_time')) {
                $table->dropColumn('default_time');
            }
        });
    }
};
