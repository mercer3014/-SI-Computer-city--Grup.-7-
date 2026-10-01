<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

class BitacoraService
{
    /**
     * Inyecta usuario e IP en la sesión de Postgres para los triggers.
     */
    public static function bindContext(?int $usuarioId = null, ?string $ip = null): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $usuarioId ??= Auth::id();
        $ip ??= Request::ip();

        // is_local = false: dura toda la conexión del request (Laravel no
        // envuelve el request en una sola transacción).
        DB::statement(
            "SELECT set_config('app.usuario_id', ?, false), set_config('app.direccion_ip', ?, false)",
            [
                $usuarioId !== null ? (string) $usuarioId : '',
                $ip ?? '',
            ],
        );
    }

    public static function clearContext(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(
            "SELECT set_config('app.usuario_id', '', false), set_config('app.direccion_ip', '', false)",
        );
    }

    /**
     * @param  array<string, mixed>|null  $anterior
     * @param  array<string, mixed>|null  $nuevo
     */
    public static function registrar(
        string $modulo,
        string $accion,
        ?string $tipoEntidad = null,
        ?int $entidadId = null,
        ?array $anterior = null,
        ?array $nuevo = null,
        ?string $motivo = null,
        ?int $usuarioId = null,
    ): void {
        self::bindContext($usuarioId);

        DB::table('bitacora_auditoria')->insert([
            'usuario_id' => $usuarioId ?? Auth::id(),
            'modulo' => $modulo,
            'accion' => $accion,
            'tipo_entidad' => $tipoEntidad,
            'entidad_id' => $entidadId,
            'valor_anterior' => $anterior === null ? null : json_encode($anterior, JSON_UNESCAPED_UNICODE),
            'valor_nuevo' => $nuevo === null ? null : json_encode($nuevo, JSON_UNESCAPED_UNICODE),
            'motivo' => $motivo,
            'direccion_ip' => Request::ip(),
            'fecha_hora' => now(),
        ]);
    }

    public static function login(int $usuarioId, string $email): void
    {
        self::registrar(
            'Seguridad',
            'LOGIN',
            'usuario',
            $usuarioId,
            null,
            ['email' => $email],
            'Inicio de sesión',
            $usuarioId,
        );
    }

    public static function logout(int $usuarioId, ?string $email = null): void
    {
        self::registrar(
            'Seguridad',
            'LOGOUT',
            'usuario',
            $usuarioId,
            null,
            $email ? ['email' => $email] : null,
            'Cierre de sesión',
            $usuarioId,
        );
    }

    public static function loginFallido(?string $email): void
    {
        self::registrar(
            'Seguridad',
            'LOGIN_FALLIDO',
            'usuario',
            null,
            null,
            ['email' => $email],
            'Intento de inicio de sesión fallido',
            null,
        );
    }
}
