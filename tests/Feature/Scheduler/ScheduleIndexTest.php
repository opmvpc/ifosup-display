<?php

use App\Models\Assignment;
use App\Models\Course;
use App\Models\Room;
use Inertia\Testing\AssertableInertia as Assert;

it('redirige un visiteur non authentifié vers la connexion', function () {
    $this->get(route('schedule'))->assertRedirect(route('login'));
});

it('affiche la page scheduler pour un utilisateur connecté', function () {
    actingAsUser();

    $this->get(route('schedule'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Schedule'));
});

it('utilise les dates par défaut quand aucun paramètre ni cookie ne sont fournis', function () {
    actingAsUser();

    $expectedFrom = now()->subDay()->toDateString();
    $expectedTo = now()->addDays(30)->toDateString();

    $this->get(route('schedule'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fromDate', $expectedFrom)
            ->where('toDate', $expectedTo)
        );
});

it('utilise les paramètres from et to fournis en query string', function () {
    actingAsUser();

    $this->get(route('schedule', ['from' => '2026-09-01', 'to' => '2026-09-10']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fromDate', '2026-09-01')
            ->where('toDate', '2026-09-10')
        );
});

it('rejette un paramètre from mal formé avec une erreur de validation', function () {
    actingAsUser();

    $this->get(route('schedule', ['from' => 'not-a-date']))
        ->assertStatus(302)
        ->assertSessionHasErrors('from');
});

it('inverse silencieusement from et to si from est postérieur à to', function () {
    actingAsUser();

    $this->get(route('schedule', ['from' => '2026-09-20', 'to' => '2026-09-01']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('fromDate', '2026-09-01')
            ->where('toDate', '2026-09-20')
        );
});

it('retombe sur les cookies quand les query params sont absents', function () {
    actingAsUser();

    $this->withCookie('scheduler_from_date', '2026-09-05')
        ->get(route('schedule'))
        ->assertInertia(fn (Assert $page) => $page->where('fromDate', '2026-09-05'));
});

it('ignore un cookie corrompu et retombe sur la valeur par défaut', function () {
    actingAsUser();

    $expectedFrom = now()->subDay()->toDateString();

    $this->withCookie('scheduler_from_date', "n'importe-quoi")
        ->get(route('schedule'))
        ->assertInertia(fn (Assert $page) => $page->where('fromDate', $expectedFrom));
});

it('écrit les cookies scheduler_from_date et scheduler_to_date après la requête', function () {
    actingAsUser();

    $this->get(route('schedule', ['from' => '2026-09-01', 'to' => '2026-09-10']))
        ->assertCookie('scheduler_from_date', '2026-09-01')
        ->assertCookie('scheduler_to_date', '2026-09-10');
});

it('inclut les attributions planned cancelled et late sans filtrage par statut', function () {
    actingAsUser();

    Assignment::factory()->planned()->onDate(now())->create();
    Assignment::factory()->cancelled()->onDate(now())->create();
    Assignment::factory()->late()->onDate(now())->create();

    $this->get(route('schedule'))
        ->assertInertia(fn (Assert $page) => $page->has('assignments', 3));
});

it('exclut les attributions hors de la plage from/to', function () {
    actingAsUser();

    Assignment::factory()->onDate(now())->create();
    Assignment::factory()->onDate(now()->subDays(10))->create();
    Assignment::factory()->onDate(now()->addDays(60))->create();

    $this->get(route('schedule'))
        ->assertInertia(fn (Assert $page) => $page->has('assignments', 1));
});

it('retourne toutes les salles et tous les cours triés par nom', function () {
    actingAsUser();

    Room::factory()->count(2)->create();
    Course::factory()->create(['code' => 'ZZZ-1', 'name' => 'Anglais']);
    Course::factory()->create(['code' => 'AAA-1', 'name' => 'Zoologie']);
    Course::factory()->create(['code' => 'MMM-1', 'name' => 'Mathématiques']);

    $this->get(route('schedule'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rooms', 2)
            ->has('courses', 3)
            ->where('courses.0.name', 'Anglais')
            ->where('courses.1.name', 'Mathématiques')
            ->where('courses.2.name', 'Zoologie')
        );
});
