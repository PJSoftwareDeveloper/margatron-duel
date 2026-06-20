<?php

namespace App\Http\Controllers\Auth;

use App\Game\Services\GameProfileService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class AuthenticatedSessionController extends Controller
{
    public function store(LoginRequest $request, GameProfileService $profiles): RedirectResponse
    {
        $credentials = $request->only('email', 'password');

        if (! Auth::attempt($credentials, true)) {
            throw ValidationException::withMessages([
                'email' => 'Nieprawidłowy email lub hasło.',
            ]);
        }

        $request->session()->regenerate();
        $profiles->ensureFor($request->user());

        return redirect()->route('game.show');
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('home');
    }
}
