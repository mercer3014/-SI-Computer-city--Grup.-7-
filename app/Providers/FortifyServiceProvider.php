<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\OtpFailedResponse;
use App\Http\Responses\OtpSentResponse;
use App\Http\Responses\PasswordResetCompleteResponse;
use App\Http\Responses\RegisteredResponse;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponseContract::class, OtpSentResponse::class);
        $this->app->singleton(FailedPasswordResetResponseContract::class, OtpFailedResponse::class);
        $this->app->singleton(PasswordResetResponseContract::class, PasswordResetCompleteResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisteredResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureOtpMail();

        Route::getRoutes()->getByName('password.update')?->middleware('throttle:otp');
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = Str::lower($request->string(Fortify::username())->toString());

            $user = User::query()
                ->whereRaw('lower(email) = ?', [$email])
                ->first();

            $password = $request->string('password')->toString();

            if (! $user || ! Hash::check($password, $user->password_hash)) {
                return null;
            }

            if (! $user->puedeIniciarSesion()) {
                throw ValidationException::withMessages([
                    Fortify::username() => 'Tu cuenta está inactiva o bloqueada.',
                ]);
            }

            if (Hash::needsRehash($user->password_hash)) {
                $user->password_hash = $password;
            }

            $user->forceFill([
                'fecha_ultimo_login' => now(),
                'intentos_fallidos' => 0,
            ])->save();

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canUsePasskeys' => Features::canManagePasskeys(),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(function (Request $request) {
            if ($request->boolean('otro')) {
                $request->session()->forget('password_reset_email');
            }

            return Inertia::render('auth/ForgotPassword', [
                'status' => $request->session()->get('status'),
                'email' => $request->session()->get('password_reset_email'),
                'recovered' => false,
            ]);
        });

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn (Request $request) => Inertia::render('auth/Register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
            'registered' => false,
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });

        RateLimiter::for('otp', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('email')).'|'.$request->ip());
        });
    }

    /**
     * El correo lleva el código, no un enlace.
     */
    private function configureOtpMail(): void
    {
        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            Log::info('OTP recuperar cuenta', [
                'email' => $notifiable->email,
                'codigo' => $token,
            ]);

            return (new MailMessage)
                ->subject('Código para recuperar tu cuenta')
                ->line('Recibimos una solicitud para recuperar tu cuenta.')
                ->line('Tu código es: '.$token)
                ->line('Caduca en '.$minutes.' minutos. Si no pediste este código, ignora este correo.');
        });
    }
}
