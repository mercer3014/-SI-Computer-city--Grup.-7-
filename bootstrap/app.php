<?php

use App\Http\Middleware\EnsurePrimerLoginCompleto;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetPostgresAuditContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->trustProxies(at: '*');

        $middleware->redirectUsersTo(function (Request $request) {
            return $request->user()?->primer_login
                ? '/primer-ingreso'
                : '/dashboard';
        });

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            SetPostgresAuditContext::class,
            AddLinkHeadersForPreloadedAssets::class,
            EnsurePrimerLoginCompleto::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e, Request $request) {
            if (! $request->routeIs('password.email')) {
                return null;
            }

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'El correo no se pudo enviar. Revisá BREVO_KEY en Render o el código en Logs.',
                ]);
        });
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($e->status !== 429 || ! $request->hasSession()) {
                return null;
            }

            $seconds = null;
            $message = null;

            foreach ($e->errors() as $messages) {
                foreach ($messages as $text) {
                    if (preg_match('/(\d+)\s*(?:seconds?|segundos?)/i', $text, $match)) {
                        $seconds = (int) $match[1];
                        $message = $text;
                        break 2;
                    }
                }
            }

            if ($seconds === null) {
                return null;
            }

            $message = $seconds === 1
                ? 'Demasiados intentos. Esperá 1 segundo.'
                : "Demasiados intentos. Esperá {$seconds} segundos.";

            $errorKey = $request->routeIs('login', 'login.store')
                ? 'password'
                : (array_key_first($e->errors()) ?: 'email');

            return redirect()
                ->back()
                ->withInput($request->except(['password', 'password_confirmation', 'token', 'code', 'recovery_code']))
                ->withErrors([$errorKey => $message])
                ->with('throttleSeconds', $seconds);
        });

        // Middleware throttle:*: sin pantalla cruda 429 — error bajo el campo + conteo.
        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            // JSON puro (no Inertia): conservar 429.
            if ($request->expectsJson() && ! $request->header('X-Inertia')) {
                return null;
            }

            $seconds = max(1, (int) ($e->getHeaders()['Retry-After'] ?? 60));

            $message = $seconds === 1
                ? 'Demasiados intentos. Esperá 1 segundo.'
                : "Demasiados intentos. Esperá {$seconds} segundos.";

            $errorKey = match (true) {
                $request->routeIs('login', 'login.store') => 'password',
                $request->routeIs('password.confirm', 'password.confirm.store') => 'password',
                $request->routeIs('register.otp.verify') => 'token',
                $request->routeIs('register.otp') => 'email',
                $request->routeIs('two-factor.login', 'two-factor.login.store') => $request->filled('recovery_code')
                    ? 'recovery_code'
                    : 'code',
                $request->filled('token') => 'token',
                default => 'email',
            };

            return redirect()
                ->back()
                ->withInput($request->except(['password', 'password_confirmation', 'token', 'code', 'recovery_code']))
                ->withErrors([$errorKey => $message])
                ->with('throttleSeconds', $seconds);
        });
    })->create();
