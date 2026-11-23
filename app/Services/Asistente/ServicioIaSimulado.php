<?php

namespace App\Services\Asistente;

use App\Services\Reportes\InterpreteComandoVoz;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Tienda\ConfiguracionTiendaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Respuestas de demostración con reglas fijas en español, para probar el asistente sin llamar a xAI.
 * Solo se usa con ASISTENTE_SIMULADO=true en los entornos local y testing (ver AppServiceProvider).
 * Devuelve el mismo tipo de especificación que la IA real y pasa por la misma validación.
 */
class ServicioIaSimulado implements ServicioIa
{
    private const METRICAS = [
        'ventas_cantidad' => [' cuantas ventas ', ' numero de ventas ', ' cantidad de ventas ', ' tickets '],
        'unidades_vendidas' => [' unidades ', ' piezas '],
        'utilidad_bruta' => [' utilidad ', ' ganancia ', ' ganancias ', ' margen '],
        'gastos' => [' gastos ', ' gasto ', ' gastamos '],
        'compras' => [' compras ', ' compramos ', ' compra '],
        'saldo_caja' => [' caja ', ' saldo ', ' flujo '],
        'pedidos' => [' pedidos ', ' pedido '],
        'cotizaciones' => [' cotizaciones ', ' cotizacion '],
        'calificacion_promedio' => [' calificacion ', ' calificaciones ', ' opiniones ', ' estrellas ', ' resenas '],
        'ventas_monto' => [' ventas ', ' venta ', ' vendimos ', ' vendido ', ' vendemos ', ' facturamos ', ' ingresos '],
    ];

    private const DIMENSIONES = [
        'dia' => [' por dia ', ' diaria ', ' diario ', ' dia a dia ', ' cada dia '],
        'semana' => [' por semana ', ' semanal ', ' semana a semana ', ' cada semana '],
        'mes' => [' por mes ', ' mensual ', ' mes a mes ', ' cada mes '],
        'producto' => [' por producto ', ' por productos '],
        'categoria' => [' por categoria ', ' por categorias '],
        'marca' => [' por marca ', ' por marcas '],
        'proveedor' => [' por proveedor ', ' por proveedores '],
        'metodo_pago' => [' por metodo de pago ', ' por forma de pago ', ' por metodo '],
        'estado' => [' por estado '],
    ];

    private const MARCAS_DE_COMPARACION = [' compara ', ' comparar ', ' comparalo ', ' comparala ', ' comparacion ', ' contra ', ' versus ', ' vs '];

    public function __construct(
        private InterpreteComandoVoz $voz,
        private ConfiguracionTiendaService $tienda,
    ) {}

    public function interpretar(array $historial, string $consulta): RespuestaIa
    {
        $hoy = PeriodoReporte::hoy();
        $normal = $this->normalizar($consulta);
        $envuelto = ' '.$normal.' ';

        $tienda = $this->datosDeLaTienda($normal, $envuelto);

        if ($tienda !== null) {
            return $tienda;
        }

        $base = ConstructorPrompt::ultimaEspecificacion($historial);
        [$izquierda, $derecha] = $this->partirComparacion($normal);
        $metrica = $this->metrica(' '.$izquierda.' ');

        if ($metrica === null && $base !== null && $derecha !== null) {
            return $this->refinar($base, $derecha, $hoy);
        }

        if ($metrica === null) {
            return new RespuestaIa('no_soportado', 'No tengo cómo responder eso con los datos del negocio.');
        }

        return $this->armar($metrica, $izquierda, $derecha, $hoy);
    }

    private function armar(string $metrica, string $texto, ?string $comparacion, CarbonImmutable $hoy): RespuestaIa
    {
        $envuelto = ' '.$texto.' ';
        $definicion = CatalogoAsistente::metricas()[$metrica];
        $nombres = $this->voz->nombresMencionados($texto);

        $filtros = [];

        foreach (['categoria', 'marca'] as $clave) {
            if ($nombres[$clave] !== null && in_array($clave, $definicion['filtros'], true)) {
                $filtros[$clave] = $nombres[$clave];
            }
        }

        $dimensiones = $this->dimensiones($envuelto, $metrica, $definicion['dimensiones']);

        if ($metrica === 'stock') {
            $filtros['estado'] = str_contains($envuelto, ' agotad') || str_contains($envuelto, ' sin stock ') ? 'agotado' : 'bajo_minimo';
            $dimensiones = $dimensiones === [] ? ['producto'] : $dimensiones;
        }

        $periodo = $this->periodo($texto, $hoy);

        $especificacion = [
            'metrica' => $metrica,
            'dimensiones' => $dimensiones,
            'filtros' => $filtros,
            'periodo' => $periodo,
        ];

        if ($comparacion !== null) {
            $especificacion['comparar_con'] = $this->periodo($comparacion, $hoy, $periodo);
        }

        $esReporte = $comparacion !== null || ($dimensiones !== [] && $metrica !== 'stock');

        return new RespuestaIa(
            $esReporte ? 'reporte' : 'consulta',
            $esReporte ? 'Armé el reporte con lo que pediste.' : 'Esto es lo que encontré.',
            $especificacion,
        );
    }

    /**
     * "ahora compáralo con septiembre": repite la última especificación y agrega el período de comparación.
     *
     * @param  array<array-key, mixed>  $base
     */
    private function refinar(array $base, string $comparacion, CarbonImmutable $hoy): RespuestaIa
    {
        $actual = $base['periodo'] ?? null;
        $anterior = is_array($actual)
            ? ['desde' => (string) ($actual['desde'] ?? ''), 'hasta' => (string) ($actual['hasta'] ?? '')]
            : null;

        $base['comparar_con'] = $this->periodo($comparacion, $hoy, $anterior);

        return new RespuestaIa('reporte', 'Listo, ahora con la comparación.', $base);
    }

    /**
     * @param  array{desde: string, hasta: string}|null  $referencia  período contra el que se compara cuando el texto no dice cuál
     * @return array{desde: string, hasta: string}
     */
    private function periodo(string $texto, CarbonImmutable $hoy, ?array $referencia = null): array
    {
        $texto = (string) preg_replace('/\bultimo mes\b/', 'mes pasado', $texto);
        $texto = (string) preg_replace('/\bultima semana\b/', 'semana pasada', $texto);

        if (str_contains(' '.$texto.' ', ' este ano ')) {
            return ['desde' => $hoy->startOfYear()->toDateString(), 'hasta' => $hoy->toDateString()];
        }

        $periodo = $this->voz->periodoDe($texto, $hoy);

        if ($periodo === null && $referencia !== null && str_contains($texto, 'anterior')) {
            $desde = CarbonImmutable::parse($referencia['desde']);
            $hasta = CarbonImmutable::parse($referencia['hasta']);
            $dias = (int) floor($desde->diffInDays($hasta)) + 1;

            return ['desde' => $desde->subDays($dias)->toDateString(), 'hasta' => $desde->subDay()->toDateString()];
        }

        $periodo ??= PeriodoReporte::resolver('mensual', $hoy->toDateString());

        return ['desde' => $periodo->desde->toDateString(), 'hasta' => $periodo->hasta->toDateString()];
    }

    private function metrica(string $envuelto): ?string
    {
        if (str_contains($envuelto, ' stock ') || str_contains($envuelto, ' agotad') || str_contains($envuelto, ' existencias ') || str_contains($envuelto, ' inventario ')) {
            return 'stock';
        }

        foreach (self::METRICAS as $metrica => $palabras) {
            foreach ($palabras as $palabra) {
                if (str_contains($envuelto, $palabra)) {
                    return $metrica;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $permitidas
     * @return list<string>
     */
    private function dimensiones(string $envuelto, string $metrica, array $permitidas): array
    {
        $porGasto = $metrica === 'gastos' && (str_contains($envuelto, ' por categoria ') || str_contains($envuelto, ' por categorias '));
        $dimensiones = [];

        foreach (self::DIMENSIONES as $dimension => $palabras) {
            foreach ($palabras as $palabra) {
                if (str_contains($envuelto, $palabra)) {
                    $dimensiones[] = $dimension;

                    break;
                }
            }
        }

        if ($porGasto) {
            $dimensiones = array_values(array_diff($dimensiones, ['categoria']));
            $dimensiones[] = 'categoria_gasto';
        }

        $dimensiones = array_values(array_filter($dimensiones, fn (string $d): bool => in_array($d, $permitidas, true)));
        usort($dimensiones, fn (string $a, string $b): int => (int) CatalogoAsistente::esTemporal($b) <=> (int) CatalogoAsistente::esTemporal($a));

        $temporales = array_values(array_filter($dimensiones, CatalogoAsistente::esTemporal(...)));
        $otras = array_values(array_filter($dimensiones, fn (string $d): bool => ! CatalogoAsistente::esTemporal($d)));

        return array_slice([...array_slice($temporales, 0, 1), ...$otras], 0, 2);
    }

    /**
     * Separa "ventas del mes contra septiembre" en lo que se pide y contra qué se compara.
     *
     * @return array{0: string, 1: string|null}
     */
    private function partirComparacion(string $normal): array
    {
        $envuelto = ' '.$normal.' ';

        foreach (self::MARCAS_DE_COMPARACION as $marca) {
            $posicion = strrpos($envuelto, $marca);

            if ($posicion === false) {
                continue;
            }

            $izquierda = trim(substr($envuelto, 0, $posicion));
            $derecha = trim(substr($envuelto, $posicion + strlen($marca)));

            if (str_contains(' '.$derecha, ' con ')) {
                $derecha = trim((string) preg_replace('/^.*?\bcon\b/', '', $derecha));
            }

            return [$izquierda, $derecha !== '' ? $derecha : null];
        }

        if (str_contains($envuelto, ' compar') && preg_match('/\bcon\b(.+)$/', $normal, $partes) === 1) {
            return [trim((string) preg_replace('/\bcon\b.+$/', '', $normal)), trim($partes[1])];
        }

        return [$normal, null];
    }

    private function datosDeLaTienda(string $normal, string $envuelto): ?RespuestaIa
    {
        $tienda = $this->tienda->actual();

        if (preg_match('/^(hola|buenas|buenos dias|buen dia|buenas tardes|buenas noches)\b/', $normal) === 1) {
            return new RespuestaIa('respuesta', '¡Hola! Soy el asistente de '.$tienda['nombre'].'. Pregúntame por ventas, stock, gastos o compras.');
        }

        foreach ([' hora ', ' horas ', ' horario ', ' horarios ', ' abren ', ' abrimos ', ' cierran ', ' cerramos '] as $palabra) {
            if (str_contains($envuelto, $palabra)) {
                $dias = array_map(
                    fn (array $dia): string => $dia['dia'].': '.($dia['horario'] ?? 'cerrado'),
                    $tienda['horarios'],
                );

                return new RespuestaIa('respuesta', 'Atendemos así: '.implode('; ', $dias).'.');
            }
        }

        foreach ([' direccion ', ' ubicacion ', ' donde estamos ', ' donde queda '] as $palabra) {
            if (str_contains($envuelto, $palabra)) {
                return new RespuestaIa('respuesta', 'Estamos en '.($tienda['ubicacion'] !== '' ? $tienda['ubicacion'] : 'la dirección que figura en la configuración de la tienda').'.');
            }
        }

        return null;
    }

    private function normalizar(string $texto): string
    {
        $texto = (string) preg_replace('/[^a-z0-9 ]+/', ' ', Str::ascii(mb_strtolower($texto)));

        return trim((string) preg_replace('/\s+/', ' ', $texto));
    }
}
