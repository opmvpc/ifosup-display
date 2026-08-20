<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unicité du nom d'enseignant, décidée par l'école le 2026-08-20.
     *
     * Réserve consignée : deux enseignants peuvent légitimement être homonymes, et
     * cette contrainte empêchera alors d'encoder le second. Si le cas se présente,
     * la bonne réponse n'est pas de retirer la contrainte mais d'ajouter un
     * discriminant métier (matricule, initiales) et de l'inclure dans l'unicité.
     */
    public function up(): void
    {
        $this->mergeDuplicates();

        Schema::table('teachers', function (Blueprint $table): void {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table): void {
            $table->dropUnique(['name']);
        });
    }

    /**
     * Rattache les cours des doublons à l'enseignant le plus ancien, puis supprime
     * les fiches en double.
     */
    private function mergeDuplicates(): void
    {
        $duplicates = DB::table('teachers')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicates as $name) {
            $ids = DB::table('teachers')->where('name', $name)->orderBy('id')->pluck('id');
            $canonical = $ids->shift();

            DB::table('courses')->whereIn('teacher_id', $ids)->update(['teacher_id' => $canonical]);
            DB::table('teachers')->whereIn('id', $ids)->delete();
        }
    }
};
