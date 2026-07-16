<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('feedback_submissions')) {
            Schema::create('feedback_submissions', function (Blueprint $table) {
                $table->id();
                $table->string('issue_faced');
                $table->text('description');
                $table->timestamp('resolved_at')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('created_at');
                $table->index('resolved_at');
            });

            return;
        }

        Schema::table('feedback_submissions', function (Blueprint $table) {
            if (! Schema::hasColumn('feedback_submissions', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('feedback_submissions') && Schema::hasColumn('feedback_submissions', 'resolved_at')) {
            Schema::table('feedback_submissions', function (Blueprint $table) {
                $table->dropColumn('resolved_at');
            });
        }
    }
};
