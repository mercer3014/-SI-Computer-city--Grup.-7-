<?php

namespace App\Providers;

use App\Auth\OtpPasswordBrokerManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
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
