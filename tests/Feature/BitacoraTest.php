<?php

namespace Tests\Feature;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BitacoraTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('bitacora.index'))->assertRedirect(route('login'));
        $this->get(route('bitacora.exportar'))->assertRedirect(route('login'));
    }

    public function test_users_without_permission_cannot_consult_the_log(): void
    {
        $usuario = User::factory()->create(['primer_login' => false]);

        $this->actingAs($usuario)
            ->get(route('bitacora.index'))
            ->assertForbidden();

        $this->actingAs($usuario)
            ->get(route('bitacora.exportar'))
            ->assertForbidden();
    }

    public function test_administrator_can_consult_events_without_changing_them(): void
    {
        $admin = $this->administrador();
        $this->registrar($admin, 'Seguridad', 'LOGIN', 'Inicio de sesión');
        $this->registrar($admin, 'Inventario', 'ACTUALIZAR_PRECIO', 'Actualización de precio de venta');

        $antes = DB::table('bitacora_auditoria')->count();

        $this->actingAs($admin)
            ->get(route('bitacora.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bitacora')
                ->where('error', null)
                ->where('eventos.meta.total', 2)
                ->where('eventos.data.0.modulo', 'Inventario')
                ->where('eventos.data.0.descripcion', 'Actualización de precio de venta')
                ->has('opciones.modulos', 2)
                ->has('opciones.acciones', 2));

        $this->assertSame($antes, DB::table('bitacora_auditoria')->count());
    }

    public function test_filters_limit_the_list_and_empty_results_stay_readable(): void
    {
        $admin = $this->administrador();
        $this->registrar($admin, 'Seguridad', 'LOGIN', 'Inicio de sesión');

        $this->actingAs($admin)
            ->get(route('bitacora.index', ['modulo' => 'Ventas']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('eventos.meta.total', 0)
                ->where('filtros.modulo', 'Ventas')
                ->where('error', null));

        $this->actingAs($admin)
            ->get(route('bitacora.index', ['q' => 'sesión']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('eventos.meta.total', 1)
                ->where('eventos.data.0.accion', 'LOGIN'));
    }

    public function test_administrator_can_export_the_filtered_log_as_csv(): void
    {
        $admin = $this->administrador();
        $this->registrar($admin, 'Seguridad', 'LOGIN', 'Inicio de sesión');
        $this->registrar($admin, 'Inventario', 'ACTUALIZAR_PRECIO', 'Actualización de precio de venta');

        $antes = DB::table('bitacora_auditoria')->count();

        $respuesta = $this->actingAs($admin)->get(route('bitacora.exportar', [
            'modulo' => 'Seguridad',
        ]));

        $respuesta->assertOk();
        $contenido = $respuesta->streamedContent();

        $this->assertStringContainsString('Fecha/hora', $contenido);
        $this->assertStringContainsString('Inicio de sesión', $contenido);
        $this->assertStringNotContainsString('Actualización de precio de venta', $contenido);
        $this->assertSame($antes, DB::table('bitacora_auditoria')->count());
    }

    private function administrador(): User
    {
        $permiso = Permiso::query()->firstOrCreate(['clave' => 'bitacora.ver'], [
            'clave' => 'bitacora.ver',
            'nombre' => 'Consultar la bitácora de auditoría',
            'modulo' => 'Seguridad',
        ]);

        $rol = Rol::factory()->create(['nombre' => 'Administrador']);
        $rol->permisos()->attach($permiso->id);

        return User::factory()->create([
            'rol_id' => $rol->id,
            'primer_login' => false,
        ]);
    }

    private function registrar(User $usuario, string $modulo, string $accion, string $motivo): void
    {
        DB::table('bitacora_auditoria')->insert([
            'usuario_id' => $usuario->id,
            'modulo' => $modulo,
            'accion' => $accion,
            'tipo_entidad' => 'usuario',
            'entidad_id' => $usuario->id,
            'valor_nuevo' => json_encode(['email' => $usuario->email], JSON_UNESCAPED_UNICODE),
            'motivo' => $motivo,
            'direccion_ip' => '127.0.0.1',
            'fecha_hora' => now(),
        ]);
    }
}
