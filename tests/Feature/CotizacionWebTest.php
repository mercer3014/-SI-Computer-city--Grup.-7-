<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ConfiguracionTienda;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Services\Cotizaciones\CotizacionWebService;
use App\Services\Tienda\ConfiguracionTiendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class CotizacionWebTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-10-08 10:00:00');
    }

    /* ── Acceso ───────────────────────────────────────────────────────────── */

    public function test_un_invitado_va_al_login(): void
    {
        $this->get(route('tienda.cotizar'))->assertRedirect(route('login'));
        $this->post(route('tienda.cotizaciones.store'), [])->assertRedirect(route('login'));
        $this->get(route('tienda.cotizaciones.index'))->assertRedirect(route('login'));
    }

    public function test_un_usuario_interno_es_redirigido_igual_que_en_el_checkout(): void
    {
        $vendedor = User::factory()->create([
            'rol_id' => $this->rolConPermisos('Vendedor', ['ventas.crear'])->id,
            'primer_login' => false,
        ]);

        $this->actingAs($vendedor)
            ->get(route('tienda.cotizar'))
            ->assertRedirect(route('tienda.carrito'))
            ->assertInertiaFlash('toast', [
                'type' => 'warning',
                'message' => 'Para cotizar necesitás una cuenta de cliente. Las cuentas del personal usan el POS.',
            ]);

        $this->actingAs($vendedor)
            ->get(route('tienda.checkout'))
            ->assertRedirect(route('tienda.carrito'))
            ->assertInertiaFlash('toast', [
                'type' => 'warning',
                'message' => 'Para comprar en línea necesitás una cuenta de cliente. Las cuentas del personal usan el POS.',
            ]);

        $this->actingAs($vendedor)->get(route('tienda.cotizaciones.index'))->assertRedirect(route('tienda.carrito'));
    }

    public function test_el_cliente_ve_sus_datos_de_contacto_prellenados(): void
    {
        $cliente = $this->clienteWeb();

        $this->actingAs($cliente)
            ->get(route('tienda.cotizar'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tienda/Cotizar')
                ->where('cliente.nombre', 'Lucía Vaca Aguilar')
                ->where('cliente.email', 'lucia@correo.com')
                ->where('cliente.numero_documento', '7788990')
                ->where('validezDias', 7));
    }

    /* ── Cotización válida ────────────────────────────────────────────────── */

    public function test_se_registra_la_cotizacion_con_el_precio_vigente_y_sin_tocar_el_stock(): void
    {
        $usuario = $this->clienteWeb();
        $mouse = $this->variante('Mouse Logitech G203', precio: 180, stock: 10);
        $teclado = $this->variante('Teclado Redragon K552', precio: 200, stock: 4);

        $respuesta = $this->actingAs($usuario)->post(route('tienda.cotizaciones.store'), $this->datos([
            'items' => [
                ['variante_id' => $mouse->id, 'cantidad' => 2, 'precio_visto' => 180],
                ['variante_id' => $teclado->id, 'cantidad' => 1, 'precio_visto' => 200],
            ],
            'nombre' => 'Lucía Vaca',
            'telefono' => '76543210',
            'email' => 'Lucia.Nueva@Correo.com',
            'tipo_documento' => 'NIT',
            'numero_documento' => '102030405',
        ]));

        $cotizacion = Cotizacion::query()->with('detalles')->firstOrFail();

        $respuesta->assertRedirect(route('tienda.cotizaciones.show', [$cotizacion->codigo_cotizacion, 'nueva' => 1]))
            ->assertInertiaFlash('toast', [
                ['type' => 'success', 'message' => 'Cotización COT-2026-00001 generada.'],
            ]);

        $this->assertSame('COT-2026-00001', $cotizacion->codigo_cotizacion);
        $this->assertSame(Cotizacion::PENDIENTE, $cotizacion->estado);
        $this->assertSame(Cotizacion::ORIGEN_TIENDA_WEB, $cotizacion->origen);
        $this->assertSame($usuario->id, $cotizacion->usuario_id);
        $this->assertSame($usuario->cliente_id, $cotizacion->cliente_id);
        $this->assertSame('2026-10-15', $cotizacion->fecha_vencimiento?->toDateString());
        $this->assertSame(560.0, $cotizacion->total());
        $this->assertCount(2, $cotizacion->detalles);
        $this->assertSame(180.0, $cotizacion->detalles[0]->precio_unitario);
        $this->assertSame(2, $cotizacion->detalles[0]->cantidad);
        $this->assertSame('Mouse Logitech G203', $cotizacion->detalles[0]->descripcion);

        $this->assertSame(10, $mouse->fresh()->stock_actual);
        $this->assertSame(4, $teclado->fresh()->stock_actual);

        $this->assertDatabaseHas('cliente', [
            'id' => $usuario->cliente_id,
            'nombre' => 'Lucía Vaca',
            'telefono' => '76543210',
            'email' => 'lucia.nueva@correo.com',
            'tipo_documento' => 'NIT',
            'numero_documento' => '102030405',
        ]);

        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'GENERAR_COTIZACION_WEB',
            'tipo_entidad' => 'cotizacion',
            'entidad_id' => $cotizacion->id,
            'usuario_id' => $usuario->id,
        ]);
    }

    public function test_los_codigos_son_correlativos_y_unicos(): void
    {
        $usuario = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203', precio: 100, stock: 10);

        foreach (range(1, 3) as $_) {
            $this->actingAs($usuario)->post(route('tienda.cotizaciones.store'), $this->datos([
                'items' => [['variante_id' => $variante->id, 'cantidad' => 1, 'precio_visto' => 100]],
            ]))->assertSessionHasNoErrors();
        }

        $this->assertSame(
            ['COT-2026-00001', 'COT-2026-00002', 'COT-2026-00003'],
            Cotizacion::query()->orderBy('id')->pluck('codigo_cotizacion')->all(),
        );
    }

    public function test_la_vigencia_sale_de_la_configuracion(): void
    {
        config(['tienda.dias_validez_cotizacion' => 3]);

        $usuario = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203', precio: 100, stock: 10);

        $this->actingAs($usuario)->post(route('tienda.cotizaciones.store'), $this->datos([
            'items' => [['variante_id' => $variante->id, 'cantidad' => 1, 'precio_visto' => 100]],
        ]));

        $this->assertSame('2026-10-11', Cotizacion::query()->firstOrFail()->fecha_vencimiento?->toDateString());
    }

    public function test_las_lineas_repetidas_de_una_variante_se_juntan(): void
    {
        $usuario = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203', precio: 100, stock: 10);

        $this->actingAs($usuario)->post(route('tienda.cotizaciones.store'), $this->datos([
            'items' => [
                ['variante_id' => $variante->id, 'cantidad' => 2, 'precio_visto' => 100],
                ['variante_id' => $variante->id, 'cantidad' => 3, 'precio_visto' => 100],
            ],
        ]))->assertSessionHasNoErrors();

        $cotizacion = Cotizacion::query()->with('detalles')->firstOrFail();

        $this->assertCount(1, $cotizacion->detalles);
        $this->assertSame(5, $cotizacion->detalles[0]->cantidad);
    }

    /* ── Excepciones ──────────────────────────────────────────────────────── */

    public function test_ex01_un_carrito_vacio_se_rechaza(): void
    {
        $usuario = $this->clienteWeb();

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos(['items' => []]))
            ->assertSessionHasErrors(['items' => 'Tu carrito está vacío.']);

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos(['items' => null]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Cotizacion::query()->count());
    }

    public function test_ex02_una_cantidad_mayor_al_stock_se_ajusta_y_se_avisa(): void
    {
        $usuario = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203', precio: 100, stock: 2);

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos([
                'items' => [['variante_id' => $variante->id, 'cantidad' => 5, 'precio_visto' => 100]],
            ]))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', [
                ['type' => 'success', 'message' => 'Cotización COT-2026-00001 generada.'],
                ['type' => 'warning', 'message' => 'Solo quedan 2 unidades de Mouse Logitech G203; ajustamos la cantidad.'],
            ]);

        $cotizacion = Cotizacion::query()->with('detalles')->firstOrFail();

        $this->assertSame(2, $cotizacion->detalles[0]->cantidad);
        $this->assertSame(200.0, $cotizacion->total());
        $this->assertSame(2, $variante->fresh()->stock_actual);
    }

    public function test_ex03_si_el_precio_cambio_no_se_registra_y_se_devuelven_los_precios_nuevos(): void
    {
        $usuario = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203', precio: 120, stock: 5);

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos([
                'items' => [['variante_id' => $variante->id, 'cantidad' => 1, 'precio_visto' => 100]],
            ]))
            ->assertSessionHasErrors('precios')
            ->assertInertiaFlash('precios_nuevos', [[
                'variante_id' => $variante->id,
                'producto' => 'Mouse Logitech G203',
                'anterior' => 100,
                'nuevo' => 120,
            ]]);

        $this->assertSame(0, Cotizacion::query()->count());
        $this->assertDatabaseMissing('bitacora_auditoria', ['accion' => 'GENERAR_COTIZACION_WEB']);

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos([
                'items' => [['variante_id' => $variante->id, 'cantidad' => 1, 'precio_visto' => 120]],
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(120.0, Cotizacion::query()->firstOrFail()->total());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function contactosInvalidos(): array
    {
        return [
            'teléfono inválido' => [['telefono' => '12ab'], 'telefono'],
            'teléfono muy largo' => [['telefono' => '1234567890123456'], 'telefono'],
            'sin nombre' => [['nombre' => ''], 'nombre'],
            'correo inválido' => [['email' => 'no-es-un-correo'], 'email'],
            'sin correo' => [['email' => ''], 'email'],
            'documento sin tipo' => [['tipo_documento' => '', 'numero_documento' => '123'], 'tipo_documento'],
            'tipo de documento desconocido' => [['tipo_documento' => 'DNI', 'numero_documento' => '123'], 'tipo_documento'],
        ];
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    #[DataProvider('contactosInvalidos')]
    public function test_ex04_los_datos_de_contacto_invalidos_no_se_registran(array $cambios, string $campo): void
    {
        $usuario = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203', precio: 100, stock: 5);

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos([
                'items' => [['variante_id' => $variante->id, 'cantidad' => 1, 'precio_visto' => 100]],
                ...$cambios,
            ]))
            ->assertSessionHasErrors($campo);

        $this->assertSame(0, Cotizacion::query()->count());
    }

    public function test_ex04_el_documento_de_otro_cliente_se_rechaza(): void
    {
        $usuario = $this->clienteWeb();
        $this->clienteWeb('5566778', 'otro@correo.com', 'Pedro Gómez');
        $variante = $this->variante('Mouse Logitech G203', precio: 100, stock: 5);

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos([
                'items' => [['variante_id' => $variante->id, 'cantidad' => 1, 'precio_visto' => 100]],
                'tipo_documento' => 'CI',
                'numero_documento' => '5566778',
            ]))
            ->assertSessionHasErrors('numero_documento');

        $this->assertSame(0, Cotizacion::query()->count());
    }

    public function test_ex04_el_propio_documento_se_puede_dejar_igual(): void
    {
        $usuario = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203', precio: 100, stock: 5);

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos([
                'items' => [['variante_id' => $variante->id, 'cantidad' => 1, 'precio_visto' => 100]],
                'tipo_documento' => 'CI',
                'numero_documento' => '7788990',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Cotizacion::query()->count());
    }

    public function test_ex05_sin_whatsapp_se_oculta_el_boton_y_se_muestra_el_telefono(): void
    {
        $this->configurarTienda(whatsapp: null, telefono: '33445566');

        $usuario = $this->clienteWeb();
        $cotizacion = $this->cotizar($usuario);

        $this->actingAs($usuario)
            ->get(route('tienda.cotizaciones.show', $cotizacion->codigo_cotizacion))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tienda/Cotizacion')
                ->where('whatsapp_url', null)
                ->where('telefono_tienda', '33445566'));
    }

    public function test_el_boton_de_whatsapp_arma_el_mensaje_con_el_codigo_los_productos_y_el_total(): void
    {
        $this->configurarTienda(whatsapp: '+591 76543210', telefono: '33445566');

        $usuario = $this->clienteWeb();
        $mouse = $this->variante('Mouse Logitech G203', precio: 180, stock: 10);
        $teclado = $this->variante('Teclado Redragon K552', precio: 250.5, stock: 10);
        $cotizacion = $this->cotizar($usuario, [
            ['variante_id' => $mouse->id, 'cantidad' => 2, 'precio_visto' => 180],
            ['variante_id' => $teclado->id, 'cantidad' => 1, 'precio_visto' => 250.5],
        ]);

        $url = null;

        $this->actingAs($usuario)
            ->get(route('tienda.cotizaciones.show', [$cotizacion->codigo_cotizacion, 'nueva' => 1]))
            ->assertInertia(function (Assert $page) use (&$url): void {
                $url = $page->toArray()['props']['whatsapp_url'];
            });

        $this->assertStringStartsWith('https://wa.me/59176543210?text=', (string) $url);

        $mensaje = rawurldecode((string) preg_replace('/^.*\?text=/', '', (string) $url));

        $this->assertSame(
            "Hola Computer City, quiero confirmar la cotización COT-2026-00001:\n"
            ."- 2 × Mouse Logitech G203 (Bs 180,00)\n"
            ."- 1 × Teclado Redragon K552 (Bs 250,50)\n"
            .'Total: Bs 610,50',
            $mensaje,
        );
    }

    /**
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function numerosDeWhatsapp(): array
    {
        return [
            'local' => ['76543210', '59176543210'],
            'con código y signo' => ['+591 76543210', '59176543210'],
            'con 00' => ['00591-765-43210', '59176543210'],
            'con guiones' => ['765-432-10', '59176543210'],
            'vacío' => ['', null],
            'nulo' => [null, null],
            'sin dígitos' => ['---', null],
        ];
    }

    #[DataProvider('numerosDeWhatsapp')]
    public function test_el_numero_de_whatsapp_se_normaliza_con_el_codigo_de_pais(?string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, app(CotizacionWebService::class)->normalizarWhatsapp($entrada));
    }

    public function test_ex06_una_variante_inactiva_se_quita_y_se_avisa(): void
    {
        $usuario = $this->clienteWeb();
        $activa = $this->variante('Mouse Logitech G203', precio: 100, stock: 5);
        $inactiva = $this->variante('Teclado Redragon K552', precio: 200, stock: 5);
        $inactiva->update(['estado' => 'INACTIVO']);

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos([
                'items' => [
                    ['variante_id' => $activa->id, 'cantidad' => 1, 'precio_visto' => 100],
                    ['variante_id' => $inactiva->id, 'cantidad' => 1, 'precio_visto' => 200],
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', [
                ['type' => 'success', 'message' => 'Cotización COT-2026-00001 generada.'],
                ['type' => 'warning', 'message' => 'Teclado Redragon K552 ya no está disponible y lo quitamos de tu cotización.'],
            ]);

        $cotizacion = Cotizacion::query()->with('detalles')->firstOrFail();

        $this->assertCount(1, $cotizacion->detalles);
        $this->assertSame($activa->id, $cotizacion->detalles[0]->variante_id);
    }

    public function test_ex06_si_no_queda_ningun_producto_no_se_registra(): void
    {
        $usuario = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203', precio: 100, stock: 5);
        $variante->producto->update(['estado' => 'INACTIVO']);

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos([
                'items' => [['variante_id' => $variante->id, 'cantidad' => 1, 'precio_visto' => 100]],
            ]))
            ->assertSessionHasErrors('items');

        $this->assertSame(0, Cotizacion::query()->count());
    }

    public function test_un_producto_sin_stock_se_quita_de_la_cotizacion(): void
    {
        $usuario = $this->clienteWeb();
        $conStock = $this->variante('Mouse Logitech G203', precio: 100, stock: 5);
        $agotada = $this->variante('Teclado Redragon K552', precio: 200, stock: 0);

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos([
                'items' => [
                    ['variante_id' => $conStock->id, 'cantidad' => 1, 'precio_visto' => 100],
                    ['variante_id' => $agotada->id, 'cantidad' => 1, 'precio_visto' => 200],
                ],
            ]))
            ->assertSessionHasNoErrors()
            ->assertInertiaFlash('toast', [
                ['type' => 'success', 'message' => 'Cotización COT-2026-00001 generada.'],
                ['type' => 'warning', 'message' => 'Teclado Redragon K552 se agotó y lo quitamos de tu cotización.'],
            ]);

        $this->assertCount(1, Cotizacion::query()->with('detalles')->firstOrFail()->detalles);
    }

    /* ── Mis cotizaciones, detalle y PDF ──────────────────────────────────── */

    public function test_cada_cliente_ve_solo_sus_cotizaciones(): void
    {
        $lucia = $this->clienteWeb();
        $pedro = $this->clienteWeb('5566778', 'otro@correo.com', 'Pedro Gómez');
        $propia = $this->cotizar($lucia);
        $ajena = $this->cotizar($pedro);

        $this->actingAs($lucia)
            ->get(route('tienda.cotizaciones.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tienda/MisCotizaciones')
                ->has('cotizaciones', 1)
                ->where('cotizaciones.0.codigo', $propia->codigo_cotizacion));

        $this->actingAs($lucia)->get(route('tienda.cotizaciones.show', $ajena->codigo_cotizacion))->assertNotFound();
        $this->actingAs($lucia)->get(route('tienda.cotizaciones.pdf', $ajena->codigo_cotizacion))->assertNotFound();
    }

    public function test_el_detalle_muestra_el_estado_el_vencimiento_y_los_items(): void
    {
        $usuario = $this->clienteWeb();
        $cotizacion = $this->cotizar($usuario);

        $this->actingAs($usuario)
            ->get(route('tienda.cotizaciones.show', [$cotizacion->codigo_cotizacion, 'nueva' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tienda/Cotizacion')
                ->where('nueva', true)
                ->where('cotizacion.codigo', 'COT-2026-00001')
                ->where('cotizacion.estado', 'PENDIENTE')
                ->where('cotizacion.vencimiento', '15/10/2026')
                ->where('cotizacion.vigente', true)
                ->where('cotizacion.total', 100)
                ->where('cotizacion.items.0.descripcion', 'Mouse Logitech G203')
                ->where('cotizacion.pdf', route('tienda.cotizaciones.pdf', 'COT-2026-00001')));
    }

    public function test_el_pdf_de_una_cotizacion_propia_se_descarga(): void
    {
        $usuario = $this->clienteWeb();
        $cotizacion = $this->cotizar($usuario);

        $respuesta = $this->actingAs($usuario)->get(route('tienda.cotizaciones.pdf', $cotizacion->codigo_cotizacion));

        $respuesta->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $respuesta->getContent());
        $this->assertStringContainsString(
            'cotizacion-COT-2026-00001.pdf',
            (string) $respuesta->headers->get('content-disposition'),
        );
    }

    public function test_el_pdf_lleva_la_leyenda_de_vigencia_y_los_datos_de_la_tienda(): void
    {
        $this->configurarTienda(whatsapp: '76543210', telefono: '33445566');

        $cotizacion = $this->cotizar($this->clienteWeb());
        $html = view('pdf.cotizacion', [
            'cotizacion' => $cotizacion->load(['cliente', 'detalles']),
            'total' => $cotizacion->total(),
            'zona' => config('app.timezone'),
            'tienda' => app(ConfiguracionTiendaService::class)->actual(),
            'logo' => null,
        ])->render();

        $this->assertStringContainsString('Precios válidos hasta 15/10/2026. No reserva stock.', $html);
        $this->assertStringContainsString('COT-2026-00001', $html);
        $this->assertStringContainsString('Computer City', $html);
        $this->assertStringContainsString('Lucía Vaca Aguilar', $html);
    }

    /* ── Apoyo ────────────────────────────────────────────────────────────── */

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'items' => [],
            'nombre' => 'Lucía Vaca Aguilar',
            'telefono' => '76543210',
            'email' => 'lucia@correo.com',
            'tipo_documento' => '',
            'numero_documento' => '',
            ...$cambios,
        ];
    }

    /**
     * @param  list<array{variante_id: int, cantidad: int, precio_visto: float|int}>|null  $items
     */
    private function cotizar(User $usuario, ?array $items = null): Cotizacion
    {
        $items ??= [['variante_id' => $this->variante('Mouse Logitech G203', precio: 100, stock: 10)->id, 'cantidad' => 1, 'precio_visto' => 100]];

        $this->actingAs($usuario)
            ->post(route('tienda.cotizaciones.store'), $this->datos(['items' => $items]))
            ->assertSessionHasNoErrors();

        return Cotizacion::query()->where('usuario_id', $usuario->id)->orderByDesc('id')->firstOrFail();
    }

    private function configurarTienda(?string $whatsapp, ?string $telefono): void
    {
        $servicio = app(ConfiguracionTiendaService::class);
        $configuracion = $servicio->asegurar();
        $configuracion->forceFill(['whatsapp' => $whatsapp, 'telefono' => $telefono])->save();
        $servicio->olvidar();

        $this->assertSame(1, ConfiguracionTienda::query()->count());
    }

    private function clienteWeb(string $documento = '7788990', string $email = 'lucia@correo.com', string $nombre = 'Lucía Vaca Aguilar'): User
    {
        $cliente = Cliente::query()->create([
            'tipo_documento' => 'CI',
            'numero_documento' => $documento,
            'nombre' => $nombre,
            'email' => $email,
            'estado' => 'ACTIVO',
        ]);

        return User::factory()->create([
            'rol_id' => Rol::query()->where('nombre', Rol::CLIENTE_WEB)->value('id'),
            'cliente_id' => $cliente->id,
            'personal_id' => null,
            'email' => $email,
            'primer_login' => false,
        ]);
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
}
