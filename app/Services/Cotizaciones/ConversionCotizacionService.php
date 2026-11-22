<?php

namespace App\Services\Cotizaciones;

use App\Models\Cotizacion;
use App\Models\User;
use App\Models\Venta;
use App\Services\BitacoraService;
use Illuminate\Validation\ValidationException;

/**
 * CU20: reglas para convertir una cotización en venta desde el POS (flujo de CU11).
 */
class ConversionCotizacionService
{
    public function motivoBloqueo(Cotizacion $cotizacion): ?string
    {
        $codigo = $cotizacion->codigo_cotizacion;

        return match (true) {
            $cotizacion->estado === Cotizacion::CONVERTIDA => "La cotización {$codigo} ya fue convertida en venta.",
            $cotizacion->estado === Cotizacion::RECHAZADA => "La cotización {$codigo} fue rechazada y no se puede convertir.",
            $cotizacion->estado === Cotizacion::VENCIDA || ! $cotizacion->vigente() => "La cotización {$codigo} venció el {$cotizacion->fecha_vencimiento?->format('d/m/Y')}. Renuévala con precios actuales para venderla.",
            default => null,
        };
    }

    /**
     * EX-01 y EX-02: solo se convierte una cotización abierta y vigente.
     *
     * @throws ValidationException
     */
    public function exigirConvertible(Cotizacion $cotizacion): void
    {
        $motivo = $this->motivoBloqueo($cotizacion);

        if ($motivo !== null) {
            throw ValidationException::withMessages(['cotizacion_id' => $motivo]);
        }
    }

    /**
     * Precio cotizado por variante.
     *
     * @return array<int, float>
     */
    public function precios(Cotizacion $cotizacion): array
    {
        $cotizacion->loadMissing('detalles');
        $precios = [];

        foreach ($cotizacion->detalles as $detalle) {
            $precios[(int) $detalle->variante_id] = round((float) $detalle->precio_unitario, 2);
        }

        return $precios;
    }

    /**
     * EX-04: una línea puede mantener el precio cotizado solo si viene `cotizacion_id`, la cotización está
     * vigente y el precio coincide exactamente con el de `detalle_cotizacion` para esa variante.
     * Devuelve el precio a usar por variante, solo para las líneas que lo mantienen.
     *
     * @param  array<array-key, mixed>  $items
     * @return array<int, float>
     *
     * @throws ValidationException
     */
    public function preciosMantenidos(?Cotizacion $cotizacion, array $items): array
    {
        $mantenidos = [];
        $cotizados = null;

        foreach ($items as $indice => $item) {
            if (! is_array($item) || ($item['precio_unitario'] ?? null) === null || $item['precio_unitario'] === '') {
                continue;
            }

            if ($cotizacion === null) {
                throw ValidationException::withMessages([
                    "items.$indice.precio_unitario" => 'No se puede fijar el precio de una línea sin una cotización.',
                ]);
            }

            $this->exigirConvertible($cotizacion);
            $cotizados ??= $this->precios($cotizacion);

            $varianteId = (int) ($item['variante_id'] ?? 0);
            $cotizado = $cotizados[$varianteId] ?? null;
            $enviado = round((float) $item['precio_unitario'], 2);

            if ($cotizado === null || abs($enviado - $cotizado) >= 0.005) {
                throw ValidationException::withMessages([
                    "items.$indice.precio_unitario" => 'El precio de la línea no coincide con el de la cotización.',
                ]);
            }

            $mantenidos[$varianteId] = $cotizado;
        }

        return $mantenidos;
    }

    public function marcarConvertida(Cotizacion $cotizacion, Venta $venta, User $usuario): void
    {
        $anterior = $cotizacion->estado;

        $cotizacion->forceFill([
            'estado' => Cotizacion::CONVERTIDA,
            'fecha_estado' => now(),
            'actualizado_por' => $usuario->id,
        ])->save();

        BitacoraService::registrar(
            GestionCotizacionService::MODULO,
            'CONVERTIR_COTIZACION',
            'cotizacion',
            $cotizacion->id,
            ['estado' => $anterior],
            ['estado' => Cotizacion::CONVERTIDA, 'venta' => $venta->numero_venta, 'total' => $venta->total()],
            "Cotización {$cotizacion->codigo_cotizacion} convertida en la venta {$venta->numero_venta}",
            $usuario->id,
        );
    }
}
