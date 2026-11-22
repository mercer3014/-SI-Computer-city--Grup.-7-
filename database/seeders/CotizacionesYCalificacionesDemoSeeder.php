<?php

namespace Database\Seeders;

use App\Models\Calificacion;
use App\Models\Cotizacion;
use App\Models\DetalleCotizacion;
use App\Models\Pedido;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Models\Venta;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Cotizaciones y calificaciones de demostración (CU19, CU20, CU23, CU24).
 *
 * Corre después de OperacionesDemoSeeder: las calificaciones salen de los pedidos pagados del cliente web
 * y la cotización convertida se vincula a una venta que ya existe. Deja el pedido más reciente sin
 * calificar para que "Mis pedidos" ofrezca calificarlo.
 */
class CotizacionesYCalificacionesDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') || DB::table('cotizacion')->exists() || DB::table('calificacion')->exists()) {
            return;
        }

        $web = User::query()->where('email', UsuariosDemoSeeder::CLIENTE_WEB)->first();
        $admin = User::query()->where('email', UsuariosDemoSeeder::ADMIN)->first();
        $vendedor = User::query()->where('email', UsuariosDemoSeeder::VENDEDOR)->first();

        if ($web === null || $admin === null || $vendedor === null) {
            throw new RuntimeException('Faltan los usuarios demo: corre UsuariosDemoSeeder antes.');
        }

        DB::transaction(function () use ($web, $admin, $vendedor): void {
            $this->calificaciones($web, $admin);
            $this->cotizaciones($web, $vendedor);
        });
    }

    private function calificaciones(User $web, User $admin): void
    {
        $pedidos = Pedido::query()
            ->with('detalles.variante')
            ->where('usuario_id', $web->id)
            ->where('estado', Pedido::PAGADO)
            ->orderBy('fecha')
            ->get();

        // Del más viejo al más nuevo; el último queda sin calificar a propósito.
        $guion = [
            [
                [Calificacion::PUBLICADA, 5, 'Excelente producto, llegó en perfecto estado y la atención fue muy buena.', null],
                [Calificacion::PUBLICADA, 4, 'Buen precio, cumple lo que promete.', null],
            ],
            [
                [Calificacion::PUBLICADA, 5, 'Muy recomendado, lo uso todos los días.', null],
                [Calificacion::OCULTA, 1, 'Pésimo, no lo compren, son unos estafadores.', 'Lenguaje ofensivo y acusaciones sin sustento'],
            ],
            [
                [Calificacion::PENDIENTE, 3, 'Cumple, aunque esperaba un poco más por el precio.', null],
                [Calificacion::PENDIENTE, 4, null, null],
            ],
        ];

        foreach ($pedidos->take(count($guion)) as $indice => $pedido) {
            $vistos = [];
            $lineas = $guion[$indice];

            foreach ($pedido->detalles as $detalle) {
                $productoId = $detalle->variante?->producto_id;

                if ($productoId === null || isset($vistos[$productoId]) || $lineas === []) {
                    continue;
                }

                $vistos[$productoId] = true;
                [$estado, $puntuacion, $comentario, $motivo] = array_shift($lineas);
                $fecha = ($pedido->fecha_pago ?? $pedido->fecha)?->addDays(2);

                Calificacion::query()->create([
                    'cliente_id' => $pedido->cliente_id,
                    'usuario_id' => $web->id,
                    'pedido_id' => $pedido->id,
                    'producto_id' => $productoId,
                    'puntuacion' => $puntuacion,
                    'comentario' => $comentario,
                    'estado' => $estado,
                    'motivo_ocultamiento' => $motivo,
                    'moderado_por' => $estado === Calificacion::PENDIENTE ? null : $admin->id,
                    'fecha_moderacion' => $estado === Calificacion::PENDIENTE ? null : $fecha?->addDay(),
                    'fecha' => $fecha,
                ]);
            }
        }
    }

    private function cotizaciones(User $web, User $vendedor): void
    {
        $hoy = CarbonImmutable::now((string) config('app.timezone'));
        $dias = max(1, (int) config('tienda.dias_validez_cotizacion', 7));

        $cobra = $this->variante('CAM-LOG-C920-NEG');
        $mouse = $this->variante('MOU-LOG-G203-NEG');
        $teclado = $this->variante('TEC-RED-K552-NEG');
        $audifonos = $this->variante('AUD-LOG-H390-NEG');
        $parlante = $this->variante('PAR-LOG-Z200-NEG');

        // Pendiente y vigente: el precio cotizado de un producto es menor al actual, y otro ya no tiene stock.
        $this->cotizacion($web, $vendedor, Cotizacion::PENDIENTE, $hoy->subDay(), $hoy->subDay()->addDays($dias), [
            [$mouse, 2, round((float) $mouse->precio_venta - 15, 2)],
            [$cobra, 1, (float) $cobra->precio_venta],
        ], null);

        // Atendida y vigente.
        $this->cotizacion($web, $vendedor, Cotizacion::ATENDIDA, $hoy->subDays(3), $hoy->subDays(3)->addDays($dias), [
            [$teclado, 1, (float) $teclado->precio_venta],
            [$audifonos, 1, (float) $audifonos->precio_venta],
        ], 'Se le escribió por WhatsApp; confirma el sábado.');

        // Vencida, para renovarla con precios actuales.
        $this->cotizacion($web, $vendedor, Cotizacion::VENCIDA, $hoy->subDays(16), $hoy->subDays(9), [
            [$parlante, 2, round((float) $parlante->precio_venta - 10, 2)],
        ], null);

        // Rechazada, con su motivo.
        $this->cotizacion($web, $vendedor, Cotizacion::RECHAZADA, $hoy->subDays(6), $hoy->subDays(6)->addDays($dias), [
            [$teclado, 2, (float) $teclado->precio_venta],
        ], 'El cliente encontró un precio menor en otra tienda.');

        // Convertida: queda vinculada a una venta real del mostrador.
        $venta = Venta::query()
            ->with('detalles')
            ->where('estado', 'COMPLETADA')
            ->whereNull('pedido_id')
            ->whereNull('cotizacion_id')
            ->where('fecha', '<', $hoy->subDays(12))
            ->orderByDesc('fecha')
            ->first();

        if ($venta !== null && $venta->detalles->isNotEmpty()) {
            $lineas = [];

            foreach ($venta->detalles as $detalle) {
                $lineas[] = [
                    VarianteProducto::query()->whereKey($detalle->variante_id)->firstOrFail(),
                    (int) $detalle->cantidad,
                    (float) $detalle->precio_unitario,
                ];
            }

            $convertida = $this->cotizacion($web, $vendedor, Cotizacion::CONVERTIDA, $hoy->subDays(20), $hoy->subDays(20)->addDays($dias), $lineas, 'Convertida en la venta '.$venta->numero_venta.'.');
            $venta->forceFill(['cotizacion_id' => $convertida->id])->save();
        }
    }

    /**
     * @param  list<array{0: VarianteProducto, 1: int, 2: float}>  $lineas
     */
    private function cotizacion(User $web, User $gestor, string $estado, CarbonImmutable $emision, CarbonImmutable $vence, array $lineas, ?string $observacion): Cotizacion
    {
        $cotizacion = Cotizacion::query()->create([
            'cliente_id' => $web->cliente_id,
            'usuario_id' => $web->id,
            'codigo_cotizacion' => Cotizacion::siguienteCodigo(),
            'fecha_emision' => $emision,
            'fecha_vencimiento' => $vence->toDateString(),
            'estado' => $estado,
            'observacion' => $observacion,
            'origen' => Cotizacion::ORIGEN_TIENDA_WEB,
            'fecha_estado' => $estado === Cotizacion::PENDIENTE ? $emision : $emision->addDay(),
            'actualizado_por' => $estado === Cotizacion::PENDIENTE ? null : $gestor->id,
        ]);

        foreach ($lineas as [$variante, $cantidad, $precio]) {
            $etiqueta = $variante->etiquetaAtributos();

            DetalleCotizacion::query()->create([
                'cotizacion_id' => $cotizacion->id,
                'variante_id' => $variante->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'descuento' => 0,
                'descripcion' => $variante->producto->nombre.($etiqueta !== '' ? ' · '.$etiqueta : ''),
            ]);
        }

        return $cotizacion;
    }

    private function variante(string $sku): VarianteProducto
    {
        return VarianteProducto::query()->with('producto')->where('sku', $sku)->firstOrFail();
    }
}
