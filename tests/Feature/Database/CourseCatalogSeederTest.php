<?php

use App\Models\Course;
use App\Models\Group;
use Database\Seeders\CourseCatalogSeeder;

it('crée les sections et les cours du catalogue', function () {
    $this->seed(CourseCatalogSeeder::class);

    expect(Group::where('name', 'Bachelier en Informatique')->exists())->toBeTrue()
        ->and(Course::where('code', '5IPRO')->value('name'))->toBe('Principes d’algorithmique et de programmation');
});

it('regroupe tous les cours d’œnologie dans une section unique', function () {
    $this->seed(CourseCatalogSeeder::class);

    $oenologie = Group::where('name', 'Œnologie')->first();

    expect($oenologie)->not->toBeNull()
        ->and($oenologie->courses)->toHaveCount(13)
        ->and($oenologie->courses->pluck('code'))->toContain('OENO-MONDE', 'OENO-EUROPE', 'OENO-ACCORDS');
});

it('rattache un cours partagé entre plusieurs sections sans le dupliquer', function () {
    $this->seed(CourseCatalogSeeder::class);

    expect(Course::where('code', '5LAS2')->count())->toBe(1)
        ->and(Course::where('code', '5LAS2')->first()->groups->pluck('name'))
        ->toContain('BES Webdesigner UI/UX', 'BES Webdeveloper', 'Bachelier en Informatique', 'Bachelier en Comptabilité');
});

it('est idempotent et ne touche pas aux cours existants portant un code du catalogue', function () {
    $existing = Course::factory()->create([
        'code' => '5IPRO',
        'name' => 'Nom personnalisé par la directrice',
    ]);

    $this->seed(CourseCatalogSeeder::class);
    $this->seed(CourseCatalogSeeder::class);

    expect(Course::where('code', '5IPRO')->count())->toBe(1)
        ->and($existing->fresh()->name)->toBe('Nom personnalisé par la directrice')
        ->and(Group::where('name', 'Œnologie')->count())->toBe(1)
        ->and(Group::where('name', 'Œnologie')->first()->courses)->toHaveCount(13);
});
