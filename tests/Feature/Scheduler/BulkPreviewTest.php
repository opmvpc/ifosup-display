<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;

it('calcule les dates hebdomadaires entre start_week et end_week pour le jour donné', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.bulk.preview'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'day_of_week' => 3,
        'period' => 'morning',
        'start_week' => '2026-W02',
        'end_week' => '2026-W04',
    ]);

    $response->assertOk();
    expect($response->json('dates'))->toBe([
        '2026-01-07',
        '2026-01-14',
        '2026-01-21',
    ]);
});

it('retourne une seule date quand start_week et end_week sont la même semaine', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.bulk.preview'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'day_of_week' => 1,
        'period' => 'morning',
        'start_week' => '2026-W10',
        'end_week' => '2026-W10',
    ]);

    $response->assertOk();
    expect($response->json('dates'))->toHaveCount(1);
});

it('gère correctement une plage à cheval sur deux années civiles', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.bulk.preview'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'day_of_week' => 1,
        'period' => 'morning',
        'start_week' => '2025-W52',
        'end_week' => '2026-W02',
    ]);

    $response->assertOk();
    $dates = $response->json('dates');

    $sorted = $dates;
    sort($sorted);
    expect($dates)->toBe($sorted);
    expect($dates)->toHaveCount(3);
});

it("n'échoue pas la validation quand end_week est antérieure à start_week (la règle gte compare la longueur des chaînes, pas leur ordre) et retourne une liste de dates vide", function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.bulk.preview'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'day_of_week' => 1,
        'period' => 'morning',
        'start_week' => '2026-W10',
        'end_week' => '2026-W05',
    ]);

    $response->assertOk();
    expect($response->json('dates'))->toBe([]);
    expect($response->json('existing'))->toBe([]);
});

it('refuse un day_of_week hors de 1 à 7', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.bulk.preview'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'day_of_week' => 8,
        'period' => 'morning',
        'start_week' => '2026-W02',
        'end_week' => '2026-W04',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('day_of_week');
});

it('refuse un format de semaine ISO invalide', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.bulk.preview'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'day_of_week' => 1,
        'period' => 'morning',
        'start_week' => '2026-13',
        'end_week' => '2026-W04',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('start_week');
});

it('retourne les attributions existantes sur les dates et la période calculées', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $existing = Assignment::factory()->forSlot($room, '2026-01-14', 'morning')->create();

    $response = $this->postJson(route('schedule.assignments.bulk.preview'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'day_of_week' => 3,
        'period' => 'morning',
        'start_week' => '2026-W02',
        'end_week' => '2026-W04',
    ]);

    $response->assertOk();
    $existingRows = $response->json('existing');
    expect($existingRows)->toHaveCount(1);
    expect($existingRows[0]['id'])->toBe($existing->id);
    expect($existingRows[0]['course']['id'])->toBe($existing->course_id);
});

it('exclut de existing une attribution sur un autre local que celui demandé', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();
    $otherRoom = Room::factory()->create();

    Assignment::factory()->forSlot($otherRoom, '2026-01-14', 'morning')->create();

    $response = $this->postJson(route('schedule.assignments.bulk.preview'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'day_of_week' => 3,
        'period' => 'morning',
        'start_week' => '2026-W02',
        'end_week' => '2026-W04',
    ]);

    $response->assertOk();
    expect($response->json('existing'))->toBe([]);
});

it('exclut de existing les attributions sur une autre période', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    Assignment::factory()->forSlot($room, '2026-01-14', 'afternoon')->create();

    $response = $this->postJson(route('schedule.assignments.bulk.preview'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'day_of_week' => 3,
        'period' => 'morning',
        'start_week' => '2026-W02',
        'end_week' => '2026-W04',
    ]);

    $response->assertOk();
    expect($response->json('existing'))->toBe([]);
});

it('refuse un course_id ou room_id inexistant', function () {
    actingAsUser();

    $response = $this->postJson(route('schedule.assignments.bulk.preview'), [
        'course_id' => 999999,
        'room_id' => 999999,
        'day_of_week' => 1,
        'period' => 'morning',
        'start_week' => '2026-W02',
        'end_week' => '2026-W04',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['course_id', 'room_id']);
});
