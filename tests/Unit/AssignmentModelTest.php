<?php

use App\Models\Assignment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

it('caste la colonne date au format Y-m-d', function () {
    $assignment = Assignment::factory()->onDate('2026-09-15')->create();

    expect($assignment->date->toDateString())->toBe('2026-09-15');
    expect($assignment->toArray()['date'])->toBe('2026-09-15');
});

it('charge automatiquement course et room via $with', function () {
    $created = Assignment::factory()->create();

    $assignment = Assignment::find($created->id);

    expect($assignment->relationLoaded('course'))->toBeTrue();
    expect($assignment->relationLoaded('room'))->toBeTrue();
});
