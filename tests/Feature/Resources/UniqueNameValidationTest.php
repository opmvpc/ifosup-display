<?php

use App\Models\Group;
use App\Models\Room;
use App\Models\Teacher;

// Les contraintes d'unicité posées en base (IFO-010) doivent avoir leur pendant
// applicatif : sans règle `unique` dans la FormRequest, la validation laisse passer
// le doublon et l'erreur remonte en 500 SQL au lieu d'un message sous le champ.
// C'est exactement ce qui s'est produit en recette le 2026-08-20, avec un local « 106 ».

dataset('ressources uniques', [
    'local' => [Room::class, 'rooms', 'Un local porte déjà ce nom.'],
    'section' => [Group::class, 'groups', 'Une section porte déjà ce nom.'],
    'enseignant' => [Teacher::class, 'teachers', 'Un enseignant porte déjà ce nom.'],
]);

it('refuse la création avec un nom déjà utilisé', function (string $model, string $route, string $message) {
    actingAsUser();
    $model::factory()->create(['name' => 'Déjà pris']);

    $response = $this->post(route("{$route}.store"), ['name' => 'Déjà pris']);

    $response->assertSessionHasErrors(['name' => $message]);
    expect($model::where('name', 'Déjà pris')->count())->toBe(1);
})->with('ressources uniques');

it('refuse la modification vers un nom déjà utilisé', function (string $model, string $route, string $message) {
    actingAsUser();
    $model::factory()->create(['name' => 'Premier']);
    $second = $model::factory()->create(['name' => 'Second']);

    $response = $this->put(route("{$route}.update", $second), ['name' => 'Premier']);

    $response->assertSessionHasErrors(['name' => $message]);
    expect($second->fresh()->name)->toBe('Second');
})->with('ressources uniques');

it('accepte la modification qui conserve son propre nom', function (string $model, string $route) {
    actingAsUser();
    $resource = $model::factory()->create(['name' => 'Inchangé']);

    $this->put(route("{$route}.update", $resource), ['name' => 'Inchangé'])
        ->assertSessionHasNoErrors();

    expect($resource->fresh()->name)->toBe('Inchangé');
})->with('ressources uniques');

// Les noms de locaux de l'école sont purement numériques (« 106 », « 204 »). Les
// fixtures n'utilisaient que des noms alphanumériques, ce qui laissait ce cas sans
// couverture alors que c'est le format réel des données.
it('applique aussi l\'unicité à un nom purement numérique', function () {
    actingAsUser();
    Room::factory()->create(['name' => '106']);

    $this->post(route('rooms.store'), ['name' => '106'])
        ->assertSessionHasErrors(['name' => 'Un local porte déjà ce nom.']);

    expect(Room::where('name', '106')->count())->toBe(1);
});
