<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\RegisterOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterOtpController extends Controller
{
    public function store(Request $request, RegisterOtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150', Rule::unique(User::class)],
        ]);

        $otp->send($data['email']);

        return back()->with('status', 'Te enviamos un código de 6 dígitos a tu correo.');
    }

    public function verify(Request $request, RegisterOtpService $otp): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:150'],
            'token' => ['required', 'digits:6'],
        ]);

        if (! $otp->verify($data['email'], $data['token'])) {
            throw ValidationException::withMessages([
                'token' => 'El código no es válido o ya venció.',
            ]);
        }

        return back()->with('status', 'Correo verificado.');
    }
}
