<?php

namespace App\Auth;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;

class OtpTokenRepository extends DatabaseTokenRepository
{
    /**
     * Código numérico de un solo uso, en lugar del token largo del enlace.
     */
    public function createNewToken()
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
