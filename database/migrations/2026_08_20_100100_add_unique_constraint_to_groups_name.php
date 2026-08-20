<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deux sections pouvaient porter le même nom, ce qui scindait leurs cours entre
     * deux identifiants dans la table pivot `course_group`.
     */
    public function up(): void
    {
        $this->mergeDuplicates();

        Schema::table('groups', function (Blueprint $table): void {
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table): void {
            $table->dropUnique(['name']);
        });
    }

    /**
     * Reporte les liens de la pivot vers la section la plus ancienne, en écartant les
     * paires qui existent déjà des deux côtés pour ne pas créer de doublon, puis
     * supprime les sections en double.
     */
    private function mergeDuplicates(): void
    {
        $duplicates = DB::table('groups')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('name');

        foreach ($duplicates as $name) {
            $ids = DB::table('groups')->where('name', $name)->orderBy('id')->pluck('id');
            $canonical = $ids->shift();

            $alreadyLinked = DB::table('course_group')
                ->where('group_id', $canonical)
                ->pluck('course_id');

            DB::table('course_group')
                ->whereIn('group_id', $ids)
                ->whereIn('course_id', $alreadyLinked)
                ->delete();

            DB::table('course_group')->whereIn('group_id', $ids)->update(['group_id' => $canonical]);
            DB::table('groups')->whereIn('id', $ids)->delete();
        }
    }
};
