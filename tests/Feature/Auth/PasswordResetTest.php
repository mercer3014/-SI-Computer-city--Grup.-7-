<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::resetPasswords());
    }

    public function test_reset_password_link_screen_can_be_rendered()
    {
        $response = $this->get(route('password.request'));

        $response->assertOk();
    }

    public function test_reset_code_can_be_requested()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
            return preg_match('/^\d{6}$/', $notification->token) === 1;
        });
    }

    public function test_reset_code_is_found_with_different_email_case(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'arielgongoravalencia@gmail.com',
        ]);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'ArielGongoraValencia@gmail.com'])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_returns_spanish_error(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'nadie@computercity.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors([
                'email' => 'No existe una cuenta con ese correo.',
            ]);
    }

    public function test_password_can_be_reset_with_valid_code()
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post(route('password.update'), [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'Password1!',
                'password_confirmation' => 'Password1!',
            ]);

            $response
                ->assertSessionHasNoErrors()
                ->assertRedirect(route('password.complete'));

            return true;
        });
    }

    public function test_password_cannot_be_reset_with_invalid_code(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('password.update'), [
            'token' => '000000',
            'email' => $user->email,
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $response->assertSessionHasErrors('token');
    }
}
