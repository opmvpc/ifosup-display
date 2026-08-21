<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

it('redirige les invités vers la connexion sur toutes les routes admin/users', function () {
    $user = User::factory()->create();

    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
    $this->get(route('admin.users.create'))->assertRedirect(route('login'));
    $this->post(route('admin.users.store'), [
        'name' => 'Jean',
        'email' => 'jean@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])->assertRedirect(route('login'));
    $this->get(route('admin.users.show', $user))->assertRedirect(route('login'));
    $this->get(route('admin.users.edit', $user))->assertRedirect(route('login'));
    $this->put(route('admin.users.update', $user), ['name' => 'Nouveau', 'email' => $user->email])->assertRedirect(route('login'));
    $this->delete(route('admin.users.destroy', $user))->assertRedirect(route('login'));
});

describe('index', function () {
    it('affiche la liste des utilisateurs à un simple utilisateur connecté (pas de garde-fou de rôle)', function () {
        actingAsUser();
        User::factory()->count(2)->create();

        $this->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/Index'));
    });
});

describe('create', function () {
    it('affiche le formulaire de création', function () {
        actingAsUser();

        $this->get(route('admin.users.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/Create'));
    });
});

describe('store', function () {
    it('crée un utilisateur avec mot de passe confirmé', function () {
        actingAsUser();

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Jean Dupont',
            'email' => 'jean.dupont@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $user = User::where('email', 'jean.dupont@example.com')->first();
        $response->assertRedirect(route('admin.users.show', $user));
        $this->assertDatabaseHas('users', ['email' => 'jean.dupont@example.com']);
        expect($user->password)->not->toBe('Password123!');
        expect(Hash::check('Password123!', $user->password))->toBeTrue();
    });

    it('refuse la création sans nom', function () {
        actingAsUser();

        $response = $this->post(route('admin.users.store'), [
            'name' => '',
            'email' => 'jean@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('name');
    });

    it('refuse la création sans email', function () {
        actingAsUser();

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Jean',
            'email' => '',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
    });

    it('refuse la création avec un email invalide', function () {
        actingAsUser();

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Jean',
            'email' => 'pas-un-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
    });

    it('refuse la création avec un email déjà utilisé', function () {
        actingAsUser();
        User::factory()->create(['email' => 'x@x.com']);

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Jean',
            'email' => 'x@x.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertSessionHasErrors('email');
    });

    it('refuse la création si password et password_confirmation ne correspondent pas', function () {
        actingAsUser();

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Jean',
            'email' => 'jean@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Autrechose123!',
        ]);

        $response->assertSessionHasErrors('password');
    });

    it('refuse la création sans mot de passe', function () {
        actingAsUser();

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Jean',
            'email' => 'jean@example.com',
            'password' => '',
            'password_confirmation' => '',
        ]);

        $response->assertSessionHasErrors('password');
    });

    it('redirige vers create quand _create_another est présent', function () {
        actingAsUser();

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Jean',
            'email' => 'jean@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            '_create_another' => 1,
        ]);

        $response->assertRedirect(route('admin.users.create'));
    });
});

describe('show', function () {
    it('affiche un utilisateur (sans exposer le mot de passe)', function () {
        actingAsUser();
        $user = User::factory()->create();

        $response = $this->get(route('admin.users.show', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/Show')
                ->where('user.id', $user->id)
                ->where('user.name', $user->name)
                ->where('user.email', $user->email)
                ->missing('user.password')
            );

        expect($response->viewData('page')['props']['user'])->not->toHaveKey('password');
    });
});

describe('edit', function () {
    it("affiche le formulaire d'édition", function () {
        actingAsUser();
        $user = User::factory()->create();

        $this->get(route('admin.users.edit', $user))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/users/Edit')
                ->where('user.id', $user->id)
                ->missing('user.password')
            );
    });
});

describe('update', function () {
    it('met à jour le nom et l\'email sans changer le mot de passe', function () {
        actingAsUser();
        $user = User::factory()->create();

        $this->put(route('admin.users.update', $user), [
            'name' => 'Nouveau Nom',
            'email' => $user->email,
            'password' => null,
            'password_confirmation' => null,
        ]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Nouveau Nom']);
        expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
    });

    it('met à jour le mot de passe quand un nouveau est fourni', function () {
        actingAsUser();
        $user = User::factory()->create();

        $this->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'NouveauPass123!',
            'password_confirmation' => 'NouveauPass123!',
        ]);

        $fresh = $user->fresh();
        expect(Hash::check('password', $fresh->password))->toBeFalse();
        expect(Hash::check('NouveauPass123!', $fresh->password))->toBeTrue();
    });

    it('permet de conserver son propre email à la mise à jour', function () {
        actingAsUser();
        $user = User::factory()->create();

        $response = $this->put(route('admin.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $response->assertSessionHasNoErrors();
    });

    it("refuse la mise à jour avec l'email d'un autre utilisateur", function () {
        actingAsUser();
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $response = $this->put(route('admin.users.update', $userB), [
            'name' => $userB->name,
            'email' => $userA->email,
        ]);

        $response->assertSessionHasErrors('email');
    });
});

describe('destroy', function () {
    it('supprime un utilisateur', function () {
        actingAsUser();
        $user = User::factory()->create();

        $response = $this->delete(route('admin.users.destroy', $user));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    });
});
