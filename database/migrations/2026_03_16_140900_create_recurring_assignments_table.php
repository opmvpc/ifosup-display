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
        Schema::create('recurring_assignments', function (Blueprint $table) {
            $table->id();
            $table->timestamps();

            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();

            $table->integer('day_of_week'); // 1 (lundi) à 7 (dimanche)
            $table->enum('period', ['morning', 'afternoon', 'evening']);

            $table->string('start_week', 8);
            $table->string('end_week', 8);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_assignments');
    }
};
