<?php

namespace Tests\Feature;

use App\Http\Controllers\RolController;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class RolTest extends TestCase
{
    use CreaAdministrador, RefreshDatabase;

    private function ids(array $claves): array
    {
        return Permiso::query()->whereIn('clave', $claves)->pluck('id')->all();
    }

    private function marcarSistema(User $admin): Rol
    {
        $rol = $admin->rol;
        $rol->forceFill(['es_sistema' => true])->save();

        return $rol;
    }

    public function test_requires_permission(): void
    {
        $this->get(route('roles.index'))->assertRedirect(route('login'));

        $vendedor = User::factory()->create(['primer_login' => false]);
        $this->actingAs($vendedor)->get(route('roles.index'))->assertForbidden();
    }

    public function test_lists_roles_and_permission_matrix(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)->get(route('roles.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p
                ->component('Roles')
                ->has('roles', 1)
                ->has('permisos', 3)
                ->where('roles.0.usuarios', 1));
    }

    public function test_creates_role_with_permissions(): void
    {
        $admin = $this->administrador();
        Permiso::query()->create(['clave' => 'ventas.crear', 'nombre' => 'Registrar ventas', 'modulo' => 'Ventas']);

        $this->actingAs($admin)->post(route('roles.store'), [
            'nombre' => 'Coordinador de tienda',
            'descripcion' => 'Supervisa la operación diaria.',
            'activo' => true,
            'permisos' => $this->ids(['ventas.crear']),
        ])->assertRedirect();

        $rol = Rol::query()->where('nombre', 'Coordinador de tienda')->firstOrFail();
        $this->assertSame('ACTIVO', $rol->estado);
        $this->assertFalse((bool) $rol->es_sistema);
        $this->assertSame(['ventas.crear'], $rol->permisos()->pluck('clave')->all());
    }

    public function test_rejects_duplicate_name_case_insensitive(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)->post(route('roles.store'), [
            'nombre' => strtoupper($admin->rol->nombre),
            'descripcion' => 'x',
            'permisos' => [],
        ])->assertSessionHasErrors('nombre');
    }

    public function test_updates_permissions_of_a_normal_role(): void
    {
        $admin = $this->administrador();
        $permiso = Permiso::query()->create(['clave' => 'clientes.gestionar', 'nombre' => 'Clientes', 'modulo' => 'Ventas']);
        $rol = Rol::factory()->create(['nombre' => 'Vendedor']);

        $this->actingAs($admin)->put(route('roles.update', $rol), [
            'nombre' => 'Vendedor',
            'descripcion' => 'Atiende ventas',
            'permisos' => [$permiso->id],
        ])->assertRedirect();

        $this->assertSame([$permiso->id], $rol->permisos()->pluck('permiso.id')->all());

        $this->actingAs($admin)->put(route('roles.update', $rol), [
            'nombre' => 'Vendedor',
            'descripcion' => 'Atiende ventas',
            'permisos' => [],
        ]);
        $this->assertCount(0, $rol->permisos()->get());
    }

    public function test_blocks_removing_essential_permissions_from_main_administrator(): void
    {
        $admin = $this->administrador();
        $rol = $this->marcarSistema($admin);
        $antes = $rol->permisos()->count();

        $this->actingAs($admin)->put(route('roles.update', $rol), [
            'nombre' => $rol->nombre,
            'descripcion' => 'Todo',
            'permisos' => $this->ids(['usuarios.administrar', 'bitacora.ver']),
        ])->assertSessionHasErrors('permisos');

        $this->assertSame($antes, $rol->permisos()->count());
    }

    public function test_main_administrator_cannot_be_renamed_or_deactivated(): void
    {
        $admin = $this->administrador();
        $rol = Rol::query()->find($admin->rol_id);
        $rol->forceFill(['nombre' => 'Administrador', 'es_sistema' => true])->save();

        $this->actingAs($admin)->put(route('roles.update', $rol), [
            'nombre' => 'Jefe',
            'descripcion' => 'x',
            'permisos' => $this->ids(RolControllerEsenciales::claves()),
        ])->assertSessionHasErrors('nombre');

        $this->actingAs($admin)->patch(route('roles.estado', $rol), ['estado' => 'INACTIVO']);
        $this->assertSame('ACTIVO', $rol->refresh()->estado);
    }

    public function test_cannot_deactivate_role_with_active_users_but_can_when_empty(): void
    {
        $admin = $this->administrador();
        $rol = Rol::factory()->create();
        $usuario = User::factory()->create(['rol_id' => $rol->id]);

        $this->actingAs($admin)->patch(route('roles.estado', $rol), ['estado' => 'INACTIVO']);
        $this->assertSame('ACTIVO', $rol->refresh()->estado);

        $usuario->forceFill(['estado' => 'INACTIVO'])->save();
        $this->actingAs($admin)->patch(route('roles.estado', $rol), ['estado' => 'INACTIVO']);
        $this->assertSame('INACTIVO', $rol->refresh()->estado);
    }

    public function test_users_with_inactive_role_lose_permissions(): void
    {
        $admin = $this->administrador();
        $admin->rol->forceFill(['estado' => 'INACTIVO'])->save();

        $this->assertFalse($admin->fresh()->tienePermiso('usuarios.administrar'));
    }
}

class RolControllerEsenciales
{
    /** @return list<string> */
    public static function claves(): array
    {
        return RolController::ESENCIALES;
    }
}
