<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse as FailedPasswordResetResponseContract;

class OtpFailedResponse implements FailedPasswordResetResponseContract
{
    public function __construct(public string $status) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request)
    {
        $message = 'El código no es válido o ya venció.';

        return $request->wantsJson()
            ? response()->json(['message' => $message], 422)
            : back()
                ->withInput($request->only('email'))
                ->withErrors(['token' => $message]);
    }
}
