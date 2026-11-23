<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Convierte una frase dictada en español ("productos más vendidos de octubre en Excel") en los
 * parámetros de un reporte predefinido. Es un analizador determinista: funciona sin IA y sin clave.
 *
 * Solo propone: nunca genera ni descarga nada. El formulario se llena con lo que se entendió
 * y la persona confirma o corrige.
 *
 * @phpstan-type Interpretacion array{tipo: string|null, periodo: string|null, desde: string|null, hasta: string|null, formato: string|null, filtros: array<string, int>, entendido: string, faltantes: list<string>, es_predefinido: bool}
 */
class InterpreteComandoVoz
{
    private const TIPOS = [
        'estado_resultados' => [' estado de resultados ', ' resultados '],
        'productos_mas_vendidos' => [' mas vendidos ', ' mas vendido ', ' top ', ' ranking '],
        'utilidad_bruta' => [' utilidad ', ' ganancia ', ' ganancias ', ' margen '],
        'gastos_flujo_caja' => [' gastos ', ' gasto ', ' flujo ', ' caja '],
        'compras_proveedor' => [' compras ', ' compra ', ' proveedor ', ' proveedores '],
        'ventas_periodo' => [' ventas ', ' venta ', ' vendimos ', ' vendido '],
    ];

    /** Pedidos que ningún reporte predefinido resuelve: se sugiere el Asistente (CU22). */
    private const FUERA_DEL_CATALOGO = [
        ' comparar ', ' compara ', ' comparacion ', ' contra ', ' versus ', ' vs ',
        ' tendencia ', ' proyeccion ', ' crecimiento ', ' evolucion ', ' promedio ',
        ' por marca ', ' por vendedor ', ' por cliente ',
    ];

    private const FORMATOS = [
        'pdf' => [' pdf '],
        'xlsx' => [' excel ', ' xlsx ', ' hoja de calculo '],
        'docx' => [' word ', ' docx '],
    ];

    private const PALABRAS_VACIAS = ['y', 'de', 'del', 'la', 'el', 'los', 'las', 'para'];

    /**
     * @return Interpretacion
     */
    public function interpretar(string $texto, ?CarbonImmutable $hoy = null): array
    {
        $hoy ??= PeriodoReporte::hoy();
        $normal = $this->normalizar($texto);
        $envuelto = ' '.$normal.' ';

        $tipo = $this->detectarTipo($envuelto);
        $periodo = $this->detectarPeriodo($normal, $hoy);
        $formato = $this->detectarFormato($envuelto);
        $filtros = $tipo === null ? [] : $this->detectarFiltros($normal, $tipo);
        $esPredefinido = $tipo !== null && ! $this->contieneAlguna($envuelto, self::FUERA_DEL_CATALOGO);

        $faltantes = [];

        if ($tipo === null) {
            $faltantes[] = 'tipo';
        }

        if ($periodo === null) {
            $faltantes[] = 'periodo';
        }

        return [
            'tipo' => $tipo,
            'periodo' => $periodo?->tipo,
            'desde' => $periodo?->desde->toDateString(),
            'hasta' => $periodo?->hasta->toDateString(),
            'formato' => $formato,
            'filtros' => $filtros,
            'entendido' => $this->resumen($tipo, $periodo, $formato, $filtros),
            'faltantes' => $faltantes,
            'es_predefinido' => $esPredefinido,
        ];
    }

    /**
     * El período que menciona un texto ("esta semana", "octubre", "del 1 al 15 de octubre"), o null.
     * Lo usa también el asistente virtual simulado.
     */
    public function periodoDe(string $texto, ?CarbonImmutable $hoy = null): ?PeriodoReporte
    {
        return $this->detectarPeriodo($this->normalizar($texto), $hoy ?? PeriodoReporte::hoy());
    }

    /**
     * Categoría y marca que menciona un texto, con el nombre tal como está en la base de datos.
     *
     * @return array{categoria: string|null, marca: string|null}
     */
    public function nombresMencionados(string $texto): array
    {
        $palabras = $this->singulares(explode(' ', $this->normalizar($texto)));

        return [
            'categoria' => $this->buscarFila('categoria', $palabras)?->nombre,
            'marca' => $this->buscarFila('marca', $palabras)?->nombre,
        ];
    }

    private function normalizar(string $texto): string
    {
        $texto = Str::ascii(mb_strtolower($texto));
        $texto = (string) preg_replace('/[^a-z0-9 ]+/', ' ', $texto);

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }

    private function detectarTipo(string $envuelto): ?string
    {
        foreach (self::TIPOS as $tipo => $palabras) {
            if ($this->contieneAlguna($envuelto, $palabras)) {
                return $tipo;
            }
        }

        return null;
    }

    private function detectarFormato(string $envuelto): ?string
    {
        foreach (self::FORMATOS as $formato => $palabras) {
            if ($this->contieneAlguna($envuelto, $palabras)) {
                return $formato;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $palabras
     */
    private function contieneAlguna(string $envuelto, array $palabras): bool
    {
        foreach ($palabras as $palabra) {
            if (str_contains($envuelto, $palabra)) {
                return true;
            }
        }

        return false;
    }

    private function detectarPeriodo(string $normal, CarbonImmutable $hoy): ?PeriodoReporte
    {
        $meses = implode('|', array_values(Fechas::MESES));

        try {
            if (preg_match('/del (\d{1,2}) al (\d{1,2}) de ('.$meses.')(?: de (\d{4}))?/', $normal, $m) === 1) {
                $anio = isset($m[4]) ? (int) $m[4] : $this->anioDelMes($this->numeroDeMes($m[3]), $hoy);
                $mes = $this->numeroDeMes($m[3]);

                return PeriodoReporte::resolver(
                    'personalizado',
                    sprintf('%04d-%02d-%02d', $anio, $mes, (int) $m[1]),
                    sprintf('%04d-%02d-%02d', $anio, $mes, (int) $m[2]),
                );
            }

            if (preg_match('/ultimos? (\d{1,3}) dias/', $normal, $m) === 1) {
                $dias = max(1, min(PeriodoReporte::MAXIMO_DIAS, (int) $m[1]));

                return PeriodoReporte::resolver(
                    'personalizado',
                    $hoy->subDays($dias - 1)->toDateString(),
                    $hoy->toDateString(),
                );
            }

            $envuelto = ' '.$normal.' ';

            return match (true) {
                str_contains($envuelto, ' semana pasada ') => PeriodoReporte::resolver('semanal', $hoy->subWeek()->toDateString()),
                str_contains($envuelto, ' esta semana ') => PeriodoReporte::resolver('semanal', $hoy->toDateString()),
                str_contains($envuelto, ' mes pasado ') => PeriodoReporte::resolver('mensual', $hoy->subMonthNoOverflow()->toDateString()),
                str_contains($envuelto, ' este mes ') => PeriodoReporte::resolver('mensual', $hoy->toDateString()),
                str_contains($envuelto, ' hoy ') => PeriodoReporte::resolver('diario', $hoy->toDateString()),
                str_contains($envuelto, ' ayer ') => PeriodoReporte::resolver('diario', $hoy->subDay()->toDateString()),
                preg_match('/\b('.$meses.')\b(?: de (\d{4})| (\d{4}))?/', $normal, $m) === 1 => PeriodoReporte::resolver(
                    'mensual',
                    sprintf(
                        '%04d-%02d-01',
                        ($m[2] ?? '') !== '' ? (int) $m[2] : (($m[3] ?? '') !== '' ? (int) $m[3] : $this->anioDelMes($this->numeroDeMes($m[1]), $hoy)),
                        $this->numeroDeMes($m[1]),
                    ),
                ),
                default => null,
            };
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function numeroDeMes(string $nombre): int
    {
        return (int) array_search($nombre, Fechas::MESES, true);
    }

    /** Sin año, un mes que todavía no llegó se entiende como el del año pasado. */
    private function anioDelMes(int $mes, CarbonImmutable $hoy): int
    {
        return $mes > $hoy->month ? $hoy->year - 1 : $hoy->year;
    }

    /**
     * @return array<string, int>
     */
    private function detectarFiltros(string $normal, string $tipo): array
    {
        $filtros = [];
        $palabras = $this->singulares(explode(' ', $normal));

        if (in_array($tipo, ['ventas_periodo', 'productos_mas_vendidos', 'utilidad_bruta'], true)) {
            $categoria = $this->buscarPorNombre('categoria', $palabras);
            $marca = $this->buscarPorNombre('marca', $palabras);

            if ($categoria !== null) {
                $filtros['categoria_id'] = $categoria;
            }

            if ($marca !== null) {
                $filtros['marca_id'] = $marca;
            }
        }

        if ($tipo === 'productos_mas_vendidos' && preg_match('/\b(?:top|los|las)\s+(\d{1,2})\b/', $normal, $m) === 1) {
            $filtros['limite'] = max(1, min(50, (int) $m[1]));
        }

        return $filtros;
    }

    /**
     * @param  list<string>  $palabras
     */
    private function buscarPorNombre(string $tabla, array $palabras): ?int
    {
        $fila = $this->buscarFila($tabla, $palabras);

        return $fila !== null ? (int) $fila->id : null;
    }

    /**
     * @param  list<string>  $palabras
     */
    private function buscarFila(string $tabla, array $palabras): ?\stdClass
    {
        $candidatos = DB::table($tabla)->orderBy('id')->get(['id', 'nombre']);

        foreach ($candidatos as $fila) {
            $tokens = array_values(array_filter(
                $this->singulares(explode(' ', $this->normalizar((string) $fila->nombre))),
                fn (string $token): bool => $token !== '' && ! in_array($token, self::PALABRAS_VACIAS, true),
            ));

            if ($tokens === []) {
                continue;
            }

            $todas = array_diff($tokens, $palabras) === [];
            $primera = count($tokens) > 1 && mb_strlen($tokens[0]) >= 6 && in_array($tokens[0], $palabras, true);

            if ($todas || $primera) {
                return $fila;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $palabras
     * @return list<string>
     */
    private function singulares(array $palabras): array
    {
        return array_map(
            fn (string $palabra): string => mb_strlen($palabra) > 3 && str_ends_with($palabra, 's') ? mb_substr($palabra, 0, -1) : $palabra,
            $palabras,
        );
    }

    /**
     * @param  array<string, int>  $filtros
     */
    private function resumen(?string $tipo, ?PeriodoReporte $periodo, ?string $formato, array $filtros): string
    {
        $partes = [];

        if ($tipo !== null) {
            $partes[] = CatalogoReportes::titulo($tipo);
        }

        if ($periodo !== null) {
            $partes[] = match ($periodo->tipo) {
                'mensual' => Fechas::mesYAnio($periodo->desde),
                'diario' => Fechas::corta($periodo->desde),
                default => $periodo->etiqueta(),
            };
        }

        if (isset($filtros['categoria_id'])) {
            $partes[] = 'categoría '.DB::table('categoria')->where('id', $filtros['categoria_id'])->value('nombre');
        }

        if (isset($filtros['marca_id'])) {
            $partes[] = 'marca '.DB::table('marca')->where('id', $filtros['marca_id'])->value('nombre');
        }

        if (isset($filtros['limite'])) {
            $partes[] = 'los '.$filtros['limite'].' primeros';
        }

        if ($formato !== null) {
            $partes[] = ['pdf' => 'PDF', 'xlsx' => 'Excel', 'docx' => 'Word'][$formato];
        }

        return implode(' · ', $partes);
    }
}
