<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(): Response
    {
        return Inertia::render('settings/Password');
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        // `AuthenticateSession` compare le hash stocké en session à celui du compte :
        // les autres sessions de ce compte sont déconnectées à leur prochaine requête,
        // et celle-ci doit être rafraîchie pour ne pas se déconnecter elle-même.
        $request->session()->put(
            'password_hash_'.Auth::getDefaultDriver(),
            $request->user()->getAuthPassword(),
        );

        return back();
    }
}
