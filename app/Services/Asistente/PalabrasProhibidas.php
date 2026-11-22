<?php

namespace App\Services\Asistente;

use Illuminate\Support\Str;

/**
 * Filtro previo (EX-03): lo que se pide sobre datos personales, claves, tarjetas o cambios en la base
 * se rechaza antes de llamar a la IA, para que ni siquiera salga del servidor.
 */
final class PalabrasProhibidas
{
    private const PALABRAS = [
        'contrasena', 'contrasenas', 'clave', 'claves', 'password', 'passwords', 'hash', 'hashes',
        'tarjeta', 'tarjetas', 'ci', 'nit', 'telefono', 'telefonos', 'celular', 'celulares',
        'email', 'emails', 'borrar', 'eliminar', 'update', 'delete', 'drop', 'insert', 'sql',
    ];

    public static function contiene(string $texto): bool
    {
        $normal = (string) preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(mb_strtolower($texto)));

        foreach (self::PALABRAS as $palabra) {
            if (preg_match('/\b'.$palabra.'\b/', $normal) === 1) {
                return true;
            }
        }

        return false;
    }
}
