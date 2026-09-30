<?php

namespace App\Providers;

use App\Auth\OtpPasswordBrokerManager;
use App\Models\User;
use App\Services\BitacoraService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // El PasswordResetServiceProvider es diferido y pisa un singleton
        // propio. extend() se aplica cuando Laravel termina de resolverlo.
        $this->app->extend('auth.password', function ($manager, $app) {
            return $manager instanceof OtpPasswordBrokerManager
                ? $manager
                : new OtpPasswordBrokerManager($app);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureBitacoraAuth();
    }

    /**
     * Login / logout / fallo → bitacora_auditoria (no se resuelve bien con triggers).
     */
    protected function configureBitacoraAuth(): void
    {
        Event::listen(Login::class, function (Login $event): void {
            $user = $event->user;

            if (! $user instanceof User) {
                return;
            }

            BitacoraService::login((int) $user->getAuthIdentifier(), $user->email);
        });

        Event::listen(Logout::class, function (Logout $event): void {
            $user = $event->user;

            if (! $user instanceof User) {
                return;
            }

            BitacoraService::logout((int) $user->getAuthIdentifier(), $user->email);
        });

        Event::listen(Failed::class, function (Failed $event): void {
            $email = $event->credentials['email'] ?? null;

            BitacoraService::loginFallido(is_string($email) ? $email : null);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): Password => Password::min(8)
            ->mixedCase()
            ->letters()
            ->numbers()
            ->symbols(),
        );
    }
}
