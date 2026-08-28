<?php

use App\Models\Course;
use App\Models\Group;
use App\Models\Teacher;
use Inertia\Testing\AssertableInertia as Assert;

it('redirige les invités vers la connexion sur toutes les routes courses', function () {
    $course = Course::factory()->create();

    $this->get(route('courses.index'))->assertRedirect(route('login'));
    $this->get(route('courses.create'))->assertRedirect(route('login'));
    $this->post(route('courses.store'), ['name' => 'Maths', 'code' => 'X'])->assertRedirect(route('login'));
    $this->get(route('courses.show', $course))->assertRedirect(route('login'));
    $this->get(route('courses.edit', $course))->assertRedirect(route('login'));
    $this->put(route('courses.update', $course), ['name' => 'Nouveau', 'code' => $course->code])->assertRedirect(route('login'));
    $this->delete(route('courses.destroy', $course))->assertRedirect(route('login'));
});

describe('index', function () {
    it('affiche la liste des cours triée par code', function () {
        actingAsUser();
        Course::factory()->create(['code' => 'ZZZ-1']);
        Course::factory()->create(['code' => 'AAA-1']);

        $response = $this->get(route('courses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/courses/Index'));

        $codes = collect($response->viewData('page')['props']['courses'])->pluck('code')->all();

        expect($codes)->toBe(['AAA-1', 'ZZZ-1']);
    });
});

describe('create', function () {
    it('affiche le formulaire de création avec les enseignants et groupes disponibles', function () {
        actingAsUser();
        Teacher::factory()->create();
        Group::factory()->create();

        $this->get(route('courses.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/courses/Create')
                ->has('teachers')
                ->has('groups')
            );
    });

    it('trie les enseignants du formulaire par ordre alphabétique', function () {
        actingAsUser();
        Teacher::factory()->create(['name' => 'Zoé Wallon']);
        Teacher::factory()->create(['name' => 'Alice Martin']);

        $response = $this->get(route('courses.create'))->assertOk();

        $names = collect($response->viewData('page')['props']['teachers'])->pluck('name')->all();

        expect($names)->toBe(['Alice Martin', 'Zoé Wallon']);
    });
});

describe('store', function () {
    it('crée un cours avec un enseignant et des groupes', function () {
        actingAsUser();
        $teacher = Teacher::factory()->create();
        $group = Group::factory()->create();

        $response = $this->post(route('courses.store'), [
            'name' => 'Maths',
            'code' => 'X-1',
            'teacher_id' => $teacher->id,
            'groups' => [$group->id],
        ]);

        $course = Course::first();
        $response->assertRedirect(route('courses.show', $course));
        $this->assertDatabaseHas('courses', ['code' => 'X-1', 'name' => 'Maths', 'teacher_id' => $teacher->id]);
        $this->assertDatabaseHas('course_group', ['course_id' => $course->id, 'group_id' => $group->id]);
    });

    it('crée un cours sans enseignant (teacher_id nullable)', function () {
        actingAsUser();

        $response = $this->post(route('courses.store'), [
            'name' => 'Maths',
            'code' => 'X-1',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', ['code' => 'X-1', 'teacher_id' => null]);
    });

    it("crée un cours avec une année d'étude", function () {
        actingAsUser();

        $response = $this->post(route('courses.store'), [
            'name' => 'Maths',
            'code' => 'X-1',
            'year' => 2,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('courses', ['code' => 'X-1', 'year' => 2]);
    });

    it('refuse une année hors de 1, 2, 3', function () {
        actingAsUser();

        $response = $this->post(route('courses.store'), [
            'name' => 'Maths',
            'code' => 'X-1',
            'year' => 4,
        ]);

        $response->assertSessionHasErrors('year');
    });

    it("refuse la création d'un cours sans nom", function () {
        actingAsUser();

        $response = $this->post(route('courses.store'), ['name' => '', 'code' => 'X-1']);

        $response->assertSessionHasErrors('name');
    });

    it("refuse la création d'un cours sans code", function () {
        actingAsUser();

        $response = $this->post(route('courses.store'), ['name' => 'Maths', 'code' => '']);

        $response->assertSessionHasErrors('code');
    });

    it("refuse la création d'un cours avec un code déjà utilisé", function () {
        actingAsUser();
        Course::factory()->create(['code' => 'ABC-1']);

        $response = $this->post(route('courses.store'), ['name' => 'Maths', 'code' => 'ABC-1']);

        $response->assertSessionHasErrors('code');
    });

    it('refuse un teacher_id inexistant', function () {
        actingAsUser();

        $response = $this->post(route('courses.store'), [
            'name' => 'Maths',
            'code' => 'X-1',
            'teacher_id' => 999999,
        ]);

        $response->assertSessionHasErrors('teacher_id');
    });

    it('refuse un id de groupe inexistant dans groups.*', function () {
        actingAsUser();

        $response = $this->post(route('courses.store'), [
            'name' => 'Maths',
            'code' => 'X-1',
            'groups' => [999999],
        ]);

        $response->assertSessionHasErrors('groups.0');
    });

    it('redirige vers create quand _create_another est présent', function () {
        actingAsUser();

        $response = $this->post(route('courses.store'), [
            'name' => 'Maths',
            'code' => 'X-1',
            '_create_another' => 1,
        ]);

        $response->assertRedirect(route('courses.create'));
    });
});

describe('show', function () {
    it('affiche un cours avec son enseignant et ses groupes', function () {
        actingAsUser();
        $teacher = Teacher::factory()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $group = Group::factory()->create();
        $course->groups()->attach($group);

        $this->get(route('courses.show', $course))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/courses/Show')
                ->has('course.teacher')
                ->has('course.groups', 1)
            );
    });

    it('affiche une 404 pour un cours inexistant', function () {
        actingAsUser();

        $this->get('/courses/999999')->assertNotFound();
    });
});

describe('edit', function () {
    it('affiche le formulaire d\'édition avec le cours, les enseignants et les groupes', function () {
        actingAsUser();
        $course = Course::factory()->create();
        Teacher::factory()->create();
        Group::factory()->create();

        $this->get(route('courses.edit', $course))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('resources/courses/Edit')
                ->has('course')
                ->has('teachers')
                ->has('groups')
            );
    });
});

describe('update', function () {
    it("met à jour le code d'un cours en conservant l'unicité (hors lui-même)", function () {
        actingAsUser();
        $course = Course::factory()->create(['code' => 'X-1']);

        $response = $this->put(route('courses.update', $course), [
            'name' => $course->name,
            'code' => 'X-1',
        ]);

        $response->assertRedirect(route('courses.show', $course));
        $response->assertSessionHasNoErrors();
    });

    it("refuse la mise à jour avec le code d'un autre cours", function () {
        actingAsUser();
        $courseA = Course::factory()->create(['code' => 'A-1']);
        $courseB = Course::factory()->create(['code' => 'B-1']);

        $response = $this->put(route('courses.update', $courseB), [
            'name' => $courseB->name,
            'code' => $courseA->code,
        ]);

        $response->assertSessionHasErrors('code');
    });

    it("met à jour l'année d'un cours", function () {
        actingAsUser();
        $course = Course::factory()->create(['year' => 1]);

        $this->put(route('courses.update', $course), [
            'name' => $course->name,
            'code' => $course->code,
            'year' => 3,
        ]);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'year' => 3]);
    });

    it("efface l'année d'un cours quand le champ est soumis vide", function () {
        // La croix « effacer » du combobox Année soumet year="" : le middleware
        // ConvertEmptyStringsToNull doit le transformer en null en base.
        actingAsUser();
        $course = Course::factory()->create(['year' => 2]);

        $this->put(route('courses.update', $course), [
            'name' => $course->name,
            'code' => $course->code,
            'year' => '',
        ]);

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'year' => null]);
    });

    it("resynchronise les groupes d'un cours à la mise à jour", function () {
        actingAsUser();
        $course = Course::factory()->create();
        $groupA = Group::factory()->create();
        $groupB = Group::factory()->create();
        $course->groups()->attach($groupA);

        $this->put(route('courses.update', $course), [
            'name' => $course->name,
            'code' => $course->code,
            'groups' => [$groupB->id],
        ]);

        $this->assertDatabaseMissing('course_group', ['course_id' => $course->id, 'group_id' => $groupA->id]);
        $this->assertDatabaseHas('course_group', ['course_id' => $course->id, 'group_id' => $groupB->id]);
    });

    it('détache tous les groupes si la clé groups est absente à la mise à jour', function () {
        actingAsUser();
        $course = Course::factory()->create();
        $group = Group::factory()->create();
        $course->groups()->attach($group);

        $this->put(route('courses.update', $course), [
            'name' => $course->name,
            'code' => $course->code,
        ]);

        $this->assertDatabaseMissing('course_group', ['course_id' => $course->id]);
    });
});

describe('destroy', function () {
    it('supprime un cours et ses liaisons de groupes', function () {
        actingAsUser();
        $course = Course::factory()->create();
        $group = Group::factory()->create();
        $course->groups()->attach($group);

        $response = $this->delete(route('courses.destroy', $course));

        $response->assertRedirect(route('courses.index'));
        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseMissing('course_group', ['course_id' => $course->id]);
    });
});
