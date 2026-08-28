<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    private static int $courseCounter = 0;

    private static array $courseNames = [
        // Cours généraux
        'Mathématiques',
        'Français',
        'Anglais',
        'Allemand',
        'Espagnol',
        'Sciences Naturelles',
        'Physique',
        'Chimie',
        'Biologie',
        'Histoire',
        'Géographie',
        'Éducation Civique',
        'Informatique',
        'Éducation Physique',
        'Arts Plastiques',
        'Musique',
        'Littérature',
        'Philosophie',
        'Économie',
        'Gestion',
        'Comptabilité',
        'Droit',
        'Marketing',
    ];

    public function definition(): array
    {
        $level = fake()->randomElement([2, 4, 5]); // 2=inférieur, 4=supérieur, 5=université
        $letters = strtoupper(fake()->lexify('????'));
        $suffix = fake()->numberBetween(1, 3);
        $code = $level.$letters.'-'.$suffix;

        $name = fake()->randomElement(self::$courseNames);
        if ($level === 2) {
            $name .= ' - 1ère année';
        } elseif ($level === 4) {
            $name .= ' - Avancé';
        } else {
            $name .= ' - Licence';
        }

        return [
            'code' => $code,
            'name' => $name,
            'year' => fake()->optional(0.7)->numberBetween(1, 3),
            'teacher_id' => Teacher::factory(),
        ];
    }
}
