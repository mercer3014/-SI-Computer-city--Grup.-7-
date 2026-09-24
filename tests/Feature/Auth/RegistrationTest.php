<?php

namespace Tests\Feature\Auth;

use App\Models\Personal;
use App\Models\Rol;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        Mail::fake();

        Rol::factory()->create([
            'nombre' => 'Vendedor',
            'estado' => 'ACTIVO',
        ]);

        $this->post(route('register.otp'), [
            'email' => 'test@example.com',
        ])->assertRedirect();

        $this->post(route('register.otp.verify'), [
            'email' => 'test@example.com',
            'token' => '123456',
        ])->assertRedirect();

        $response = $this->post(route('register.store'), [
            'nombre' => 'Ana',
            'apellido' => 'Perez',
            'ci' => '12345678',
            'cargo' => 'Vendedora',
            'telefono' => '70000000',
            'email' => 'test@example.com',
            'token' => '123456',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('register.complete', absolute: false));
        $this->assertDatabaseHas(Personal::class, [
            'nombre_completo' => 'Ana Perez',
            'ci' => '12345678',
            'cargo' => 'Vendedora',
            'telefono' => '70000000',
        ]);
    }
}
