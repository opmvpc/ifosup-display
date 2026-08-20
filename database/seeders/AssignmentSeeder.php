<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;
use Illuminate\Database\Seeder;

class AssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $courseIds = Course::query()->pluck('id');
        $roomIds = Room::query()->pluck('id');

        if ($courseIds->isEmpty()) {
            return;
        }

        Assignment::factory(100)
            ->state(fn (): array => [
                'course_id' => $courseIds->random(),
                'room_id' => $roomIds->isNotEmpty() && fake()->boolean(90) ? $roomIds->random() : null,
            ])
            ->create();
    }
}
