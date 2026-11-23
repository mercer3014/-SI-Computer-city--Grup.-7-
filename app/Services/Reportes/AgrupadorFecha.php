<?php

namespace App\Services\Reportes;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Expresión SQL para agrupar una columna de fecha por día, semana (desde el lunes) o mes.
 * Es el único lugar donde PostgreSQL y SQLite difieren; el resto de las consultas es común.
 *
 * Las expresiones devuelven texto ordenable: "2026-10-05", "2026-10-05" (lunes de la semana) y "2026-10".
 * `$columna` siempre es un literal del código, nunca un dato del usuario.
 */
final class AgrupadorFecha
{
    public const DIA = 'dia';

    public const SEMANA = 'semana';

    public const MES = 'mes';

    public const NIVELES = [self::DIA, self::SEMANA, self::MES];

    /**
     * @param  literal-string  $columna
     * @return literal-string
     */
    public static function expresion(string $columna, string $nivel): string
    {
        $postgres = DB::getDriverName() === 'pgsql';

        return match ($nivel) {
            self::DIA => $postgres
                ? "to_char($columna, 'YYYY-MM-DD')"
                : "strftime('%Y-%m-%d', $columna)",
            self::SEMANA => $postgres
                ? "to_char(date_trunc('week', $columna), 'YYYY-MM-DD')"
                : "date($columna, 'weekday 0', '-6 days')",
            self::MES => $postgres
                ? "to_char($columna, 'YYYY-MM')"
                : "strftime('%Y-%m', $columna)",
            default => throw new InvalidArgumentException("Nivel de agrupación desconocido: $nivel"),
        };
    }

    /**
     * Claves ordenadas de todos los grupos del período, para rellenar con ceros los que no tuvieron datos.
     * Tienen el mismo formato que devuelve `expresion()`.
     *
     * @return list<string>
     */
    public static function claves(PeriodoReporte $periodo, string $nivel): array
    {
        $claves = [];
        $cursor = match ($nivel) {
            self::MES => $periodo->desde->startOfMonth(),
            self::SEMANA => $periodo->desde->startOfWeek(),
            default => $periodo->desde,
        };

        while ($cursor->lessThanOrEqualTo($periodo->hasta)) {
            $claves[] = $nivel === self::MES ? $cursor->format('Y-m') : $cursor->toDateString();
            $cursor = match ($nivel) {
                self::MES => $cursor->addMonth(),
                self::SEMANA => $cursor->addWeek(),
                default => $cursor->addDay(),
            };
        }

        return $claves;
    }

    /** Día para rangos cortos, semana para rangos medianos y mes para rangos largos. */
    public static function sugerido(int $dias): string
    {
        return match (true) {
            $dias <= 31 => self::DIA,
            $dias <= 120 => self::SEMANA,
            default => self::MES,
        };
    }
}
