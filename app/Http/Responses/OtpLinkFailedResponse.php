<?php

namespace App\Http\Responses;

use App\Auth\OtpPasswordBroker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse as FailedPasswordResetLinkRequestResponseContract;

class OtpLinkFailedResponse implements FailedPasswordResetLinkRequestResponseContract
{
    public function __construct(public string $status) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request)
    {
        $message = match ($this->status) {
            Password::INVALID_USER => 'No existe una cuenta con ese correo.',
            Password::RESET_THROTTLED => 'Esperá un minuto antes de pedir otro código.',
            OtpPasswordBroker::MAIL_FAILED => 'El correo no se pudo enviar. El código puede estar en Logs de Render.',
            default => 'No se pudo enviar el código. Intentá de nuevo.',
        };

        if ($request->header('X-Inertia') || $request->wantsJson()) {
            throw ValidationException::withMessages([
                'email' => [$message],
            ]);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $message]);
    }
}
