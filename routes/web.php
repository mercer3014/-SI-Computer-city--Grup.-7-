<?php

use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\FirstLoginController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VarianteController;
use App\Http\Controllers\VentaController;
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

    // ── CU07: Módulo Productos / Inventario ──────────────────────────────
    // Vendedor: acceso de solo lectura (inventario.ver)
    // Administrador: gestión completa (productos.gestionar)
    Route::middleware('permiso:inventario.ver')->prefix('productos')->name('productos.')->group(function () {
        Route::get('/', [ProductoController::class, 'index'])->name('index');

        // CU08: Variantes y precios
        Route::get('{producto}/variantes', [VarianteController::class, 'index'])->whereNumber('producto')->name('variantes.index');

        // Las siguientes rutas requieren permiso adicional de escritura
        Route::middleware('permiso:productos.gestionar')->group(function () {
            Route::post('/', [ProductoController::class, 'store'])->name('store');
            Route::put('{producto}', [ProductoController::class, 'update'])->whereNumber('producto')->name('update');
            Route::patch('{producto}/estado', [ProductoController::class, 'estado'])->whereNumber('producto')->name('estado');

            // Imágenes
            Route::post('{producto}/imagenes', [ProductoController::class, 'subirImagen'])->whereNumber('producto')->name('imagenes.store');
            Route::delete('imagenes/{imagen}', [ProductoController::class, 'eliminarImagen'])->whereNumber('imagen')->name('imagenes.destroy');

            // Categorías y marcas (gestión inline)
            Route::post('categorias', [ProductoController::class, 'storeCategoria'])->name('categorias.store');
            Route::post('marcas', [ProductoController::class, 'storeMarca'])->name('marcas.store');

            Route::post('{producto}/variantes', [VarianteController::class, 'store'])->whereNumber('producto')->name('variantes.store');
            Route::patch('variantes/{variante}/precio', [VarianteController::class, 'updatePrecio'])->whereNumber('variante')->name('variantes.precio');
            Route::patch('variantes/{variante}/estado', [VarianteController::class, 'estado'])->whereNumber('variante')->name('variantes.estado');
            Route::delete('variantes/{variante}', [VarianteController::class, 'destroy'])->whereNumber('variante')->name('variantes.destroy');
        });
    });

    // ── CU10: Gestión de clientes ───────────────────────────────────────
    Route::middleware('permiso:clientes.gestionar')->prefix('clientes')->name('clientes.')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('index');
        Route::post('/', [ClienteController::class, 'store'])->name('store');
        Route::put('{cliente}', [ClienteController::class, 'update'])->whereNumber('cliente')->name('update');
        Route::patch('{cliente}/estado', [ClienteController::class, 'estado'])->whereNumber('cliente')->name('estado');
        Route::delete('{cliente}', [ClienteController::class, 'destroy'])->whereNumber('cliente')->name('destroy');
    });

    // ── CU09: Control de inventario / Kardex ─────────────────────────────
    Route::middleware('permiso:inventario.ver')->prefix('inventario')->name('inventario.')->group(function () {
        Route::get('/', [InventarioController::class, 'consultar'])->name('index');

        Route::middleware('permiso:inventario.ajustar')->group(function () {
            Route::post('ajustar', [InventarioController::class, 'ajustar'])->name('ajustar');
            Route::patch('variantes/{variante}/minimo', [InventarioController::class, 'minimo'])->whereNumber('variante')->name('minimo');
        });
    });

    // ── CU11: Punto de venta ─────────────────────────────────────────────
    Route::prefix('ventas')->name('ventas.')->group(function () {
        Route::get('/', [VentaController::class, 'index'])->name('index');
        Route::get('{venta}/nota', [VentaController::class, 'nota'])->whereNumber('venta')->name('nota');

        Route::middleware('permiso:ventas.crear')->group(function () {
            Route::post('/', [VentaController::class, 'store'])->name('store');
            Route::post('cliente', [VentaController::class, 'storeCliente'])->name('cliente');
            Route::post('{venta}/reenviar', [VentaController::class, 'reenviar'])->whereNumber('venta')->name('reenviar');
        });

        Route::middleware('permiso:ventas.anular')->group(function () {
            Route::patch('{venta}/anular', [VentaController::class, 'anular'])->whereNumber('venta')->name('anular');
        });
    });
});

require __DIR__.'/settings.php';
