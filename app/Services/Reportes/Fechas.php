<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;

/**
 * Nombres de meses y etiquetas de fechas en español, sin depender del idioma de la aplicación.
 */
final class Fechas
{
    public const MESES = [
        1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio',
        7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre',
    ];

    public static function mes(int $numero): string
    {
        return self::MESES[$numero] ?? '';
    }

    /** "octubre de 2026" */
    public static function mesYAnio(CarbonImmutable $fecha): string
    {
        return self::mes($fecha->month).' de '.$fecha->year;
    }

    /** "03/11/2026" */
    public static function corta(CarbonImmutable $fecha): string
    {
        return $fecha->format('d/m/Y');
    }

    /**
     * Etiqueta legible de un grupo que devolvió AgrupadorFecha: "05/10/2026", "Sem. 05/10" u "octubre de 2026".
     */
    public static function etiquetaGrupo(string $clave, string $nivel): string
    {
        $zona = (string) config('app.timezone');

        return match ($nivel) {
            AgrupadorFecha::MES => self::mesYAnio(CarbonImmutable::createFromFormat('Y-m-d', $clave.'-01', $zona)->startOfDay()),
            AgrupadorFecha::SEMANA => 'Sem. '.CarbonImmutable::parse($clave, $zona)->format('d/m'),
            default => self::corta(CarbonImmutable::parse($clave, $zona)),
        };
    }
}
