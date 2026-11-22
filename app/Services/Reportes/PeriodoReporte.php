<?php

namespace App\Services\Reportes;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Rango de fechas de un reporte, en la zona horaria de la aplicación (America/La_Paz).
 *
 * Con "diario", "semanal" y "mensual" el rango sale de una fecha de referencia (hoy si no hay).
 * Con "personalizado" el rango es exactamente desde..hasta.
 */
final class PeriodoReporte
{
    public const TIPOS = ['diario', 'semanal', 'mensual', 'personalizado'];

    public const MAXIMO_DIAS = 366;

    private function __construct(
        public readonly string $tipo,
        public readonly CarbonImmutable $desde,
        public readonly CarbonImmutable $hasta,
    ) {}

    public static function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('app.timezone'))->startOfDay();
    }

    /**
     * @throws InvalidArgumentException con el mensaje que ve el usuario
     */
    public static function resolver(string $tipo, ?string $desde = null, ?string $hasta = null): self
    {
        if (! in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException('El período elegido no existe.');
        }

        if ($tipo === 'personalizado') {
            if (($desde ?? '') === '' || ($hasta ?? '') === '') {
                throw new InvalidArgumentException('Indicá la fecha de inicio y la de fin del período.');
            }

            return self::validado($tipo, self::fecha($desde), self::fecha($hasta));
        }

        $referencia = ($desde ?? '') !== '' ? self::fecha($desde) : self::hoy();

        return match ($tipo) {
            'diario' => self::validado($tipo, $referencia, $referencia),
            'semanal' => self::validado(
                $tipo,
                $referencia->startOfWeek(CarbonInterface::MONDAY),
                $referencia->endOfWeek(CarbonInterface::SUNDAY)->startOfDay(),
            ),
            default => self::validado(
                $tipo,
                $referencia->startOfMonth(),
                $referencia->endOfMonth()->startOfDay(),
            ),
        };
    }

    public function dias(): int
    {
        return (int) floor($this->desde->diffInDays($this->hasta)) + 1;
    }

    /** Inicio del rango como texto para comparar con columnas de fecha y hora. */
    public function inicio(): string
    {
        return $this->desde->startOfDay()->toDateTimeString();
    }

    /** Fin del rango (23:59:59 del último día) como texto. */
    public function fin(): string
    {
        return $this->hasta->endOfDay()->toDateTimeString();
    }

    /** "01/10/2026 – 31/10/2026" */
    public function etiqueta(): string
    {
        return $this->desde->equalTo($this->hasta)
            ? Fechas::corta($this->desde)
            : Fechas::corta($this->desde).' – '.Fechas::corta($this->hasta);
    }

    private static function fecha(?string $texto): CarbonImmutable
    {
        try {
            return CarbonImmutable::parse((string) $texto, (string) config('app.timezone'))->startOfDay();
        } catch (\Throwable) {
            throw new InvalidArgumentException('Una de las fechas del período no es válida.');
        }
    }

    private static function validado(string $tipo, CarbonImmutable $desde, CarbonImmutable $hasta): self
    {
        if ($hasta->lessThan($desde)) {
            throw new InvalidArgumentException('La fecha de fin no puede ser anterior a la de inicio.');
        }

        $periodo = new self($tipo, $desde, $hasta);

        if ($periodo->dias() > self::MAXIMO_DIAS) {
            throw new InvalidArgumentException('El período no puede superar los '.self::MAXIMO_DIAS.' días.');
        }

        return $periodo;
    }
}
