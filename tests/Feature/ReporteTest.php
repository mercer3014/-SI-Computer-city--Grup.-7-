<?php

namespace Tests\Feature;

use App\Models\Rol;
use App\Models\User;
use App\Services\Reportes\CatalogoReportes;
use App\Services\Reportes\Generadores\ProductosMasVendidos;
use App\Services\Reportes\Generadores\VentasPeriodo;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\ReporteResultado;
use App\Services\Reportes\ServicioReportes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use Tests\Concerns\CreaAdministrador;
use Tests\Concerns\CreaEscenarioDeReportes;
use Tests\TestCase;

class ReporteTest extends TestCase
{
    use CreaAdministrador;
    use CreaEscenarioDeReportes;
    use RefreshDatabase;

    private const PARAMETROS = [
        'tipo' => 'ventas_periodo',
        'periodo' => 'personalizado',
        'desde' => '2026-10-01',
        'hasta' => '2026-10-31',
    ];

    public function test_sin_el_permiso_reportes_ver_todo_responde_403(): void
    {
        $this->get(route('reportes.index'))->assertRedirect(route('login'));

        $sinPermiso = $this->usuarioCon(['inventario.ver']);
        $vendedor = $this->usuarioCon(['ventas.crear', 'clientes.gestionar', 'cotizaciones.gestionar']);

        foreach ([$sinPermiso, $vendedor] as $usuario) {
            $this->actingAs($usuario)->get(route('reportes.index'))->assertForbidden();
            $this->actingAs($usuario)->get(route('reportes.generar', self::PARAMETROS))->assertForbidden();
            $this->actingAs($usuario)->get(route('reportes.exportar', [...self::PARAMETROS, 'formato' => 'pdf']))->assertForbidden();
            $this->actingAs($usuario)->postJson(route('reportes.interpretar-voz'), ['texto' => 'ventas de hoy'])->assertForbidden();
        }
    }

    public function test_ventas_por_periodo_suma_solo_ventas_validas_y_avisa_del_costo_faltante(): void
    {
        $this->escenarioDeReportes();

        $r = $this->generar('ventas_periodo', ['agrupar' => 'dia']);

        $this->assertSame('Ventas por período', $r->titulo);
        $this->assertSame(['05/10/2026', '06/10/2026', '08/10/2026'], array_column($r->filas, 'periodo'));
        $this->assertEquals(
            ['periodo' => '05/10/2026', 'ventas' => 1, 'ingresos' => 400.0, 'costo' => 240.0, 'utilidad' => 160.0],
            $r->filas[0],
        );
        $this->assertEquals(['periodo' => '08/10/2026', 'ventas' => 1, 'ingresos' => 100.0, 'costo' => 0.0, 'utilidad' => 0.0], $r->filas[2]);
        $this->assertEquals(['periodo' => 'Total', 'ventas' => 3, 'ingresos' => 800.0, 'costo' => 420.0, 'utilidad' => 280.0], $r->totales);
        $this->assertSame(['1 línea de venta sin costo registrado: la utilidad puede estar incompleta.'], $r->advertencias);
        $this->assertSame('linea', $r->grafico['tipo']);
        $this->assertFalse($r->vacio());
    }

    public function test_ventas_por_periodo_se_agrupa_por_semana_y_por_mes(): void
    {
        $this->escenarioDeReportes();

        $semana = $this->generar('ventas_periodo', ['agrupar' => 'semana']);
        $this->assertSame(['Sem. 05/10'], array_column($semana->filas, 'periodo'));
        $this->assertEquals(800.0, $semana->filas[0]['ingresos']);

        $mes = $this->generar('ventas_periodo', ['agrupar' => 'mes']);
        $this->assertSame(['octubre de 2026'], array_column($mes->filas, 'periodo'));
        $this->assertSame(3, $mes->filas[0]['ventas']);
    }

    public function test_ventas_por_periodo_se_filtra_por_categoria_marca_y_metodo_de_pago(): void
    {
        $e = $this->escenarioDeReportes();

        $mouse = $this->generar('ventas_periodo', ['categoria_id' => $e['categorias']['mouse']->id]);
        $this->assertEquals(['periodo' => 'Total', 'ventas' => 2, 'ingresos' => 500.0, 'costo' => 300.0, 'utilidad' => 200.0], $mouse->totales);

        $redragon = $this->generar('ventas_periodo', ['marca_id' => $e['marcas']['redragon']->id]);
        $this->assertEquals(['periodo' => 'Total', 'ventas' => 1, 'ingresos' => 200.0, 'costo' => 120.0, 'utilidad' => 80.0], $redragon->totales);

        $qr = $this->generar('ventas_periodo', ['metodo_pago_id' => $e['metodos']['qr']->id]);
        $this->assertEquals(['periodo' => 'Total', 'ventas' => 1, 'ingresos' => 300.0, 'costo' => 180.0, 'utilidad' => 120.0], $qr->totales);
        $this->assertSame([], $qr->advertencias);
    }

    public function test_productos_mas_vendidos_ordena_por_unidades_y_respeta_el_limite(): void
    {
        $e = $this->escenarioDeReportes();

        $r = $this->generar('productos_mas_vendidos');

        $this->assertSame(['MOU-1', 'ACC-1', 'TEC-1'], array_column($r->filas, 'sku'));
        $this->assertSame([5, 2, 1], array_column($r->filas, 'unidades'));
        $this->assertEquals([500.0, 100.0, 200.0], array_column($r->filas, 'total'));
        $this->assertSame(8, $r->totales['unidades']);
        $this->assertSame('barra', $r->grafico['tipo']);

        $this->assertCount(2, $this->generar('productos_mas_vendidos', ['limite' => 2])->filas);

        $soloTeclados = $this->generar('productos_mas_vendidos', ['categoria_id' => $e['categorias']['teclados']->id]);
        $this->assertSame(['TEC-1'], array_column($soloTeclados->filas, 'sku'));
    }

    public function test_utilidad_bruta_por_categoria_con_costo_historico(): void
    {
        $this->escenarioDeReportes();

        $r = $this->generar('utilidad_bruta');

        $this->assertSame(['Mouse', 'Teclados', 'Accesorios'], array_column($r->filas, 'categoria'));
        $this->assertEquals(['categoria' => 'Mouse', 'ingresos' => 500.0, 'costo' => 300.0, 'utilidad' => 200.0, 'margen' => 40.0], $r->filas[0]);
        $this->assertEquals(0.0, $r->filas[2]['margen']);
        $this->assertEquals(['categoria' => 'Total', 'ingresos' => 800.0, 'costo' => 420.0, 'utilidad' => 280.0, 'margen' => 40.0], $r->totales);
        $this->assertCount(1, $r->advertencias);
    }

    public function test_gastos_y_flujo_de_caja_no_suman_anulados(): void
    {
        $this->escenarioDeReportes();

        $r = $this->generar('gastos_flujo_caja');

        $conceptos = array_column($r->filas, 'monto', 'concepto');

        $this->assertEquals(800.0, $conceptos['Ingresos por ventas']);
        $this->assertEquals(1000.0, $conceptos['Otros ingresos']);
        $this->assertEquals(2400.0, $conceptos['Compras a proveedores']);
        $this->assertEquals(500.0, $conceptos['Gastos operativos']);
        $this->assertEquals(0.0, $conceptos['Otros egresos']);
        $this->assertEquals(500.0, $conceptos['Alquiler']);
        $this->assertArrayNotHasKey('Publicidad', $conceptos);
        $this->assertEquals(-1100.0, $r->totales['monto']);
        $this->assertSame('torta', $r->grafico['tipo']);
    }

    public function test_gastos_y_flujo_se_filtran_por_metodo_de_pago_y_categoria_de_gasto(): void
    {
        $e = $this->escenarioDeReportes();

        $qr = $this->generar('gastos_flujo_caja', ['metodo_pago_id' => $e['metodos']['qr']->id]);
        $this->assertEquals(300.0, array_column($qr->filas, 'monto', 'concepto')['Ingresos por ventas']);
        $this->assertEquals(300.0, $qr->totales['monto']);

        $publicidad = $this->generar('gastos_flujo_caja', ['categoria_gasto_id' => $e['gastos']['publicidad']->id]);
        $this->assertArrayNotHasKey('Alquiler', array_column($publicidad->filas, 'monto', 'concepto'));
        $this->assertEquals(0.0, array_column($publicidad->filas, 'monto', 'concepto')['Gastos operativos']);
    }

    public function test_compras_por_proveedor_no_cuenta_las_anuladas(): void
    {
        $e = $this->escenarioDeReportes();

        $r = $this->generar('compras_proveedor');

        $this->assertSame(['Prov A', 'Prov B'], array_column($r->filas, 'proveedor'));
        $this->assertSame([1, 1], array_column($r->filas, 'compras'));
        $this->assertEquals([1200.0, 1200.0], array_column($r->filas, 'monto'));
        $this->assertEquals(['proveedor' => 'Total', 'compras' => 2, 'monto' => 2400.0], $r->totales);

        $soloB = $this->generar('compras_proveedor', ['proveedor_id' => $e['proveedores']['b']->id]);
        $this->assertSame(['Prov B'], array_column($soloB->filas, 'proveedor'));
    }

    public function test_estado_de_resultados_es_mensual_y_resta_los_gastos_no_anulados(): void
    {
        $this->escenarioDeReportes();

        $r = $this->generar('estado_resultados', [], '2026-09-01', '2026-10-31');

        $this->assertSame(['septiembre de 2026', 'octubre de 2026'], array_column($r->filas, 'mes'));
        $this->assertEquals(0.0, $r->filas[0]['ingresos']);
        $this->assertEquals([
            'mes' => 'octubre de 2026',
            'ingresos' => 800.0,
            'costo_ventas' => 420.0,
            'utilidad_bruta' => 280.0,
            'gastos' => 500.0,
            'utilidad_neta' => -220.0,
        ], $r->filas[1]);
        $this->assertEquals(-220.0, $r->totales['utilidad_neta']);
        $this->assertCount(1, $r->advertencias);
    }

    public function test_un_periodo_sin_operaciones_devuelve_un_resultado_vacio(): void
    {
        $this->escenarioDeReportes();

        foreach (CatalogoReportes::claves() as $clave) {
            $r = $this->generar($clave, [], '2026-12-01', '2026-12-31');

            $this->assertTrue($r->vacio(), "El reporte $clave debería estar vacío.");
            $this->assertSame([], $r->filas);
            $this->assertSame([], $r->totales);
        }
    }

    public function test_los_periodos_diario_semanal_y_mensual_salen_de_una_fecha(): void
    {
        $dia = PeriodoReporte::resolver('diario', '2026-10-08');
        $this->assertSame(['2026-10-08', '2026-10-08'], [$dia->desde->toDateString(), $dia->hasta->toDateString()]);

        $semana = PeriodoReporte::resolver('semanal', '2026-10-08');
        $this->assertSame(['2026-10-05', '2026-10-11'], [$semana->desde->toDateString(), $semana->hasta->toDateString()]);

        $mes = PeriodoReporte::resolver('mensual', '2026-02-10');
        $this->assertSame(['2026-02-01', '2026-02-28'], [$mes->desde->toDateString(), $mes->hasta->toDateString()]);
        $this->assertSame(28, $mes->dias());
    }

    public function test_el_periodo_valida_el_orden_y_el_maximo_de_dias(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('La fecha de fin no puede ser anterior a la de inicio.');

        PeriodoReporte::resolver('personalizado', '2026-10-31', '2026-10-01');
    }

    public function test_el_periodo_no_puede_superar_366_dias(): void
    {
        $this->assertSame(366, PeriodoReporte::resolver('personalizado', '2026-01-01', '2027-01-01')->dias());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('366');

        PeriodoReporte::resolver('personalizado', '2026-01-01', '2027-01-02');
    }

    public function test_una_fecha_de_fin_anterior_al_inicio_es_un_error_de_validacion(): void
    {
        $this->actingAs($this->usuarioConReportes())
            ->get(route('reportes.generar', [...self::PARAMETROS, 'desde' => '2026-10-31', 'hasta' => '2026-10-01']))
            ->assertSessionHasErrors(['hasta' => 'La fecha de fin no puede ser anterior a la de inicio.']);

        $this->actingAs($this->usuarioConReportes())
            ->get(route('reportes.generar', [...self::PARAMETROS, 'desde' => '2025-01-01', 'hasta' => '2026-10-01']))
            ->assertSessionHasErrors('hasta');

        $this->actingAs($this->usuarioConReportes())
            ->get(route('reportes.generar', ['tipo' => 'ventas_periodo', 'periodo' => 'personalizado']))
            ->assertSessionHasErrors(['desde', 'hasta']);
    }

    public function test_la_pantalla_lista_los_reportes_y_las_opciones_de_filtro(): void
    {
        $this->escenarioDeReportes();

        $this->actingAs($this->usuarioConReportes())
            ->get(route('reportes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reportes/Index')
                ->has('catalogo', 6)
                ->where('catalogo.0.clave', 'ventas_periodo')
                ->has('opciones.categorias', 3)
                ->has('opciones.marcas', 2)
                ->has('opciones.proveedores', 2)
                ->has('opciones.categorias_gasto', 2)
                ->has('opciones.metodos_pago', 3)
                ->where('resultado', null)
                ->where('puedeAsistente', false));
    }

    public function test_generar_devuelve_el_resultado_en_la_misma_pagina_y_deja_bitacora(): void
    {
        $this->escenarioDeReportes();

        $this->actingAs($this->usuarioConReportes())
            ->get(route('reportes.generar', [...self::PARAMETROS, 'agrupar' => 'dia']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('reportes/Index')
                ->where('resultado.titulo', 'Ventas por período')
                ->where('resultado.vacio', false)
                ->where('resultado.totales.ingresos', 800)
                ->has('resultado.filas', 3)
                ->where('resultado.encabezado.tienda', 'Computer City')
                ->where('resultado.encabezado.periodo', '01/10/2026 – 31/10/2026')
                ->where('resultado.parametros.tipo', 'ventas_periodo')
                ->has('resultado.advertencias', 1));

        $evento = DB::table('bitacora_auditoria')->where('accion', 'GENERAR_REPORTE')->first();
        $this->assertNotNull($evento);
        $this->assertSame('Reportes', $evento->modulo);
        $nuevo = json_decode((string) $evento->valor_nuevo, true);
        $this->assertSame('ventas_periodo', $nuevo['tipo']);
        $this->assertSame('2026-10-01', $nuevo['desde']);
    }

    public function test_un_periodo_vacio_se_marca_como_vacio_en_pantalla(): void
    {
        $this->escenarioDeReportes();

        $this->actingAs($this->usuarioConReportes())
            ->get(route('reportes.generar', [...self::PARAMETROS, 'desde' => '2026-12-01', 'hasta' => '2026-12-31']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('resultado.vacio', true)
                ->where('resultado.filas', []));
    }

    public function test_solo_el_administrador_con_el_permiso_puede_usar_el_asistente(): void
    {
        $rol = $this->rolConPermisos('Administrador', ['reportes.ver', 'asistente.usar']);
        $admin = User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);

        $otro = $this->rolConPermisos('Gerente', ['reportes.ver', 'asistente.usar']);
        $gerente = User::factory()->create(['rol_id' => $otro->id, 'primer_login' => false]);

        $this->assertTrue($admin->puedeUsarAsistente());
        $this->assertFalse($gerente->puedeUsarAsistente());

        $this->actingAs($admin)->get(route('reportes.index'))->assertInertia(fn (Assert $page) => $page->where('puedeAsistente', true));
        $this->actingAs($gerente)->get(route('reportes.index'))->assertInertia(fn (Assert $page) => $page->where('puedeAsistente', false));
        $this->assertSame('Administrador', Rol::query()->whereKey($admin->rol_id)->value('nombre'));
    }

    public function test_paridad_con_las_funciones_de_postgresql(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Las funciones fn_* solo existen en PostgreSQL.');
        }

        $this->escenarioDeReportes();
        $periodo = PeriodoReporte::resolver('personalizado', '2026-10-01', '2026-10-31');

        $conFuncion = app(VentasPeriodo::class)->generar($periodo, []);
        $conConsulta = app(VentasPeriodo::class)->usarFunciones(false)->generar($periodo, []);
        $this->assertEquals($conConsulta['totales'], $conFuncion['totales']);

        $ranking = app(ProductosMasVendidos::class)->generar($periodo, []);
        $rankingConsulta = app(ProductosMasVendidos::class)->usarFunciones(false)->generar($periodo, []);
        $this->assertEquals($rankingConsulta['filas'], $ranking['filas']);
    }

    /**
     * @param  array<string, int|string>  $filtros
     */
    private function generar(string $clave, array $filtros = [], string $desde = '2026-10-01', string $hasta = '2026-10-31'): ReporteResultado
    {
        return app(ServicioReportes::class)->generar(
            $clave,
            PeriodoReporte::resolver('personalizado', $desde, $hasta),
            $filtros,
            $this->usuarioConReportes(),
        );
    }

    private function usuarioConReportes(): User
    {
        return $this->usuarioCon(['reportes.ver']);
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
