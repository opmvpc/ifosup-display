<?php

use Carbon\Carbon;
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
        $hasStartWeek = Schema::hasColumn('recurring_assignments', 'start_week');
        $hasEndWeek = Schema::hasColumn('recurring_assignments', 'end_week');

        if (! $hasStartWeek || ! $hasEndWeek) {
            Schema::table('recurring_assignments', function (Blueprint $table) use ($hasStartWeek, $hasEndWeek): void {
                if (! $hasStartWeek) {
                    $table->string('start_week', 8)->nullable()->after('period');
                }

                if (! $hasEndWeek) {
                    $table->string('end_week', 8)->nullable()->after('start_week');
                }
            });
        }

        if (Schema::hasColumn('recurring_assignments', 'start_date') && Schema::hasColumn('recurring_assignments', 'end_date')) {
            DB::table('recurring_assignments')
                ->select(['id', 'start_date', 'end_date'])
                ->orderBy('id')
                ->chunkById(200, function ($rows): void {
                    foreach ($rows as $row) {
                        if (! $row->start_date || ! $row->end_date) {
                            continue;
                        }

                        DB::table('recurring_assignments')
                            ->where('id', $row->id)
                            ->update([
                                'start_week' => Carbon::parse($row->start_date, 'UTC')->format('o-\\WW'),
                                'end_week' => Carbon::parse($row->end_date, 'UTC')->format('o-\\WW'),
                            ]);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('recurring_assignments', function (Blueprint $table) {
            $table->dropColumn(['start_week', 'end_week']);
        });
    }
};
