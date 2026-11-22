<?php

namespace App\Services\Asistente;

use InvalidArgumentException;

/**
 * La especificación que propuso la IA no pasó la validación (EX-04). El mensaje es para la bitácora,
 * no para el usuario: a la persona solo se le dice que pruebe reformular la consulta.
 */
final class EspecificacionInvalida extends InvalidArgumentException
{
    public const MENSAJE_PARA_EL_USUARIO = 'No pude armar esa consulta de forma segura. Prueba reformularla.';
}
