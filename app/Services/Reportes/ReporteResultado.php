<?php

namespace App\Services\Reportes;

/**
 * Resultado de un reporte, listo para mostrarse en pantalla y para exportarse.
 * La pantalla, el PDF, el Excel y el Word leen exactamente esta misma estructura,
 * así que las cifras y los totales siempre coinciden.
 *
 * @phpstan-type Columna array{clave: string, etiqueta: string, tipo: 'texto'|'entero'|'moneda'|'porcentaje'|'fecha'}
 * @phpstan-type Fila array<string, string|int|float|null>
 * @phpstan-type Grafico array{tipo: 'barra'|'linea'|'torta'|'ninguno', etiquetas: list<string>, series: list<array{nombre: string, valores: list<float>}>}
 * @phpstan-type Encabezado array{tienda: string, ubicacion: string, desde: string, hasta: string, periodo: string, usuario: string, generado: string}
 * @phpstan-type Parametros array{tipo: string, periodo: string, desde: string, hasta: string, filtros: array<string, int|string>}
 */
final readonly class ReporteResultado
{
    /**
     * @param  Encabezado  $encabezado
     * @param  list<Columna>  $columnas
     * @param  list<Fila>  $filas
     * @param  Fila  $totales
     * @param  list<string>  $advertencias
     * @param  Grafico  $grafico
     * @param  Parametros  $parametros
     */
    public function __construct(
        public string $clave,
        public string $titulo,
        public array $encabezado,
        public array $columnas,
        public array $filas,
        public array $totales,
        public array $advertencias,
        public array $grafico,
        public array $parametros,
    ) {}

    public function vacio(): bool
    {
        return $this->filas === [];
    }

    /** reporte-<clave>-<desde>_<hasta>.<extensión> */
    public function nombreArchivo(string $extension): string
    {
        return sprintf('reporte-%s-%s_%s.%s', $this->clave, $this->parametros['desde'], $this->parametros['hasta'], $extension);
    }

    /**
     * @return array<string, mixed>
     */
    public function aArray(): array
    {
        return [
            'clave' => $this->clave,
            'titulo' => $this->titulo,
            'encabezado' => $this->encabezado,
            'columnas' => $this->columnas,
            'filas' => $this->filas,
            'totales' => $this->totales,
            'advertencias' => $this->advertencias,
            'grafico' => $this->grafico,
            'parametros' => $this->parametros,
            'vacio' => $this->vacio(),
        ];
    }
}
