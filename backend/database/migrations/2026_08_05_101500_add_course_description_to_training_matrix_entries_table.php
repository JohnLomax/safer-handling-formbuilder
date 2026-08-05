<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('training_matrix_entries', 'course_description')) {
            Schema::table('training_matrix_entries', function (Blueprint $table) {
                $table->text('course_description')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('training_matrix_entries', 'course_description')) {
            Schema::table('training_matrix_entries', function (Blueprint $table) {
                $table->dropColumn('course_description');
            });
        }
    }
};
