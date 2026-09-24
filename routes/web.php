<?php

use App\Http\Controllers\RegisterOtpController;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::post('register/otp', [RegisterOtpController::class, 'store'])
    ->middleware(['guest', 'throttle:otp'])
    ->name('register.otp');

Route::post('register/otp/verificar', [RegisterOtpController::class, 'verify'])
    ->middleware(['guest', 'throttle:otp'])
    ->name('register.otp.verify');

Route::middleware('guest')->group(function () {
    Route::get('cuenta-recuperada', function () {
        return Inertia::render('auth/ForgotPassword', [
            'recovered' => true,
        ]);
    })->name('password.complete');
});

Route::middleware('auth')->group(function () {
    Route::get('registro-completo', function () {
        return Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'registered' => true,
        ]);
    })->name('register.complete');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
