<?php

use App\Models\Room;
use Inertia\Testing\AssertableInertia as Assert;

it('redirige les invités vers la connexion sur toutes les routes rooms', function () {
    $room = Room::factory()->create();

    $this->get(route('rooms.index'))->assertRedirect(route('login'));
    $this->get(route('rooms.create'))->assertRedirect(route('login'));
    $this->post(route('rooms.store'), ['name' => 'Salle 101'])->assertRedirect(route('login'));
    $this->get(route('rooms.show', $room))->assertRedirect(route('login'));
    $this->get(route('rooms.edit', $room))->assertRedirect(route('login'));
    $this->put(route('rooms.update', $room), ['name' => 'Nouveau'])->assertRedirect(route('login'));
    $this->delete(route('rooms.destroy', $room))->assertRedirect(route('login'));
});

describe('index', function () {
    it('affiche la liste des salles', function () {
        actingAsUser();
        Room::factory()->count(3)->create();

        $this->get(route('rooms.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/rooms/Index'));
    });
});

describe('create', function () {
    it('affiche le formulaire de création', function () {
        actingAsUser();

        $this->get(route('rooms.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/rooms/Create'));
    });
});

describe('store', function () {
    it('crée une salle avec un nom valide', function () {
        actingAsUser();

        $response = $this->post(route('rooms.store'), ['name' => 'Salle 101']);

        $room = Room::first();
        $response->assertRedirect(route('rooms.show', $room));
        $this->assertDatabaseHas('rooms', ['name' => 'Salle 101']);
    });

    it("refuse la création d'une salle sans nom", function () {
        actingAsUser();

        $response = $this->post(route('rooms.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('rooms', 0);
    });

    it("refuse la création d'une salle avec un nom de plus de 255 caractères", function () {
        actingAsUser();

        $response = $this->post(route('rooms.store'), ['name' => str_repeat('a', 256)]);

        $response->assertSessionHasErrors('name');
    });

    it('redirige vers create quand _create_another est présent', function () {
        actingAsUser();

        $response = $this->post(route('rooms.store'), [
            'name' => 'Salle 101',
            '_create_another' => 1,
        ]);

        $response->assertRedirect(route('rooms.create'));
    });
});

describe('show', function () {
    it('affiche une salle', function () {
        actingAsUser();
        $room = Room::factory()->create(['name' => 'Salle 101']);

        $this->get(route('rooms.show', $room))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/rooms/Show')
                ->where('room.name', 'Salle 101')
            );
    });

    it('affiche une 404 pour une salle inexistante', function () {
        actingAsUser();

        $this->get('/rooms/999999')->assertNotFound();
    });
});

describe('edit', function () {
    it("affiche le formulaire d'édition", function () {
        actingAsUser();
        $room = Room::factory()->create(['name' => 'Salle 101']);

        $this->get(route('rooms.edit', $room))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/rooms/Edit')
                ->where('room.name', 'Salle 101')
            );
    });
});

describe('update', function () {
    it("met à jour le nom d'une salle", function () {
        actingAsUser();
        $room = Room::factory()->create();

        $this->put(route('rooms.update', $room), ['name' => 'Nouveau']);

        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'name' => 'Nouveau']);
    });

    it('refuse la mise à jour sans nom', function () {
        actingAsUser();
        $room = Room::factory()->create();

        $response = $this->put(route('rooms.update', $room), ['name' => '']);

        $response->assertSessionHasErrors('name');
    });
});

describe('destroy', function () {
    it('supprime une salle', function () {
        actingAsUser();
        $room = Room::factory()->create();

        $response = $this->delete(route('rooms.destroy', $room));

        $response->assertRedirect(route('rooms.index'));
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    });
});
