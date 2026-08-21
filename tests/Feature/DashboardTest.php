<?php

use App\Models\User;

// Le projet n'a pas de route `dashboard` : le point d'entrée du backoffice
// après connexion est le planning (`/scheduler`, nommée `schedule`).

test('guests are redirected to the login page', function () {
    $response = $this->get(route('schedule'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the scheduler', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('schedule'));

    $response->assertOk();
});
