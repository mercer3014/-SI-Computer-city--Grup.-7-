<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class BloqueoLoginService
{
    public const INTENTOS_POR_NIVEL = 3;

    public const NIVEL_1_SEGUNDOS = 30;

    public const NIVEL_2_SEGUNDOS = 15 * 60;

    public const NIVEL_MAXIMO = 3;

    public function rechazarSiNoPuedeIntentar(User $user): void
    {
        $activo = $user->estado === null || $user->estado === 'ACTIVO';

        if (! $activo) {
            throw ValidationException::withMessages([
                Fortify::username() => 'Tu cuenta está inactiva o bloqueada.',
            ]);
        }

        if ($this->estaBloqueadoPorAdmin($user)) {
            BitacoraService::registrar(
                'Seguridad',
                'LOGIN_BLOQUEADO',
                'usuario',
                (int) $user->id,
                null,
                ['email' => $user->email, 'nivel_bloqueo' => (int) $user->nivel_bloqueo],
                'Intento de login con cuenta bloqueada por intentos',
                (int) $user->id,
            );

            throw ValidationException::withMessages([
                Fortify::username() => 'Tu cuenta está bloqueada. Pedile a un administrador que la habilite.',
            ]);
        }

        $restantes = $this->segundosDePausa($user);

        if ($restantes > 0) {
            $this->lanzarPausa($restantes);
        }
    }

    public function registrarFallo(User $user): never
    {
        $intentos = (int) $user->intentos_fallidos + 1;
        $nivel = (int) ($user->nivel_bloqueo ?? 0);
        $bloqueado = (bool) $user->bloqueado;
        $fecha = $user->fecha_bloqueo;

        if ($intentos === self::INTENTOS_POR_NIVEL) {
            $nivel = 1;
            $fecha = now();
        } elseif ($intentos === self::INTENTOS_POR_NIVEL * 2) {
            $nivel = 2;
            $fecha = now();
        } elseif ($intentos >= self::INTENTOS_POR_NIVEL * self::NIVEL_MAXIMO) {
            $nivel = self::NIVEL_MAXIMO;
            $bloqueado = true;
            $fecha = now();
        }

        $user->forceFill([
            'intentos_fallidos' => $intentos,
            'nivel_bloqueo' => $nivel,
            'bloqueado' => $bloqueado,
            'fecha_bloqueo' => $fecha,
        ])->save();

        BitacoraService::loginFallido($user->email);

        if ($bloqueado && $intentos === self::INTENTOS_POR_NIVEL * self::NIVEL_MAXIMO) {
            BitacoraService::registrar(
                'Seguridad',
                'BLOQUEAR_USUARIO',
                'usuario',
                (int) $user->id,
                ['bloqueado' => false, 'nivel_bloqueo' => 2],
                ['bloqueado' => true, 'nivel_bloqueo' => self::NIVEL_MAXIMO],
                'Cuenta bloqueada tras 9 claves incorrectas',
                (int) $user->id,
            );

            throw ValidationException::withMessages([
                Fortify::username() => 'Tu cuenta está bloqueada. Pedile a un administrador que la habilite.',
            ]);
        }

        if ($intentos === self::INTENTOS_POR_NIVEL) {
            $this->lanzarPausa(self::NIVEL_1_SEGUNDOS);
        }

        if ($intentos === self::INTENTOS_POR_NIVEL * 2) {
            $this->lanzarPausa(self::NIVEL_2_SEGUNDOS);
        }

        throw ValidationException::withMessages([
            'password' => 'La clave no es correcta.',
        ]);
    }

    public function resetear(User $user): void
    {
        $user->forceFill([
            'intentos_fallidos' => 0,
            'nivel_bloqueo' => 0,
            'bloqueado' => false,
            'fecha_bloqueo' => null,
        ])->save();
    }

    public function estaBloqueadoPorAdmin(User $user): bool
    {
        return (bool) $user->bloqueado || (int) $user->nivel_bloqueo >= self::NIVEL_MAXIMO;
    }

    public function segundosDePausa(User $user): int
    {
        $nivel = (int) ($user->nivel_bloqueo ?? 0);

        if ($nivel !== 1 && $nivel !== 2) {
            return 0;
        }

        if ($user->fecha_bloqueo === null) {
            return 0;
        }

        $duracion = $nivel === 1 ? self::NIVEL_1_SEGUNDOS : self::NIVEL_2_SEGUNDOS;
        $queda = $user->fecha_bloqueo->getTimestamp() + $duracion - now()->getTimestamp();

        return max(0, $queda);
    }

    private function lanzarPausa(int $segundos): never
    {
        session()->flash('throttleSeconds', $segundos);

        throw ValidationException::withMessages([
            'password' => $segundos === 1
                ? 'Demasiados intentos. Esperá 1 segundo.'
                : "Demasiados intentos. Esperá {$segundos} segundos.",
        ]);
    }
}
