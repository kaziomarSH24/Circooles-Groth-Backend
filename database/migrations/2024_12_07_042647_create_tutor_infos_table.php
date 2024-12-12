<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tutor_infos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('address');
            $table->text('description');
            $table->json('subjects_id');
            $table->string('designation');
            $table->string('organization');
            $table->string('teaching_experience');
            $table->string('expertise_area');
            $table->string('degree');
            $table->string('institute');
            $table->bigInteger('graduation_year');
            $table->string('time_zone');
            $table->json('online')->nullable();
            $table->json('offline')->nullable();
            $table->string('session_charge');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutor_infos');
    }
};
