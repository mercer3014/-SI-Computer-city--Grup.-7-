<?php

namespace Tests\Feature;

use App\Models\Atributo;
use App\Models\Producto;
use App\Models\User;
use App\Models\ValorAtributo;
use App\Models\VarianteProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class VarianteTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    public function test_seller_can_view_variants_but_not_change_them(): void
    {
        $producto = $this->producto();
        $variante = $this->variante($producto, 'MOU-G203-NEG', 120);
        $vendedor = $this->usuarioCon(['inventario.ver']);

        $this->actingAs($vendedor)
            ->get(route('productos.variantes.index', $producto))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Variantes')
                ->where('puedeGestionar', false)
                ->has('variantes', 1));

        $this->actingAs($vendedor)
            ->post(route('productos.variantes.store', $producto), $this->datosVariante())
            ->assertForbidden();

        $this->actingAs($vendedor)
            ->patch(route('productos.variantes.precio', $variante), ['precio_venta' => 99])
            ->assertForbidden();

        $this->assertSame(120.0, $variante->fresh()->precio_venta);
    }

    public function test_administrator_registers_a_variant_with_attributes(): void
    {
        $producto = $this->producto();
        [$color, $negro] = $this->atributoConValor('Color', 'Negro');
        $conexion = Atributo::query()->create(['nombre' => 'Conectividad', 'estado' => 'ACTIVO']);

        $this->actingAs($this->gestor())
            ->post(route('productos.variantes.store', $producto), $this->datosVariante([
                'sku' => ' mou-g203-neg-bt ',
                'atributos' => [
                    ['atributo_id' => $color->id, 'valor_atributo_id' => $negro->id, 'valor' => null],
                    ['atributo_id' => $conexion->id, 'valor_atributo_id' => null, 'valor' => 'Bluetooth'],
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $variante = VarianteProducto::query()->where('sku', 'MOU-G203-NEG-BT')->firstOrFail();

        $this->assertSame(0, $variante->stock_actual);
        $this->assertEqualsCanonicalizing(
            ['Negro', 'Bluetooth'],
            $variante->valores()->pluck('valor')->all(),
        );
        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'CREAR_VARIANTE',
            'tipo_entidad' => 'variante_producto',
            'entidad_id' => $variante->id,
        ]);
    }

    public function test_sku_must_be_unique_and_price_greater_than_zero(): void
    {
        $producto = $this->producto();
        $this->variante($producto, 'MOU-G203-NEG', 120);
        $admin = $this->gestor();

        $this->actingAs($admin)
            ->post(route('productos.variantes.store', $producto), $this->datosVariante(['sku' => 'mou-g203-neg']))
            ->assertSessionHasErrors(['sku' => 'El SKU ya existe.']);

        $this->actingAs($admin)
            ->post(route('productos.variantes.store', $producto), $this->datosVariante(['precio_venta' => 0]))
            ->assertSessionHasErrors(['precio_venta' => 'El precio de venta debe ser mayor a cero.']);

        $this->actingAs($admin)
            ->post(route('productos.variantes.store', $producto), $this->datosVariante(['precio_venta' => -5]))
            ->assertSessionHasErrors(['precio_venta' => 'El precio de venta debe ser mayor a cero.']);

        $this->assertSame(1, VarianteProducto::query()->count());
    }

    public function test_duplicate_attribute_combination_is_rejected_for_the_same_product(): void
    {
        $producto = $this->producto();
        [$color, $negro] = $this->atributoConValor('Color', 'Negro');
        $existente = $this->variante($producto, 'MOU-G203-NEG', 120);
        $existente->valores()->attach($negro->id);

        $this->actingAs($this->gestor())
            ->post(route('productos.variantes.store', $producto), $this->datosVariante([
                'sku' => 'MOU-G203-NEG-2',
                'atributos' => [
                    ['atributo_id' => $color->id, 'valor_atributo_id' => null, 'valor' => 'negro'],
                ],
            ]))
            ->assertSessionHasErrors(['atributos' => 'La combinación de atributos ya existe para este producto.']);

        $this->assertSame(1, VarianteProducto::query()->count());
    }

    public function test_price_update_keeps_previous_price_and_audits_the_change(): void
    {
        $producto = $this->producto();
        $variante = $this->variante($producto, 'MOU-G203-NEG', 120);
        $admin = $this->gestor();

        $this->actingAs($admin)
            ->patch(route('productos.variantes.precio', $variante), [
                'precio_venta' => '135.50',
                'motivo' => 'Subió el dólar',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $variante->refresh();
        $this->assertSame(135.5, $variante->precio_venta);
        $this->assertSame(120.0, $variante->precio_anterior);

        $registro = DB::table('bitacora_auditoria')
            ->where('accion', 'ACTUALIZAR_PRECIO')
            ->where('entidad_id', $variante->id)
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame($admin->id, (int) $registro->usuario_id);
        $this->assertSame('Cambio de precio: Subió el dólar', $registro->motivo);
        $this->assertEquals(120, json_decode($registro->valor_anterior, true)['precio_venta']);
        $this->assertEquals(135.5, json_decode($registro->valor_nuevo, true)['precio_venta']);
    }

    public function test_price_update_rejects_invalid_prices_and_ignores_unchanged_ones(): void
    {
        $variante = $this->variante($this->producto(), 'MOU-G203-NEG', 120);
        $admin = $this->gestor();

        $this->actingAs($admin)
            ->patch(route('productos.variantes.precio', $variante), ['precio_venta' => 0])
            ->assertSessionHasErrors(['precio_venta' => 'El precio de venta debe ser mayor a cero.']);

        $this->actingAs($admin)
            ->patch(route('productos.variantes.precio', $variante), ['precio_venta' => '120.00'])
            ->assertSessionHasNoErrors();

        $this->assertSame(120.0, $variante->fresh()->precio_venta);
        $this->assertDatabaseMissing('bitacora_auditoria', ['accion' => 'ACTUALIZAR_PRECIO']);
    }

    public function test_variants_without_sales_in_six_months_are_listed_as_without_rotation(): void
    {
        $producto = $this->producto();
        $reciente = $this->variante($producto, 'SKU-RECIENTE', 50);
        $antigua = $this->variante($producto, 'SKU-ANTIGUA', 50);
        $anulada = $this->variante($producto, 'SKU-ANULADA', 50);
        $nunca = $this->variante($producto, 'SKU-NUNCA', 50);
        $sinStock = $this->variante($producto, 'SKU-SIN-STOCK', 50, 0);
        $vendedor = $this->gestor();

        $this->venta($vendedor, $reciente, now()->subMonth());
        $this->venta($vendedor, $antigua, now()->subMonths(8));
        $this->venta($vendedor, $anulada, now()->subWeek(), 'ANULADA');

        $sinRotacion = VarianteProducto::query()->sinRotacion()->pluck('sku')->all();

        $this->assertEqualsCanonicalizing(['SKU-ANTIGUA', 'SKU-ANULADA', 'SKU-NUNCA'], $sinRotacion);
        $this->assertNotContains($reciente->sku, $sinRotacion);
        $this->assertNotContains($sinStock->sku, $sinRotacion);
    }

    public function test_variant_with_movements_cannot_be_deleted_and_can_be_inactivated(): void
    {
        $producto = $this->producto();
        $conVenta = $this->variante($producto, 'SKU-VENTA', 80);
        $sinMovimiento = $this->variante($producto, 'SKU-NUEVA', 80);
        $admin = $this->gestor();
        $this->venta($admin, $conVenta, now()->subDay());

        $this->actingAs($admin)
            ->delete(route('productos.variantes.destroy', $conVenta))
            ->assertSessionHasErrors('variante');

        $this->assertDatabaseHas('variante_producto', ['id' => $conVenta->id]);

        $this->actingAs($admin)
            ->patch(route('productos.variantes.estado', $conVenta), ['estado' => 'INACTIVO'])
            ->assertSessionHasNoErrors();

        $this->assertSame('INACTIVO', $conVenta->fresh()->estado);

        $this->actingAs($admin)
            ->delete(route('productos.variantes.destroy', $sinMovimiento))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('variante_producto', ['id' => $sinMovimiento->id]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $claves
     */
    private function usuarioCon(array $claves): User
    {
        $rol = $this->rolConPermisos('Rol '.implode('-', $claves), $claves);

        return User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);
    }

    private function gestor(): User
    {
        return $this->usuarioCon(['inventario.ver', 'productos.gestionar']);
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

    private function variante(Producto $producto, string $sku, float $precio, int $stock = 3): VarianteProducto
    {
        return $producto->variantes()->create([
            'sku' => $sku,
            'precio_venta' => $precio,
            'stock_actual' => $stock,
            'stock_minimo' => 5,
            'estado' => 'ACTIVO',
        ]);
    }

    /**
     * @return array{0: Atributo, 1: ValorAtributo}
     */
    private function atributoConValor(string $atributo, string $valor): array
    {
        $modelo = Atributo::query()->create(['nombre' => $atributo, 'estado' => 'ACTIVO']);

        return [$modelo, $modelo->valores()->create(['valor' => $valor, 'estado' => 'ACTIVO'])];
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datosVariante(array $cambios = []): array
    {
        return array_merge([
            'sku' => 'MOU-G203-BLA',
            'codigo_barras' => '',
            'precio_venta' => '125.00',
            'stock_minimo' => 4,
            'atributos' => [],
        ], $cambios);
    }

    private function venta(User $usuario, VarianteProducto $variante, \DateTimeInterface $fecha, string $estado = 'COMPLETADA'): void
    {
        $clienteId = DB::table('cliente')->insertGetId(['nombre' => 'Cliente Final']);

        $ventaId = DB::table('venta')->insertGetId([
            'cliente_id' => $clienteId,
            'usuario_id' => $usuario->id,
            'numero_venta' => 'V-'.uniqid(),
            'fecha' => $fecha,
            'estado' => $estado,
        ]);

        DB::table('detalle_venta')->insert([
            'venta_id' => $ventaId,
            'variante_id' => $variante->id,
            'cantidad' => 1,
            'precio_unitario' => $variante->precio_venta,
        ]);
    }
}
