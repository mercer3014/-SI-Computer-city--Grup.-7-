<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RegisterOtpService
{
    public function send(string $email): void
    {
        $email = Str::lower($email);
        $code = app()->environment('testing')
            ? '123456'
            : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put($this->key($email), [
            'hash' => Hash::make($code),
            'verified' => false,
        ], now()->addMinutes(10));

        Log::info('OTP registro', [
            'email' => $email,
            'codigo' => $code,
        ]);

        Mail::raw(
            "Tu código para crear la cuenta es: {$code}\nCaduca en 10 minutos.",
            function ($message) use ($email): void {
                $message->to($email)->subject('Código para verificar tu correo');
            },
        );
    }

    public function verify(string $email, string $code): bool
    {
        $email = Str::lower($email);
        $payload = Cache::get($this->key($email));

        if (! is_array($payload) || ! Hash::check($code, $payload['hash'] ?? '')) {
            return false;
        }

        Cache::put($this->key($email), [
            ...$payload,
            'verified' => true,
        ], now()->addMinutes(10));

        return true;
    }

    public function isVerified(string $email): bool
    {
        $payload = Cache::get($this->key(Str::lower($email)));

        return is_array($payload) && ($payload['verified'] ?? false) === true;
    }

    public function forget(string $email): void
    {
        Cache::forget($this->key(Str::lower($email)));
    }

    private function key(string $email): string
    {
        return 'register-otp:'.$email;
    }
}
