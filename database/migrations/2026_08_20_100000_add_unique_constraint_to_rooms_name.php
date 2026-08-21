<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deux locaux pouvaient porter le même nom. L'import Excel résout les locaux par
     * `Room::whereIn('name', ...)->pluck('id', 'name')`, qui ne retient qu'un
     * identifiant par nom, choisi arbitrairement : un import pouvait donc rattacher
     * le planning à un local homonyme sans historique.
     */
    public function up(): void
    {
        $this->mergeDuplicates();

        Schema::table('rooms', function (Blueprint $table): void {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table): void {
            $table->dropUnique(['name']);
        });
    }

    /**
     * Reporte les attributions des doublons vers le local le plus ancien, puis
     * supprime les doublons devenus orphelins.
     */
    private function mergeDuplicates(): void
    {
        $duplicates = DB::table('rooms')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicates as $name) {
            $ids = DB::table('rooms')->where('name', $name)->orderBy('id')->pluck('id');
            $canonical = $ids->shift();

            DB::table('assignments')->whereIn('room_id', $ids)->update(['room_id' => $canonical]);
            DB::table('rooms')->whereIn('id', $ids)->delete();
        }
    }
};
