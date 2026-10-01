<?php

namespace Tests\Feature;

use App\Mail\ClaveTemporalMail;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class UsuarioTest extends TestCase
{
    use CreaAdministrador, RefreshDatabase;

    public function test_requires_permission(): void
    {
        $this->get(route('usuarios.index'))->assertRedirect(route('login'));

        $vendedor = User::factory()->create(['primer_login' => false]);
        $this->actingAs($vendedor)->get(route('usuarios.index'))->assertForbidden();
        $this->actingAs($vendedor)->post(route('usuarios.store'), [])->assertForbidden();
    }

    public function test_lists_users_with_filters(): void
    {
        $admin = $this->administrador();
        $rol = Rol::factory()->create(['nombre' => 'Vendedor']);
        User::factory()->create(['rol_id' => $rol->id, 'email' => 'ana@computercity.com']);

        $this->actingAs($admin)->get(route('usuarios.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Usuarios')
                ->where('usuarios.meta.total', 2));

        $this->actingAs($admin)->get(route('usuarios.index', ['q' => 'ana@']))
            ->assertInertia(fn (Assert $p) => $p->where('usuarios.meta.total', 1));

        $this->actingAs($admin)->get(route('usuarios.index', ['estado' => 'INACTIVO']))
            ->assertInertia(fn (Assert $p) => $p->where('usuarios.meta.total', 0));
    }

    public function test_creating_a_user_sends_temporary_password_and_marks_first_login(): void
    {
        Mail::fake();
        $admin = $this->administrador();
        $rol = Rol::factory()->create(['nombre' => 'Vendedor']);

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'personal_modo' => 'nuevo',
            'nombre_completo' => 'Laura Jiménez',
            'ci' => '1234567',
            'telefono' => '70000000',
            'cargo' => 'Vendedora',
            'email' => 'Laura@computercity.com',
            'rol_id' => $rol->id,
        ])->assertRedirect();

        $user = User::query()->where('email', 'Laura@computercity.com')->firstOrFail();
        $this->assertTrue($user->primer_login);
        $this->assertSame('ACTIVO', $user->estado);
        $this->assertNotNull($user->personal_id);

        $enviada = null;
        Mail::assertSent(ClaveTemporalMail::class, function (ClaveTemporalMail $mail) use (&$enviada) {
            $enviada = $mail->clave;

            return $mail->hasTo('Laura@computercity.com');
        });
        $this->assertTrue(Hash::check($enviada, $user->password_hash));
    }

    public function test_can_link_existing_personal_without_user(): void
    {
        Mail::fake();
        $admin = $this->administrador();
        $rol = Rol::factory()->create();
        $personal = Personal::factory()->create();

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'personal_modo' => 'existente',
            'personal_id' => $personal->id,
            'email' => 'nuevo@computercity.com',
            'rol_id' => $rol->id,
        ])->assertRedirect();

        $this->assertSame($personal->id, User::query()->where('email', 'nuevo@computercity.com')->value('personal_id'));

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'personal_modo' => 'existente',
            'personal_id' => $personal->id,
            'email' => 'otro@computercity.com',
            'rol_id' => $rol->id,
        ])->assertSessionHasErrors('personal_id');
    }

    public function test_rejects_duplicated_email_case_insensitive(): void
    {
        Mail::fake();
        $admin = $this->administrador();
        $rol = Rol::factory()->create();

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'personal_modo' => 'nuevo',
            'nombre_completo' => 'X Y',
            'ci' => '999',
            'cargo' => 'Caja',
            'email' => strtoupper($admin->email),
            'rol_id' => $rol->id,
        ])->assertSessionHasErrors('email');

        Mail::assertNothingSent();
    }

    public function test_rejects_invalid_data(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'personal_modo' => 'nuevo',
            'email' => 'no-es-correo',
        ])->assertSessionHasErrors(['email', 'rol_id', 'nombre_completo', 'ci', 'cargo']);
    }

    public function test_mail_failure_keeps_the_user_created(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp caído'));
        $admin = $this->administrador();
        $rol = Rol::factory()->create();

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'personal_modo' => 'nuevo',
            'nombre_completo' => 'Sin Correo',
            'ci' => '555',
            'cargo' => 'Caja',
            'email' => 'sincorreo@computercity.com',
            'rol_id' => $rol->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('usuario', ['email' => 'sincorreo@computercity.com']);
    }

    public function test_disabling_blocks_login_keeps_row_and_clears_sessions(): void
    {
        $admin = $this->administrador();
        $objetivo = User::factory()->create();
        DB::table('sessions')->insert([
            'id' => 's1', 'user_id' => $objetivo->id, 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->actingAs($admin)
            ->patch(route('usuarios.estado', $objetivo), ['estado' => 'INACTIVO'])
            ->assertRedirect();

        $objetivo->refresh();
        $this->assertSame('INACTIVO', $objetivo->estado);
        $this->assertFalse($objetivo->puedeIniciarSesion());
        $this->assertDatabaseHas('usuario', ['id' => $objetivo->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 's1']);

        $this->actingAs($admin)
            ->patch(route('usuarios.estado', $objetivo), ['estado' => 'ACTIVO']);
        $this->assertTrue($objetivo->refresh()->puedeIniciarSesion());
    }

    public function test_cannot_disable_self_or_last_admin(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)
            ->patch(route('usuarios.estado', $admin), ['estado' => 'INACTIVO']);
        $this->assertSame('ACTIVO', $admin->refresh()->estado);

        $segundo = User::factory()->create(['rol_id' => $admin->rol_id]);
        $this->actingAs($segundo)
            ->patch(route('usuarios.estado', $admin), ['estado' => 'INACTIVO']);
        $this->assertSame('INACTIVO', $admin->refresh()->estado);

        $this->actingAs($segundo)
            ->patch(route('usuarios.estado', $segundo), ['estado' => 'INACTIVO']);
        $this->assertSame('ACTIVO', $segundo->refresh()->estado);
    }

    public function test_cannot_change_role_of_last_admin(): void
    {
        $admin = $this->administrador();
        $otro = Rol::factory()->create();

        $this->actingAs($admin)->put(route('usuarios.update', $admin), [
            'email' => $admin->email,
            'rol_id' => $otro->id,
            'nombre_completo' => 'Admin',
        ]);

        $this->assertNotSame($otro->id, $admin->refresh()->rol_id);
    }

    public function test_can_update_user_data(): void
    {
        $admin = $this->administrador();
        $objetivo = User::factory()->create();
        $rol = Rol::factory()->create();

        $this->actingAs($admin)->put(route('usuarios.update', $objetivo), [
            'email' => 'cambiado@computercity.com',
            'rol_id' => $rol->id,
            'nombre_completo' => 'Nombre Nuevo',
            'telefono' => '71234567',
            'cargo' => 'Cajero',
        ])->assertRedirect();

        $objetivo->refresh();
        $this->assertSame('cambiado@computercity.com', $objetivo->email);
        $this->assertSame($rol->id, $objetivo->rol_id);
        $this->assertSame('Nombre Nuevo', $objetivo->personal->nombre_completo);
    }

    public function test_resend_temporary_password(): void
    {
        Mail::fake();
        $admin = $this->administrador();
        $objetivo = User::factory()->create(['primer_login' => false]);
        $anterior = $objetivo->password_hash;

        $this->actingAs($admin)->post(route('usuarios.reenviar', $objetivo))->assertRedirect();

        $objetivo->refresh();
        $this->assertTrue($objetivo->primer_login);
        $this->assertNotSame($anterior, $objetivo->password_hash);
        Mail::assertSent(ClaveTemporalMail::class, fn ($m) => $m->hasTo($objetivo->email));
    }

    public function test_export_csv(): void
    {
        $admin = $this->administrador();

        $contenido = $this->actingAs($admin)->get(route('usuarios.exportar'))
            ->assertOk()
            ->streamedContent();

        $this->assertStringContainsString('Correo', $contenido);
        $this->assertStringContainsString($admin->email, $contenido);
    }
}
