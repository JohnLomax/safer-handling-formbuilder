<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->string('resume_token', 64)->nullable()->after('form_data_json');
            $table->timestamp('resume_email_sent_at')->nullable()->after('quote_email_sent_at');
            $table->index('resume_token');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table) {
            $table->dropIndex(['resume_token']);
            $table->dropColumn(['resume_token', 'resume_email_sent_at']);
        });
    }
};
