<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FirstLoginController extends Controller
{
    use PasswordValidationRules;

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->primer_login) {
            return redirect()->route('dashboard');
        }

        $user->loadMissing('personal');

        return Inertia::render('auth/FirstLogin', [
            'nombre' => $user->name !== '' ? $user->name : 'compañero',
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user === null || ! $user->primer_login) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'password' => $this->passwordRules(),
        ]);

        if (Hash::check($validated['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                'password' => 'Elegí una clave distinta a la temporal.',
            ]);
        }

        $user->forceFill([
            'password_hash' => $validated['password'],
            'primer_login' => false,
        ])->save();

        return redirect()->route('dashboard');
    }
}
