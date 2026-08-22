<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ce fichier est l'ancien `2026_03_26_131518_...`, renommé pour rétablir
        // l'ordre des migrations (IFO-006). Laravel identifiant les migrations par
        // leur nom de fichier, une base migrée avant le renommage considérerait
        // celle-ci comme jamais exécutée : elle recréerait la table alors que
        // `2026_05_11_135528_drop_recurring_assignments` l'a déjà supprimée — et ne
        // sera pas rejouée. Dans ce cas, on n'exécute rien : la migration est
        // simplement enregistrée sous son nouveau nom.
        $alreadyRanUnderFormerName = DB::table('migrations')
            ->where('migration', '2026_03_26_131518_create_recurring_assignments_table')
            ->exists();

        if ($alreadyRanUnderFormerName) {
            return;
        }

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
