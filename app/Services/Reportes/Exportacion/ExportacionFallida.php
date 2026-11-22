<?php

namespace App\Services\Reportes\Exportacion;

use RuntimeException;
use Throwable;

/**
 * Error controlado al generar un archivo (EX-03): el mensaje es el que ve el usuario.
 */
final class ExportacionFallida extends RuntimeException
{
    public const MENSAJE = 'No se pudo generar el archivo. Intenta de nuevo o elige otro formato.';

    public function __construct(?Throwable $anterior = null)
    {
        parent::__construct(self::MENSAJE, 0, $anterior);
    }
}
