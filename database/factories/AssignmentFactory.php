<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Assignment>
 */
class AssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'room_id' => fake()->boolean(90) ? Room::factory() : null,
            'date' => fake()->dateTimeThisMonth()->format('Y-m-d'),
            'period' => fake()->randomElement(['morning', 'afternoon', 'evening']),
            'status' => 'planned',
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (): array => ['status' => 'planned']);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => ['status' => 'cancelled']);
    }

    public function late(): static
    {
        return $this->state(fn (): array => ['status' => 'late']);
    }

    public function onDate(CarbonInterface|string $date): static
    {
        return $this->state(fn (): array => [
            'date' => $date instanceof CarbonInterface ? $date->toDateString() : $date,
        ]);
    }

    public function inRoom(Room|int $room): static
    {
        return $this->state(fn (): array => [
            'room_id' => $room instanceof Room ? $room->id : $room,
        ]);
    }

    public function withoutRoom(): static
    {
        return $this->state(fn (): array => ['room_id' => null]);
    }

    public function inPeriod(string $period): static
    {
        return $this->state(fn (): array => ['period' => $period]);
    }

    public function forCourse(Course|int $course): static
    {
        return $this->state(fn (): array => [
            'course_id' => $course instanceof Course ? $course->id : $course,
        ]);
    }

    /**
     * Raccourci pour poser une attribution sur un créneau précis
     * (local + date + période), le trio qui définit un conflit.
     */
    public function forSlot(Room|int $room, CarbonInterface|string $date, string $period): static
    {
        return $this->inRoom($room)->onDate($date)->inPeriod($period);
    }
}
