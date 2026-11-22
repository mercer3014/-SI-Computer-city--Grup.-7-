<?php

namespace App\Services\Cotizaciones;

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\User;
use App\Services\BitacoraService;
use App\Services\Tienda\ConfiguracionTiendaService;
use App\Services\Tienda\ValidadorCarrito;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CU19: el Cliente Web convierte su carrito en una cotización formal.
 *
 * La cotización no descuenta ni reserva stock. Los precios son los vigentes en la base: si el
 * navegador vio otros, no se registra nada y se devuelven los nuevos (EX-03).
 */
class CotizacionWebService
{
    public const MODULO = 'Tienda';

    private const INTENTOS_DE_CODIGO = 3;

    public function __construct(
        private ValidadorCarrito $validador,
        private ConfiguracionTiendaService $tienda,
    ) {}

    /**
     * @param  list<array{variante_id: int, cantidad: int, precio_visto: float|int|string}>  $items
     * @param  array{nombre: string, telefono: string, email: string|null, tipo_documento: string|null, numero_documento: string|null}  $contacto
     *
     * @throws PreciosDesactualizados EX-03
     * @throws ValidationException EX-01 y EX-06 cuando no queda ningún producto cotizable
     */
    public function generar(User $usuario, array $items, array $contacto): ResultadoCotizacion
    {
        if ($usuario->cliente_id === null) {
            throw ValidationException::withMessages(['items' => 'Para cotizar necesitas una cuenta de cliente.']);
        }

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Tu carrito está vacío.']);
        }

        for ($intento = 1; ; $intento++) {
            try {
                return DB::transaction(fn (): ResultadoCotizacion => $this->registrar($usuario, $items, $contacto));
            } catch (UniqueConstraintViolationException $e) {
                if ($intento >= self::INTENTOS_DE_CODIGO) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Enlace de WhatsApp con el mensaje de la cotización, o null si la tienda no tiene WhatsApp (EX-05).
     */
    public function enlaceWhatsapp(Cotizacion $cotizacion): ?string
    {
        $tienda = $this->tienda->actual();
        $numero = $this->normalizarWhatsapp($tienda['whatsapp']);

        if ($numero === null) {
            return null;
        }

        $cotizacion->loadMissing('detalles');

        $lineas = $cotizacion->detalles->map(fn (DetalleCotizacion $d): string => sprintf(
            '- %d × %s (Bs %s)',
            $d->cantidad,
            $d->descripcion ?? 'Producto',
            number_format((float) $d->precio_unitario, 2, ',', '.'),
        ))->implode("\n");

        $mensaje = "Hola {$tienda['nombre']}, quiero confirmar la cotización {$cotizacion->codigo_cotizacion}:\n"
            .$lineas."\n"
            .'Total: Bs '.number_format($cotizacion->total(), 2, ',', '.');

        return 'https://wa.me/'.$numero.'?text='.rawurlencode($mensaje);
    }

    /**
     * Solo dígitos y con el código de país: "76543210" → "59176543210".
     */
    public function normalizarWhatsapp(?string $numero): ?string
    {
        $digitos = preg_replace('/\D+/', '', (string) $numero) ?? '';

        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }

        if ($digitos === '') {
            return null;
        }

        $pais = (string) config('tienda.codigo_pais', '591');

        return str_starts_with($digitos, $pais) && strlen($digitos) >= strlen($pais) + 7
            ? $digitos
            : $pais.ltrim($digitos, '0');
    }

    /**
     * @param  list<array{variante_id: int, cantidad: int, precio_visto: float|int|string}>  $items
     * @param  array{nombre: string, telefono: string, email: string|null, tipo_documento: string|null, numero_documento: string|null}  $contacto
     */
    private function registrar(User $usuario, array $items, array $contacto): ResultadoCotizacion
    {
        $agrupados = $this->validador->agrupar($items);
        $vistos = [];

        foreach ($items as $item) {
            $vistos[(int) $item['variante_id']] ??= round((float) $item['precio_visto'], 2);
        }

        $revision = $this->validador->ajustar(
            $agrupados,
            $this->validador->variantes(array_keys($agrupados)),
            $vistos,
        );

        if ($revision->huboCambioDePrecio()) {
            throw new PreciosDesactualizados($revision->preciosCambiados);
        }

        if ($revision->vacio()) {
            throw ValidationException::withMessages([
                'items' => 'Los productos de tu carrito ya no están disponibles para cotizar.',
            ]);
        }

        $cliente = Cliente::query()->findOrFail($usuario->cliente_id);
        $this->actualizarCliente($cliente, $contacto);

        $hoy = CarbonImmutable::now((string) config('app.timezone'));
        $cotizacion = Cotizacion::query()->create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'codigo_cotizacion' => Cotizacion::siguienteCodigo(),
            'fecha_emision' => now(),
            'fecha_vencimiento' => $hoy->addDays(max(1, (int) config('tienda.dias_validez_cotizacion', 7)))->toDateString(),
            'estado' => Cotizacion::PENDIENTE,
            'origen' => Cotizacion::ORIGEN_TIENDA_WEB,
            'fecha_estado' => now(),
        ]);

        foreach ($revision->lineas as $linea) {
            $variante = $linea['variante'];
            $etiqueta = $variante->etiquetaAtributos();

            DetalleCotizacion::query()->create([
                'cotizacion_id' => $cotizacion->id,
                'variante_id' => $variante->id,
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio'],
                'descuento' => 0,
                'descripcion' => $variante->producto->nombre.($etiqueta !== '' ? ' · '.$etiqueta : ''),
            ]);
        }

        $cotizacion->load('detalles');

        BitacoraService::registrar(
            self::MODULO,
            'GENERAR_COTIZACION_WEB',
            'cotizacion',
            $cotizacion->id,
            null,
            [
                'codigo' => $cotizacion->codigo_cotizacion,
                'total' => $cotizacion->total(),
                'productos' => count($revision->lineas),
                'vence' => $cotizacion->fecha_vencimiento?->toDateString(),
            ],
            'Cotización web '.$cotizacion->codigo_cotizacion,
            $usuario->id,
        );

        return new ResultadoCotizacion($cotizacion, $revision->avisos);
    }

    /**
     * @param  array{nombre: string, telefono: string, email: string|null, tipo_documento: string|null, numero_documento: string|null}  $contacto
     */
    private function actualizarCliente(Cliente $cliente, array $contacto): void
    {
        $cambios = [
            'nombre' => trim($contacto['nombre']),
            'telefono' => trim($contacto['telefono']),
        ];

        if ($contacto['email'] !== null && trim($contacto['email']) !== '') {
            $cambios['email'] = trim($contacto['email']);
        }

        if ($contacto['numero_documento'] !== null && trim($contacto['numero_documento']) !== '') {
            $cambios['numero_documento'] = trim($contacto['numero_documento']);
            $cambios['tipo_documento'] = $contacto['tipo_documento'] ?? $cliente->tipo_documento;
        }

        $cliente->forceFill($cambios)->save();
    }
}
