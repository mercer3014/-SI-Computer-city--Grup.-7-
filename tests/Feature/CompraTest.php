<?php

namespace Tests\Feature;

use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\VarianteProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class CompraTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    public function test_users_without_permission_cannot_register_a_purchase(): void
    {
        $usuario = User::factory()->create(['primer_login' => false]);

        $this->actingAs($usuario)->get(route('compras.index'))->assertForbidden();
        $this->actingAs($usuario)->post(route('compras.store'), [])->assertForbidden();
    }

    public function test_screen_lists_active_suppliers_methods_and_skus(): void
    {
        $this->proveedor(['razon_social' => 'Inactivo SA', 'nit' => '100', 'estado' => 'INACTIVO']);
        $activo = $this->proveedor(['razon_social' => 'Importadora Norte', 'nit' => '200']);
        $producto = $this->producto();
        $this->variante($producto, 'MOU-G203-NEG', 4);
        $inactiva = $this->variante($producto, 'MOU-G203-OFF', 1);
        $inactiva->forceFill(['estado' => 'INACTIVO'])->save();
        MetodoPago::query()->create(['codigo' => 'EFECTIVO', 'nombre' => 'Efectivo', 'estado' => 'ACTIVO']);
        MetodoPago::query()->create(['codigo' => 'VIEJO', 'nombre' => 'Cheque', 'estado' => 'INACTIVO']);

        $admin = $this->usuarioCon(['compras.crear']);
        $compraId = DB::table('compra')->insertGetId([
            'proveedor_id' => $activo->id,
            'usuario_id' => $admin->id,
            'metodo_pago_id' => MetodoPago::query()->where('codigo', 'EFECTIVO')->value('id'),
            'numero_compra' => 'C-HIST-1',
            'fecha_compra' => now(),
        ]);
        DB::table('detalle_compra')->insert([
            'compra_id' => $compraId,
            'variante_id' => VarianteProducto::query()->where('sku', 'MOU-G203-NEG')->value('id'),
            'cantidad' => 3,
            'costo_unitario' => 40,
        ]);

        $this->actingAs($admin)
            ->get(route('compras.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('NuevaCompra')
                ->has('proveedores', 1)
                ->where('proveedores.0.id', $activo->id)
                ->has('metodos', 1)
                ->where('metodos.0.nombre', 'Efectivo')
                ->has('catalogo', 1)
                ->where('catalogo.0.sku', 'MOU-G203-NEG')
                ->where('catalogo.0.proveedor', 'Importadora Norte')
                ->where('reposicion.0.sku', 'MOU-G203-NEG')
                ->where('reposicion.0.sugerido', 1)
                ->has('compras.data', 1)
                ->where('compras.data.0.numero_compra', 'C-HIST-1')
                ->where('compras.data.0.proveedor', 'Importadora Norte')
                ->has('numeroSugerido'));
    }

    public function test_store_persists_the_purchase_without_touching_stock(): void
    {
        $proveedor = $this->proveedor();
        $metodo = MetodoPago::query()->create([
            'codigo' => 'TRANSFERENCIA',
            'nombre' => 'Transferencia',
            'estado' => 'ACTIVO',
        ]);
        $producto = $this->producto();
        $variante = $this->variante($producto, 'MOU-G203-NEG', 4);
        $admin = $this->usuarioCon(['compras.crear']);

        $this->actingAs($admin)
            ->post(route('compras.store'), $this->datos($proveedor, $metodo, [
                [
                    'sku' => 'mou-g203-neg',
                    'cantidad' => 2,
                    'costo_unitario' => 80.5,
                    'numero_serie' => 'SN-9',
                ],
                [
                    'sku' => 'MOU-G203-NEG',
                    'cantidad' => 1,
                    'costo_unitario' => 70,
                    'numero_serie' => '',
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $compraId = (int) DB::table('compra')->where('numero_compra', 'C-TEST-1')->value('id');

        $this->assertNotSame(0, $compraId);
        $this->assertDatabaseHas('compra', [
            'id' => $compraId,
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $admin->id,
            'metodo_pago_id' => $metodo->id,
            'numero_compra' => 'C-TEST-1',
        ]);
        $this->assertDatabaseHas('detalle_compra', [
            'compra_id' => $compraId,
            'variante_id' => $variante->id,
            'cantidad' => 2,
            'numero_serie' => 'SN-9',
        ]);
        $this->assertDatabaseHas('detalle_compra', [
            'compra_id' => $compraId,
            'variante_id' => $variante->id,
            'cantidad' => 1,
            'numero_serie' => null,
        ]);
        $this->assertSame(4, $variante->fresh()->stock_actual);
        $this->assertDatabaseMissing('movimiento_inventario', ['compra_id' => $compraId]);
        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'REGISTRAR_COMPRA',
            'tipo_entidad' => 'compra',
            'entidad_id' => $compraId,
        ]);
    }

    public function test_unknown_sku_empty_cart_and_duplicate_number_are_rejected(): void
    {
        $proveedor = $this->proveedor();
        $metodo = MetodoPago::query()->create([
            'codigo' => 'EFECTIVO',
            'nombre' => 'Efectivo',
            'estado' => 'ACTIVO',
        ]);
        $admin = $this->usuarioCon(['compras.crear']);

        $this->actingAs($admin)
            ->post(route('compras.store'), $this->datos($proveedor, $metodo, [
                ['sku' => 'NO-EXISTE', 'cantidad' => 1, 'costo_unitario' => 10, 'numero_serie' => null],
            ]))
            ->assertSessionHasErrors('items');

        $this->actingAs($admin)
            ->post(route('compras.store'), $this->datos($proveedor, $metodo, []))
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('compra', 0);

        DB::table('compra')->insert([
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $admin->id,
            'metodo_pago_id' => $metodo->id,
            'numero_compra' => 'C-TEST-1',
            'fecha_compra' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('compras.store'), $this->datos($proveedor, $metodo, [
                ['sku' => 'X', 'cantidad' => 1, 'costo_unitario' => 10, 'numero_serie' => null],
            ]))
            ->assertSessionHasErrors('numero_compra');
    }

    /**
     * @param  list<string>  $claves
     */
    private function usuarioCon(array $claves): User
    {
        $rol = $this->rolConPermisos('Rol '.implode('-', $claves), $claves);

        return User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function proveedor(array $cambios = []): Proveedor
    {
        return Proveedor::query()->create(array_merge([
            'nit' => '555444333',
            'razon_social' => 'Importadora Norte',
            'estado' => 'ACTIVO',
        ], $cambios));
    }

    private function producto(): Producto
    {
        $categoriaId = DB::table('categoria')->insertGetId([
            'nombre' => 'Mouse',
            'slug' => 'mouse-'.uniqid(),
        ]);

        return Producto::query()->create([
            'categoria_id' => $categoriaId,
            'nombre' => 'Mouse Logitech G203',
            'slug' => 'mouse-logitech-g203-'.uniqid(),
            'estado' => 'ACTIVO',
        ]);
    }

    private function variante(Producto $producto, string $sku, int $stock): VarianteProducto
    {
        return $producto->variantes()->create([
            'sku' => $sku,
            'precio_venta' => 120,
            'stock_actual' => $stock,
            'stock_minimo' => 5,
            'estado' => 'ACTIVO',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function datos(Proveedor $proveedor, MetodoPago $metodo, array $items): array
    {
        return [
            'proveedor_id' => $proveedor->id,
            'metodo_pago_id' => $metodo->id,
            'numero_compra' => 'C-TEST-1',
            'fecha_compra' => now()->toDateString(),
            'items' => $items,
        ];
    }
}
