<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse as SuccessfulPasswordResetLinkRequestResponseContract;

class OtpSentResponse implements SuccessfulPasswordResetLinkRequestResponseContract
{
    public function __construct(public string $status) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => 'Te enviamos un código de 6 dígitos a tu correo.'], 200);
        }

        $request->session()->put(
            'password_reset_email',
            $request->string('email')->toString(),
        );

        return back()->with('status', 'Te enviamos un código de 6 dígitos a tu correo.');
    }
}
