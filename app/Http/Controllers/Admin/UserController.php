<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::select(['id', 'name', 'email', 'created_at'])->get();

        return Inertia::render('admin/users/Index', [
            'users' => $users,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/users/Create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['password'] = Hash::make($validated['password']);

        $user = User::create($validated);

        if ($request->boolean('_create_another')) {
            return redirect()->route('admin.users.create');
        }

        return redirect()->route('admin.users.show', $user);
    }

    public function show(Request $request, User $user): Response
    {
        return Inertia::render('admin/users/Show', [
            'user' => $user->only(['id', 'name', 'email', 'created_at']),
            'deletionBlockedReason' => $this->deletionBlockedReason($request, $user),
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('admin/users/Edit', [
            'user' => $user->only(['id', 'name', 'email']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();

        if (! empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        // `AuthenticateSession` déconnecte les sessions dont le hash ne correspond
        // plus : voulu pour les autres sessions du compte modifié, mais un admin qui
        // change son propre mot de passe ici ne doit pas être déconnecté lui-même.
        if (isset($validated['password']) && $user->is($request->user())) {
            $request->session()->put(
                'password_hash_'.Auth::getDefaultDriver(),
                $user->getAuthPassword(),
            );
        }

        return redirect()->route('admin.users.show', $user);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($this->deletionBlockedReason($request, $user) !== null) {
            return redirect()->route('admin.users.show', $user);
        }

        $user->delete();

        return redirect()->route('admin.users.index');
    }

    /**
     * L'inscription et la réinitialisation par e-mail sont désactivées : supprimer
     * son propre compte ou le dernier compte verrouillerait définitivement le
     * backoffice (seul un redéploiement avec les variables ADMIN_* le rouvrirait).
     */
    private function deletionBlockedReason(Request $request, User $user): ?string
    {
        if ($user->is($request->user())) {
            return 'Vous ne pouvez pas supprimer votre propre compte depuis cette page. Passez par les paramètres du profil.';
        }

        if (User::count() <= 1) {
            return 'Impossible de supprimer le dernier compte : plus personne ne pourrait se connecter.';
        }

        return null;
    }
}
