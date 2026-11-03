<?php

use App\Http\Controllers\BitacoraController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CompraController;
use App\Http\Controllers\FirstLoginController;
use App\Http\Controllers\GarantiaController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\Tienda\CarritoController;
use App\Http\Controllers\Tienda\CatalogoController;
use App\Http\Controllers\Tienda\CheckoutController;
use App\Http\Controllers\Tienda\RegistroClienteController;
use App\Http\Controllers\Tienda\StripeWebhookController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VarianteController;
use App\Http\Controllers\VentaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── CU18: la tienda pública es la portada; el personal sigue yendo al panel ──
Route::get('/', function (Request $request) {
    $user = $request->user();

    if ($user !== null && ! $user->esClienteWeb()) {
        return $user->primer_login
            ? redirect()->route('password.first')
            : redirect()->route('dashboard');
    }

    return app(CatalogoController::class)->index($request);
})->name('home');

Route::prefix('tienda')->name('tienda.')->group(function () {
    Route::get('/', [CatalogoController::class, 'index'])->name('index');
    Route::get('producto/{slug}', [CatalogoController::class, 'show'])->name('producto');
    Route::get('carrito', [CarritoController::class, 'show'])->name('carrito');
    Route::get('carrito/variantes', [CarritoController::class, 'variantes'])->name('carrito.variantes');

    // ── CU21: pasarela de pagos (requiere cuenta de cliente) ──
    Route::middleware('auth')->group(function () {
        Route::get('checkout', [CheckoutController::class, 'checkout'])->name('checkout');
        Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
        Route::get('pedidos', [CheckoutController::class, 'index'])->name('pedidos.index');
        Route::get('pedidos/{numero}', [CheckoutController::class, 'show'])->name('pedidos.show');
        Route::get('pedidos/{numero}/pago', [CheckoutController::class, 'pago'])->name('pedidos.pago');
        Route::post('pedidos/{numero}/confirmar', [CheckoutController::class, 'confirmar'])->name('pedidos.confirmar');
        Route::post('pedidos/{numero}/fallo', [CheckoutController::class, 'fallo'])->name('pedidos.fallo');
        Route::get('pedidos/{numero}/comprobante', [CheckoutController::class, 'comprobante'])->name('pedidos.comprobante');
    });
});

Route::post('stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

Route::middleware('guest')->group(function () {
    Route::get('cuenta-recuperada', function () {
        return Inertia::render('auth/ForgotPassword', [
            'recovered' => true,
        ]);
    })->name('password.complete');

    Route::get('registro', [RegistroClienteController::class, 'create'])->name('registro');
    Route::post('registro', [RegistroClienteController::class, 'store'])->middleware('throttle:6,1')->name('registro.store');
});

Route::middleware('auth')->group(function () {
    Route::get('primer-ingreso', [FirstLoginController::class, 'show'])->name('password.first');
    Route::post('primer-ingreso', [FirstLoginController::class, 'update'])->name('password.first.update');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function (Request $request) {
        return $request->user()?->esClienteWeb()
            ? redirect()->route('tienda.index')
            : Inertia::render('Dashboard');
    })->name('dashboard');

    // ── CU15: Gastos y flujo de caja ────────────────────────────────────
    Route::prefix('caja')->name('caja.')->group(function () {
        Route::get('/', [CajaController::class, 'index'])->name('index');
        Route::post('movimientos', [CajaController::class, 'store'])->name('store');
        Route::post('categorias', [CajaController::class, 'storeCategoria'])->name('categorias.store');
        Route::patch('{origen}/{id}/anular', [CajaController::class, 'anular'])
            ->whereIn('origen', ['GASTO', 'MOVIMIENTO'])
            ->whereNumber('id')
            ->name('anular');
    });

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

    // ── CU14: Registro de compras ───────────────────────────────────────
    Route::middleware('permiso:compras.crear')->prefix('compras')->name('compras.')->group(function () {
        Route::get('/', [CompraController::class, 'index'])->name('index');
        Route::post('/', [CompraController::class, 'store'])->name('store');
    });

    // ── CU13: Gestión de proveedores ────────────────────────────────────
    Route::middleware('permiso:proveedores.gestionar')->prefix('proveedores')->name('proveedores.')->group(function () {
        Route::get('/', [ProveedorController::class, 'index'])->name('index');
        Route::post('/', [ProveedorController::class, 'store'])->name('store');
        Route::put('{proveedor}', [ProveedorController::class, 'update'])->whereNumber('proveedor')->name('update');
        Route::patch('{proveedor}/estado', [ProveedorController::class, 'estado'])->whereNumber('proveedor')->name('estado');
    });

    // ── CU12: Garantías y devoluciones ──────────────────────────────────
    Route::middleware('permiso:garantias.gestionar')->prefix('garantias')->name('garantias.')->group(function () {
        Route::get('/', [GarantiaController::class, 'index'])->name('index');
        Route::post('devolver', [GarantiaController::class, 'devolver'])->name('devolver');
    });
});

require __DIR__.'/settings.php';
