<?php

namespace App\Services\Reportes;

/**
 * Formato de números a la boliviana ("Bs 1.234,50"), para la pantalla, el PDF y el Word.
 * El Excel guarda números reales con formato de celda.
 */
final class Formato
{
    public static function moneda(float $valor): string
    {
        return 'Bs '.number_format($valor, 2, ',', '.');
    }

    public static function entero(float|int $valor): string
    {
        return number_format($valor, 0, ',', '.');
    }

    public static function decimal(float $valor): string
    {
        return number_format($valor, 2, ',', '.');
    }

    public static function porcentaje(float $valor): string
    {
        return number_format($valor, 2, ',', '.').' %';
    }

    /**
     * @param  'texto'|'entero'|'decimal'|'moneda'|'porcentaje'|'fecha'  $tipo
     */
    public static function valor(string|int|float|null $valor, string $tipo): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }

        if (! is_numeric($valor)) {
            return (string) $valor;
        }

        return match ($tipo) {
            'moneda' => self::moneda((float) $valor),
            'entero' => self::entero((float) $valor),
            'decimal' => self::decimal((float) $valor),
            'porcentaje' => self::porcentaje((float) $valor),
            default => (string) $valor,
        };
    }
}
