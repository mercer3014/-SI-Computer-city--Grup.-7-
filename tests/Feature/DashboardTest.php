<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Pedido;
use App\Models\Rol;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Concerns\CreaAdministrador;
use Tests\Concerns\CreaEscenarioDeReportes;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreaAdministrador;
    use CreaEscenarioDeReportes;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-08 18:00:00');
    }

    public function test_guests_are_redirected_to_the_login_page()
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_dashboard()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->get(route('dashboard'));
        $response->assertOk();
    }

    public function test_un_usuario_sin_permisos_ve_un_panel_vacio_y_sin_datos_financieros(): void
    {
        $this->actingAs(User::factory()->create(['primer_login' => false]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('operativos', [])
                ->missing('financieros'));
    }

    public function test_el_cliente_web_sigue_yendo_a_la_tienda(): void
    {
        $rol = Rol::factory()->create(['nombre' => Rol::CLIENTE_WEB]);
        $cliente = User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);

        $this->actingAs($cliente)->get(route('dashboard'))->assertRedirect(route('tienda.index'));
    }

    public function test_el_administrador_ve_los_indicadores_financieros_y_las_alertas(): void
    {
        $e = $this->escenarioDeReportes();
        $e['accesorio']->update(['stock_actual' => 0]);
        $e['mouse']->update(['stock_actual' => 1]);
        $this->garantiaPorVencer();
        $this->pedidoPagado();
        $this->cotizaciones();

        $this->actingAs($this->administrador())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('periodo.clave', 'mes')
                ->where('periodo.desde', '2026-10-01')
                ->where('periodo.hasta', '2026-10-31')
                ->where('hoy', '2026-10-08')
                ->where('operativos.ventasDelDia.cantidad', 1)
                ->where('operativos.ventasDelDia.monto', 100)
                ->where('operativos.ventasDelDia.propias', false)
                ->where('operativos.alertasStock.agotadas', 1)
                ->where('operativos.alertasStock.bajoMinimo', 1)
                ->where('operativos.alertasStock.items.0.sku', 'ACC-1')
                ->where('operativos.garantiasPorVencer.total', 1)
                ->where('operativos.garantiasPorVencer.items.0.serie', 'SN-001')
                ->where('operativos.garantiasPorVencer.items.0.vence', '18/10/2026')
                ->where('operativos.pedidosPendientes.total', 1)
                ->where('operativos.cotizaciones.total', 2)
                ->where('operativos.cotizaciones.porVencer', 1)
                ->where('financieros.resumen.ingresos', 800)
                ->where('financieros.resumen.utilidad', 280)
                ->where('financieros.resumen.utilidadIncompleta', true)
                ->where('financieros.saldoCaja.saldo', -1100)
                ->where('financieros.saldoCaja.neto', -1100)
                ->where('financieros.calificacion.promedio', null)
                ->where('financieros.ventasPorDia.nivel', 'dia')
                ->has('financieros.ventasPorDia.etiquetas', 31)
                ->where('financieros.ventasPorDia.valores.4', 400)
                ->where('financieros.ventasPorDia.valores.5', 300)
                ->where('financieros.ventasPorDia.valores.6', 0)
                ->where('financieros.ventasPorDia.valores.7', 100)
                ->has('financieros.topProductos', 3)
                ->where('financieros.topProductos.0.sku', 'MOU-1')
                ->where('financieros.topProductos.0.unidades', 5));
    }

    public function test_el_vendedor_no_recibe_los_indicadores_financieros_y_solo_cuenta_sus_ventas(): void
    {
        $e = $this->escenarioDeReportes();
        $e['mouse']->update(['stock_actual' => 0]);
        $this->garantiaPorVencer();

        $vendedor = $this->usuarioCon(['ventas.crear', 'inventario.ver', 'garantias.gestionar']);
        $this->ventaDePrueba('2026-10-08 10:00:00', [[$e['teclado'], 1]], [[$e['metodos']['efectivo'], 200]])
            ->forceFill(['usuario_id' => $vendedor->id])
            ->save();

        $respuesta = $this->actingAs($vendedor)->get(route('dashboard'));

        $respuesta->assertOk()->assertInertia(fn (Assert $page) => $page
            ->missing('financieros')
            ->where('operativos.ventasDelDia.cantidad', 1)
            ->where('operativos.ventasDelDia.monto', 200)
            ->where('operativos.ventasDelDia.propias', true)
            ->where('operativos.alertasStock.agotadas', 1)
            ->where('operativos.garantiasPorVencer.total', 1)
            ->missing('operativos.pedidosPendientes')
            ->missing('operativos.cotizaciones'));

        $props = $respuesta->viewData('page')['props'];
        $this->assertArrayNotHasKey('financieros', $props);
        $this->assertStringNotContainsString('ingresos', json_encode($props, JSON_THROW_ON_ERROR));
    }

    public function test_sin_ventas_todo_queda_en_cero(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('dashboard', ['periodo' => 'hoy']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('periodo.clave', 'hoy')
                ->where('operativos.ventasDelDia.cantidad', 0)
                ->where('operativos.ventasDelDia.monto', 0)
                ->where('operativos.alertasStock.agotadas', 0)
                ->where('operativos.garantiasPorVencer.total', 0)
                ->where('financieros.resumen.ingresos', 0)
                ->where('financieros.resumen.utilidad', 0)
                ->where('financieros.resumen.utilidadIncompleta', false)
                ->where('financieros.saldoCaja.saldo', 0)
                ->where('financieros.ventasPorDia.valores', [0])
                ->where('financieros.topProductos', []));
    }

    public function test_un_indicador_que_falla_queda_en_null_y_el_resto_sigue(): void
    {
        $this->escenarioDeReportes();

        $this->partialMock(DashboardService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('saldoCaja')->andThrow(new RuntimeException('falla simulada'));
        });

        $this->actingAs($this->administrador())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('financieros.saldoCaja', null)
                ->where('financieros.resumen.ingresos', 800)
                ->has('financieros.topProductos', 3)
                ->where('operativos.ventasDelDia.cantidad', 1));
    }

    public function test_cambiar_el_periodo_recalcula_los_indicadores(): void
    {
        $this->escenarioDeReportes();
        $admin = $this->administrador();

        $this->actingAs($admin)
            ->get(route('dashboard', ['periodo' => 'semana']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('periodo.clave', 'semana')
                ->where('periodo.desde', '2026-10-05')
                ->where('periodo.hasta', '2026-10-11')
                ->where('financieros.resumen.ingresos', 800)
                ->has('financieros.ventasPorDia.etiquetas', 7));

        $this->actingAs($admin)
            ->get(route('dashboard', ['periodo' => 'hoy']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('financieros.resumen.ingresos', 100)
                ->where('financieros.resumen.utilidad', 0));

        $this->actingAs($admin)
            ->get(route('dashboard', ['periodo' => 'rango', 'desde' => '2026-10-06', 'hasta' => '2026-10-07']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('periodo.clave', 'rango')
                ->where('financieros.resumen.ingresos', 300)
                ->where('financieros.resumen.utilidad', 120)
                ->where('financieros.ventasPorDia.valores', [300, 0]));
    }

    public function test_un_rango_invalido_es_un_error_de_validacion(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)
            ->get(route('dashboard', ['periodo' => 'rango', 'desde' => '2026-10-10', 'hasta' => '2026-10-01']))
            ->assertSessionHasErrors(['hasta' => 'La fecha de fin no puede ser anterior a la de inicio.']);

        $this->actingAs($admin)
            ->get(route('dashboard', ['periodo' => 'rango']))
            ->assertSessionHasErrors(['desde', 'hasta']);

        $this->actingAs($admin)
            ->get(route('dashboard', ['periodo' => 'anio']))
            ->assertSessionHasErrors('periodo');
    }

    public function test_el_enlace_de_pedidos_web_filtra_las_ventas_de_la_tienda_en_linea(): void
    {
        $e = $this->escenarioDeReportes();
        $web = $this->ventaDePrueba('2026-10-08 09:00:00', [[$e['mouse'], 1]], [[$e['metodos']['qr'], 100]]);
        $pedido = Pedido::query()->create([
            'cliente_id' => $web->cliente_id,
            'numero_pedido' => 'PED-2026-0001',
            'estado' => 'PAGADO',
            'total' => 100,
            'fecha' => now(),
        ]);
        $web->forceFill(['pedido_id' => $pedido->id])->save();

        $admin = $this->usuarioCon(['ventas.crear', 'reportes.ver']);

        $this->actingAs($admin)
            ->get(route('ventas.index', ['origen' => 'web']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('ventas.data', 1)
                ->where('ventas.data.0.numero', $web->numero_venta));

        $this->actingAs($admin)
            ->get(route('ventas.index'))
            ->assertInertia(fn (Assert $page) => $page->has('ventas.data', 5));
    }

    public function test_los_enlaces_de_stock_y_garantias_ya_filtran_en_su_modulo(): void
    {
        $e = $this->escenarioDeReportes();
        $e['accesorio']->update(['stock_actual' => 0]);
        $this->garantiaPorVencer();

        $usuario = $this->usuarioCon(['inventario.ver', 'garantias.gestionar']);

        $this->actingAs($usuario)
            ->get(route('inventario.index', ['alerta' => 'cero']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('variantes.data', 1)
                ->where('variantes.data.0.sku', 'ACC-1'));

        $this->actingAs($usuario)
            ->get(route('garantias.index', ['filtro' => 'por_vencer']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('resultados', 1)
                ->where('resultados.0.numero_serie', 'SN-001'));
    }

    private function garantiaPorVencer(): void
    {
        DetalleVenta::query()
            ->whereHas('variante', fn ($q) => $q->where('sku', 'MOU-1'))
            ->orderBy('id')
            ->firstOrFail()
            ->forceFill(['numero_serie' => 'SN-001', 'fecha_fin_garantia' => '2026-10-18'])
            ->save();
    }

    private function pedidoPagado(): void
    {
        Pedido::query()->create([
            'cliente_id' => Cliente::query()->firstOrFail()->id,
            'numero_pedido' => 'PED-2026-0009',
            'estado' => Pedido::PAGADO,
            'origen' => Pedido::ORIGEN_TIENDA,
            'total' => 150,
            'fecha' => now(),
            'fecha_pago' => now(),
        ]);
    }

    private function cotizaciones(): void
    {
        $cliente = Cliente::query()->firstOrFail();

        foreach ([['COT-001', '2026-10-10'], ['COT-002', '2026-10-20']] as [$codigo, $vence]) {
            DB::table('cotizacion')->insert([
                'cliente_id' => $cliente->id,
                'usuario_id' => User::query()->firstOrFail()->id,
                'codigo_cotizacion' => $codigo,
                'fecha_emision' => '2026-10-06 10:00:00',
                'fecha_vencimiento' => $vence,
                'estado' => 'PENDIENTE',
            ]);
        }
    }

    protected function administrador(): User
    {
        $rol = $this->rolConPermisos('Administrador', [
            'reportes.ver',
            'ventas.crear',
            'ventas.anular',
            'inventario.ver',
            'garantias.gestionar',
            'cotizaciones.gestionar',
        ]);

        return User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);
    }

    /**
     * @param  list<string>  $claves
     */
    private function usuarioCon(array $claves): User
    {
        $rol = $this->rolConPermisos('Rol '.implode('-', $claves), $claves);

        return User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);
    }
}
