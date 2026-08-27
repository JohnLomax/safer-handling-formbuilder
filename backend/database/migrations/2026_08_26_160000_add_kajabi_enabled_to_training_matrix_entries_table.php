<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('training_matrix_entries', 'kajabi_enabled')) {
            Schema::table('training_matrix_entries', function (Blueprint $table) {
                // Default true so existing course rows keep current Kajabi behaviour.
                $table->boolean('kajabi_enabled')->default(true)->after('is_active');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('training_matrix_entries', 'kajabi_enabled')) {
            Schema::table('training_matrix_entries', function (Blueprint $table) {
                $table->dropColumn('kajabi_enabled');
            });
        }
    }
};
