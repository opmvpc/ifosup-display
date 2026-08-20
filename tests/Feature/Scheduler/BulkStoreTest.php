<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;

it('crée une attribution planned par ligne fournie', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $rooms = Room::factory()->count(3)->create();

    $rows = $rooms->map(fn ($room, $i) => [
        'date' => '2026-09-0'.($i + 1),
        'room_id' => $room->id,
    ])->all();

    $response = $this->postJson(route('schedule.assignments.bulk'), [
        'course_id' => $course->id,
        'period' => 'morning',
        'rows' => $rows,
    ]);

    $response->assertOk();
    $response->assertJson(['inserted' => 3]);
    expect(Assignment::where('course_id', $course->id)->where('status', 'planned')->count())->toBe(3);
});

it("écrase une attribution existante sur le même créneau lors d'un bulkStore", function () {
    actingAsUser();
    $course = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $room = Room::factory()->create();

    $existing = Assignment::factory()->forSlot($room, '2026-09-01', 'morning')->forCourse($otherCourse)->create();

    $response = $this->postJson(route('schedule.assignments.bulk'), [
        'course_id' => $course->id,
        'period' => 'morning',
        'rows' => [
            ['date' => '2026-09-01', 'room_id' => $room->id],
        ],
    ]);

    $response->assertOk();
    $this->assertDatabaseMissing('assignments', ['id' => $existing->id]);

    $slotAssignments = Assignment::where('room_id', $room->id)
        ->whereDate('date', '2026-09-01')
        ->where('period', 'morning')
        ->get();

    expect($slotAssignments)->toHaveCount(1);
    expect($slotAssignments->first()->course_id)->toBe($course->id);
});

it("écrase une attribution cancelled ou late lors d'un bulkStore", function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    Assignment::factory()->forSlot($room, '2026-09-01', 'morning')->late()->create();

    $response = $this->postJson(route('schedule.assignments.bulk'), [
        'course_id' => $course->id,
        'period' => 'morning',
        'rows' => [
            ['date' => '2026-09-01', 'room_id' => $room->id],
        ],
    ]);

    $response->assertOk();

    $slotAssignments = Assignment::where('room_id', $room->id)
        ->whereDate('date', '2026-09-01')
        ->where('period', 'morning')
        ->get();

    expect($slotAssignments)->toHaveCount(1);
    expect($slotAssignments->first()->status)->toBe('planned');
});

it('ne touche pas les attributions sur une autre période ou une autre salle', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();
    $otherRoom = Room::factory()->create();

    $otherPeriod = Assignment::factory()->forSlot($room, '2026-09-01', 'afternoon')->create();
    $otherRoomAssignment = Assignment::factory()->forSlot($otherRoom, '2026-09-01', 'morning')->create();

    $response = $this->postJson(route('schedule.assignments.bulk'), [
        'course_id' => $course->id,
        'period' => 'morning',
        'rows' => [
            ['date' => '2026-09-01', 'room_id' => $room->id],
        ],
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('assignments', ['id' => $otherPeriod->id]);
    $this->assertDatabaseHas('assignments', ['id' => $otherRoomAssignment->id]);
});

it('applique la mise à jour dans une transaction : aucune ligne créée si une ligne est invalide', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.bulk'), [
        'course_id' => $course->id,
        'period' => 'morning',
        'rows' => [
            ['date' => '2026-09-01', 'room_id' => $room->id],
            ['date' => '2026-09-02', 'room_id' => 999999],
        ],
    ]);

    $response->assertStatus(422);
    expect(Assignment::count())->toBe(0);
});

it("gère un doublon de ligne (même room_id/date) en ne conservant qu'une attribution", function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.bulk'), [
        'course_id' => $course->id,
        'period' => 'morning',
        'rows' => [
            ['date' => '2026-09-01', 'room_id' => $room->id],
            ['date' => '2026-09-01', 'room_id' => $room->id],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['inserted' => 2]);

    expect(Assignment::where('room_id', $room->id)->whereDate('date', '2026-09-01')->where('period', 'morning')->count())
        ->toBe(1);
});

it('refuse un payload sans rows ou avec rows vide', function () {
    actingAsUser();
    $course = Course::factory()->create();

    $response = $this->postJson(route('schedule.assignments.bulk'), [
        'course_id' => $course->id,
        'period' => 'morning',
        'rows' => [],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('rows');
});

it('refuse une ligne avec une date mal formée', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.bulk'), [
        'course_id' => $course->id,
        'period' => 'morning',
        'rows' => [
            ['date' => '2026/09/01', 'room_id' => $room->id],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('rows.0.date');
});

it('redirige un utilisateur non authentifié qui tente un bulkStore', function () {
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->post(route('schedule.assignments.bulk'), [
        'course_id' => $course->id,
        'period' => 'morning',
        'rows' => [
            ['date' => '2026-09-01', 'room_id' => $room->id],
        ],
    ]);

    $response->assertRedirect(route('login'));
});
