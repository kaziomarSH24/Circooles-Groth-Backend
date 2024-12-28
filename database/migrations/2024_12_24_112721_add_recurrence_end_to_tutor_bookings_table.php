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
        Schema::table('tutor_bookings', function (Blueprint $table) {
            $table->timestamp('recurrence_end')->nullable()->after('repeat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tutor_bookings', function (Blueprint $table) {
            $table->dropColumn('recurrence_end');
        });
    }
};
