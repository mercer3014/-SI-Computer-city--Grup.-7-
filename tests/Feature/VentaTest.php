<?php

namespace Tests\Feature;

use App\Mail\NotaVentaMail;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class VentaTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    public function test_users_without_permission_cannot_open_pos(): void
    {
        $usuario = User::factory()->create(['primer_login' => false]);

        $this->actingAs($usuario)->get(route('ventas.index'))->assertForbidden();
    }

    public function test_seller_can_open_pos_and_register_a_sale(): void
    {
        Mail::fake();
        [$variante, $cliente, $efectivo] = $this->escenario(10);
        $vendedor = $this->vendedor();

        $this->actingAs($vendedor)
            ->get(route('ventas.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ventas')
                ->where('puedeCrear', true)
                ->where('puedeAnular', false));

        $this->actingAs($vendedor)
            ->post(route('ventas.store'), $this->payload($variante, $cliente, $efectivo, 2))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('venta', [
            'numero_venta' => 'V-'.now()->year.'-0001',
            'cliente_id' => $cliente->id,
            'usuario_id' => $vendedor->id,
            'estado' => 'COMPLETADA',
        ]);
        $this->assertDatabaseHas('detalle_venta', [
            'variante_id' => $variante->id,
            'cantidad' => 2,
            'precio_unitario' => 120,
            'numero_serie' => 'SN-G203-01',
            'meses_garantia' => 6,
            'estado' => 'VIGENTE',
        ]);
        $this->assertDatabaseHas('pago_venta', [
            'metodo_pago_id' => $efectivo->id,
            'monto' => 240,
        ]);
        $this->assertSame(8, $variante->fresh()->stock_actual);
        $this->assertDatabaseHas('movimiento_inventario', [
            'tipo' => 'SALIDA',
            'referencia' => 'V-'.now()->year.'-0001',
        ]);
        $this->assertDatabaseHas('bitacora_auditoria', ['accion' => 'REGISTRAR_VENTA']);
        Mail::assertNothingSent();
    }

    public function test_second_sale_increments_correlative_and_combined_payments_are_accepted(): void
    {
        [$variante, $cliente, $efectivo] = $this->escenario(10);
        $qr = $this->metodo('QR', 'Pago con QR');
        $admin = $this->cajero();

        $this->actingAs($admin)->post(route('ventas.store'), $this->payload($variante, $cliente, $efectivo, 1));
        $this->actingAs($admin)
            ->post(route('ventas.store'), [
                'cliente_id' => $cliente->id,
                'items' => [[
                    'variante_id' => $variante->id,
                    'cantidad' => 1,
                    'descuento' => 0,
                    'numero_serie' => 'SN-2',
                    'meses_garantia' => 3,
                ]],
                'pagos' => [
                    ['metodo_pago_id' => $efectivo->id, 'monto' => 50, 'referencia' => null],
                    ['metodo_pago_id' => $qr->id, 'monto' => 70, 'referencia' => 'QR-99'],
                ],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('venta', ['numero_venta' => 'V-'.now()->year.'-0002']);
        $this->assertSame(8, $variante->fresh()->stock_actual);
    }

    public function test_insufficient_stock_blocks_the_sale(): void
    {
        [$variante, $cliente, $efectivo] = $this->escenario(1);

        $this->actingAs($this->vendedor())
            ->post(route('ventas.store'), $this->payload($variante, $cliente, $efectivo, 2))
            ->assertSessionHasErrors('items.0.cantidad');

        $this->assertDatabaseMissing('venta', ['cliente_id' => $cliente->id]);
        $this->assertSame(1, $variante->fresh()->stock_actual);
    }

    public function test_payments_must_match_total_and_be_positive(): void
    {
        [$variante, $cliente, $efectivo] = $this->escenario(5);
        $vendedor = $this->vendedor();

        $this->actingAs($vendedor)
            ->post(route('ventas.store'), [
                'cliente_id' => $cliente->id,
                'items' => [[
                    'variante_id' => $variante->id,
                    'cantidad' => 1,
                    'descuento' => 0,
                    'meses_garantia' => 3,
                ]],
                'pagos' => [
                    ['metodo_pago_id' => $efectivo->id, 'monto' => 10, 'referencia' => null],
                ],
            ])
            ->assertSessionHasErrors('pagos');

        $this->actingAs($vendedor)
            ->post(route('ventas.store'), [
                'cliente_id' => $cliente->id,
                'items' => [[
                    'variante_id' => $variante->id,
                    'cantidad' => 1,
                    'descuento' => 0,
                    'meses_garantia' => 3,
                ]],
                'pagos' => [
                    ['metodo_pago_id' => $efectivo->id, 'monto' => 0, 'referencia' => null],
                ],
            ])
            ->assertSessionHasErrors('pagos.0.monto');
    }

    public function test_sale_without_items_or_client_is_rejected(): void
    {
        [, $cliente, $efectivo] = $this->escenario(2);
        $vendedor = $this->vendedor();

        $this->actingAs($vendedor)
            ->post(route('ventas.store'), [
                'cliente_id' => $cliente->id,
                'items' => [],
                'pagos' => [['metodo_pago_id' => $efectivo->id, 'monto' => 10]],
            ])
            ->assertSessionHasErrors('items');

        $this->actingAs($vendedor)
            ->post(route('ventas.store'), [
                'items' => [[
                    'variante_id' => 1,
                    'cantidad' => 1,
                    'meses_garantia' => 3,
                ]],
                'pagos' => [['metodo_pago_id' => $efectivo->id, 'monto' => 120]],
            ])
            ->assertSessionHasErrors('cliente_id');
    }

    public function test_note_pdf_can_be_downloaded_and_email_is_sent_when_requested(): void
    {
        Mail::fake();
        [$variante, $cliente, $efectivo] = $this->escenario(4);
        $vendedor = $this->vendedor();

        $this->actingAs($vendedor)
            ->post(route('ventas.store'), $this->payload($variante, $cliente, $efectivo, 1, true))
            ->assertSessionHasNoErrors();

        Mail::assertSent(NotaVentaMail::class, fn (NotaVentaMail $mail) => $mail->hasTo($cliente->email));

        $venta = Venta::query()->first();

        $this->actingAs($vendedor)
            ->get(route('ventas.nota', $venta))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_seller_cannot_void_a_sale_and_admin_needs_a_reason(): void
    {
        [$variante, $cliente, $efectivo] = $this->escenario(5);
        $vendedor = $this->vendedor();
        $admin = $this->cajero();

        $this->actingAs($vendedor)->post(route('ventas.store'), $this->payload($variante, $cliente, $efectivo, 1));
        $venta = Venta::query()->first();

        $this->actingAs($vendedor)
            ->patch(route('ventas.anular', $venta), ['motivo' => 'Error de caja'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('ventas.anular', $venta), ['motivo' => ''])
            ->assertSessionHasErrors('motivo');

        $this->assertSame('COMPLETADA', $venta->fresh()->estado);
    }

    public function test_admin_can_void_a_sale_and_stock_returns(): void
    {
        [$variante, $cliente, $efectivo] = $this->escenario(5);
        $admin = $this->cajero();

        $this->actingAs($admin)->post(route('ventas.store'), $this->payload($variante, $cliente, $efectivo, 2));
        $venta = Venta::query()->first();
        $this->assertSame(3, $variante->fresh()->stock_actual);

        $this->actingAs($admin)
            ->patch(route('ventas.anular', $venta), ['motivo' => 'Cliente devolvió el producto en el mostrador'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADA', $venta->fresh()->estado);
        $this->assertSame(5, $variante->fresh()->stock_actual);
        $this->assertDatabaseHas('detalle_venta', [
            'venta_id' => $venta->id,
            'estado' => 'DEVUELTO',
        ]);
        $this->assertDatabaseHas('movimiento_inventario', [
            'tipo' => 'AJUSTE',
            'referencia' => $venta->numero_venta,
        ]);

        $this->actingAs($admin)
            ->patch(route('ventas.anular', $venta), ['motivo' => 'Segundo intento de anulación'])
            ->assertSessionHasErrors('venta');
    }

    /**
     * @param  list<string>  $claves
     */
    private function usuarioCon(array $claves): User
    {
        $rol = $this->rolConPermisos('Rol '.implode('-', $claves), $claves);

        return User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);
    }

    private function vendedor(): User
    {
        return $this->usuarioCon(['ventas.crear', 'clientes.gestionar']);
    }

    private function cajero(): User
    {
        return $this->usuarioCon(['ventas.crear', 'ventas.anular', 'clientes.gestionar']);
    }

    /**
     * @return array{0: VarianteProducto, 1: Cliente, 2: MetodoPago}
     */
    private function escenario(int $stock): array
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

        $variante = $producto->variantes()->create([
            'sku' => 'MOU-G203-NEG',
            'precio_venta' => 120,
            'stock_actual' => $stock,
            'stock_minimo' => 2,
            'estado' => 'ACTIVO',
        ]);

        $cliente = Cliente::query()->create([
            'tipo_documento' => 'CI',
            'numero_documento' => '4521230 SC',
            'nombre' => 'Juan Perez Rojas',
            'email' => 'juanperez@gmail.com',
            'estado' => 'ACTIVO',
        ]);

        return [$variante, $cliente, $this->metodo('EFECTIVO', 'Efectivo')];
    }

    private function metodo(string $codigo, string $nombre): MetodoPago
    {
        return MetodoPago::query()->create([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'estado' => 'ACTIVO',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(
        VarianteProducto $variante,
        Cliente $cliente,
        MetodoPago $metodo,
        int $cantidad,
        bool $email = false,
    ): array {
        $total = $cantidad * (float) $variante->precio_venta;

        return [
            'cliente_id' => $cliente->id,
            'enviar_email' => $email,
            'items' => [[
                'variante_id' => $variante->id,
                'cantidad' => $cantidad,
                'descuento' => 0,
                'numero_serie' => 'SN-G203-01',
                'meses_garantia' => 6,
            ]],
            'pagos' => [[
                'metodo_pago_id' => $metodo->id,
                'monto' => $total,
                'referencia' => null,
            ]],
        ];
    }
}
