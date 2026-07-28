<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_inbound_auto_replies', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject', 500)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('auto_reply_sent_at')->nullable();
            $table->string('skipped_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_inbound_auto_replies');
    }
};
