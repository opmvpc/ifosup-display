<?php

use App\Models\Assignment;
use App\Models\Room;

it("change le statut vers cancelled sans vérifier l'occupation du créneau", function () {
    actingAsUser();
    $room = Room::factory()->create();
    Assignment::factory()->planned()->forSlot($room, '2026-09-01', 'morning')->create();
    $assignment = Assignment::factory()->planned()->forSlot($room, '2026-09-01', 'morning')->create();

    $response = $this->patchJson(route('schedule.assignments.update-status', $assignment), [
        'status' => 'cancelled',
    ]);

    $response->assertOk();
    $this->assertDatabaseHas('assignments', [
        'id' => $assignment->id,
        'status' => 'cancelled',
    ]);
});

it('change le statut vers late', function () {
    actingAsUser();
    $assignment = Assignment::factory()->planned()->create();

    $this->patchJson(route('schedule.assignments.update-status', $assignment), [
        'status' => 'late',
    ])->assertOk();

    $this->assertDatabaseHas('assignments', [
        'id' => $assignment->id,
        'status' => 'late',
    ]);
});

it('remet le statut à planned', function () {
    actingAsUser();
    $assignment = Assignment::factory()->cancelled()->create();

    $this->patchJson(route('schedule.assignments.update-status', $assignment), [
        'status' => 'planned',
    ])->assertOk();

    $this->assertDatabaseHas('assignments', [
        'id' => $assignment->id,
        'status' => 'planned',
    ]);
});

it('refuse un statut invalide', function () {
    actingAsUser();
    $assignment = Assignment::factory()->create();

    $this->patchJson(route('schedule.assignments.update-status', $assignment), [
        'status' => 'unknown',
    ])->assertJsonValidationErrors('status');
});

it('retourne 404 pour une attribution inexistante', function () {
    actingAsUser();

    $this->patchJson(route('schedule.assignments.update-status', 999999), [
        'status' => 'planned',
    ])->assertNotFound();
});

it('ne modifie ni la salle ni la date ni le cours', function () {
    actingAsUser();
    $room = Room::factory()->create();
    $assignment = Assignment::factory()->forSlot($room, '2026-09-01', 'morning')->create();

    $this->patchJson(route('schedule.assignments.update-status', $assignment), [
        'status' => 'cancelled',
    ])->assertOk();

    $this->assertDatabaseHas('assignments', [
        'id' => $assignment->id,
        'course_id' => $assignment->course_id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ]);
});

it('redirige un utilisateur non authentifié qui tente de modifier le statut', function () {
    $assignment = Assignment::factory()->create();

    $this->patch(route('schedule.assignments.update-status', $assignment), [
        'status' => 'cancelled',
    ])->assertRedirect(route('login'));
});
