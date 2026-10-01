<?php

namespace App\Auth;

use Closure;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class OtpPasswordBroker extends PasswordBroker
{
    public const MAIL_FAILED = 'otp.mail_failed';

    /**
     * @param  array<string, mixed>  $credentials
     */
    public function sendResetLink(#[\SensitiveParameter] array $credentials, ?Closure $callback = null)
    {
        try {
            return parent::sendResetLink($credentials, $callback);
        } catch (TransportExceptionInterface $e) {
            Log::error('OTP recuperar: SMTP no respondió', [
                'error' => $e->getMessage(),
            ]);

            return self::MAIL_FAILED;
        }
    }
}
