<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;

class OtpSentResponse implements SuccessfulPasswordResetLinkRequestResponseContract
{
    public function __construct(public string $status) {}

    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => 'Te enviamos un código de 6 dígitos a tu correo.'], 200);
        }

        return back()->with('status', 'Te enviamos un código de 6 dígitos a tu correo.');
    }
}
