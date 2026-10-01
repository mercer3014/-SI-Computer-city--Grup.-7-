<?php

namespace App\Providers;

use App\Auth\CaseInsensitiveUserProvider;
use App\Auth\OtpPasswordBrokerManager;
use App\Models\User;
use App\Services\BitacoraService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;
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
     * @param  array<string, mixed>  $config
     */
    private function registerUserProvider(): void
    {
        Auth::provider('eloquent', function ($app, array $config) {
            return new CaseInsensitiveUserProvider($app['hash'], $config['model']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerUserProvider();
        $this->registerBrevoMailer();
        $this->configureDefaults();
        $this->configureBitacoraAuth();
    }

    /**
     * API HTTPS de Brevo (producción). Evita SMTP 587, que Render suele cortar.
     */
    private function registerBrevoMailer(): void
    {
        Mail::extend('brevo', function (array $config = []) {
            $key = $config['key'] ?? config('services.brevo.key');

            return (new BrevoTransportFactory(
                client: HttpClient::create([
                    'timeout' => (int) env('MAIL_TIMEOUT', 8),
                ]),
            ))->create(new Dsn(
                'brevo+api',
                'default',
                $key,
            ));
        });
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

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
