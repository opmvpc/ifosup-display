<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Group;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Remplit les sections et les cours à partir du catalogue IFOSUP
 * (database/seeders/data/course_catalog.php).
 *
 * À lancer manuellement, y compris sur une base déjà remplie :
 *
 *     php artisan db:seed --class=CourseCatalogSeeder
 *
 * Le seeder est idempotent : un cours existant (même code) ou une section
 * existante (même nom) est réutilisé tel quel, jamais modifié ni dupliqué.
 */
class CourseCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $catalog = require database_path('seeders/data/course_catalog.php');

        $created = 0;

        DB::transaction(function () use ($catalog, &$created) {
            foreach ($catalog as $sectionName => $courses) {
                $group = Group::firstOrCreate(['name' => $sectionName]);

                foreach ($courses as $code => $courseName) {
                    $course = Course::firstOrCreate(
                        ['code' => $code],
                        ['name' => $courseName],
                    );

                    if ($course->wasRecentlyCreated) {
                        $created++;
                    }

                    $course->groups()->syncWithoutDetaching([$group->id]);
                }
            }
        });

        $this->command?->info(sprintf(
            '%d sections traitées, %d cours créés (les codes déjà présents sont laissés intacts).',
            count($catalog),
            $created,
        ));
    }
}
