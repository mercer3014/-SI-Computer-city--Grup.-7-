<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\BloqueoLoginService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class BloqueoLoginTest extends TestCase
{
    use CreaAdministrador, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_three_wrong_passwords_pause_for_seconds(): void
    {
        $user = User::factory()->create();

        $this->fallar($user, 2);
        $this->assertSame(2, $user->fresh()->intentos_fallidos);
        $this->assertSame(0, (int) $user->fresh()->nivel_bloqueo);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password')
            ->assertSessionHas('throttleSeconds', BloqueoLoginService::NIVEL_1_SEGUNDOS);

        $user->refresh();
        $this->assertSame(3, $user->intentos_fallidos);
        $this->assertSame(1, $user->nivel_bloqueo);
        $this->assertNotNull($user->fecha_bloqueo);
    }

    public function test_pause_does_not_count_more_attempts(): void
    {
        $user = User::factory()->create([
            'intentos_fallidos' => 3,
            'nivel_bloqueo' => 1,
            'fecha_bloqueo' => now(),
        ]);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertGuest();

        $this->assertSame(3, $user->fresh()->intentos_fallidos);
    }

    public function test_correct_password_after_pause_resets_the_counter(): void
    {
        $user = User::factory()->create([
            'intentos_fallidos' => 3,
            'nivel_bloqueo' => 1,
            'fecha_bloqueo' => now()->subSeconds(BloqueoLoginService::NIVEL_1_SEGUNDOS + 1),
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $user->refresh();
        $this->assertSame(0, $user->intentos_fallidos);
        $this->assertSame(0, (int) $user->nivel_bloqueo);
        $this->assertFalse((bool) $user->bloqueado);
        $this->assertNull($user->fecha_bloqueo);
    }

    public function test_six_failures_pause_for_minutes_and_nine_need_an_admin(): void
    {
        $user = User::factory()->create();

        $this->fallar($user, 3);
        $this->assertSame(1, $user->fresh()->nivel_bloqueo);

        Carbon::setTestNow(now()->addSeconds(BloqueoLoginService::NIVEL_1_SEGUNDOS + 1));
        $this->fallar($user, 3);
        $this->assertSame(2, $user->fresh()->nivel_bloqueo);
        $this->assertFalse((bool) $user->fresh()->bloqueado);

        Carbon::setTestNow(now()->addSeconds(BloqueoLoginService::NIVEL_2_SEGUNDOS + 1));
        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $user->refresh();
        $this->assertTrue((bool) $user->bloqueado);
        $this->assertSame(3, $user->nivel_bloqueo);
        $this->assertSame(9, $user->intentos_fallidos);

        $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertGuest()->assertSessionHasErrors('email');
    }

    public function test_administrator_can_unlock_a_blocked_user(): void
    {
        $admin = $this->administrador();
        $objetivo = User::factory()->create([
            'bloqueado' => true,
            'nivel_bloqueo' => 3,
            'intentos_fallidos' => 9,
            'fecha_bloqueo' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('usuarios.desbloquear', $objetivo))
            ->assertRedirect();

        $objetivo->refresh();
        $this->assertFalse((bool) $objetivo->bloqueado);
        $this->assertSame(0, (int) $objetivo->nivel_bloqueo);
        $this->assertSame(0, (int) $objetivo->intentos_fallidos);

        $this->post(route('logout'));
        $this->post(route('login.store'), [
            'email' => $objetivo->email,
            'password' => 'password',
        ]);
        $this->assertAuthenticatedAs($objetivo);
    }

    private function fallar(User $user, int $veces): void
    {
        for ($i = 0; $i < $veces; $i++) {
            $this->from(route('login'))->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }
    }
}
