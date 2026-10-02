<?php

use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\FirstLoginController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\UsuarioController;
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

    Route::middleware('permiso:bitacora.ver')->group(function () {
        Route::get('bitacora', [BitacoraController::class, 'index'])->name('bitacora.index');
        Route::get('bitacora/exportar', [BitacoraController::class, 'exportar'])->name('bitacora.exportar');
    });

    Route::middleware('permiso:usuarios.administrar')->prefix('usuarios')->name('usuarios.')->group(function () {
        Route::get('/', [UsuarioController::class, 'index'])->name('index');
        Route::get('exportar', [UsuarioController::class, 'exportar'])->name('exportar');
        Route::post('/', [UsuarioController::class, 'store'])->name('store');
        Route::put('{usuario}', [UsuarioController::class, 'update'])->whereNumber('usuario')->name('update');
        Route::patch('{usuario}/estado', [UsuarioController::class, 'estado'])->whereNumber('usuario')->name('estado');
        Route::post('{usuario}/reenviar-clave', [UsuarioController::class, 'reenviarClave'])->whereNumber('usuario')->name('reenviar');
        Route::post('{usuario}/desbloquear', [UsuarioController::class, 'desbloquear'])->whereNumber('usuario')->name('desbloquear');
    });

    Route::middleware('permiso:roles.administrar')->prefix('roles')->name('roles.')->group(function () {
        Route::get('/', [RolController::class, 'index'])->name('index');
        Route::post('/', [RolController::class, 'store'])->name('store');
        Route::put('{rol}', [RolController::class, 'update'])->whereNumber('rol')->name('update');
        Route::patch('{rol}/estado', [RolController::class, 'estado'])->whereNumber('rol')->name('estado');
    });
});

require __DIR__.'/settings.php';
