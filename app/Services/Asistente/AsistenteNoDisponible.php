<?php

namespace App\Services\Asistente;

use RuntimeException;
use Throwable;

/**
 * El Servicio de IA no se puede usar: no hay clave, no respondió a tiempo, devolvió un error o un JSON inválido (EX-02).
 */
final class AsistenteNoDisponible extends RuntimeException
{
    public const MENSAJE = 'El asistente no está disponible en este momento.';

    public function __construct(string $motivo = '', ?Throwable $anterior = null)
    {
        parent::__construct($motivo !== '' ? $motivo : self::MENSAJE, 0, $anterior);
    }
}
