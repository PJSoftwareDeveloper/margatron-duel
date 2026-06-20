<?php

namespace App\Http\Controllers\Auth;

use App\Game\Services\GameProfileService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class RegisteredUserController extends Controller
{
    public function store(RegisterRequest $request, GameProfileService $profiles): RedirectResponse
    {
        $user = User::query()->create([
            'name' => $request->string('nick')->toString(),
            'email' => $request->string('email')->lower()->toString(),
            'password' => $request->string('password')->toString(),
        ]);

        $profiles->ensureFor($user);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('game.show');
    }
}
