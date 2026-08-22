<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;

it('crée une attribution valide et retourne 201', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ]);

    $response->assertCreated();
    $this->assertDatabaseCount('assignments', 1);
});

it('crée une attribution avec le statut planned par défaut si status est omis', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ]);

    $response->assertCreated();
    $this->assertDatabaseHas('assignments', [
        'id' => $response->json('assignment.id'),
        'status' => 'planned',
    ]);
});

it('accepte un statut cancelled ou late explicite à la création', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
        'status' => 'cancelled',
    ])->assertJsonPath('assignment.status', 'cancelled');
});

it('refuse une attribution sur un local déjà occupé au même créneau planned', function () {
    actingAsUser();
    $room = Room::factory()->create();
    Assignment::factory()->planned()->forSlot($room, '2026-09-01', 'morning')->create();

    $response = $this->postJson(route('schedule.assignments.store'), [
        'course_id' => Course::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Ce créneau est déjà occupé.');
});

it('refuse une attribution sur un créneau occupé par une attribution cancelled', function () {
    actingAsUser();
    $room = Room::factory()->create();
    Assignment::factory()->cancelled()->forSlot($room, '2026-09-01', 'morning')->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => Course::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertStatus(422);
});

it('autorise une attribution sur la même salle et date mais une période différente', function () {
    actingAsUser();
    $room = Room::factory()->create();
    Assignment::factory()->forSlot($room, '2026-09-01', 'morning')->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => Course::factory()->create()->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'afternoon',
    ])->assertCreated();
});

it('autorise une attribution sur le même créneau mais une salle différente', function () {
    actingAsUser();
    $occupiedRoom = Room::factory()->create();
    $freeRoom = Room::factory()->create();
    Assignment::factory()->forSlot($occupiedRoom, '2026-09-01', 'morning')->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => Course::factory()->create()->id,
        'room_id' => $freeRoom->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertCreated();
});

it('refuse la création sans course_id', function () {
    actingAsUser();
    $room = Room::factory()->create();

    $this->postJson(route('schedule.assignments.store'), [
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertJsonValidationErrors('course_id');
});

it('refuse la création avec un course_id inexistant', function () {
    actingAsUser();
    $room = Room::factory()->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => 999999,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertJsonValidationErrors('course_id');
});

it('refuse la création sans room_id', function () {
    actingAsUser();
    $course = Course::factory()->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertJsonValidationErrors('room_id');
});

it('refuse la création avec un room_id inexistant', function () {
    actingAsUser();
    $course = Course::factory()->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => 999999,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertJsonValidationErrors('room_id');
});

it('refuse une date mal formée', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'date' => '01/09/2026',
        'period' => 'morning',
    ])->assertJsonValidationErrors('date');
});

it('refuse une date calendaire invalide comme 2026-02-30', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'date' => '2026-02-30',
        'period' => 'morning',
    ])->assertJsonValidationErrors('date');
});

it('refuse une période invalide', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'night',
    ])->assertJsonValidationErrors('period');
});

it('refuse un statut invalide à la création', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
        'status' => 'done',
    ])->assertJsonValidationErrors('status');
});

it('redirige un utilisateur non authentifié qui tente de créer une attribution', function () {
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $this->post(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ])->assertRedirect(route('login'));
});

it("retourne l'attribution créée avec ses relations course et room chargées", function () {
    actingAsUser();
    $course = Course::factory()->create();
    $room = Room::factory()->create();

    $response = $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ]);

    $response->assertJsonPath('assignment.course.id', $course->id);
    $response->assertJsonPath('assignment.room.id', $room->id);
});

it('répond 422 « créneau occupé » quand une écriture concurrente gagne la course (IFO-015)', function () {
    actingAsUser();
    $course = Course::factory()->create();
    $other = Course::factory()->create();
    $room = Room::factory()->create();

    // Simule le concurrent : il insère le même créneau APRÈS le contrôle
    // slotIsOccupied du contrôleur, juste avant l'insertion réelle.
    Assignment::creating(function () use ($room, $other): void {
        DB::table('assignments')->insert([
            'course_id' => $other->id,
            'room_id' => $room->id,
            'date' => '2026-09-01',
            'period' => 'morning',
            'status' => 'planned',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $response = $this->postJson(route('schedule.assignments.store'), [
        'course_id' => $course->id,
        'room_id' => $room->id,
        'date' => '2026-09-01',
        'period' => 'morning',
    ]);

    $response->assertStatus(422);
    $response->assertJsonPath('message', 'Ce créneau est déjà occupé.');
    expect(Assignment::count())->toBe(1);
});
