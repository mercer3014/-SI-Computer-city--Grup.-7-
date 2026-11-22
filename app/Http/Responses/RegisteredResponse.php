<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

class RegisteredResponse implements RegisterResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 201);
        }

        return redirect()->route('register.complete');
    }
}
