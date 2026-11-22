<?php

namespace App\Auth;

use Illuminate\Auth\AuthManager;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Application;
use InvalidArgumentException;

class OtpPasswordBrokerManager extends PasswordBrokerManager
{
    /**
     * @param  string  $name
     */
    protected function resolve($name)
    {
        $config = $this->getConfig($name);

        if ($config === null) {
            throw new InvalidArgumentException("Password resetter [{$name}] is not defined.");
        }

        $app = $this->aplicacion();
        $auth = $app->make(AuthManager::class);
        $configuracion = $app->make(Repository::class);
        $proveedor = $config['provider'] ?? null;

        return new OtpPasswordBroker(
            $this->createTokenRepository($config),
            $auth->createUserProvider(is_string($proveedor) ? $proveedor : null),
            $app->bound(Dispatcher::class) ? $app->make(Dispatcher::class) : null,
            timeboxDuration: (int) $configuracion->get('auth.timebox_duration', 200000),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createTokenRepository(array $config)
    {
        $app = $this->aplicacion();
        $configuracion = $app->make(Repository::class);
        $key = $configuracion->get('app.key');

        if (! is_string($key) || $key === '') {
            throw new InvalidArgumentException('APP_KEY no está configurada.');
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            $key = is_string($decoded) ? $decoded : $key;
        }

        $hash = $app->make('hash');
        $db = $app->make(DatabaseManager::class);

        $conexion = $config['connection'] ?? null;
        $tabla = $config['table'] ?? 'password_reset_tokens';

        return new OtpTokenRepository(
            $db->connection(is_string($conexion) ? $conexion : null),
            $hash,
            is_string($tabla) ? $tabla : 'password_reset_tokens',
            $key,
            (int) ($config['expire'] ?? 60) * 60,
            (int) ($config['throttle'] ?? 0),
        );
    }

    private function aplicacion(): Application
    {
        $app = $this->app;

        if (! $app instanceof Application) {
            throw new InvalidArgumentException('Se esperaba el contenedor de Laravel.');
        }

        return $app;
    }
}
