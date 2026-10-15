<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class ClienteTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    public function test_users_without_permission_cannot_manage_clients(): void
    {
        $usuario = User::factory()->create(['primer_login' => false]);

        $this->actingAs($usuario)->get(route('clientes.index'))->assertForbidden();
    }

    public function test_seller_can_list_and_register_a_client(): void
    {
        $vendedor = $this->usuarioCon(['clientes.gestionar']);

        $this->actingAs($vendedor)
            ->get(route('clientes.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Clientes'));

        $this->actingAs($vendedor)
            ->post(route('clientes.store'), $this->datos())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('cliente', [
            'nombre' => 'Juan Perez Rojas',
            'numero_documento' => '4521230 SC',
            'estado' => 'ACTIVO',
        ]);
        $this->assertDatabaseHas('bitacora_auditoria', ['accion' => 'CREAR_CLIENTE']);
    }

    public function test_document_must_be_unique_and_name_is_required(): void
    {
        $this->cliente(['numero_documento' => '4521230 SC']);
        $admin = $this->usuarioCon(['clientes.gestionar']);

        $this->actingAs($admin)
            ->post(route('clientes.store'), $this->datos(['numero_documento' => '4521230 SC']))
            ->assertSessionHasErrors(['numero_documento' => 'Ya existe un cliente registrado con el documento indicado.']);

        $this->actingAs($admin)
            ->post(route('clientes.store'), $this->datos(['nombre' => '']))
            ->assertSessionHasErrors('nombre');
    }

    public function test_invalid_email_and_phone_are_rejected(): void
    {
        $admin = $this->usuarioCon(['clientes.gestionar']);

        $this->actingAs($admin)
            ->post(route('clientes.store'), $this->datos(['email' => 'no-es-un-mail', 'telefono' => 'abc']))
            ->assertSessionHasErrors(['email', 'telefono']);
    }

    public function test_client_with_sales_cannot_be_deleted_and_can_be_inactivated(): void
    {
        $cliente = $this->cliente();
        $admin = $this->usuarioCon(['clientes.gestionar']);
        $this->venta($cliente, $admin);

        $this->actingAs($admin)
            ->delete(route('clientes.destroy', $cliente))
            ->assertSessionHasErrors('cliente');

        $this->assertDatabaseHas('cliente', ['id' => $cliente->id]);

        $this->actingAs($admin)
            ->patch(route('clientes.estado', $cliente), ['estado' => 'INACTIVO'])
            ->assertSessionHasNoErrors();

        $this->assertSame('INACTIVO', $cliente->fresh()->estado);
    }

    public function test_client_without_sales_can_be_deleted(): void
    {
        $cliente = $this->cliente();
        $admin = $this->usuarioCon(['clientes.gestionar']);

        $this->actingAs($admin)
            ->delete(route('clientes.destroy', $cliente))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('cliente', ['id' => $cliente->id]);
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
    private function cliente(array $cambios = []): Cliente
    {
        return Cliente::query()->create(array_merge([
            'tipo_documento' => 'CI',
            'numero_documento' => '9998887 SC',
            'nombre' => 'Andrea Gutierrez',
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
            'tipo_documento' => 'CI',
            'numero_documento' => '4521230 SC',
            'nombre' => 'Juan Perez Rojas',
            'razon_social' => '',
            'telefono' => '76541230',
            'email' => 'juanperez@gmail.com',
            'direccion' => 'Av. Chiriguano',
            'observacion' => '',
        ], $cambios);
    }

    private function venta(Cliente $cliente, User $usuario): void
    {
        DB::table('venta')->insert([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'numero_venta' => 'V-'.uniqid(),
            'fecha' => now(),
            'estado' => 'COMPLETADA',
        ]);
    }
}
