<?php

use App\Models\Course;
use App\Models\Group;
use Inertia\Testing\AssertableInertia as Assert;

it('redirige les invités vers la connexion sur toutes les routes groups', function () {
    $group = Group::factory()->create();

    $this->get(route('groups.index'))->assertRedirect(route('login'));
    $this->get(route('groups.create'))->assertRedirect(route('login'));
    $this->post(route('groups.store'), ['name' => 'G-1A'])->assertRedirect(route('login'));
    $this->get(route('groups.show', $group))->assertRedirect(route('login'));
    $this->get(route('groups.edit', $group))->assertRedirect(route('login'));
    $this->put(route('groups.update', $group), ['name' => 'Nouveau'])->assertRedirect(route('login'));
    $this->delete(route('groups.destroy', $group))->assertRedirect(route('login'));
});

describe('index', function () {
    it('affiche la liste des groupes', function () {
        actingAsUser();
        Group::factory()->count(3)->create();

        $this->get(route('groups.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/groups/Index'));
    });
});

describe('create', function () {
    it('affiche le formulaire de création', function () {
        actingAsUser();

        $this->get(route('groups.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/groups/Create'));
    });
});

describe('store', function () {
    it('crée un groupe avec un nom valide', function () {
        actingAsUser();

        $response = $this->post(route('groups.store'), ['name' => 'G-1A']);

        $group = Group::first();
        $response->assertRedirect(route('groups.show', $group));
        $this->assertDatabaseHas('groups', ['name' => 'G-1A']);
    });

    it("refuse la création d'un groupe sans nom", function () {
        actingAsUser();

        $response = $this->post(route('groups.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('groups', 0);
    });

    it("refuse la création d'un groupe avec un nom de plus de 255 caractères", function () {
        actingAsUser();

        $response = $this->post(route('groups.store'), ['name' => str_repeat('a', 256)]);

        $response->assertSessionHasErrors('name');
    });

    it('redirige vers create quand _create_another est présent', function () {
        actingAsUser();

        $response = $this->post(route('groups.store'), [
            'name' => 'G-1A',
            '_create_another' => 1,
        ]);

        $response->assertRedirect(route('groups.create'));
    });
});

describe('show', function () {
    it('affiche un groupe', function () {
        actingAsUser();
        $group = Group::factory()->create(['name' => 'G-1A']);

        $this->get(route('groups.show', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/groups/Show')
                ->where('group.name', 'G-1A')
            );
    });

    it('affiche une 404 pour un groupe inexistant', function () {
        actingAsUser();

        $this->get('/groups/999999')->assertNotFound();
    });

    it('affiche un groupe avec les cours qui lui sont rattachés', function () {
        actingAsUser();
        $group = Group::factory()->create();
        $course = Course::factory()->create();
        $group->courses()->attach($course);

        $this->get(route('groups.show', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('group.courses', 1));
    });
});

describe('edit', function () {
    it("affiche le formulaire d'édition", function () {
        actingAsUser();
        $group = Group::factory()->create(['name' => 'G-1A']);

        $this->get(route('groups.edit', $group))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/groups/Edit')
                ->where('group.name', 'G-1A')
            );
    });
});

describe('update', function () {
    it("met à jour le nom d'un groupe", function () {
        actingAsUser();
        $group = Group::factory()->create();

        $this->put(route('groups.update', $group), ['name' => 'Nouveau']);

        $this->assertDatabaseHas('groups', ['id' => $group->id, 'name' => 'Nouveau']);
    });

    it('refuse la mise à jour sans nom', function () {
        actingAsUser();
        $group = Group::factory()->create();

        $response = $this->put(route('groups.update', $group), ['name' => '']);

        $response->assertSessionHasErrors('name');
    });
});

describe('destroy', function () {
    it('supprime un groupe', function () {
        actingAsUser();
        $group = Group::factory()->create();

        $response = $this->delete(route('groups.destroy', $group));

        $response->assertRedirect(route('groups.index'));
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    });

    it('supprimer un groupe détache les cours sans les supprimer', function () {
        actingAsUser();
        $group = Group::factory()->create();
        $course = Course::factory()->create();
        $group->courses()->attach($course);

        $this->delete(route('groups.destroy', $group));

        $this->assertDatabaseMissing('course_group', ['group_id' => $group->id, 'course_id' => $course->id]);
        $this->assertDatabaseHas('courses', ['id' => $course->id]);
    });
});
