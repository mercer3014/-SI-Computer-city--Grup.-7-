<?php

namespace App\Auth;

use Illuminate\Auth\Passwords\PasswordBrokerManager;
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

        return new OtpPasswordBroker(
            $this->createTokenRepository($config),
            $this->app['auth']->createUserProvider($config['provider'] ?? null),
            $this->app['events'] ?? null,
            timeboxDuration: $this->app['config']->get('auth.timebox_duration', 200000),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function createTokenRepository(array $config)
    {
        $key = $this->app['config']['app.key'];

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return new OtpTokenRepository(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
