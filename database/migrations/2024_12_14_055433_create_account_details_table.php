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
        Schema::create('account_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_id')->constrained('tutor_infos')->onDelete('cascade');
            $table->string('account_number');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('bank_name');
            $table->string('bank_code');
            $table->string('account_type')->nullable();
            $table->string('currency')->nullable();
            $table->string('country_code')->nullable();
            $table->string('routing_number')->nullable();
            $table->string('recipient_code')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_details');
    }
};
