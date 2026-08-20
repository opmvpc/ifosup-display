<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Le trio (date, période, local) définit un créneau. Le code le traite partout
     * comme unique — `ScheduleController::slotIsOccupied()`, `bulkStore()`,
     * l'import Excel — mais par des vérifications « lire puis écrire » sans garantie
     * en base : deux écritures concurrentes pouvaient placer deux cours dans le même
     * local au même moment, et l'écran des télévisions affichait les deux.
     *
     * L'index composite remplace `(date, room_id)` : il couvre les mêmes requêtes et
     * sert en plus le filtre par période, le plus sollicité (`ScreenController::data`,
     * interrogé en boucle par les écrans).
     *
     * Les attributions sans local (`room_id` nul) ne se gênent pas entre elles : MySQL
     * comme SQLite considèrent deux NULL comme distincts dans un index unique.
     */
    public function up(): void
    {
        $this->removeDuplicateSlots();

        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropIndex('assignments_date_room_id_index');
            $table->unique(['date', 'period', 'room_id'], 'assignments_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table): void {
            $table->dropUnique('assignments_slot_unique');
            $table->index(['date', 'room_id'], 'assignments_date_room_id_index');
        });
    }

    /**
     * Ne conserve que l'attribution la plus ancienne de chaque créneau occupé.
     */
    private function removeDuplicateSlots(): void
    {
        $duplicates = DB::table('assignments')
            ->select('date', 'period', 'room_id')
            ->whereNotNull('room_id')
            ->groupBy('date', 'period', 'room_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicates as $slot) {
            $ids = DB::table('assignments')
                ->where('date', $slot->date)
                ->where('period', $slot->period)
                ->where('room_id', $slot->room_id)
                ->orderBy('id')
                ->pluck('id');

            DB::table('assignments')->whereIn('id', $ids->slice(1))->delete();
        }
    }
};
