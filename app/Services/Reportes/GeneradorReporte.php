<?php

namespace App\Services\Reportes;

/**
 * Calcula un reporte predefinido. Devuelve solo el contenido; el encabezado
 * (tienda, usuario, fecha de generación) lo agrega ServicioReportes.
 *
 * @phpstan-import-type Columna from ReporteResultado
 * @phpstan-import-type Fila from ReporteResultado
 * @phpstan-import-type Grafico from ReporteResultado
 *
 * @phpstan-type Datos array{columnas: list<Columna>, filas: list<Fila>, totales: Fila, advertencias: list<string>, grafico: Grafico}
 */
interface GeneradorReporte
{
    /**
     * @param  array<string, int|string>  $filtros
     * @return Datos
     */
    public function generar(PeriodoReporte $periodo, array $filtros): array;
}
