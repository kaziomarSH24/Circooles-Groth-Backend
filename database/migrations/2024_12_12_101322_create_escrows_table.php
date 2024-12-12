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
        Schema::create('escrows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained('tutor_bookings')->onDelete('cascade');
            //hold amount in escrow
            $table->decimal('hold_amount', 10, 2);
            $table->enum('status', ['hold', 'released', 'refunded'])->default('hold');
            $table->timestamp('release_date')->nullable();
            $table->timestamp('refund_date')->nullable();
            $table->string('reference_id')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escrows');
    }
};
