<?php

use App\Http\Controllers\FirstLoginController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()?->primer_login
        ? redirect()->route('password.first')
        : redirect()->route('dashboard');
})->name('home');

Route::middleware('guest')->group(function () {
    Route::get('cuenta-recuperada', function () {
        return Inertia::render('auth/ForgotPassword', [
            'recovered' => true,
        ]);
    })->name('password.complete');
});

Route::middleware('auth')->group(function () {
    Route::get('primer-ingreso', [FirstLoginController::class, 'show'])->name('password.first');
    Route::post('primer-ingreso', [FirstLoginController::class, 'update'])->name('password.first.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
