<?php

namespace Tests\Feature;

use App\Models\Producto;
use App\Models\User;
use App\Models\VarianteProducto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class InventarioTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    public function test_seller_can_consult_stock_but_cannot_adjust_it(): void
    {
        $variante = $this->variante('MOU-G203-NEG', 3);
        $vendedor = $this->usuarioCon(['inventario.ver']);

        $this->actingAs($vendedor)
            ->get(route('inventario.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Inventario')
                ->where('puedeAjustar', false)
                ->has('variantes.data', 1));

        $this->actingAs($vendedor)
            ->post(route('inventario.ajustar'), [
                'sku' => $variante->sku,
                'tipo' => 'ENTRADA',
                'cantidad' => 2,
                'motivo' => 'Conteo físico de góndola',
            ])
            ->assertForbidden();

        $this->assertSame(3, $variante->fresh()->stock_actual);
    }

    public function test_admin_entrada_updates_stock_and_writes_kardex_and_audit(): void
    {
        $variante = $this->variante('MOU-G203-NEG', 0);
        $admin = $this->gestor();

        $this->actingAs($admin)
            ->post(route('inventario.ajustar'), [
                'sku' => 'mou-g203-neg',
                'tipo' => 'ENTRADA',
                'cantidad' => 10,
                'motivo' => 'Stock inicial por conteo físico',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(10, $variante->fresh()->stock_actual);
        $this->assertDatabaseHas('movimiento_inventario', [
            'tipo' => 'AJUSTE',
            'referencia' => 'AJUSTE-ENTRADA',
            'observacion' => 'Stock inicial por conteo físico',
            'usuario_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('detalle_movimiento_inventario', [
            'variante_id' => $variante->id,
            'cantidad' => 10,
        ]);
        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'AJUSTAR_STOCK',
            'entidad_id' => $variante->id,
        ]);
    }

    public function test_outbound_adjustment_cannot_exceed_available_stock(): void
    {
        $variante = $this->variante('MOU-G203-NEG', 4);

        $this->actingAs($this->gestor())
            ->post(route('inventario.ajustar'), [
                'sku' => $variante->sku,
                'tipo' => 'SALIDA',
                'cantidad' => 5,
                'motivo' => 'Merma por producto dañado',
            ])
            ->assertSessionHasErrors('cantidad');

        $this->assertSame(4, $variante->fresh()->stock_actual);
        $this->assertDatabaseMissing('movimiento_inventario', ['tipo' => 'AJUSTE']);
    }

    public function test_adjustment_requires_reason_and_rejects_unknown_sku(): void
    {
        $this->variante('MOU-G203-NEG', 4);
        $admin = $this->gestor();

        $this->actingAs($admin)
            ->post(route('inventario.ajustar'), [
                'sku' => 'MOU-G203-NEG',
                'tipo' => 'ENTRADA',
                'cantidad' => 1,
                'motivo' => '',
            ])
            ->assertSessionHasErrors('motivo');

        $this->actingAs($admin)
            ->post(route('inventario.ajustar'), [
                'sku' => 'SKU-INEXISTENTE',
                'tipo' => 'ENTRADA',
                'cantidad' => 1,
                'motivo' => 'Carga de stock inicial',
            ])
            ->assertSessionHasErrors(['sku' => 'No existe variante con el SKU indicado.']);
    }

    public function test_serial_not_found_stays_readable(): void
    {
        $this->variante('MOU-G203-NEG', 1);

        $this->actingAs($this->gestor())
            ->get(route('inventario.index', ['tab' => 'serie', 'serie' => 'ABC123']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('serie.busqueda', 'ABC123')
                ->where('serie.encontrada', false));
    }

    public function test_admin_can_update_minimum_stock(): void
    {
        $variante = $this->variante('MOU-G203-NEG', 2, 5);

        $this->actingAs($this->gestor())
            ->patch(route('inventario.minimo', $variante), ['stock_minimo' => 8])
            ->assertSessionHasNoErrors();

        $this->assertSame(8, $variante->fresh()->stock_minimo);
    }

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
        return $this->usuarioCon(['inventario.ver', 'inventario.ajustar']);
    }

    private function variante(string $sku, int $stock, int $minimo = 5): VarianteProducto
    {
        $categoriaId = DB::table('categoria')->insertGetId([
            'nombre' => 'Mouse',
            'slug' => 'mouse-'.uniqid(),
        ]);

        $producto = Producto::query()->create([
            'categoria_id' => $categoriaId,
            'nombre' => 'Mouse Logitech G203',
            'slug' => 'mouse-'.uniqid(),
            'estado' => 'ACTIVO',
        ]);

        return $producto->variantes()->create([
            'sku' => $sku,
            'precio_venta' => 120,
            'stock_actual' => $stock,
            'stock_minimo' => $minimo,
            'estado' => 'ACTIVO',
        ]);
    }
}
