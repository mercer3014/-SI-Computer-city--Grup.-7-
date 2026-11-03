<?php

namespace Tests\Feature;

use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class ProveedorTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    public function test_users_without_permission_cannot_manage_suppliers(): void
    {
        $usuario = User::factory()->create(['primer_login' => false]);

        $this->actingAs($usuario)->get(route('proveedores.index'))->assertForbidden();
    }

    public function test_admin_lists_active_suppliers_and_registers_one(): void
    {
        $this->proveedor(['razon_social' => 'Inactivo SA', 'nit' => '100', 'estado' => 'INACTIVO']);
        $admin = $this->usuarioCon(['proveedores.gestionar']);

        $this->actingAs($admin)
            ->get(route('proveedores.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Proveedores')
                ->where('filtros.estado', 'ACTIVO')
                ->has('proveedores.data', 0));

        $this->actingAs($admin)
            ->post(route('proveedores.store'), $this->datos())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('proveedor', [
            'nit' => '123456789',
            'razon_social' => 'Distribuidora Andes SRL',
            'nombre_comercial' => 'Andes',
            'estado' => 'ACTIVO',
            'direccion' => 'Av. Banzer 100',
        ]);
        $this->assertDatabaseHas('bitacora_auditoria', ['accion' => 'CREAR_PROVEEDOR']);
    }

    public function test_nit_must_be_unique_and_razon_social_is_required(): void
    {
        $this->proveedor(['nit' => '123456789']);
        $admin = $this->usuarioCon(['proveedores.gestionar']);

        $this->actingAs($admin)
            ->post(route('proveedores.store'), $this->datos())
            ->assertSessionHasErrors('nit');

        $this->actingAs($admin)
            ->post(route('proveedores.store'), $this->datos(['razon_social' => '', 'nit' => '999']))
            ->assertSessionHasErrors('razon_social');
    }

    public function test_supplier_can_be_updated_and_inactivated(): void
    {
        $proveedor = $this->proveedor();
        $admin = $this->usuarioCon(['proveedores.gestionar']);

        $this->actingAs($admin)
            ->put(route('proveedores.update', $proveedor), $this->datos([
                'nit' => $proveedor->nit,
                'razon_social' => 'Distribuidora Andes Actualizada',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Distribuidora Andes Actualizada', $proveedor->fresh()->razon_social);

        $this->actingAs($admin)
            ->patch(route('proveedores.estado', $proveedor), ['estado' => 'INACTIVO'])
            ->assertSessionHasNoErrors();

        $this->assertSame('INACTIVO', $proveedor->fresh()->estado);
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
     */
    private function proveedor(array $cambios = []): Proveedor
    {
        return Proveedor::query()->create(array_merge([
            'nit' => '555444333',
            'razon_social' => 'Importadora Norte',
            'estado' => 'ACTIVO',
        ], $cambios));
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nit' => '123456789',
            'razon_social' => 'Distribuidora Andes SRL',
            'nombre_comercial' => 'Andes',
            'contacto' => 'María López',
            'telefono' => '70012345',
            'email' => 'ventas@andes.test',
            'direccion' => 'Av. Banzer 100',
            'observacion' => 'Entrega los lunes',
        ], $cambios);
    }
}
