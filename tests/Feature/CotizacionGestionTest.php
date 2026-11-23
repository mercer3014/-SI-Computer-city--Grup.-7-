<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\EventoProducto;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Models\Venta;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class CotizacionGestionTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    private int $secuencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-08 10:00:00');
    }

    /* ── Permisos ─────────────────────────────────────────────────────────── */

    public function test_sin_el_permiso_todo_da_403(): void
    {
        $cotizacion = $this->cotizacion();
        $sinPermiso = $this->usuarioCon(['ventas.crear']);

        $this->actingAs($sinPermiso)->get(route('cotizaciones.index'))->assertForbidden();
        $this->actingAs($sinPermiso)->get(route('cotizaciones.mas-visitados'))->assertForbidden();
        $this->actingAs($sinPermiso)->get(route('cotizaciones.show', $cotizacion))->assertForbidden();
        $this->actingAs($sinPermiso)->patch(route('cotizaciones.estado', $cotizacion), ['estado' => 'ATENDIDA'])->assertForbidden();
        $this->actingAs($sinPermiso)->post(route('cotizaciones.renovar', $cotizacion))->assertForbidden();

        auth()->logout();
        $this->get(route('cotizaciones.index'))->assertRedirect(route('login'));
    }

    public function test_un_vendedor_con_el_permiso_entra(): void
    {
        $this->actingAs($this->gestor())
            ->get(route('cotizaciones.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cotizaciones/Index')
                ->where('tab', 'cotizaciones')
                ->where('puedeConvertir', true));

        $this->actingAs($this->usuarioCon(['cotizaciones.gestionar']))
            ->get(route('cotizaciones.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('puedeConvertir', false));
    }

    public function test_quien_gestiona_cotizaciones_pero_no_puede_vender_no_convierte(): void
    {
        $cotizacion = $this->cotizacion();
        $soloGestiona = $this->usuarioCon(['cotizaciones.gestionar']);

        $this->actingAs($soloGestiona)->get(route('ventas.index', ['cotizacion' => $cotizacion->id]))->assertForbidden();

        $respuesta = $this->actingAs($soloGestiona)->post(route('ventas.store'), $this->venta($cotizacion));
        $respuesta->assertForbidden();

        $this->assertSame(0, Venta::query()->count());
        $this->assertSame(Cotizacion::PENDIENTE, $cotizacion->fresh()->estado);
    }

    /* ── Listado y vencimiento ────────────────────────────────────────────── */

    public function test_el_listado_filtra_por_estado_fecha_y_texto(): void
    {
        $a = $this->cotizacion(estado: Cotizacion::PENDIENTE, emitida: '2026-10-01 10:00:00', cliente: 'Lucía Vaca');
        $b = $this->cotizacion(estado: Cotizacion::ATENDIDA, emitida: '2026-10-05 10:00:00', cliente: 'Pedro Gómez');
        $c = $this->cotizacion(estado: Cotizacion::RECHAZADA, emitida: '2026-10-07 10:00:00', cliente: 'Carla Rojas');

        $gestor = $this->gestor();

        $filas = function (array $filtros) use ($gestor): array {
            $filas = [];

            $this->actingAs($gestor)
                ->get(route('cotizaciones.index', $filtros))
                ->assertOk()
                ->assertInertia(function (Assert $page) use (&$filas): void {
                    $filas = $page->toArray()['props']['cotizaciones']['data'];
                });

            return $filas;
        };

        $this->assertCount(3, $filas([]));
        $this->assertSame([$b->id], array_column($filas(['estado' => 'ATENDIDA']), 'id'));
        $this->assertSame([$c->id], array_column($filas(['desde' => '2026-10-06']), 'id'));
        $this->assertSame([$a->id], array_column($filas(['hasta' => '2026-10-02']), 'id'));
        $this->assertSame([$b->id], array_column($filas(['desde' => '2026-10-04', 'hasta' => '2026-10-06']), 'id'));
        $this->assertSame([$a->id], array_column($filas(['q' => 'lucía']), 'id'));
        $this->assertSame([$c->id], array_column($filas(['q' => $c->codigo_cotizacion]), 'id'));
        $this->assertCount(3, $filas(['estado' => 'cualquiera', 'desde' => 'ayer']));

        $this->actingAs($gestor)
            ->get(route('cotizaciones.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('kpis.total', 3)
                ->where('kpis.PENDIENTE', 1)
                ->where('kpis.ATENDIDA', 1)
                ->where('kpis.RECHAZADA', 1)
                ->where('kpis.VENCIDA', 0)
                ->where('cotizaciones.data.0.total', 100));
    }

    public function test_al_listar_una_cotizacion_vencida_queda_vencida(): void
    {
        $pasada = $this->cotizacion(vence: '2026-10-07');
        $atendida = $this->cotizacion(estado: Cotizacion::ATENDIDA, vence: '2026-10-01');
        $hoy = $this->cotizacion(vence: '2026-10-08');
        $rechazada = $this->cotizacion(estado: Cotizacion::RECHAZADA, vence: '2026-09-01');
        $convertida = $this->cotizacion(estado: Cotizacion::CONVERTIDA, vence: '2026-09-01');

        $this->actingAs($this->gestor())->get(route('cotizaciones.index'))->assertOk();

        $this->assertSame(Cotizacion::VENCIDA, $pasada->fresh()->estado);
        $this->assertSame(Cotizacion::VENCIDA, $atendida->fresh()->estado);
        $this->assertNotNull($pasada->fresh()->fecha_estado);
        $this->assertSame(Cotizacion::PENDIENTE, $hoy->fresh()->estado, 'La que vence hoy sigue vigente.');
        $this->assertSame(Cotizacion::RECHAZADA, $rechazada->fresh()->estado);
        $this->assertSame(Cotizacion::CONVERTIDA, $convertida->fresh()->estado);

        $this->assertSame(1, DB::table('bitacora_auditoria')->where('accion', 'VENCER_COTIZACIONES')->count());
        $evento = DB::table('bitacora_auditoria')->where('accion', 'VENCER_COTIZACIONES')->first();
        $this->assertSame(2, json_decode((string) $evento?->valor_nuevo, true)['cantidad']);

        $this->actingAs($this->gestor())->get(route('cotizaciones.index'))->assertOk();
        $this->assertSame(1, DB::table('bitacora_auditoria')->where('accion', 'VENCER_COTIZACIONES')->count(), 'Sin nada por vencer no se registra otra vez.');
    }

    public function test_el_comando_vence_las_cotizaciones(): void
    {
        $pasada = $this->cotizacion(vence: '2026-10-07');
        $vigente = $this->cotizacion(vence: '2026-10-09');

        $this->artisan('cotizaciones:vencer')
            ->expectsOutput('Cotizaciones marcadas como vencidas: 1.')
            ->assertSuccessful();

        $this->assertSame(Cotizacion::VENCIDA, $pasada->fresh()->estado);
        $this->assertSame(Cotizacion::PENDIENTE, $vigente->fresh()->estado);

        $this->artisan('cotizaciones:vencer')->expectsOutput('No hay cotizaciones por vencer.')->assertSuccessful();
    }

    public function test_el_comando_esta_programado_cada_dia_a_las_0010(): void
    {
        $eventos = collect(app(Schedule::class)->events())
            ->filter(fn ($evento): bool => str_contains((string) $evento->command, 'cotizaciones:vencer'));

        $this->assertCount(1, $eventos);
        $this->assertSame('10 0 * * *', $eventos->first()->expression);
    }

    public function test_el_detalle_se_abre_como_panel_del_listado(): void
    {
        $cotizacion = $this->cotizacion(cliente: 'Lucía Vaca', telefono: '76543210');
        $gestor = $this->gestor();

        $this->actingAs($gestor)
            ->get(route('cotizaciones.show', $cotizacion))
            ->assertRedirect(route('cotizaciones.index', ['ficha' => $cotizacion->id]));

        $this->actingAs($gestor)
            ->get(route('cotizaciones.index', ['ficha' => $cotizacion->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('ficha.codigo', $cotizacion->codigo_cotizacion)
                ->where('ficha.estado', 'PENDIENTE')
                ->where('ficha.cliente.nombre', 'Lucía Vaca')
                ->where('ficha.cliente.whatsapp_url', 'https://wa.me/59176543210')
                ->where('ficha.puede_convertir', true)
                ->where('ficha.puede_renovar', false)
                ->where('ficha.puede_cambiar_estado', true)
                ->where('ficha.items.0.precio_cotizado', 100)
                ->where('ficha.items.0.precio_actual', 100)
                ->where('ficha.total', 100));

        $this->actingAs($gestor)
            ->get(route('cotizaciones.index', ['ficha' => 99999]))
            ->assertInertia(fn (Assert $page) => $page->where('ficha', null));
    }

    /* ── Estado ───────────────────────────────────────────────────────────── */

    public function test_rechazar_sin_observacion_da_error_y_con_observacion_queda_registrado(): void
    {
        $cotizacion = $this->cotizacion();
        $gestor = $this->gestor();

        $this->actingAs($gestor)
            ->patch(route('cotizaciones.estado', $cotizacion), ['estado' => 'RECHAZADA'])
            ->assertSessionHasErrors('observacion');

        $this->actingAs($gestor)
            ->patch(route('cotizaciones.estado', $cotizacion), ['estado' => 'RECHAZADA', 'observacion' => '   '])
            ->assertSessionHasErrors('observacion');

        $this->assertSame(Cotizacion::PENDIENTE, $cotizacion->fresh()->estado);

        $this->actingAs($gestor)
            ->patch(route('cotizaciones.estado', $cotizacion), ['estado' => 'RECHAZADA', 'observacion' => 'El cliente ya compró en otro lado'])
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Cotización '.$cotizacion->codigo_cotizacion.' actualizada.']);

        $cotizacion->refresh();
        $this->assertSame(Cotizacion::RECHAZADA, $cotizacion->estado);
        $this->assertSame('El cliente ya compró en otro lado', $cotizacion->observacion);
        $this->assertSame($gestor->id, $cotizacion->actualizado_por);
        $this->assertNotNull($cotizacion->fecha_estado);

        $evento = DB::table('bitacora_auditoria')->where('accion', 'CAMBIAR_ESTADO_COTIZACION')->first();
        $this->assertNotNull($evento);
        $this->assertSame('PENDIENTE', json_decode((string) $evento->valor_anterior, true)['estado']);
        $this->assertSame('RECHAZADA', json_decode((string) $evento->valor_nuevo, true)['estado']);
        $this->assertSame('El cliente ya compró en otro lado', json_decode((string) $evento->valor_nuevo, true)['observacion']);
    }

    public function test_se_puede_pasar_a_atendida_y_volver_a_pendiente(): void
    {
        $cotizacion = $this->cotizacion();
        $gestor = $this->gestor();

        $this->actingAs($gestor)->patch(route('cotizaciones.estado', $cotizacion), ['estado' => 'ATENDIDA', 'observacion' => 'Se le escribió por WhatsApp'])->assertSessionHasNoErrors();
        $this->assertSame(Cotizacion::ATENDIDA, $cotizacion->fresh()->estado);

        $this->actingAs($gestor)->patch(route('cotizaciones.estado', $cotizacion), ['estado' => 'PENDIENTE'])->assertSessionHasNoErrors();
        $this->assertSame(Cotizacion::PENDIENTE, $cotizacion->fresh()->estado);
        $this->assertSame('Se le escribió por WhatsApp', $cotizacion->fresh()->observacion, 'Sin observación nueva se conserva la anterior.');
    }

    /**
     * @return array<string, array{0: string, 1: array<string, string>}>
     */
    public static function cambiosNoPermitidos(): array
    {
        return [
            'a convertida' => [Cotizacion::PENDIENTE, ['estado' => 'CONVERTIDA']],
            'a vencida' => [Cotizacion::PENDIENTE, ['estado' => 'VENCIDA']],
            'estado desconocido' => [Cotizacion::PENDIENTE, ['estado' => 'ACEPTADA']],
            'al mismo estado' => [Cotizacion::PENDIENTE, ['estado' => 'PENDIENTE']],
            'desde convertida' => [Cotizacion::CONVERTIDA, ['estado' => 'ATENDIDA']],
            'desde vencida' => [Cotizacion::VENCIDA, ['estado' => 'ATENDIDA']],
        ];
    }

    /**
     * @param  array<string, string>  $datos
     */
    #[DataProvider('cambiosNoPermitidos')]
    public function test_los_cambios_de_estado_no_permitidos_se_rechazan(string $inicial, array $datos): void
    {
        $cotizacion = $this->cotizacion(estado: $inicial);

        $this->actingAs($this->gestor())
            ->patch(route('cotizaciones.estado', $cotizacion), $datos)
            ->assertSessionHasErrors('estado');

        $this->assertSame($inicial, $cotizacion->fresh()->estado);
    }

    public function test_una_cotizacion_con_la_fecha_pasada_no_se_reabre(): void
    {
        $cotizacion = $this->cotizacion(estado: Cotizacion::RECHAZADA, vence: '2026-10-01', observacion: 'No quiso');

        $this->actingAs($this->gestor())
            ->patch(route('cotizaciones.estado', $cotizacion), ['estado' => 'PENDIENTE'])
            ->assertSessionHasErrors('estado');

        $this->assertSame(Cotizacion::RECHAZADA, $cotizacion->fresh()->estado);
    }

    /* ── Conversión en venta ──────────────────────────────────────────────── */

    public function test_la_conversion_crea_la_venta_vinculada_descuenta_el_stock_y_deja_la_cotizacion_convertida(): void
    {
        $cotizacion = $this->cotizacion(cantidad: 2, precio: 100, stock: 10);
        $variante = $cotizacion->detalles->first()->variante;
        $gestor = $this->gestor();

        $this->actingAs($gestor)
            ->post(route('ventas.store'), $this->venta($cotizacion, cantidad: 2))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $venta = Venta::query()->firstOrFail();
        $this->assertSame($cotizacion->id, $venta->cotizacion_id);
        $this->assertSame($cotizacion->cliente_id, $venta->cliente_id);
        $this->assertSame(8, $variante->fresh()->stock_actual);

        $cotizacion->refresh();
        $this->assertSame(Cotizacion::CONVERTIDA, $cotizacion->estado);
        $this->assertSame($gestor->id, $cotizacion->actualizado_por);
        $this->assertNotNull($cotizacion->fecha_estado);

        $evento = DB::table('bitacora_auditoria')->where('accion', 'CONVERTIR_COTIZACION')->first();
        $this->assertNotNull($evento);
        $this->assertSame($cotizacion->id, (int) $evento->entidad_id);
        $this->assertSame($venta->numero_venta, json_decode((string) $evento->valor_nuevo, true)['venta']);
    }

    public function test_ex02_un_segundo_intento_o_una_rechazada_se_rechazan(): void
    {
        $cotizacion = $this->cotizacion(cantidad: 1, stock: 10);
        $gestor = $this->gestor();

        $this->actingAs($gestor)->post(route('ventas.store'), $this->venta($cotizacion))->assertSessionHasNoErrors();

        $this->actingAs($gestor)
            ->post(route('ventas.store'), $this->venta($cotizacion))
            ->assertSessionHasErrors(['cotizacion_id' => 'La cotización '.$cotizacion->codigo_cotizacion.' ya fue convertida en venta.']);

        $this->assertSame(1, Venta::query()->count());

        $rechazada = $this->cotizacion(estado: Cotizacion::RECHAZADA, cantidad: 1, stock: 10);

        $this->actingAs($gestor)
            ->post(route('ventas.store'), $this->venta($rechazada))
            ->assertSessionHasErrors('cotizacion_id');

        $this->assertSame(1, Venta::query()->count());
    }

    public function test_ex01_una_cotizacion_vencida_no_se_convierte(): void
    {
        $gestor = $this->gestor();
        $porEstado = $this->cotizacion(estado: Cotizacion::VENCIDA, cantidad: 1, stock: 10);
        $porFecha = $this->cotizacion(vence: '2026-10-07', cantidad: 1, stock: 10);

        $this->actingAs($gestor)->post(route('ventas.store'), $this->venta($porEstado))->assertSessionHasErrors('cotizacion_id');
        $this->actingAs($gestor)->post(route('ventas.store'), $this->venta($porFecha))->assertSessionHasErrors('cotizacion_id');

        $this->assertSame(0, Venta::query()->count());
        $this->assertSame(Cotizacion::PENDIENTE, $porFecha->fresh()->estado);
    }

    public function test_el_pos_recibe_la_cotizacion_con_precios_stock_y_vigencia(): void
    {
        $cotizacion = $this->cotizacion(cantidad: 3, precio: 100, stock: 2, cliente: 'Lucía Vaca');
        $cotizacion->detalles->first()->variante->update(['precio_venta' => 130]);

        $this->actingAs($this->gestor())
            ->get(route('ventas.index', ['cotizacion' => $cotizacion->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ventas')
                ->where('filtros.cotizacion', (string) $cotizacion->id)
                ->where('cotizacion.codigo', $cotizacion->codigo_cotizacion)
                ->where('cotizacion.puede_convertir', true)
                ->where('cotizacion.motivo', null)
                ->where('cotizacion.vence', '15/10/2026')
                ->where('cotizacion.cliente.nombre', 'Lucía Vaca')
                ->where('cotizacion.items.0.cantidad', 3)
                ->where('cotizacion.items.0.precio_cotizado', 100)
                ->where('cotizacion.items.0.precio_actual', 130)
                ->where('cotizacion.items.0.stock', 2)
                ->where('cotizacion.items.0.activa', true));

        $this->actingAs($this->gestor())
            ->get(route('ventas.index'))
            ->assertInertia(fn (Assert $page) => $page->where('cotizacion', null)->where('filtros.cotizacion', ''));
    }

    public function test_el_pos_explica_por_que_una_cotizacion_no_se_convierte(): void
    {
        $vencida = $this->cotizacion(vence: '2026-10-01');

        $this->actingAs($this->gestor())
            ->get(route('ventas.index', ['cotizacion' => $vencida->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cotizacion.puede_convertir', false)
                ->where('cotizacion.motivo', 'La cotización '.$vencida->codigo_cotizacion.' venció el 01/10/2026. Renuévala con precios actuales para venderla.'));
    }

    public function test_el_cliente_de_la_cotizacion_siempre_esta_en_la_lista_del_pos(): void
    {
        $cotizacion = $this->cotizacion(cliente: 'Zacarías Último');

        foreach (range(1, 305) as $i) {
            Cliente::query()->create([
                'tipo_documento' => 'CI',
                'numero_documento' => 'X'.$i,
                'nombre' => 'Cliente '.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'estado' => 'ACTIVO',
            ]);
        }

        $this->actingAs($this->gestor())
            ->get(route('ventas.index', ['cotizacion' => $cotizacion->id]))
            ->assertInertia(function (Assert $page) use ($cotizacion): void {
                $ids = array_column($page->toArray()['props']['clientes'], 'id');

                $this->assertContains($cotizacion->cliente_id, $ids);
            });
    }

    public function test_renovar_crea_una_cotizacion_nueva_con_precios_vigentes_y_deja_la_anterior_vencida(): void
    {
        $original = $this->cotizacion(estado: Cotizacion::VENCIDA, vence: '2026-10-01', cantidad: 2, precio: 100, stock: 10);
        $original->detalles->first()->variante->update(['precio_venta' => 150]);
        $gestor = $this->gestor();

        $this->actingAs($gestor)->post(route('cotizaciones.renovar', $original))->assertSessionHasNoErrors();

        $nueva = Cotizacion::query()->where('id', '<>', $original->id)->with('detalles')->firstOrFail();

        $this->assertSame(Cotizacion::PENDIENTE, $nueva->estado);
        $this->assertSame($original->cliente_id, $nueva->cliente_id);
        $this->assertSame($original->usuario_id, $nueva->usuario_id);
        $this->assertNotSame($original->codigo_cotizacion, $nueva->codigo_cotizacion);
        $this->assertSame('2026-10-15', $nueva->fecha_vencimiento?->toDateString());
        $this->assertSame(150.0, $nueva->detalles[0]->precio_unitario);
        $this->assertSame(2, $nueva->detalles[0]->cantidad);
        $this->assertSame(300.0, $nueva->total());

        $original->refresh();
        $this->assertSame(Cotizacion::VENCIDA, $original->estado);
        $this->assertStringContainsString('Renovada como '.$nueva->codigo_cotizacion, (string) $original->observacion);

        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'RENOVAR_COTIZACION',
            'entidad_id' => $nueva->id,
            'usuario_id' => $gestor->id,
        ]);

        $this->actingAs($gestor)
            ->post(route('ventas.store'), $this->venta($nueva, cantidad: 2, precio: 150))
            ->assertSessionHasNoErrors();
        $this->assertSame(Cotizacion::CONVERTIDA, $nueva->fresh()->estado);
    }

    public function test_renovar_una_cotizacion_con_la_fecha_pasada_aunque_siga_pendiente(): void
    {
        $original = $this->cotizacion(vence: '2026-10-05');

        $this->actingAs($this->gestor())->post(route('cotizaciones.renovar', $original))->assertSessionHasNoErrors();

        $this->assertSame(2, Cotizacion::query()->count());
        $this->assertSame(Cotizacion::VENCIDA, $original->fresh()->estado);
    }

    public function test_solo_se_renuevan_las_vencidas(): void
    {
        $vigente = $this->cotizacion();
        $convertida = $this->cotizacion(estado: Cotizacion::CONVERTIDA, vence: '2026-10-01');

        foreach ([$vigente, $convertida] as $cotizacion) {
            $this->actingAs($this->gestor())->post(route('cotizaciones.renovar', $cotizacion))->assertSessionHasErrors('estado');
        }

        $this->assertSame(2, Cotizacion::query()->count());
    }

    public function test_no_se_renueva_si_ningun_producto_sigue_disponible(): void
    {
        $original = $this->cotizacion(estado: Cotizacion::VENCIDA, vence: '2026-10-01');
        $original->detalles->first()->variante->update(['estado' => 'INACTIVO']);

        $this->actingAs($this->gestor())->post(route('cotizaciones.renovar', $original))->assertSessionHasErrors('estado');

        $this->assertSame(1, Cotizacion::query()->count());
    }

    /* ── Precio cotizado (EX-04) ──────────────────────────────────────────── */

    public function test_ex04_se_acepta_el_precio_cotizado_si_coincide_con_el_detalle(): void
    {
        $cotizacion = $this->cotizacion(cantidad: 2, precio: 100, stock: 10);
        $cotizacion->detalles->first()->variante->update(['precio_venta' => 130]);

        $this->actingAs($this->gestor())
            ->post(route('ventas.store'), $this->venta($cotizacion, cantidad: 2, precio: 100))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('detalle_venta', ['cantidad' => 2, 'precio_unitario' => 100]);
        $this->assertSame(200.0, Venta::query()->with('detalles')->firstOrFail()->total());
        $this->assertSame(Cotizacion::CONVERTIDA, $cotizacion->fresh()->estado);
    }

    public function test_ex04_sin_enviar_precio_se_cobra_el_precio_actual(): void
    {
        $cotizacion = $this->cotizacion(cantidad: 2, precio: 100, stock: 10);
        $cotizacion->detalles->first()->variante->update(['precio_venta' => 130]);

        $this->actingAs($this->gestor())
            ->post(route('ventas.store'), $this->venta($cotizacion, cantidad: 2, total: 260))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('detalle_venta', ['cantidad' => 2, 'precio_unitario' => 130]);
    }

    public function test_ex04_un_precio_manipulado_se_rechaza(): void
    {
        $cotizacion = $this->cotizacion(cantidad: 2, precio: 100, stock: 10);

        $this->actingAs($this->gestor())
            ->post(route('ventas.store'), $this->venta($cotizacion, cantidad: 2, precio: 1))
            ->assertSessionHasErrors('items.0.precio_unitario');

        $this->assertSame(0, Venta::query()->count());
        $this->assertSame(Cotizacion::PENDIENTE, $cotizacion->fresh()->estado);
    }

    public function test_ex04_un_precio_sin_cotizacion_se_rechaza(): void
    {
        $cotizacion = $this->cotizacion(cantidad: 2, precio: 100, stock: 10);
        $datos = $this->venta($cotizacion, cantidad: 2, precio: 100);
        unset($datos['cotizacion_id']);

        $this->actingAs($this->gestor())
            ->post(route('ventas.store'), $datos)
            ->assertSessionHasErrors('items.0.precio_unitario');

        $this->assertSame(0, Venta::query()->count());
    }

    public function test_ex04_el_precio_de_otra_variante_no_vale(): void
    {
        $cotizacion = $this->cotizacion(cantidad: 1, precio: 100, stock: 10);
        $otra = $this->variante('Teclado Redragon K552', precio: 500, stock: 10);

        $datos = $this->venta($cotizacion, cantidad: 1);
        $datos['items'][0] = ['variante_id' => $otra->id, 'cantidad' => 1, 'precio_unitario' => 100, 'descuento' => 0, 'numero_serie' => null, 'meses_garantia' => 3];

        $this->actingAs($this->gestor())
            ->post(route('ventas.store'), $datos)
            ->assertSessionHasErrors('items.0.precio_unitario');
    }

    /* ── Stock insuficiente (EX-03) ───────────────────────────────────────── */

    public function test_ex03_con_stock_insuficiente_el_error_indica_la_variante(): void
    {
        $cotizacion = $this->cotizacion(cantidad: 3, precio: 100, stock: 1);
        $sku = $cotizacion->detalles->first()->variante->sku;

        $this->actingAs($this->gestor())
            ->post(route('ventas.store'), $this->venta($cotizacion, cantidad: 3))
            ->assertSessionHasErrors(['items.0.cantidad' => 'Stock insuficiente para '.$sku.' (disponible: 1).']);

        $this->assertSame(0, Venta::query()->count());
        $this->assertSame(Cotizacion::PENDIENTE, $cotizacion->fresh()->estado);

        $this->actingAs($this->gestor())
            ->post(route('ventas.store'), $this->venta($cotizacion, cantidad: 1))
            ->assertSessionHasNoErrors();
        $this->assertSame(Cotizacion::CONVERTIDA, $cotizacion->fresh()->estado);
    }

    /* ── Más visitados ────────────────────────────────────────────────────── */

    public function test_el_ranking_de_mas_visitados_sigue_los_eventos_y_compara_con_las_ventas(): void
    {
        $gestor = $this->gestor();
        $mouse = $this->variante('Mouse Logitech G203', precio: 100, stock: 20);
        $teclado = $this->variante('Teclado Redragon K552', precio: 200, stock: 20);
        $parlante = $this->variante('Parlante JBL Go', precio: 300, stock: 20);

        $this->visitas($mouse, 5, '2026-10-05 10:00:00');
        $this->visitas($teclado, 3, '2026-10-06 10:00:00');
        $this->visitas($parlante, 1, '2026-10-07 10:00:00');
        $this->visitas($parlante, 9, '2026-08-01 10:00:00');
        EventoProducto::query()->create([
            'producto_id' => $mouse->producto_id,
            'tipo_evento' => 'CLIC_WHATSAPP',
            'fecha_hora' => '2026-10-05 11:00:00',
        ]);

        $this->ventaDe($mouse, 4, '2026-10-06 12:00:00');
        $this->ventaDe($mouse, 2, '2026-10-06 13:00:00', anulada: true);
        $this->ventaDe($teclado, 1, '2026-10-06 12:00:00');

        $this->actingAs($gestor)
            ->get(route('cotizaciones.mas-visitados', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('cotizaciones/Index')
                ->where('tab', 'visitados')
                ->where('cotizaciones', null)
                ->where('visitados.error', null)
                ->where('visitados.desde', '2026-10-01')
                ->where('visitados.hasta', '2026-10-31')
                ->where('visitados.resultado.vacio', false)
                ->has('visitados.resultado.filas', 3)
                ->where('visitados.resultado.filas.0.producto', 'Mouse Logitech G203')
                ->where('visitados.resultado.filas.0.visitas', 5)
                ->where('visitados.resultado.filas.0.unidades', 4)
                ->where('visitados.resultado.filas.1.producto', 'Teclado Redragon K552')
                ->where('visitados.resultado.filas.1.visitas', 3)
                ->where('visitados.resultado.filas.1.unidades', 1)
                ->where('visitados.resultado.filas.2.producto', 'Parlante JBL Go')
                ->where('visitados.resultado.filas.2.visitas', 1)
                ->where('visitados.resultado.filas.2.unidades', 0)
                ->where('visitados.resultado.totales.visitas', 9)
                ->where('visitados.resultado.grafico.tipo', 'barra')
                ->where('visitados.resultado.grafico.etiquetas.0', 'Mouse Logitech G203'));
    }

    public function test_ex05_un_periodo_sin_visitas_queda_vacio(): void
    {
        $this->visitas($this->variante('Mouse Logitech G203', precio: 100, stock: 5), 3, '2026-09-01 10:00:00');

        $this->actingAs($this->gestor())
            ->get(route('cotizaciones.mas-visitados', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visitados.resultado.vacio', true)
                ->where('visitados.resultado.filas', []));
    }

    public function test_sin_fechas_el_ranking_usa_los_ultimos_30_dias_y_un_rango_invertido_avisa(): void
    {
        $gestor = $this->gestor();

        $this->actingAs($gestor)
            ->get(route('cotizaciones.mas-visitados'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('visitados.desde', '2026-09-09')
                ->where('visitados.hasta', '2026-10-08')
                ->where('visitados.error', null));

        $this->actingAs($gestor)
            ->get(route('cotizaciones.mas-visitados', ['desde' => '2026-10-10', 'hasta' => '2026-10-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('visitados.error', 'La fecha de fin no puede ser anterior a la de inicio.')
                ->where('visitados.desde', '2026-09-09'));
    }

    /* ── Apoyo ────────────────────────────────────────────────────────────── */

    /**
     * @param  list<string>  $claves
     */
    private function usuarioCon(array $claves): User
    {
        return User::factory()->create([
            'rol_id' => $this->rolConPermisos('Rol '.implode('-', $claves).$this->secuencia++, $claves)->id,
            'primer_login' => false,
        ]);
    }

    private function gestor(): User
    {
        return $this->usuarioCon(['cotizaciones.gestionar', 'ventas.crear']);
    }

    private function variante(string $nombre, float $precio, int $stock): VarianteProducto
    {
        $categoriaId = DB::table('categoria')->insertGetId([
            'nombre' => 'Categoría '.uniqid(),
            'slug' => 'cat-'.uniqid(),
            'activo' => true,
        ]);

        $producto = Producto::query()->create([
            'categoria_id' => $categoriaId,
            'nombre' => $nombre,
            'slug' => 'prod-'.uniqid(),
            'estado' => 'ACTIVO',
        ]);

        return $producto->variantes()->create([
            'sku' => 'SKU-'.uniqid(),
            'precio_venta' => $precio,
            'stock_actual' => $stock,
            'stock_minimo' => 1,
            'estado' => 'ACTIVO',
        ]);
    }

    private function cotizacion(
        string $estado = Cotizacion::PENDIENTE,
        string $vence = '2026-10-15',
        string $emitida = '2026-10-08 09:00:00',
        string $cliente = 'Lucía Vaca Aguilar',
        ?string $telefono = null,
        int $cantidad = 1,
        float $precio = 100,
        int $stock = 10,
        ?string $observacion = null,
    ): Cotizacion {
        $n = ++$this->secuencia;

        $clienteModelo = Cliente::query()->create([
            'tipo_documento' => 'CI',
            'numero_documento' => 'DOC-'.$n,
            'nombre' => $cliente,
            'telefono' => $telefono,
            'email' => "cliente{$n}@correo.com",
            'estado' => 'ACTIVO',
        ]);

        $usuarioWeb = User::factory()->create([
            'rol_id' => $this->rolConPermisos('Web '.$n, [])->id,
            'cliente_id' => $clienteModelo->id,
            'personal_id' => null,
            'primer_login' => false,
        ]);

        $variante = $this->variante('Mouse Logitech G203', $precio, $stock);

        $cotizacion = Cotizacion::query()->create([
            'cliente_id' => $clienteModelo->id,
            'usuario_id' => $usuarioWeb->id,
            'codigo_cotizacion' => sprintf('COT-2026-%05d', $n),
            'fecha_emision' => $emitida,
            'fecha_vencimiento' => $vence,
            'estado' => $estado,
            'origen' => Cotizacion::ORIGEN_TIENDA_WEB,
            'observacion' => $observacion,
        ]);

        DetalleCotizacion::query()->create([
            'cotizacion_id' => $cotizacion->id,
            'variante_id' => $variante->id,
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'descuento' => 0,
            'descripcion' => 'Mouse Logitech G203',
        ]);

        return $cotizacion->load('detalles.variante');
    }

    /**
     * Cuerpo del POS para convertir una cotización. Sin `precio`, la línea no fija el precio.
     *
     * @return array<string, mixed>
     */
    private function venta(Cotizacion $cotizacion, int $cantidad = 1, ?float $precio = null, ?float $total = null): array
    {
        $linea = $cotizacion->detalles->first();
        $metodo = MetodoPago::query()->firstOrCreate(['codigo' => 'EFECTIVO'], ['nombre' => 'Efectivo', 'estado' => 'ACTIVO']);

        $item = [
            'variante_id' => $linea->variante_id,
            'cantidad' => $cantidad,
            'descuento' => 0,
            'numero_serie' => null,
            'meses_garantia' => 3,
        ];

        if ($precio !== null) {
            $item['precio_unitario'] = $precio;
        }

        return [
            'cotizacion_id' => $cotizacion->id,
            'cliente_id' => $cotizacion->cliente_id,
            'items' => [$item],
            'pagos' => [[
                'metodo_pago_id' => $metodo->id,
                'monto' => $total ?? $cantidad * ($precio ?? (float) $linea->precio_unitario),
                'referencia' => null,
            ]],
        ];
    }

    private function visitas(VarianteProducto $variante, int $cuantas, string $fecha): void
    {
        foreach (range(1, $cuantas) as $_) {
            EventoProducto::query()->create([
                'producto_id' => $variante->producto_id,
                'tipo_evento' => EventoProducto::VISTA,
                'fecha_hora' => $fecha,
            ]);
        }
    }

    private function ventaDe(VarianteProducto $variante, int $cantidad, string $fecha, bool $anulada = false): void
    {
        $cliente = Cliente::query()->first() ?? Cliente::query()->create(['nombre' => 'Cliente de prueba', 'estado' => 'ACTIVO']);
        $usuario = User::query()->firstOrFail();

        $ventaId = DB::table('venta')->insertGetId([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'numero_venta' => 'V-T-'.uniqid(),
            'fecha' => $fecha,
            'estado' => $anulada ? 'ANULADA' : 'COMPLETADA',
            'descuento_global' => 0,
        ]);

        DB::table('detalle_venta')->insert([
            'venta_id' => $ventaId,
            'variante_id' => $variante->id,
            'cantidad' => $cantidad,
            'precio_unitario' => $variante->precio_venta,
            'descuento' => 0,
            'nombre_producto_snapshot' => $variante->producto->nombre,
            'meses_garantia' => 3,
            'estado' => 'VIGENTE',
        ]);
    }
}
