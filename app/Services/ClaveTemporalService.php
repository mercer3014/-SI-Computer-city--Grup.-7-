<?php

namespace App\Services;

use App\Mail\ClaveTemporalMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class ClaveTemporalService
{
    /**
     * Clave aleatoria que cumple Password::defaults() (8+, mayúsculas, minúsculas, números y símbolo).
     */
    public function generar(): string
    {
        $grupos = [
            'ABCDEFGHJKLMNPQRSTUVWXYZ',
            'abcdefghijkmnpqrstuvwxyz',
            '23456789',
            '!@#$%*?',
        ];

        $caracteres = array_map(
            fn (string $grupo): string => $grupo[random_int(0, strlen($grupo) - 1)],
            $grupos,
        );

        $todos = implode('', $grupos);

        while (count($caracteres) < 12) {
            $caracteres[] = $todos[random_int(0, strlen($todos) - 1)];
        }

        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }

    /**
     * Guarda una clave nueva y marca el primer ingreso. Devuelve la clave en claro.
     */
    public function asignar(User $user): string
    {
        $clave = $this->generar();

        $user->forceFill([
            'password_hash' => $clave,
            'primer_login' => true,
            'intentos_fallidos' => 0,
            'nivel_bloqueo' => 0,
            'bloqueado' => false,
            'fecha_bloqueo' => null,
        ])->save();

        return $clave;
    }

    public function enviar(User $user, string $clave): void
    {
        $user->loadMissing('personal');

        Mail::to($user->email)->send(new ClaveTemporalMail(
            nombre: $user->name !== '' ? $user->name : $user->email,
            email: $user->email,
            clave: $clave,
            urlLogin: route('login'),
        ));
    }
}
