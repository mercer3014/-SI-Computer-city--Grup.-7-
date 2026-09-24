<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\PasswordResetResponse as PasswordResetResponseContract;

class PasswordResetCompleteResponse implements PasswordResetResponseContract
{
    public function __construct(public string $status) {}

    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => trans($this->status)], 200);
        }

        $request->session()->forget('password_reset_email');

        return redirect()->route('password.complete');
    }
}
