<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('enquiry_type');
            $table->string('status')->default('in_progress');
            $table->string('audience_type')->nullable();
            $table->string('personal_goal')->nullable();
            $table->string('trainer_course_select')->nullable();
            $table->boolean('booking_via_company')->default(false);
            $table->unsignedSmallInteger('trainer_attendees')->nullable();
            $table->string('sector')->nullable();
            $table->string('org_course')->nullable();
            $table->string('course_format')->nullable();
            $table->string('format_sub_option')->nullable();
            $table->unsignedSmallInteger('matrix_attendees')->nullable();
            $table->string('preferred_date_time')->nullable();
            $table->boolean('date_not_sure')->default(false);
            $table->unsignedSmallInteger('attendees')->nullable();
            $table->text('extra_notes')->nullable();
            $table->json('form_data_json')->nullable();
            $table->string('monday_item_id')->nullable();
            $table->timestamp('monday_synced_at')->nullable();
            $table->timestamp('quote_email_sent_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('status');
            $table->index('submitted_at');
            $table->index('monday_synced_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
