<?php

use App\Models\Assignment;

it('supprime une attribution existante', function () {
    actingAsUser();
    $assignment = Assignment::factory()->create();

    $response = $this->deleteJson(route('schedule.assignments.destroy', $assignment));

    $response->assertOk();
    $response->assertJson(['deleted' => true]);
    $this->assertDatabaseMissing('assignments', ['id' => $assignment->id]);
});

it('retourne 404 en tentant de supprimer une attribution inexistante', function () {
    actingAsUser();

    $this->deleteJson(route('schedule.assignments.destroy', 999999))
        ->assertNotFound();
});

it('redirige un utilisateur non authentifié qui tente de supprimer', function () {
    $assignment = Assignment::factory()->create();

    $this->delete(route('schedule.assignments.destroy', $assignment))
        ->assertRedirect(route('login'));
});
