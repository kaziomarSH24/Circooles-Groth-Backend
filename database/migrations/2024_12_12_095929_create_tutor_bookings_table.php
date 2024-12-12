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
        Schema::create('tutor_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained('tutor_infos')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->json('schedule');
            $table->enum('repeat', ['daily', 'weekly', 'bi-weekly', 'monthly'])->nullable(); //if null then it's not a repeat session
            $table->integer('session_quantity');
            $table->decimal('session_cost', 10, 2);
            $table->decimal('total_cost', 10, 2);
            $table->enum('status', ['pending', 'cancel', 'enrolled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutor_bookings');
    }
};
