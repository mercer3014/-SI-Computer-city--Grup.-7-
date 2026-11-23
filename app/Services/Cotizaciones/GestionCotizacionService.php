<?php

namespace App\Services\Cotizaciones;

use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\User;
use App\Services\BitacoraService;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Tienda\ValidadorCarrito;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CU20: seguimiento de las cotizaciones por el personal: vencimiento automático,
 * cambio de estado con observación y renovación con precios actuales.
 */
class GestionCotizacionService
{
    public const MODULO = 'Cotizaciones';

    private const INTENTOS_DE_CODIGO = 3;

    public function __construct(private ValidadorCarrito $validador) {}

    /**
     * Marca VENCIDA toda cotización PENDIENTE o ATENDIDA cuya fecha de vencimiento ya pasó.
     * Lo corre el comando `cotizaciones:vencer` cada día y también el listado, para no depender del scheduler.
     */
    public function vencer(): int
    {
        $hoy = PeriodoReporte::hoy()->toDateString();

        $ids = Cotizacion::query()
            ->whereIn('estado', [Cotizacion::PENDIENTE, Cotizacion::ATENDIDA])
            ->whereDate('fecha_vencimiento', '<', $hoy)
            ->orderBy('id')
            ->pluck('codigo_cotizacion', 'id');

        if ($ids->isEmpty()) {
            return 0;
        }

        $cantidad = Cotizacion::query()
            ->whereIn('id', $ids->keys()->all())
            ->whereIn('estado', [Cotizacion::PENDIENTE, Cotizacion::ATENDIDA])
            ->update(['estado' => Cotizacion::VENCIDA, 'fecha_estado' => now()]);

        BitacoraService::registrar(
            self::MODULO,
            'VENCER_COTIZACIONES',
            'cotizacion',
            null,
            null,
            ['cantidad' => $cantidad, 'codigos' => $ids->values()->take(50)->all()],
            'Cotizaciones vencidas: '.$cantidad,
        );

        return $cantidad;
    }

    /**
     * PENDIENTE, ATENDIDA o RECHAZADA, con una observación (obligatoria al rechazar).
     * VENCIDA y CONVERTIDA las pone el sistema.
     *
     * @throws ValidationException
     */
    public function cambiarEstado(Cotizacion $cotizacion, string $estado, ?string $observacion, User $usuario): Cotizacion
    {
        $observacion = $observacion === null ? null : trim($observacion);

        if (! in_array($estado, Cotizacion::ESTADOS_MANUALES, true)) {
            throw ValidationException::withMessages(['estado' => 'Ese estado no se puede asignar a mano.']);
        }

        if ($cotizacion->estado === Cotizacion::CONVERTIDA) {
            throw ValidationException::withMessages(['estado' => 'Una cotización convertida en venta ya no se puede modificar.']);
        }

        if ($cotizacion->estado === Cotizacion::VENCIDA) {
            throw ValidationException::withMessages(['estado' => 'La cotización está vencida: renuévala con precios actuales.']);
        }

        if ($estado === $cotizacion->estado) {
            throw ValidationException::withMessages(['estado' => 'La cotización ya está en ese estado.']);
        }

        if ($estado === Cotizacion::RECHAZADA && ($observacion === null || $observacion === '')) {
            throw ValidationException::withMessages(['observacion' => 'Indica el motivo del rechazo.']);
        }

        if ($estado !== Cotizacion::RECHAZADA && ! $cotizacion->vigente()) {
            throw ValidationException::withMessages(['estado' => 'La cotización ya venció: renuévala con precios actuales.']);
        }

        $anterior = $cotizacion->estado;

        $cotizacion->forceFill([
            'estado' => $estado,
            'observacion' => $observacion !== null && $observacion !== '' ? $observacion : $cotizacion->observacion,
            'fecha_estado' => now(),
            'actualizado_por' => $usuario->id,
        ])->save();

        BitacoraService::registrar(
            self::MODULO,
            'CAMBIAR_ESTADO_COTIZACION',
            'cotizacion',
            $cotizacion->id,
            ['estado' => $anterior],
            ['estado' => $estado, 'observacion' => $observacion],
            $observacion !== null && $observacion !== '' ? $observacion : 'Cambio de estado de '.$cotizacion->codigo_cotizacion,
            $usuario->id,
        );

        return $cotizacion;
    }

    /**
     * EX-01: crea una cotización nueva con los precios de hoy (mismo cliente, código y vencimiento nuevos)
     * y deja la anterior VENCIDA con una observación.
     *
     * @throws ValidationException
     */
    public function renovar(Cotizacion $original, User $usuario): Cotizacion
    {
        $vencida = $original->estado === Cotizacion::VENCIDA
            || (in_array($original->estado, [Cotizacion::PENDIENTE, Cotizacion::ATENDIDA], true) && ! $original->vigente());

        if (! $vencida) {
            throw ValidationException::withMessages(['estado' => 'Solo se pueden renovar las cotizaciones vencidas.']);
        }

        for ($intento = 1; ; $intento++) {
            try {
                return DB::transaction(fn (): Cotizacion => $this->crearRenovacion($original, $usuario));
            } catch (UniqueConstraintViolationException $e) {
                if ($intento >= self::INTENTOS_DE_CODIGO) {
                    throw $e;
                }
            }
        }
    }

    private function crearRenovacion(Cotizacion $original, User $usuario): Cotizacion
    {
        $original->load('detalles');

        $variantes = $this->validador->variantes(array_values($original->detalles->pluck('variante_id')->map(fn ($id): int => (int) $id)->all()));
        $lineas = [];
        $omitidas = [];

        foreach ($original->detalles as $detalle) {
            $variante = $variantes->get($detalle->variante_id);

            if ($variante === null || ! $this->validador->vendible($variante)) {
                $omitidas[] = $detalle->descripcion ?? 'Producto';

                continue;
            }

            $lineas[] = [
                'variante_id' => $variante->id,
                'cantidad' => $detalle->cantidad,
                'precio' => round((float) $variante->precio_venta, 2),
                'descripcion' => $detalle->descripcion,
            ];
        }

        if ($lineas === []) {
            throw ValidationException::withMessages(['estado' => 'Ningún producto de la cotización sigue disponible para renovarla.']);
        }

        $dias = max(1, (int) config('tienda.dias_validez_cotizacion', 7));

        $nueva = Cotizacion::query()->create([
            'cliente_id' => $original->cliente_id,
            'usuario_id' => $original->usuario_id,
            'codigo_cotizacion' => Cotizacion::siguienteCodigo(),
            'fecha_emision' => now(),
            'fecha_vencimiento' => PeriodoReporte::hoy()->addDays($dias)->toDateString(),
            'estado' => Cotizacion::PENDIENTE,
            'origen' => $original->origen,
            'observacion' => 'Renovación de '.$original->codigo_cotizacion.' con precios actuales.',
            'fecha_estado' => now(),
            'actualizado_por' => $usuario->id,
        ]);

        foreach ($lineas as $linea) {
            DetalleCotizacion::query()->create([
                'cotizacion_id' => $nueva->id,
                'variante_id' => $linea['variante_id'],
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio'],
                'descuento' => 0,
                'descripcion' => $linea['descripcion'],
            ]);
        }

        $nota = 'Renovada como '.$nueva->codigo_cotizacion.'.';
        $anterior = $original->estado;

        $original->forceFill([
            'estado' => Cotizacion::VENCIDA,
            'observacion' => trim(($original->observacion !== null && $original->observacion !== '' ? $original->observacion."\n" : '').$nota),
            'fecha_estado' => now(),
            'actualizado_por' => $usuario->id,
        ])->save();

        $nueva->load('detalles');

        BitacoraService::registrar(
            self::MODULO,
            'RENOVAR_COTIZACION',
            'cotizacion',
            $nueva->id,
            ['codigo' => $original->codigo_cotizacion, 'estado' => $anterior, 'total' => $original->total()],
            [
                'codigo' => $nueva->codigo_cotizacion,
                'total' => $nueva->total(),
                'vence' => $nueva->fecha_vencimiento?->toDateString(),
                'omitidos' => $omitidas,
            ],
            $nota,
            $usuario->id,
        );

        return $nueva;
    }
}
