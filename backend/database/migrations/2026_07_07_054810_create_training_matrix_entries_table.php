<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_matrix_entries', function (Blueprint $table) {
            $table->id();
            $table->string('sector');
            $table->string('course');
            $table->string('course_value');
            $table->string('format');
            $table->string('sub_option');
            $table->unsignedSmallInteger('min_attendees')->default(1);
            $table->unsignedSmallInteger('max_cap')->nullable();
            $table->unsignedSmallInteger('default_attendees')->nullable();
            $table->json('pricing');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_matrix_entries');
    }
};
