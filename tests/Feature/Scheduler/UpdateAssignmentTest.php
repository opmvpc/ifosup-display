<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;

it('met à jour la salle la date et la période d\'une attribution', function () {
    actingAsUser();
    $assignment = Assignment::factory()->forSlot(Room::factory()->create(), '2026-09-01', 'morning')->create();
    $newRoom = Room::factory()->create();

    $response = $this->patchJson(route('schedule.assignments.update', $assignment), [
        'room_id' => $newRoom->id,
        'date' => '2026-09-15',
        'period' => 'afternoon',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('assignments', [
        'id' => $assignment->id,
        'room_id' => $newRoom->id,
        'date' => '2026-09-15',
        'period' => 'afternoon',
    ]);
});

it('ignore un course_id envoyé dans le payload de mise à jour', function () {
    actingAsUser();
    $originalCourse = Course::factory()->create();
    $otherCourse = Course::factory()->create();
    $assignment = Assignment::factory()
        ->forCourse($originalCourse)
        ->forSlot(Room::factory()->create(), '2026-09-01', 'morning')
        ->create();

    $this->patchJson(route('schedule.assignments.update', $assignment), [
        'course_id' => $otherCourse->id,
        'room_id' => $assignment->room_id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertOk();

    $this->assertDatabaseHas('assignments', [
        'id' => $assignment->id,
        'course_id' => $originalCourse->id,
    ]);
});

it('ignore un status envoyé dans le payload de mise à jour', function () {
    actingAsUser();
    $assignment = Assignment::factory()->planned()
        ->forSlot(Room::factory()->create(), '2026-09-01', 'morning')
        ->create();

    $this->patchJson(route('schedule.assignments.update', $assignment), [
        'status' => 'cancelled',
        'room_id' => $assignment->room_id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertOk();

    $this->assertDatabaseHas('assignments', [
        'id' => $assignment->id,
        'status' => 'planned',
    ]);
});

it('autorise de renvoyer exactement le même créneau (no-op)', function () {
    actingAsUser();
    $room = Room::factory()->create();
    $assignment = Assignment::factory()->forSlot($room, '2026-09-01', 'morning')->create();

    $this->patchJson(route('schedule.assignments.update', $assignment), [
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertOk();
});

it('refuse de déplacer une attribution vers un créneau déjà occupé par une autre', function () {
    actingAsUser();
    $room = Room::factory()->create();
    Assignment::factory()->forSlot($room, '2026-09-10', 'afternoon')->create();
    $assignmentToMove = Assignment::factory()->forSlot($room, '2026-09-01', 'morning')->create();

    $this->patchJson(route('schedule.assignments.update', $assignmentToMove), [
        'room_id' => $room->id,
        'date' => '2026-09-10',
        'period' => 'afternoon',
    ])->assertStatus(422);
});

it('refuse de déplacer une attribution vers un créneau occupé par une attribution cancelled', function () {
    actingAsUser();
    $room = Room::factory()->create();
    Assignment::factory()->cancelled()->forSlot($room, '2026-09-10', 'afternoon')->create();
    $assignmentToMove = Assignment::factory()->forSlot($room, '2026-09-01', 'morning')->create();

    $this->patchJson(route('schedule.assignments.update', $assignmentToMove), [
        'room_id' => $room->id,
        'date' => '2026-09-10',
        'period' => 'afternoon',
    ])->assertStatus(422);
});

it('retourne 404 pour une attribution inexistante', function () {
    actingAsUser();

    $this->patchJson(route('schedule.assignments.update', 999999), [
        'room_id' => Room::factory()->create()->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertNotFound();
});

it('refuse la mise à jour sans room_id/date/period', function () {
    actingAsUser();
    $assignment = Assignment::factory()->create();

    $response = $this->patchJson(route('schedule.assignments.update', $assignment), []);

    $response->assertJsonValidationErrors(['room_id', 'date', 'period']);
});

it('redirige un utilisateur non authentifié qui tente de modifier une attribution', function () {
    $assignment = Assignment::factory()->create();

    $this->patch(route('schedule.assignments.update', $assignment), [
        'room_id' => Room::factory()->create()->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertRedirect(route('login'));
});
