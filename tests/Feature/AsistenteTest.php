<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ConversacionAsistente;
use App\Models\MensajeAsistente;
use App\Models\PlantillaReporte;
use App\Models\Proveedor;
use App\Models\Rol;
use App\Models\User;
use App\Services\Asistente\AsistenteNoDisponible;
use App\Services\Asistente\GrokServicioIa;
use App\Services\Asistente\RespuestaIa;
use App\Services\Asistente\ServicioIa;
use App\Services\Asistente\ServicioIaSimulado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaAdministrador;
use Tests\Concerns\CreaEscenarioDeReportes;
use Tests\Fakes\ServicioIaFalso;
use Tests\TestCase;

class AsistenteTest extends TestCase
{
    use CreaAdministrador;
    use CreaEscenarioDeReportes;
    use RefreshDatabase;

    private const CLAVE_DE_PRUEBA = 'xai-clave-secreta-de-prueba-123';

    private ServicioIaFalso $ia;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        $this->travelTo('2026-10-08 18:00:00');

        $this->ia = new ServicioIaFalso;
        $this->app->instance(ServicioIa::class, $this->ia);
    }

    /* ── Permisos (EX-01) ─────────────────────────────────────────────────── */

    public function test_el_invitado_va_al_login(): void
    {
        $this->get(route('asistente.index'))->assertRedirect(route('login'));
        $this->postJson(route('asistente.enviar'), ['contenido' => 'hola', 'origen' => 'TEXTO'])->assertUnauthorized();
    }

    public function test_el_vendedor_recibe_403_en_todas_las_rutas_incluso_con_el_permiso_asignado_a_mano(): void
    {
        $rol = $this->rolConPermisos('Vendedor', ['ventas.crear', 'cotizaciones.gestionar', 'asistente.usar']);
        $vendedor = User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);

        $this->assertFalse($vendedor->puedeUsarAsistente());

        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::reporte($this->especificacion(['dimensiones' => ['categoria']])));
        $mensajeId = (int) $this->enviar('Ventas por categoría')->json('mensaje_asistente.id');
        $conversacionId = (int) MensajeAsistente::query()->findOrFail($mensajeId)->conversacion_id;
        $plantilla = PlantillaReporte::query()->create([
            'usuario_id' => $this->usuarioAsistente()->id,
            'nombre' => 'Existente',
            'especificacion' => $this->especificacion(),
        ]);

        $this->actingAs($vendedor)->get(route('asistente.index'))->assertForbidden();
        $this->actingAs($vendedor)->get(route('asistente.conversaciones.mostrar', $conversacionId))->assertForbidden();
        $this->actingAs($vendedor)->postJson(route('asistente.enviar'), ['contenido' => 'hola', 'origen' => 'TEXTO'])->assertForbidden();
        $this->actingAs($vendedor)->postJson(route('asistente.conversaciones.nueva'))->assertForbidden();
        $this->actingAs($vendedor)->getJson(route('asistente.exportar', [$mensajeId, 'formato' => 'pdf']))->assertForbidden();
        $this->actingAs($vendedor)->postJson(route('asistente.plantillas.guardar'), ['mensaje_id' => $mensajeId, 'nombre' => 'Una'])->assertForbidden();
        $this->actingAs($vendedor)->postJson(route('asistente.plantillas.ejecutar', $plantilla))->assertForbidden();
        $this->actingAs($vendedor)->deleteJson(route('asistente.plantillas.eliminar', $plantilla))->assertForbidden();

        $this->assertCount(1, $this->ia->llamadas);
        $this->assertSame(1, PlantillaReporte::query()->count());
    }

    public function test_un_administrador_sin_el_permiso_tampoco_entra(): void
    {
        $rol = $this->rolConPermisos('Administrador', ['reportes.ver']);
        $admin = User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);

        $this->actingAs($admin)->get(route('asistente.index'))->assertForbidden();
    }

    public function test_el_administrador_abre_el_asistente_con_los_ejemplos(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('asistente.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('asistente/Index')
                ->where('conversacion', null)
                ->where('mensajes', [])
                ->where('conversaciones', [])
                ->where('plantillas', [])
                ->has('ejemplos', 3)
                ->where('ejemplos.0', '¿Cuánto vendimos esta semana?'));
    }

    /* ── Flujo principal ──────────────────────────────────────────────────── */

    public function test_una_consulta_puntual_se_calcula_en_el_sistema_y_deja_bitacora(): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::consulta($this->especificacion([
            'periodo' => ['desde' => '2026-10-05', 'hasta' => '2026-10-11'],
        ])));

        $respuesta = $this->enviar('¿Cuánto vendimos esta semana?')->assertOk();

        $respuesta->assertJsonPath('mensaje_asistente.tipo', 'CONSULTA')
            ->assertJsonPath('mensaje_asistente.rol', 'ASISTENTE')
            ->assertJsonPath('mensaje_usuario.contenido', '¿Cuánto vendimos esta semana?')
            ->assertJsonPath('mensaje_asistente.resultado', null);

        $texto = (string) $respuesta->json('mensaje_asistente.contenido');
        $this->assertStringContainsString('Bs 800,00', $texto);
        $this->assertStringContainsString('05/10/2026 – 11/10/2026', $texto);
        $this->assertSame('/ventas?desde=2026-10-05&hasta=2026-10-11', $respuesta->json('mensaje_asistente.enlace'));

        $this->assertSame(1, ConversacionAsistente::query()->count());
        $this->assertSame(2, MensajeAsistente::query()->count());

        $evento = DB::table('bitacora_auditoria')->where('accion', 'CONSULTA_ASISTENTE')->first();
        $this->assertNotNull($evento);
        $this->assertSame('Asistente', $evento->modulo);

        $nuevo = json_decode((string) $evento->valor_nuevo, true);
        $this->assertSame('¿Cuánto vendimos esta semana?', $nuevo['consulta']);
        $this->assertSame('ventas_monto', $nuevo['especificacion']['metrica']);
        $this->assertArrayNotHasKey('respuesta', $nuevo);
    }

    public function test_el_reporte_dinamico_de_teclados_redragon_por_semana_filtra_y_agrupa(): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::reporte($this->especificacion([
            'dimensiones' => ['semana'],
            'filtros' => ['categoria' => 'Teclados', 'marca' => 'Redragon'],
        ])));

        $respuesta = $this->enviar('Ventas de teclados Redragon por semana en octubre')->assertOk();

        $respuesta->assertJsonPath('mensaje_asistente.tipo', 'REPORTE')
            ->assertJsonPath('mensaje_asistente.resultado.vacio', false)
            ->assertJsonPath('mensaje_asistente.resultado.grafico.tipo', 'linea');

        $this->assertSame(
            'Ventas en Bs por semana, categoría Teclados, marca Redragon, 01/10/2026 – 31/10/2026',
            $respuesta->json('mensaje_asistente.interpretacion'),
        );

        $filas = $respuesta->json('mensaje_asistente.resultado.filas');
        $this->assertSame(['Sem. 28/09', 'Sem. 05/10', 'Sem. 12/10', 'Sem. 19/10', 'Sem. 26/10'], array_column($filas, 'dim1'));
        $this->assertEquals([0, 200, 0, 0, 0], array_column($filas, 'valor'));
        $this->assertEquals(200, $respuesta->json('mensaje_asistente.resultado.totales.valor'));
    }

    public function test_el_refinamiento_envia_el_historial_y_compara_con_otro_periodo(): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(
            ServicioIaFalso::reporte($this->especificacion(['dimensiones' => ['semana']])),
            ServicioIaFalso::reporte($this->especificacion([
                'dimensiones' => ['semana'],
                'comparar_con' => ['desde' => '2026-09-01', 'hasta' => '2026-09-30'],
            ])),
        );

        $primera = $this->enviar('Ventas por semana en octubre')->assertOk();
        $conversacionId = (int) $primera->json('conversacion_id');

        $segunda = $this->enviar('ahora compáralo con septiembre', $conversacionId)->assertOk();

        $this->assertSame($conversacionId, (int) $segunda->json('conversacion_id'));
        $this->assertSame(1, ConversacionAsistente::query()->count());
        $this->assertSame([], $this->ia->llamadas[0]['historial']);

        $historial = $this->ia->llamadas[1]['historial'];
        $this->assertCount(2, $historial);
        $this->assertSame(['role' => 'user', 'content' => 'Ventas por semana en octubre'], $historial[0]);
        $this->assertSame('assistant', $historial[1]['role']);

        $this->assertSame(1, preg_match('/\[Especificación aplicada: (\{.*\})\]$/s', $historial[1]['content'], $coincidencia));
        $aplicada = json_decode($coincidencia[1], true);
        $this->assertSame('ventas_monto', $aplicada['metrica']);
        $this->assertSame(['semana'], $aplicada['dimensiones']);
        $this->assertEquals(['desde' => '2026-10-01', 'hasta' => '2026-10-31'], $aplicada['periodo']);

        $this->assertSame(
            ['dim1', 'actual', 'comparacion', 'variacion'],
            array_column($segunda->json('mensaje_asistente.resultado.columnas'), 'clave'),
        );
        $this->assertStringContainsString('comparado con 01/09/2026 – 30/09/2026', (string) $segunda->json('mensaje_asistente.interpretacion'));
    }

    public function test_la_consulta_por_categoria_se_ordena_y_respeta_el_limite(): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::reporte($this->especificacion([
            'metrica' => 'unidades_vendidas',
            'dimensiones' => ['producto'],
            'limite' => 2,
            'grafico' => 'barra',
        ])));

        $filas = $this->enviar('Unidades por producto')->json('mensaje_asistente.resultado.filas');

        $this->assertSame(['Mouse Logitech G203', 'Hub USB'], array_column($filas, 'dim1'));
        $this->assertSame([5, 2], array_column($filas, 'valor'));
    }

    /* ── Especificaciones rechazadas (EX-04) ──────────────────────────────── */

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function especificacionesInvalidas(): array
    {
        $periodo = ['desde' => '2026-10-01', 'hasta' => '2026-10-31'];

        return [
            'métrica desconocida' => [['metrica' => 'usuarios', 'dimensiones' => [], 'periodo' => $periodo]],
            'dimensión no permitida' => [['metrica' => 'ventas_monto', 'dimensiones' => ['categoria_gasto'], 'periodo' => $periodo]],
            'filtro extra' => [['metrica' => 'ventas_monto', 'dimensiones' => [], 'filtros' => ['sql' => 'DROP TABLE venta'], 'periodo' => $periodo]],
            'clave extra' => [['metrica' => 'ventas_monto', 'dimensiones' => [], 'sql' => 'DROP TABLE venta', 'periodo' => $periodo]],
            'más de 366 días' => [['metrica' => 'ventas_monto', 'dimensiones' => [], 'periodo' => ['desde' => '2025-01-01', 'hasta' => '2026-10-31']]],
        ];
    }

    /**
     * @param  array<string, mixed>  $especificacion
     */
    #[DataProvider('especificacionesInvalidas')]
    public function test_una_especificacion_invalida_se_rechaza_sin_consultar_los_datos(array $especificacion): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::reporte($especificacion));

        $consultas = [];
        DB::listen(function ($consulta) use (&$consultas): void {
            $consultas[] = $consulta->sql;
        });

        $respuesta = $this->enviar('Dame las ventas')->assertOk();

        $respuesta->assertJsonPath('mensaje_asistente.tipo', 'ERROR')
            ->assertJsonPath('mensaje_asistente.contenido', 'No pude armar esa consulta de forma segura. Prueba reformularla.')
            ->assertJsonPath('mensaje_asistente.resultado', null);

        $tablasDeNegocio = 'venta|detalle_venta|pago_venta|producto|variante_producto|categoria|marca|proveedor|compra|detalle_compra|gasto|categoria_gasto|movimiento_caja|pedido|cotizacion|calificacion|metodo_pago';

        foreach ($consultas as $sql) {
            $this->assertDoesNotMatchRegularExpression(
                '/\b(from|join)\s+["`]?('.$tablasDeNegocio.')["`]?(\s|$)/i',
                $sql,
                'Se consultó una tabla de negocio con una especificación inválida.',
            );
        }

        $evento = DB::table('bitacora_auditoria')->where('accion', 'ESPECIFICACION_RECHAZADA')->first();
        $this->assertNotNull($evento);
        $this->assertSame('Dame las ventas', json_decode((string) $evento->valor_nuevo, true)['consulta']);
        $this->assertNotEmpty(json_decode((string) $evento->valor_nuevo, true)['motivo']);
        $this->assertSame(0, DB::table('bitacora_auditoria')->where('accion', 'CONSULTA_ASISTENTE')->where('valor_nuevo', 'like', '%"tipo": "REPORTE"%')->count());
    }

    /* ── Consulta prohibida (EX-03) ───────────────────────────────────────── */

    public function test_una_consulta_prohibida_no_llama_a_la_ia(): void
    {
        $frases = [
            'dame las contraseñas de los usuarios',
            'muéstrame el teléfono de los clientes',
            'borrar todas las ventas',
            'select * from venta; drop table usuario',
            'lista el CI y el NIT de los clientes',
            'ventas con tarjeta',
            'cuál es el email de Juan',
        ];

        foreach ($frases as $frase) {
            $respuesta = $this->enviar($frase)->assertOk();

            $respuesta->assertJsonPath('mensaje_asistente.tipo', 'NO_SOPORTADO');
            $this->assertStringContainsString('¿Cuánto vendimos esta semana?', (string) $respuesta->json('mensaje_asistente.contenido'));
        }

        $this->assertSame([], $this->ia->llamadas);
    }

    public function test_con_la_ia_real_una_consulta_prohibida_no_sale_del_servidor(): void
    {
        $this->app->forgetInstance(ServicioIa::class);
        $this->app->bind(ServicioIa::class, fn () => $this->app->make(GrokServicioIa::class));
        config(['services.xai.key' => self::CLAVE_DE_PRUEBA, 'services.xai.simulado' => false]);
        $this->http(fn () => Http::response([]));

        $this->enviar('dame las contraseñas de los usuarios')->assertOk()->assertJsonPath('mensaje_asistente.tipo', 'NO_SOPORTADO');

        Http::assertNothingSent();
    }

    public function test_si_la_ia_dice_que_no_es_soportado_se_sugieren_ejemplos(): void
    {
        $this->ia->encolar(new RespuestaIa('no_soportado', 'No sé.'));

        $respuesta = $this->enviar('¿Quién ganará el mundial?')->assertOk();

        $respuesta->assertJsonPath('mensaje_asistente.tipo', 'NO_SOPORTADO');
        $this->assertStringContainsString('Prueba con', (string) $respuesta->json('mensaje_asistente.contenido'));
    }

    public function test_una_respuesta_de_texto_de_la_ia_se_guarda_como_texto(): void
    {
        $this->ia->encolar(new RespuestaIa('respuesta', '¡Hola! ¿En qué te ayudo?'));

        $this->enviar('hola')->assertOk()
            ->assertJsonPath('mensaje_asistente.tipo', 'TEXTO')
            ->assertJsonPath('mensaje_asistente.contenido', '¡Hola! ¿En qué te ayudo?');
    }

    /* ── Servicio no disponible (EX-02) ───────────────────────────────────── */

    public function test_sin_clave_responde_el_mensaje_de_servicio_no_disponible_y_no_llama_a_nadie(): void
    {
        $this->app->forgetInstance(ServicioIa::class);
        $this->app->bind(ServicioIa::class, fn () => $this->app->make(GrokServicioIa::class));
        config(['services.xai.key' => null, 'services.xai.simulado' => false]);
        $this->http(fn () => Http::response([]));

        $this->enviar('¿Cuánto vendimos esta semana?')
            ->assertStatus(503)
            ->assertJsonPath('message', 'El asistente no está disponible en este momento.')
            ->assertJsonPath('ofrecer_reportes', true)
            ->assertJsonPath('enlace', '/reportes')
            ->assertJsonPath('mensaje_asistente.tipo', 'ERROR');

        Http::assertNothingSent();
        $this->assertSame(2, MensajeAsistente::query()->count());
    }

    public function test_un_timeout_o_un_error_de_xai_responde_lo_mismo_sin_500(): void
    {
        $this->app->forgetInstance(ServicioIa::class);
        $this->app->bind(ServicioIa::class, fn () => $this->app->make(GrokServicioIa::class));
        config(['services.xai.key' => self::CLAVE_DE_PRUEBA, 'services.xai.simulado' => false]);

        $this->http(fn () => throw new ConnectionException('cURL error 28: timeout'));
        $this->enviar('hola')->assertStatus(503)->assertJsonPath('message', 'El asistente no está disponible en este momento.');

        $this->http(fn () => Http::response(['error' => 'boom'], 500));
        $this->enviar('hola')->assertStatus(503)->assertJsonPath('message', 'El asistente no está disponible en este momento.');

        $this->http(fn () => Http::response(['choices' => [['message' => ['content' => 'esto no es JSON']]]]));
        $this->enviar('hola')->assertStatus(503)->assertJsonPath('message', 'El asistente no está disponible en este momento.');

        $this->http(fn () => Http::response(['choices' => [['message' => ['content' => json_encode(['tipo' => 'ejecutar_sql', 'mensaje' => 'x'])]]]]));
        $this->enviar('hola')->assertStatus(503);
    }

    public function test_el_servicio_puede_lanzar_el_error_y_la_pantalla_lo_cuenta(): void
    {
        $this->ia->encolar(new AsistenteNoDisponible('sin conexión'));

        $this->enviar('hola')->assertStatus(503)->assertJsonPath('message', 'El asistente no está disponible en este momento.');
    }

    /* ── La petición a xAI ────────────────────────────────────────────────── */

    public function test_la_peticion_a_xai_lleva_la_clave_el_endpoint_y_ningun_dato_personal(): void
    {
        $this->escenarioDeReportes();
        Cliente::query()->update([
            'email' => 'juan.perez.secreto@correo.test',
            'telefono' => '76543210',
            'direccion' => 'Calle Secreta 123',
        ]);
        Proveedor::query()->where('razon_social', 'Prov A')->update([
            'nit' => '9988776655',
            'email' => 'ventas@prov-a-secreto.test',
            'telefono' => '70001112',
            'contacto' => 'Contacto Secreto',
        ]);

        $this->app->forgetInstance(ServicioIa::class);
        $this->app->bind(ServicioIa::class, fn () => $this->app->make(GrokServicioIa::class));
        config([
            'services.xai.key' => self::CLAVE_DE_PRUEBA,
            'services.xai.model' => 'grok-de-prueba',
            'services.xai.base_url' => 'https://api.x.ai/v1',
            'services.xai.simulado' => false,
        ]);

        $this->http(['https://api.x.ai/v1/chat/completions' => Http::response([
            'choices' => [['message' => ['content' => json_encode(['tipo' => 'respuesta', 'mensaje' => 'Hola'])]]],
        ])]);

        $respuesta = $this->enviar('hola')->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $peticion): bool {
            $cuerpo = $peticion->body();

            $this->assertSame('https://api.x.ai/v1/chat/completions', $peticion->url());
            $this->assertSame('Bearer '.self::CLAVE_DE_PRUEBA, $peticion->header('Authorization')[0]);
            $this->assertSame('grok-de-prueba', $peticion->data()['model']);
            $this->assertSame('json_schema', $peticion->data()['response_format']['type']);
            $this->assertSame('system', $peticion->data()['messages'][0]['role']);
            $this->assertSame('hola', end($peticion->data()['messages'])['content']);

            foreach (['juan.perez.secreto', '76543210', 'Calle Secreta', '4521230', 'prov-a-secreto', '70001112', 'Contacto Secreto', '9988776655'] as $secreto) {
                $this->assertStringNotContainsString($secreto, $cuerpo, "La petición a xAI contiene «{$secreto}».");
            }

            $this->assertStringContainsString('2026-10-08', $cuerpo);
            $this->assertStringContainsString('Redragon', $cuerpo);
            $this->assertStringContainsString('Prov A', $cuerpo);
            $this->assertStringNotContainsString(self::CLAVE_DE_PRUEBA, $cuerpo);

            return true;
        });

        $this->assertStringNotContainsString(self::CLAVE_DE_PRUEBA, (string) $respuesta->getContent());

        $log = storage_path('logs/laravel.log');

        if (is_file($log)) {
            $this->assertStringNotContainsString(self::CLAVE_DE_PRUEBA, (string) file_get_contents($log));
        }
    }

    /* ── Resultado vacío (EX-05) y exportación ────────────────────────────── */

    public function test_un_resultado_vacio_lo_dice_y_no_se_puede_exportar(): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::reporte($this->especificacion([
            'dimensiones' => ['semana'],
            'periodo' => ['desde' => '2026-08-01', 'hasta' => '2026-08-31'],
        ])));

        $respuesta = $this->enviar('Ventas por semana en agosto')->assertOk();

        $respuesta->assertJsonPath('mensaje_asistente.tipo', 'REPORTE')
            ->assertJsonPath('mensaje_asistente.contenido', 'No existen registros para la consulta.')
            ->assertJsonPath('mensaje_asistente.resultado.vacio', true);

        $mensajeId = (int) $respuesta->json('mensaje_asistente.id');

        $this->actingAs($this->usuarioAsistente())
            ->get(route('asistente.exportar', [$mensajeId, 'formato' => 'pdf']))
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'No existen registros para la consulta.']);

        $this->assertDatabaseMissing('bitacora_auditoria', ['accion' => 'EXPORTAR_REPORTE_DINAMICO']);
    }

    public function test_un_reporte_se_exporta_en_pdf_excel_y_word_y_queda_en_la_bitacora(): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::reporte($this->especificacion(['dimensiones' => ['categoria']])));

        $mensajeId = (int) $this->enviar('Ventas por categoría en octubre')->json('mensaje_asistente.id');
        $admin = $this->usuarioAsistente();

        $tipos = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        foreach ($tipos as $formato => $mime) {
            $respuesta = $this->actingAs($admin)
                ->get(route('asistente.exportar', [$mensajeId, 'formato' => $formato]))
                ->assertOk()
                ->assertHeader('Content-Type', $mime);

            $this->assertStringContainsString(
                'reporte-asistente_ventas_monto-2026-10-01_2026-10-31.'.$formato,
                (string) $respuesta->headers->get('Content-Disposition'),
            );
        }

        $this->assertSame(3, DB::table('bitacora_auditoria')->where('accion', 'EXPORTAR_REPORTE_DINAMICO')->count());

        $this->actingAs($admin)->get(route('asistente.exportar', [$mensajeId, 'formato' => 'pptx']))->assertSessionHasErrors('formato');
    }

    public function test_la_exportacion_usa_la_especificacion_guardada_y_la_vuelve_a_validar(): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::reporte($this->especificacion(['dimensiones' => ['categoria']])));

        $mensaje = MensajeAsistente::query()->findOrFail((int) $this->enviar('Ventas por categoría')->json('mensaje_asistente.id'));
        $admin = $this->usuarioAsistente();

        $respuesta = $this->actingAs($admin)
            ->get(route('asistente.exportar', [$mensaje->id, 'formato' => 'xlsx', 'metrica' => 'gastos', 'desde' => '2000-01-01', 'dimensiones' => ['marca']]))
            ->assertOk();
        $this->assertStringContainsString('ventas_monto', (string) $respuesta->headers->get('Content-Disposition'));

        $mensaje->forceFill(['especificacion' => ['metrica' => 'usuarios', 'dimensiones' => [], 'periodo' => ['desde' => '2026-10-01', 'hasta' => '2026-10-31']]])->save();

        $this->actingAs($admin)
            ->get(route('asistente.exportar', [$mensaje->id, 'formato' => 'xlsx']))
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'No pude armar esa consulta de forma segura. Prueba reformularla.']);
    }

    public function test_no_se_puede_exportar_ni_ver_lo_de_otro_administrador(): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::reporte($this->especificacion(['dimensiones' => ['categoria']])));

        $dueno = $this->usuarioAsistente();
        $mensajeId = (int) $this->enviar('Ventas por categoría')->json('mensaje_asistente.id');
        $conversacionId = (int) MensajeAsistente::query()->findOrFail($mensajeId)->conversacion_id;
        $otro = $this->administrador();

        $this->actingAs($otro)->get(route('asistente.exportar', [$mensajeId, 'formato' => 'pdf']))->assertForbidden();
        $this->actingAs($otro)->get(route('asistente.conversaciones.mostrar', $conversacionId))->assertForbidden();
        $this->actingAs($otro)->postJson(route('asistente.enviar'), ['conversacion_id' => $conversacionId, 'contenido' => 'hola', 'origen' => 'TEXTO'])->assertNotFound();
        $this->actingAs($otro)->postJson(route('asistente.plantillas.guardar'), ['mensaje_id' => $mensajeId, 'nombre' => 'Robada'])->assertForbidden();

        $this->actingAs($dueno)->get(route('asistente.conversaciones.mostrar', $conversacionId))->assertOk();
    }

    /* ── Plantillas y conversaciones ──────────────────────────────────────── */

    public function test_una_plantilla_se_guarda_se_ejecuta_y_se_elimina(): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::reporte($this->especificacion(['dimensiones' => ['categoria']])));

        $admin = $this->usuarioAsistente();
        $mensajeId = (int) $this->enviar('Ventas por categoría')->json('mensaje_asistente.id');

        $this->actingAs($admin)
            ->postJson(route('asistente.plantillas.guardar'), ['mensaje_id' => $mensajeId, 'nombre' => 'Ventas por categoría del mes'])
            ->assertCreated()
            ->assertJsonPath('nombre', 'Ventas por categoría del mes');

        $plantilla = PlantillaReporte::query()->firstOrFail();
        $this->assertSame('ventas_monto', $plantilla->especificacion['metrica']);
        $this->assertDatabaseHas('bitacora_auditoria', ['accion' => 'GUARDAR_PLANTILLA_REPORTE']);

        $this->actingAs($admin)
            ->postJson(route('asistente.plantillas.guardar'), ['mensaje_id' => $mensajeId, 'nombre' => 'Ventas por categoría del mes'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre' => 'Ya tienes una plantilla con ese nombre.']);

        $this->actingAs($admin)
            ->postJson(route('asistente.plantillas.guardar'), ['mensaje_id' => $mensajeId, 'nombre' => 'ab'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nombre');

        $ejecucion = $this->actingAs($admin)
            ->postJson(route('asistente.plantillas.ejecutar', $plantilla))
            ->assertOk()
            ->assertJsonPath('mensaje_asistente.tipo', 'REPORTE')
            ->assertJsonPath('mensaje_usuario.contenido', 'Ejecutar la plantilla «Ventas por categoría del mes»');
        $this->assertFalse($ejecucion->json('mensaje_asistente.resultado.vacio'));

        $otro = $this->administrador();
        $this->actingAs($otro)->postJson(route('asistente.plantillas.ejecutar', $plantilla))->assertForbidden();
        $this->actingAs($otro)->deleteJson(route('asistente.plantillas.eliminar', $plantilla))->assertForbidden();

        $this->actingAs($admin)->deleteJson(route('asistente.plantillas.eliminar', $plantilla))->assertOk();
        $this->assertSame(0, PlantillaReporte::query()->count());
    }

    public function test_solo_se_guardan_como_plantilla_las_consultas_y_los_reportes(): void
    {
        $this->ia->encolar(new RespuestaIa('respuesta', 'Hola'));
        $admin = $this->usuarioAsistente();
        $mensajeId = (int) $this->enviar('hola')->json('mensaje_asistente.id');

        $this->actingAs($admin)
            ->postJson(route('asistente.plantillas.guardar'), ['mensaje_id' => $mensajeId, 'nombre' => 'No debería'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mensaje_id');
    }

    public function test_las_conversaciones_se_crean_se_listan_y_se_abren_con_sus_reportes(): void
    {
        $this->escenarioDeReportes();
        $this->ia->encolar(ServicioIaFalso::reporte($this->especificacion(['dimensiones' => ['semana']])));

        $admin = $this->usuarioAsistente();

        $this->actingAs($admin)->postJson(route('asistente.conversaciones.nueva'))->assertCreated()->assertJsonStructure(['conversacion_id']);

        $conversacionId = (int) $this->enviar('Ventas por semana en octubre')->json('conversacion_id');

        $this->actingAs($admin)->get(route('asistente.index'))->assertInertia(fn (Assert $page) => $page
            ->has('conversaciones', 1)
            ->where('conversaciones.0.titulo', 'Ventas por semana en octubre'));

        $this->actingAs($admin)->get(route('asistente.conversaciones.mostrar', $conversacionId))->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('asistente/Index')
                ->where('conversacion.id', $conversacionId)
                ->has('mensajes', 2)
                ->where('mensajes.0.rol', 'USUARIO')
                ->where('mensajes.1.tipo', 'REPORTE')
                ->where('mensajes.1.resultado.vacio', false)
                ->has('mensajes.1.resultado.filas', 5));
    }

    public function test_el_contenido_se_valida(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)->postJson(route('asistente.enviar'), ['contenido' => '', 'origen' => 'TEXTO'])->assertUnprocessable()->assertJsonValidationErrors('contenido');
        $this->actingAs($admin)->postJson(route('asistente.enviar'), ['contenido' => str_repeat('a', 1001), 'origen' => 'TEXTO'])->assertUnprocessable()->assertJsonValidationErrors('contenido');
        $this->actingAs($admin)->postJson(route('asistente.enviar'), ['contenido' => 'hola', 'origen' => 'CARTA'])->assertUnprocessable()->assertJsonValidationErrors('origen');

        $this->assertSame([], $this->ia->llamadas);
    }

    public function test_el_origen_voz_se_guarda_en_el_mensaje(): void
    {
        $this->ia->encolar(new RespuestaIa('respuesta', 'Hola'));

        $this->enviar('hola por voz', null, 'VOZ')->assertOk();

        $this->assertSame('VOZ', MensajeAsistente::query()->where('rol', 'USUARIO')->firstOrFail()->origen);
    }

    /* ── Límite por minuto (EX-08) ────────────────────────────────────────── */

    public function test_el_envio_numero_once_en_un_minuto_recibe_429_con_los_segundos(): void
    {
        config(['services.xai.limite_por_minuto' => 10]);
        $admin = $this->administrador();

        foreach (range(1, 10) as $n) {
            $this->ia->encolar(new RespuestaIa('respuesta', "Respuesta $n"));
            $this->actingAs($admin)->postJson(route('asistente.enviar'), ['contenido' => "Consulta $n", 'origen' => 'TEXTO'])->assertOk();
        }

        $respuesta = $this->actingAs($admin)
            ->postJson(route('asistente.enviar'), ['contenido' => 'Consulta 11', 'origen' => 'TEXTO'])
            ->assertStatus(429);

        $this->assertGreaterThanOrEqual(1, $respuesta->json('retry_after'));
        $this->assertStringContainsString('Superaste el límite de consultas por minuto', (string) $respuesta->json('message'));
        $this->assertStringContainsString((string) $respuesta->json('retry_after').' segundos', (string) $respuesta->json('message'));
        $this->assertCount(10, $this->ia->llamadas);
    }

    /* ── Asistente simulado (demo local) ──────────────────────────────────── */

    public function test_el_asistente_simulado_responde_los_ejemplos_de_la_demo(): void
    {
        $this->travelTo('2026-11-04 10:00:00');
        $this->escenarioDeReportes();

        $this->app->forgetInstance(ServicioIa::class);
        $this->app->bind(ServicioIa::class, function () {
            return $this->app->make(ServicioIaSimulado::class);
        });

        $semana = $this->enviar('¿Cuánto vendimos esta semana?');
        $semana->assertOk()->assertJsonPath('mensaje_asistente.tipo', 'CONSULTA');
        $this->assertStringContainsString('02/11/2026 – 08/11/2026', (string) $semana->json('mensaje_asistente.interpretacion'));

        $stock = $this->enviar('¿Qué productos están bajo el stock mínimo?');
        $stock->assertOk()->assertJsonPath('mensaje_asistente.tipo', 'CONSULTA')->assertJsonPath('mensaje_asistente.enlace', '/inventario?alerta=bajo');

        $reporte = $this->enviar('Ventas de teclados Redragon por semana en el último mes');
        $reporte->assertOk()->assertJsonPath('mensaje_asistente.tipo', 'REPORTE');
        $this->assertSame(
            'Ventas en Bs por semana, categoría Teclados, marca Redragon, 01/10/2026 – 31/10/2026',
            $reporte->json('mensaje_asistente.interpretacion'),
        );

        $conversacionId = (int) $reporte->json('conversacion_id');
        $comparacion = $this->enviar('ahora compáralo con septiembre', $conversacionId);
        $comparacion->assertOk()->assertJsonPath('mensaje_asistente.tipo', 'REPORTE');
        $this->assertStringContainsString('comparado con 01/09/2026 – 30/09/2026', (string) $comparacion->json('mensaje_asistente.interpretacion'));

        $horario = $this->enviar('¿A qué hora abren el sábado?');
        $horario->assertOk()->assertJsonPath('mensaje_asistente.tipo', 'TEXTO');
    }

    public function test_el_asistente_simulado_no_existe_en_produccion(): void
    {
        config(['services.xai.simulado' => true]);
        $this->app->forgetInstance(ServicioIa::class);
        $this->app->offsetUnset(ServicioIa::class);
        $this->app->bind(ServicioIa::class, function ($app) {
            $simulado = (bool) config('services.xai.simulado') && $app->environment(['local', 'testing']);

            return $simulado ? $app->make(ServicioIaSimulado::class) : $app->make(GrokServicioIa::class);
        });

        $this->assertInstanceOf(ServicioIaSimulado::class, $this->app->make(ServicioIa::class));

        $this->app->detectEnvironment(fn (): string => 'production');

        $this->assertInstanceOf(GrokServicioIa::class, $this->app->make(ServicioIa::class));
    }

    /* ── Apoyo ────────────────────────────────────────────────────────────── */

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function especificacion(array $cambios = []): array
    {
        return [
            'metrica' => 'ventas_monto',
            'dimensiones' => [],
            'periodo' => ['desde' => '2026-10-01', 'hasta' => '2026-10-31'],
            ...$cambios,
        ];
    }

    private function enviar(string $contenido, ?int $conversacionId = null, string $origen = 'TEXTO'): TestResponse
    {
        return $this->actingAs($this->usuarioAsistente())->postJson(route('asistente.enviar'), array_filter([
            'conversacion_id' => $conversacionId,
            'contenido' => $contenido,
            'origen' => $origen,
        ], fn (mixed $valor): bool => $valor !== null));
    }

    private ?User $administradorActual = null;

    /** El Administrador que escribe en el chat de la prueba. */
    private function usuarioAsistente(): User
    {
        return $this->administradorActual ??= $this->administrador();
    }

    /** Un Administrador nuevo; todos comparten el rol "Administrador" con el permiso del asistente. */
    protected function administrador(): User
    {
        $rol = Rol::query()->where('nombre', 'Administrador')->first()
            ?? $this->rolConPermisos('Administrador', ['asistente.usar', 'reportes.ver']);

        return User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);
    }

    /**
     * Cambia las respuestas de HTTP de la prueba (cada llamada reemplaza a la anterior).
     *
     * @param  array<string, mixed>|callable  $respuestas
     */
    private function http(array|callable $respuestas): void
    {
        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        Http::fake($respuestas);
    }
}
