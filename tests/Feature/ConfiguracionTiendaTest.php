<?php

namespace Tests\Feature;

use App\Models\ConfiguracionTienda;
use App\Models\HorarioAtencion;
use App\Models\User;
use App\Models\Venta;
use App\Services\NotaVentaPdf;
use App\Services\Tienda\ConfiguracionTiendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class ConfiguracionTiendaTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR4nGP4z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg==';

    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    public function test_guests_are_sent_to_login(): void
    {
        $this->get(route('configuracion.tienda.edit'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_get_403_on_every_route(): void
    {
        $vendedor = $this->usuarioCon(['ventas.crear', 'cotizaciones.gestionar']);

        $this->actingAs($vendedor)->get(route('configuracion.tienda.edit'))->assertForbidden();
        $this->actingAs($vendedor)->put(route('configuracion.tienda.update'), $this->datos())->assertForbidden();
        $this->actingAs($vendedor)->put(route('configuracion.tienda.horarios'), ['horarios' => $this->horarios()])->assertForbidden();
        $this->actingAs($vendedor)->post(route('configuracion.tienda.logo'))->assertForbidden();
    }

    public function test_admin_sees_the_form_with_the_data_and_the_seven_days(): void
    {
        $this->actingAs($this->admin())
            ->get(route('configuracion.tienda.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('configuracion/Tienda')
                ->where('configuracion.nombre', 'Computer City')
                ->where('configuracion.moneda', 'BOB')
                ->has('configuracion.horarios', 7)
                ->where('configuracion.horarios.0.dia', 'Lunes')
                ->where('configuracion.horarios.0.horario', '09:00 – 19:00')
                ->where('configuracion.horarios.6.cerrado', true)
                ->where('monedas', ['BOB', 'USD']));

        $this->assertSame(1, ConfiguracionTienda::query()->count());
    }

    public function test_valid_update_persists_logs_and_refreshes_the_cache(): void
    {
        $servicio = app(ConfiguracionTiendaService::class);
        $servicio->asegurar();
        $this->assertSame('Computer City', $servicio->actual()['nombre']);

        $this->actingAs($this->admin())
            ->put(route('configuracion.tienda.update'), $this->datos())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('configuracion_tienda', [
            'nombre_tienda' => 'Tecno Centro',
            'centro_comercial' => 'Comercial Chiriguano',
            'numero_pasillo' => '3',
            'numero_local' => '12',
            'whatsapp' => '+59177712345',
            'moneda' => 'USD',
        ]);
        $this->assertSame('Tecno Centro', $servicio->actual()['nombre']);
        $this->assertDatabaseHas('bitacora_auditoria', [
            'modulo' => 'Configuración',
            'accion' => 'ACTUALIZAR_CONFIGURACION_TIENDA',
        ]);
    }

    public function test_empty_name_is_rejected(): void
    {
        app(ConfiguracionTiendaService::class)->asegurar();

        $this->actingAs($this->admin())
            ->put(route('configuracion.tienda.update'), $this->datos(['nombre_tienda' => '   ']))
            ->assertSessionHasErrors(['nombre_tienda' => 'Ingresá el nombre de la tienda.']);

        $this->assertSame('Computer City', ConfiguracionTienda::query()->firstOrFail()->nombre_tienda);
    }

    public function test_whatsapp_must_be_a_valid_number(): void
    {
        app(ConfiguracionTiendaService::class)->asegurar();
        $admin = $this->admin();

        foreach (['abc123', '12345', '+5917771234567890'] as $invalido) {
            $this->actingAs($admin)
                ->put(route('configuracion.tienda.update'), $this->datos(['whatsapp' => $invalido]))
                ->assertSessionHasErrors('whatsapp');
        }

        $this->actingAs($admin)
            ->put(route('configuracion.tienda.update'), $this->datos(['whatsapp' => '777 12345']))
            ->assertSessionHasNoErrors();

        $this->assertSame('77712345', ConfiguracionTienda::query()->firstOrFail()->whatsapp);
    }

    public function test_other_fields_are_validated(): void
    {
        app(ConfiguracionTiendaService::class)->asegurar();
        $admin = $this->admin();

        $casos = [
            'email' => 'no-es-correo',
            'latitud' => '95',
            'longitud' => '-181',
            'google_maps_url' => 'esto no es una url',
            'moneda' => 'EUR',
            'anuncio' => str_repeat('a', 501),
        ];

        foreach ($casos as $campo => $valor) {
            $this->actingAs($admin)
                ->put(route('configuracion.tienda.update'), $this->datos([$campo => $valor]))
                ->assertSessionHasErrors($campo);
        }
    }

    public function test_logo_with_a_wrong_format_or_size_is_rejected_and_a_valid_one_is_stored(): void
    {
        Storage::fake('public');
        app(ConfiguracionTiendaService::class)->asegurar();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('configuracion.tienda.logo'), ['logo' => UploadedFile::fake()->createWithContent('logo.gif', base64_decode(self::GIF))])
            ->assertSessionHasErrors('logo');

        $pesado = base64_decode(self::PNG).str_repeat("\0", 1100 * 1024);
        $this->actingAs($admin)
            ->post(route('configuracion.tienda.logo'), ['logo' => UploadedFile::fake()->createWithContent('logo.png', $pesado)])
            ->assertSessionHasErrors(['logo' => 'El logo no puede pesar más de 1 MB.']);

        $this->assertNull(ConfiguracionTienda::query()->firstOrFail()->logo_url);

        $this->actingAs($admin)
            ->post(route('configuracion.tienda.logo'), ['logo' => UploadedFile::fake()->createWithContent('logo.png', base64_decode(self::PNG))])
            ->assertSessionHasNoErrors();

        $primero = (string) ConfiguracionTienda::query()->firstOrFail()->logo_url;
        $this->assertStringStartsWith('/storage/tienda/logo-', $primero);
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $primero));

        $this->actingAs($admin)
            ->post(route('configuracion.tienda.logo'), ['logo' => UploadedFile::fake()->createWithContent('otro.png', base64_decode(self::PNG))])
            ->assertSessionHasNoErrors();

        $segundo = (string) ConfiguracionTienda::query()->firstOrFail()->logo_url;
        $this->assertNotSame($primero, $segundo);
        Storage::disk('public')->assertMissing(str_replace('/storage/', '', $primero));
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $segundo));
    }

    public function test_closing_time_must_be_after_opening_time_on_open_days(): void
    {
        app(ConfiguracionTiendaService::class)->asegurar();
        $filas = $this->horarios();
        $filas[2]['hora_apertura'] = '18:00';
        $filas[2]['hora_cierre'] = '09:00';
        $filas[4]['hora_apertura'] = '10:00';
        $filas[4]['hora_cierre'] = '10:00';

        $this->actingAs($this->admin())
            ->put(route('configuracion.tienda.horarios'), ['horarios' => $filas])
            ->assertSessionHasErrors(['horarios.2.hora_cierre', 'horarios.4.hora_cierre'])
            ->assertSessionDoesntHaveErrors(['horarios.0.hora_cierre']);

        $this->assertSame('09:00', substr((string) HorarioAtencion::query()->where('dia_semana', 3)->value('hora_apertura'), 0, 5));
    }

    public function test_open_days_need_both_hours(): void
    {
        app(ConfiguracionTiendaService::class)->asegurar();
        $filas = $this->horarios();
        $filas[1]['hora_apertura'] = '';
        $filas[3]['hora_cierre'] = '';

        $this->actingAs($this->admin())
            ->put(route('configuracion.tienda.horarios'), ['horarios' => $filas])
            ->assertSessionHasErrors(['horarios.1.hora_apertura', 'horarios.3.hora_cierre']);
    }

    public function test_a_closed_day_is_saved_without_hours_even_if_the_form_sends_them(): void
    {
        app(ConfiguracionTiendaService::class)->asegurar();
        $filas = $this->horarios();
        $filas[0]['cerrado'] = true;
        $filas[0]['hora_apertura'] = '08:00';
        $filas[0]['hora_cierre'] = '07:00';

        $this->actingAs($this->admin())
            ->put(route('configuracion.tienda.horarios'), ['horarios' => $filas])
            ->assertSessionHasNoErrors();

        $lunes = HorarioAtencion::query()->where('dia_semana', 1)->firstOrFail();
        $this->assertTrue($lunes->cerrado);
        $this->assertNull($lunes->hora_apertura);
        $this->assertNull($lunes->hora_cierre);
    }

    public function test_valid_schedule_persists_and_is_logged_by_day(): void
    {
        app(ConfiguracionTiendaService::class)->asegurar();
        $filas = $this->horarios();
        $filas[0]['hora_apertura'] = '08:00';
        $filas[0]['hora_cierre'] = '20:00';
        $filas[6]['cerrado'] = false;
        $filas[6]['hora_apertura'] = '10:00';
        $filas[6]['hora_cierre'] = '14:00';

        $this->actingAs($this->admin())
            ->put(route('configuracion.tienda.horarios'), ['horarios' => $filas])
            ->assertSessionHasNoErrors();

        $actual = app(ConfiguracionTiendaService::class)->actual()['horarios'];
        $this->assertSame('08:00 – 20:00', $actual[0]['horario']);
        $this->assertSame('10:00 – 14:00', $actual[6]['horario']);

        $evento = DB::table('bitacora_auditoria')->where('accion', 'ACTUALIZAR_HORARIOS')->first();
        $this->assertNotNull($evento);
        $this->assertSame('Configuración', $evento->modulo);
        $this->assertStringContainsString('"Lunes":"09:00 – 19:00"', (string) $evento->valor_anterior);
        $this->assertStringContainsString('"Lunes":"08:00 – 20:00"', (string) $evento->valor_nuevo);
    }

    public function test_schedule_needs_the_seven_days(): void
    {
        app(ConfiguracionTiendaService::class)->asegurar();

        $this->actingAs($this->admin())
            ->put(route('configuracion.tienda.horarios'), ['horarios' => array_slice($this->horarios(), 0, 5)])
            ->assertSessionHasErrors('horarios');
    }

    public function test_the_public_catalog_and_the_sale_note_use_the_configured_data(): void
    {
        $this->actingAs($this->admin())
            ->put(route('configuracion.tienda.update'), $this->datos(['nombre_tienda' => 'Tecno Centro']))
            ->assertSessionHasNoErrors();

        Auth::forgetGuards();

        $this->get(route('tienda.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tienda.nombre', 'Tecno Centro')
                ->where('tienda.direccion', 'Av. San Martín 450')
                ->where('tienda.ubicacion', 'Comercial Chiriguano · Pasillo 3 · Local 12 · Av. San Martín 450'));

        $venta = new Venta(['numero_venta' => 'V-2026-0001', 'estado' => 'COMPLETADA']);
        $html = html_entity_decode(app(NotaVentaPdf::class)->documento($venta)->getDomPDF()->outputHtml());

        $this->assertStringContainsString('Tecno Centro', $html);
        $this->assertStringContainsString('Comercial Chiriguano · Pasillo 3 · Local 12', $html);
        $this->assertStringNotContainsString('Comercial Chiriguano, Santa Cruz', $html);
    }

    private function admin(): User
    {
        return $this->usuarioCon(['configuracion.gestionar']);
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
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombre_tienda' => 'Tecno Centro',
            'centro_comercial' => 'Comercial Chiriguano',
            'direccion' => 'Av. San Martín 450',
            'numero_pasillo' => '3',
            'numero_local' => '12',
            'telefono' => '3 3456789',
            'whatsapp' => '+591 777-12345',
            'email' => 'Contacto@TecnoCentro.com',
            'latitud' => '-17.7833',
            'longitud' => '-63.1821',
            'google_maps_url' => 'https://maps.app.goo.gl/abc123',
            'moneda' => 'USD',
            'anuncio' => 'Envíos en el día dentro de la ciudad.',
        ], $cambios);
    }

    /**
     * @return list<array{dia_semana: int, cerrado: bool, hora_apertura: string, hora_cierre: string}>
     */
    private function horarios(): array
    {
        $filas = [];

        foreach (range(1, 7) as $dia) {
            $filas[] = [
                'dia_semana' => $dia,
                'cerrado' => $dia === 7,
                'hora_apertura' => $dia === 7 ? '' : '09:00',
                'hora_cierre' => $dia === 7 ? '' : '19:00',
            ];
        }

        return $filas;
    }
}
