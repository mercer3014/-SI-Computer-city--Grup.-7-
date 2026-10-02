<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered()
    {
        $response = $this->get(route('login'));

        $response->assertOk();
    }

    public function test_users_can_authenticate_using_the_login_screen()
    {
        $user = User::factory()->create();

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_with_two_factor_enabled_are_redirected_to_two_factor_challenge()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->withTwoFactor()->create();

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('two-factor.login'));
        $response->assertSessionHas('login.id', $user->id);
        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_unknown_email_is_rejected(): void
    {
        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'nadie@computercity.com',
            'password' => 'Password1!',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
    }

    public function test_blocked_users_cannot_authenticate()
    {
        $user = User::factory()->create([
            'bloqueado' => true,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_inactive_users_cannot_authenticate()
    {
        $user = User::factory()->create([
            'estado' => 'INACTIVO',
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_users_are_rate_limited()
    {
        $user = User::factory()->create();

        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 30);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('password');
        $response->assertSessionHas('throttleSeconds');
    }

    public function test_first_login_asks_for_a_new_password_before_dashboard(): void
    {
        $user = User::factory()->create([
            'primer_login' => true,
        ]);

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('password.first', absolute: false));

        $this->get(route('dashboard'))->assertRedirect(route('password.first', absolute: false));

        $this->get(route('password.first'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('auth/FirstLogin')
                ->where('nombre', $user->name));
    }

    public function test_first_login_password_cannot_be_the_temporary_one(): void
    {
        $user = User::factory()->create([
            'primer_login' => true,
            'password_hash' => 'TempPass1!',
        ]);

        $this->actingAs($user)->post(route('password.first.update'), [
            'password' => 'TempPass1!',
            'password_confirmation' => 'TempPass1!',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->primer_login);
    }

    public function test_first_login_new_password_opens_the_dashboard(): void
    {
        $user = User::factory()->create([
            'primer_login' => true,
        ]);

        $this->actingAs($user)->post(route('password.first.update'), [
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertFalse((bool) $user->fresh()->primer_login);
        $this->get(route('dashboard'))->assertOk();
    }
}
