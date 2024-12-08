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
        Schema::create('tutor_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained('tutor_infos')->onDelete('cascade');
            $table->json('academic_certificates');
            $table->json('id_card');
            $table->json('tsc') ->nullable();
            $table->decimal('verification_fee', 8, 2)->default(0);
            $table->enum('status', ['pending', 'declined', 'verified'])->default('pending');    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tutor_verifications');
    }
};
