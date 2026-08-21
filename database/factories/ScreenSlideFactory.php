<?php

namespace Database\Factories;

use App\Models\ScreenSlide;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScreenSlide>
 */
class ScreenSlideFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => ScreenSlide::TYPE_SCHEDULE,
            'position' => fake()->numberBetween(1, 20),
            'motd' => null,
            'duration' => null,
            'image_path' => null,
            'video_path' => null,
            'is_locked' => false,
        ];
    }

    /**
     * Slide d'accueil : verrouillé et toujours en première position.
     */
    public function welcome(): static
    {
        return $this->state(fn (): array => [
            'type' => ScreenSlide::TYPE_WELCOME,
            'position' => 0,
            'motd' => fake()->sentence(),
            'is_locked' => true,
        ]);
    }

    public function schedule(): static
    {
        return $this->state(fn (): array => [
            'type' => ScreenSlide::TYPE_SCHEDULE,
        ]);
    }

    public function image(?string $path = null): static
    {
        return $this->state(fn (): array => [
            'type' => ScreenSlide::TYPE_IMAGE,
            'image_path' => $path ?? 'screen-slides/images/'.fake()->uuid().'.jpg',
            'duration' => 5000,
        ]);
    }

    public function video(?string $path = null): static
    {
        return $this->state(fn (): array => [
            'type' => ScreenSlide::TYPE_VIDEO,
            'video_path' => $path ?? 'screen-slides/videos/'.fake()->uuid().'.mp4',
            'duration' => null,
        ]);
    }

    public function locked(bool $locked = true): static
    {
        return $this->state(fn (): array => [
            'is_locked' => $locked,
        ]);
    }

    public function atPosition(int $position): static
    {
        return $this->state(fn (): array => [
            'position' => $position,
        ]);
    }
}
