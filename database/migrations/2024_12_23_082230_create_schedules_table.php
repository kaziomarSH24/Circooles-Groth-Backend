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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_booking_id')->constrained('tutor_bookings')->onDelete('cascade');
            $table->timestamp('start_time')->nullable();
            $table->timestamp('end_time')->nullable();
            $table->enum('type', ['online', 'offline'])->default('online');
            $table->enum('status', ['pending', 'cancel', 'success', 'reschedule', 'completed'])->default('pending');
            $table->timestamp('reschedule_at')->nullable();
            $table->enum('reschedule_by', ['tutor', 'student'])->nullable();
            $table->string('zoom_link')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
