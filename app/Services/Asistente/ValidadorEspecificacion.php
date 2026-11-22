<?php

namespace App\Services\Asistente;

use App\Services\Reportes\PeriodoReporte;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Valida la especificación que propone la IA contra una lista blanca y devuelve una versión normalizada.
 * Si algo no cuadra (métrica, dimensión, filtro, período, claves de más) lanza EspecificacionInvalida
 * y no se ejecuta ninguna consulta. La IA nunca escribe SQL: solo elige entre lo que este catálogo permite.
 *
 * @phpstan-type Periodo array{desde: string, hasta: string}
 * @phpstan-type Especificacion array{metrica: string, dimensiones: list<string>, filtros: array<string, string>, periodo: Periodo, comparar_con: Periodo|null, grafico: 'barra'|'linea'|'torta'|'tabla', orden: 'asc'|'desc', limite: int}
 */
class ValidadorEspecificacion
{
    private const CLAVES = ['metrica', 'dimensiones', 'filtros', 'periodo', 'comparar_con', 'grafico', 'orden', 'limite'];

    private const MAXIMO_FUTURO_DIAS = 31;

    private const MAXIMO_TEXTO_FILTRO = 80;

    /**
     * @return Especificacion
     *
     * @throws EspecificacionInvalida
     */
    public function validar(mixed $especificacion, ?CarbonImmutable $hoy = null): array
    {
        $hoy ??= PeriodoReporte::hoy();

        if (! is_array($especificacion)) {
            throw new EspecificacionInvalida('La especificación no es un objeto.');
        }

        $sobrantes = array_diff(array_keys($especificacion), self::CLAVES);

        if ($sobrantes !== []) {
            throw new EspecificacionInvalida('Claves no permitidas: '.implode(', ', array_map('strval', $sobrantes)).'.');
        }

        $metrica = $this->metrica($especificacion['metrica'] ?? null);
        $definicion = CatalogoAsistente::metricas()[$metrica];
        $dimensiones = $this->dimensiones($especificacion['dimensiones'] ?? [], $metrica, $definicion['dimensiones']);
        $filtros = $this->filtros($especificacion['filtros'] ?? [], $metrica, $definicion['filtros']);
        $periodo = $this->periodo($especificacion['periodo'] ?? null, $hoy, 'periodo');
        $comparar = $especificacion['comparar_con'] ?? null;

        $this->reglasEspeciales($metrica, $dimensiones, $filtros);

        if ($comparar !== null) {
            if (count($dimensiones) > 1 || $metrica === 'stock') {
                throw new EspecificacionInvalida('Esta consulta no se puede comparar con otro período.');
            }

            $comparar = $this->periodo($comparar, $hoy, 'comparar_con');
        }

        return [
            'metrica' => $metrica,
            'dimensiones' => $dimensiones,
            'filtros' => $filtros,
            'periodo' => $periodo,
            'comparar_con' => $comparar,
            'grafico' => $this->grafico($especificacion['grafico'] ?? null, $dimensiones),
            'orden' => $this->orden($especificacion['orden'] ?? null),
            'limite' => $this->limite($especificacion['limite'] ?? null),
        ];
    }

    private function metrica(mixed $valor): string
    {
        if (! is_string($valor) || ! in_array($valor, CatalogoAsistente::claves(), true)) {
            throw new EspecificacionInvalida('Métrica desconocida.');
        }

        return $valor;
    }

    /**
     * @param  list<string>  $permitidas
     * @return list<string>
     */
    private function dimensiones(mixed $valor, string $metrica, array $permitidas): array
    {
        if (! is_array($valor) || ! array_is_list($valor)) {
            throw new EspecificacionInvalida('Las dimensiones deben ser una lista.');
        }

        if (count($valor) > 2) {
            throw new EspecificacionInvalida('Máximo dos dimensiones.');
        }

        $dimensiones = [];

        foreach ($valor as $dimension) {
            if (! is_string($dimension) || ! in_array($dimension, CatalogoAsistente::DIMENSIONES, true)) {
                throw new EspecificacionInvalida('Dimensión desconocida.');
            }

            if (! in_array($dimension, $permitidas, true)) {
                throw new EspecificacionInvalida("La dimensión $dimension no se puede usar con $metrica.");
            }

            if (in_array($dimension, $dimensiones, true)) {
                throw new EspecificacionInvalida('Dimensión repetida.');
            }

            $dimensiones[] = $dimension;
        }

        if (count(array_filter($dimensiones, CatalogoAsistente::esTemporal(...))) > 1) {
            throw new EspecificacionInvalida('Solo una dimensión de tiempo.');
        }

        return $dimensiones;
    }

    /**
     * @param  list<string>  $permitidos
     * @return array<string, string>
     */
    private function filtros(mixed $valor, string $metrica, array $permitidos): array
    {
        if (! is_array($valor)) {
            throw new EspecificacionInvalida('Los filtros deben ser un objeto.');
        }

        $filtros = [];

        foreach ($valor as $clave => $texto) {
            if (! is_string($clave) || ! in_array($clave, CatalogoAsistente::FILTROS, true)) {
                throw new EspecificacionInvalida('Filtro desconocido.');
            }

            if ($texto === null) {
                continue;
            }

            if (! is_string($texto)) {
                throw new EspecificacionInvalida("El filtro $clave debe ser texto.");
            }

            if (! in_array($clave, $permitidos, true)) {
                throw new EspecificacionInvalida("El filtro $clave no se puede usar con $metrica.");
            }

            $texto = trim($texto);

            if ($texto === '' || mb_strlen($texto) > self::MAXIMO_TEXTO_FILTRO || preg_match('/[\x00-\x1F]/', $texto) === 1) {
                throw new EspecificacionInvalida("El valor del filtro $clave no es válido.");
            }

            $filtros[$clave] = $clave === 'estado' ? $this->estado($metrica, $texto) : $texto;
        }

        return $filtros;
    }

    private function estado(string $metrica, string $texto): string
    {
        [$valores, $normalizado] = match ($metrica) {
            'pedidos' => [CatalogoAsistente::ESTADOS_PEDIDO, strtoupper($texto)],
            'cotizaciones' => [CatalogoAsistente::ESTADOS_COTIZACION, strtoupper($texto)],
            default => [CatalogoAsistente::ESTADOS_STOCK, strtolower($texto)],
        };

        if (! in_array($normalizado, $valores, true)) {
            throw new EspecificacionInvalida('Estado no permitido.');
        }

        return $normalizado;
    }

    /**
     * Combinaciones que la matriz métrica × dimensión permite solo con condiciones.
     * Por método de pago se suma lo cobrado (tabla de pagos), que no se puede cruzar con productos.
     *
     * @param  list<string>  $dimensiones
     * @param  array<string, string>  $filtros
     */
    private function reglasEspeciales(string $metrica, array $dimensiones, array $filtros): void
    {
        if (! in_array($metrica, ['ventas_monto', 'ventas_cantidad'], true) || ! in_array('metodo_pago', $dimensiones, true)) {
            return;
        }

        $otras = array_filter($dimensiones, fn (string $d): bool => $d !== 'metodo_pago' && ! CatalogoAsistente::esTemporal($d));

        if ($otras !== [] || array_diff(array_keys($filtros), ['metodo_pago']) !== []) {
            throw new EspecificacionInvalida('Por método de pago solo se puede combinar con una dimensión de tiempo.');
        }
    }

    /**
     * @return Periodo
     */
    private function periodo(mixed $valor, CarbonImmutable $hoy, string $campo): array
    {
        if (! is_array($valor) || array_diff(array_keys($valor), ['desde', 'hasta']) !== []) {
            throw new EspecificacionInvalida("El $campo debe tener desde y hasta.");
        }

        $desde = $this->fecha($valor['desde'] ?? null, $campo);
        $hasta = $this->fecha($valor['hasta'] ?? null, $campo);

        if ($hasta->lessThan($desde)) {
            throw new EspecificacionInvalida("En $campo, la fecha de fin es anterior a la de inicio.");
        }

        if ((int) floor($desde->diffInDays($hasta)) + 1 > PeriodoReporte::MAXIMO_DIAS) {
            throw new EspecificacionInvalida("El $campo supera los ".PeriodoReporte::MAXIMO_DIAS.' días.');
        }

        if ($hasta->greaterThan($hoy->addDays(self::MAXIMO_FUTURO_DIAS))) {
            throw new EspecificacionInvalida("El $campo llega demasiado al futuro.");
        }

        return ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()];
    }

    private function fecha(mixed $valor, string $campo): CarbonImmutable
    {
        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            throw new EspecificacionInvalida("Fecha inválida en $campo.");
        }

        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor, (string) config('app.timezone'));
        } catch (Throwable) {
            throw new EspecificacionInvalida("Fecha inválida en $campo.");
        }

        if ($fecha === null || $fecha->format('Y-m-d') !== $valor) {
            throw new EspecificacionInvalida("Fecha inválida en $campo.");
        }

        return $fecha;
    }

    /**
     * @param  list<string>  $dimensiones
     * @return 'barra'|'linea'|'torta'|'tabla'
     */
    private function grafico(mixed $valor, array $dimensiones): string
    {
        if ($valor === null) {
            return match (true) {
                $dimensiones === [] => 'tabla',
                CatalogoAsistente::esTemporal($dimensiones[0]) || (isset($dimensiones[1]) && CatalogoAsistente::esTemporal($dimensiones[1])) => 'linea',
                default => 'barra',
            };
        }

        return match ($valor) {
            'barra' => 'barra',
            'linea' => 'linea',
            'torta' => 'torta',
            'tabla' => 'tabla',
            default => throw new EspecificacionInvalida('Tipo de gráfico desconocido.'),
        };
    }

    /**
     * @return 'asc'|'desc'
     */
    private function orden(mixed $valor): string
    {
        return match ($valor) {
            null, 'desc' => 'desc',
            'asc' => 'asc',
            default => throw new EspecificacionInvalida('Orden desconocido.'),
        };
    }

    private function limite(mixed $valor): int
    {
        if ($valor === null) {
            return 20;
        }

        if (! is_int($valor) || $valor < 1 || $valor > 50) {
            throw new EspecificacionInvalida('El límite debe estar entre 1 y 50.');
        }

        return $valor;
    }
}
