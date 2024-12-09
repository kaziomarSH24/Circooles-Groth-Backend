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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('subtitle');
            $table->string('slug')->unique();
            $table->decimal('price', 10, 2);
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('sub_category_id')->constrained()->onDelete('cascade');
            $table->string('topic');
            $table->string('language');
            $table->string('c_level');
            $table->integer('duration');
            $table->string('thumbnail');
            $table->string('trail_video');
            $table->text('description');
            $table->json('teach_course')->nullable();
            $table->json('targer_audience')->nullable();
            $table->json('requirements')->nullable();
            $table->bigInteger('total_enrollment')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
