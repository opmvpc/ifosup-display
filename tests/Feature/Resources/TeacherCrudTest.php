<?php

use App\Models\Course;
use App\Models\Teacher;
use Inertia\Testing\AssertableInertia as Assert;

it('redirige les invités vers la connexion sur toutes les routes teachers', function () {
    $teacher = Teacher::factory()->create();

    $this->get(route('teachers.index'))->assertRedirect(route('login'));
    $this->get(route('teachers.create'))->assertRedirect(route('login'));
    $this->post(route('teachers.store'), ['name' => 'Jean Dupont'])->assertRedirect(route('login'));
    $this->get(route('teachers.show', $teacher))->assertRedirect(route('login'));
    $this->get(route('teachers.edit', $teacher))->assertRedirect(route('login'));
    $this->put(route('teachers.update', $teacher), ['name' => 'Nouveau'])->assertRedirect(route('login'));
    $this->delete(route('teachers.destroy', $teacher))->assertRedirect(route('login'));
});

describe('index', function () {
    it('affiche la liste des enseignants à un utilisateur connecté', function () {
        actingAsUser();
        Teacher::factory()->count(3)->create();

        $this->get(route('teachers.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/teachers/Index'));
    });
});

describe('create', function () {
    it('affiche le formulaire de création', function () {
        actingAsUser();

        $this->get(route('teachers.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/teachers/Create'));
    });
});

describe('store', function () {
    it('crée un enseignant avec un nom valide', function () {
        actingAsUser();

        $response = $this->post(route('teachers.store'), ['name' => 'Jean Dupont']);

        $teacher = Teacher::first();
        $response->assertRedirect(route('teachers.show', $teacher));
        $this->assertDatabaseHas('teachers', ['name' => 'Jean Dupont']);
    });

    it("refuse la création d'un enseignant sans nom", function () {
        actingAsUser();

        $response = $this->post(route('teachers.store'), ['name' => '']);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseCount('teachers', 0);
    });

    it("refuse la création d'un enseignant avec un nom trop long", function () {
        actingAsUser();

        $response = $this->post(route('teachers.store'), ['name' => str_repeat('a', 256)]);

        $response->assertSessionHasErrors('name');
    });

    it('redirige vers create quand _create_another est présent', function () {
        actingAsUser();

        $response = $this->post(route('teachers.store'), [
            'name' => 'Jean Dupont',
            '_create_another' => 1,
        ]);

        $response->assertRedirect(route('teachers.create'));
    });
});

describe('show', function () {
    it('affiche un enseignant avec ses cours', function () {
        actingAsUser();
        $teacher = Teacher::factory()->create();
        Course::factory()->count(2)->create(['teacher_id' => $teacher->id]);

        $this->get(route('teachers.show', $teacher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/teachers/Show')
                ->has('teacher.courses', 2)
            );
    });

    it('affiche une 404 pour un enseignant inexistant', function () {
        actingAsUser();

        $this->get('/teachers/999999')->assertNotFound();
    });
});

describe('edit', function () {
    it("affiche le formulaire d'édition pré-rempli", function () {
        actingAsUser();
        $teacher = Teacher::factory()->create(['name' => 'Jean Dupont']);

        $this->get(route('teachers.edit', $teacher))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/teachers/Edit')
                ->where('teacher.name', 'Jean Dupont')
            );
    });
});

describe('update', function () {
    it("met à jour le nom d'un enseignant", function () {
        actingAsUser();
        $teacher = Teacher::factory()->create();

        $this->put(route('teachers.update', $teacher), ['name' => 'Nouveau']);

        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'name' => 'Nouveau']);
    });

    it('refuse la mise à jour sans nom', function () {
        actingAsUser();
        $teacher = Teacher::factory()->create();

        $response = $this->put(route('teachers.update', $teacher), ['name' => '']);

        $response->assertSessionHasErrors('name');
    });
});

describe('destroy', function () {
    it('supprime un enseignant', function () {
        actingAsUser();
        $teacher = Teacher::factory()->create();

        $response = $this->delete(route('teachers.destroy', $teacher));

        $response->assertRedirect(route('teachers.index'));
        $this->assertDatabaseMissing('teachers', ['id' => $teacher->id]);
    });

    it("met les cours d'un enseignant supprimé à NULL au lieu de les supprimer", function () {
        actingAsUser();
        $teacher = Teacher::factory()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->delete(route('teachers.destroy', $teacher));

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'teacher_id' => null]);
    });
});
