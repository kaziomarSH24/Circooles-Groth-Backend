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
        Schema::table('tutor_verifications', function (Blueprint $table) {
            $table->enum('payment_status', ['pending', 'paid', 'failed'])->default('pending')->after('status');
            $table->string('payment_url')->nullable()->after('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tutor_verifications', function (Blueprint $table) {
            $table->dropColumn('payment_status');
            $table->dropColumn('payment_url');
        });
    }
};
