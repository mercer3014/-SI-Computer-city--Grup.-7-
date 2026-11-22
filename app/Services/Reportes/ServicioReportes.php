<?php

namespace App\Services\Reportes;

use App\Models\User;
use App\Services\Tienda\ConfiguracionTiendaService;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Genera un reporte predefinido y le agrega el encabezado (tienda, período, usuario y fecha de generación).
 */
class ServicioReportes
{
    public function __construct(private ConfiguracionTiendaService $tienda) {}

    /**
     * @param  array<string, int|string>  $filtros
     */
    public function generar(string $clave, PeriodoReporte $periodo, array $filtros, User $usuario): ReporteResultado
    {
        $catalogo = CatalogoReportes::todos();

        if (! isset($catalogo[$clave])) {
            throw new InvalidArgumentException('El reporte elegido no existe.');
        }

        $filtros = array_intersect_key($filtros, array_flip($catalogo[$clave]['filtros']));

        return $this->armar($clave, $catalogo[$clave]['titulo'], app($catalogo[$clave]['generador']), $periodo, $filtros, $usuario);
    }

    /**
     * Arma el resultado con encabezado para un generador que no está en el catálogo de reportes
     * (por ejemplo, los productos más visitados de CU20).
     *
     * @param  array<string, int|string>  $filtros
     */
    public function armar(string $clave, string $titulo, GeneradorReporte $generador, PeriodoReporte $periodo, array $filtros, User $usuario): ReporteResultado
    {
        $datos = $generador->generar($periodo, $filtros);
        $tienda = $this->tienda->actual();

        return new ReporteResultado(
            clave: $clave,
            titulo: $titulo,
            encabezado: [
                'tienda' => $tienda['nombre'],
                'ubicacion' => $tienda['ubicacion'],
                'desde' => Fechas::corta($periodo->desde),
                'hasta' => Fechas::corta($periodo->hasta),
                'periodo' => $periodo->etiqueta(),
                'usuario' => $usuario->name !== '' ? $usuario->name : $usuario->email,
                'generado' => CarbonImmutable::now((string) config('app.timezone'))->format('d/m/Y H:i'),
            ],
            columnas: $datos['columnas'],
            filas: $datos['filas'],
            totales: $datos['totales'],
            advertencias: $datos['advertencias'],
            grafico: $datos['grafico'],
            parametros: [
                'tipo' => $clave,
                'periodo' => $periodo->tipo,
                'desde' => $periodo->desde->toDateString(),
                'hasta' => $periodo->hasta->toDateString(),
                'filtros' => $filtros,
            ],
        );
    }
}
