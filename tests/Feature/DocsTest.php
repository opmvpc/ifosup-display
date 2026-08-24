<?php

use Inertia\Testing\AssertableInertia as Assert;

it('redirige les invités vers la connexion', function () {
    $this->get('/docs')->assertRedirect(route('login'));
    $this->get('/docs/planning')->assertRedirect(route('login'));
});

it('affiche chaque chapitre de la documentation', function (string $slug, string $component) {
    actingAsUser();

    $this->get('/docs'.($slug === '' ? '' : "/{$slug}"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['', 'docs/Introduction'],
    ['introduction', 'docs/Introduction'],
    ['ressources', 'docs/Ressources'],
    ['planning', 'docs/Planning'],
    ['import-excel', 'docs/ImportExcel'],
    ['slides', 'docs/Slides'],
    ['ecran-tv', 'docs/EcranTv'],
    ['utilisateurs', 'docs/Utilisateurs'],
]);

it('renvoie une 404 pour un chapitre inconnu', function () {
    actingAsUser();

    $this->get('/docs/inexistant')->assertNotFound();
});
