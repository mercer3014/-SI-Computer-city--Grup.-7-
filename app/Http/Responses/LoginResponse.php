<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class LoginResponse implements LoginResponseContract
{
    /**
     * @param  \Illuminate\Http\Request  $request
     */
    public function toResponse($request)
    {
        $user = $request->user();

        if ($user instanceof User && $user->primer_login) {
            if ($request->wantsJson()) {
                return new JsonResponse(['two_factor' => false, 'primer_login' => true], 200);
            }

            return redirect()->route('password.first');
        }

        return $request->wantsJson()
            ? new JsonResponse(['two_factor' => false])
            : redirect()->intended(Fortify::redirects('login'));
    }
}
