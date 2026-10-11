<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('training_matrix_entries', function (Blueprint $table) {
            if (! Schema::hasColumn('training_matrix_entries', 'max_cap_description')) {
                $table->text('max_cap_description')->nullable()->after('max_cap');
            }
        });
    }

    public function down(): void
    {
        Schema::table('training_matrix_entries', function (Blueprint $table) {
            if (Schema::hasColumn('training_matrix_entries', 'max_cap_description')) {
                $table->dropColumn('max_cap_description');
            }
        });
    }
};
