<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\DetallePedido;
use App\Models\DetalleVenta;
use App\Models\Gasto;
use App\Models\MetodoPago;
use App\Models\MovimientoCaja;
use App\Models\PagoVenta;
use App\Models\Pedido;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Models\Venta;
use App\Services\VentaKardexEmulador;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Operaciones de los últimos 90 días para que reportes, dashboard y asistente tengan datos.
 *
 * Todo pasa por los mismos caminos que la aplicación: en PostgreSQL el stock y el kardex los
 * mueven los triggers; en SQLite lo hace VentaKardexEmulador (ventas) y este seeder (compras).
 * Los números salen de mt_srand(2026), así que cada corrida genera lo mismo.
 *
 * @phpstan-type Linea array{sku: string, cantidad: int, descuento: float, meses: int, serie: string|null}
 * @phpstan-type Plan array{fecha: CarbonImmutable, usuario: string, cliente: int, lineas: list<Linea>, anular: bool, web: bool}
 */
class OperacionesDemoSeeder extends Seeder
{
    /** Unidades que quedan en stock al terminar; el resto queda en mínimo + entre 2 y 8. */
    private const STOCK_FINAL = [
        'MOU-GEN-DX110-NEG' => 2,
        'TEC-HYX-ALORG-NEG' => 1,
        'AUD-JBL-T500-NEG' => 2,
        'CAM-LOG-C920-NEG' => 0,
    ];

    /** Variantes que nunca se compraron: se venden con costo desconocido (EX-04 de CU16). */
    private const SIN_COSTO = ['HUB-GEN-GHU-NEG', 'ACC-JBL-RING-NEG'];

    /** @var array<string, float> */
    private const COSTOS = [
        'MOU-LOG-G203-NEG' => 75.00, 'MOU-RED-COBRA-NEG' => 55.00, 'MOU-GEN-DX110-NEG' => 35.00,
        'TEC-RED-K552-NEG' => 170.00, 'TEC-LOG-K120-NEG' => 55.00, 'TEC-HYX-ALORG-NEG' => 430.00,
        'AUD-HYX-CSTG-NEG' => 230.00, 'AUD-LOG-H390-NEG' => 95.00, 'AUD-JBL-T500-NEG' => 140.00,
        'MIC-GEN-MIC01-NEG' => 110.00, 'MIC-HYX-SOLO-NEG' => 260.00, 'CAM-LOG-C920-NEG' => 320.00,
        'CAM-GEN-FACE-NEG' => 140.00, 'PAR-JBL-BAR20-NEG' => 450.00, 'PAR-LOG-Z200-NEG' => 100.00,
        'MSP-RED-ARCH-NEG' => 50.00, 'HUB-GEN-GHU-NEG' => 40.00, 'ADP-LOG-BT01-NEG' => 30.00,
        'CAR-RED-USBC-NEG' => 38.00, 'ACC-JBL-RING-NEG' => 90.00,
    ];

    /** NIT, razón social, nombre comercial, contacto, teléfono y email. */
    private const PROVEEDORES = [
        ['1023456011', 'Tecno Import S.R.L.', 'Tecno Import', 'Jorge Salvatierra', '70123456', 'ventas@tecnoimport.com'],
        ['1023456022', 'Global Compu Parts S.A.', 'Global Compu', 'Ana Aguilar', '70223344', 'contacto@globalcompu.com'],
        ['1023456033', 'Distribuidora ABC', 'ABC Perifericos', 'Luis Vargas', '70334455', 'abc@distribuidora.com'],
        ['1023456044', 'ImportTech Bolivia', 'ImportTech', 'Diego Suarez', '70445566', 'info@importtech.bo'],
        ['1023456055', 'Comercial Andina SRL', 'Andina Tech', 'Paola Melgar', '70556677', 'ventas@andinatech.com'],
    ];

    /** @var array<string, string> */
    private const PROVEEDOR_POR_MARCA = [
        'logitech' => '1023456011',
        'redragon' => '1023456022',
        'genius' => '1023456033',
        'hyperx' => '1023456044',
        'jbl' => '1023456055',
    ];

    /** Documento, tipo de documento, nombre, teléfono y email. */
    private const CLIENTES = [
        ['4521230 SC', 'CI', 'Juan Perez Rojas', '76541230', 'juanperez@gmail.com'],
        ['7845201 SC', 'CI', 'Andrea Gutierrez Vaca', '77896541', 'andrea.gutierrez@gmail.com'],
        ['3312045 SC', 'CI', 'Carlos Mendez Ortiz', '70112233', 'carlos.mendez@gmail.com'],
        ['5589012 SC', 'CI', 'Fernanda Justiniano Rios', '76223344', 'fernanda.justiniano@gmail.com'],
        ['6023341 SC', 'CI', 'Miguel Angel Vargas', '70334455', 'miguel.vargas@gmail.com'],
        ['7712233 SC', 'CI', 'Daniela Suarez Peña', '77445566', 'daniela.suarez@gmail.com'],
        ['8894455 SC', 'CI', 'Rodrigo Alba Cuellar', '70556677', 'rodrigo.alba@gmail.com'],
        ['9021156 SC', 'CI', 'Camila Rocha Salvatierra', '76667788', 'camila.rocha@gmail.com'],
        ['1099887766', 'NIT', 'Cyber Cabinas Central', '70998877', 'compras@cybercentral.com'],
        ['1099887777', 'NIT', 'Internet Point Norte', '70998878', 'compras@ipnorte.com'],
    ];

    private const CLIENTE_FINAL = 'CF-0000';

    private int $secuenciaSerie = 0;

    private int $secuenciaCompra = 0;

    /** @var array<string, int> */
    private array $varianteIds = [];

    /** @var array<string, float> */
    private array $precios = [];

    /** @var array<string, string> */
    private array $nombres = [];

    /** @var array<string, int> */
    private array $metodos = [];

    private int $clienteFinalId = 0;

    private int $clienteWebId = 0;

    /** @var list<int> */
    private array $clientesIds = [];

    /** @var array<string, int> */
    private array $usuarioIds = [];

    /** @var list<string> */
    private array $skus = [];

    public function run(): void
    {
        if (app()->environment('production') || DB::table('venta')->exists()) {
            return;
        }

        $web = User::query()->where('email', UsuariosDemoSeeder::CLIENTE_WEB)->first();
        $admin = User::query()->where('email', UsuariosDemoSeeder::ADMIN)->first();
        $vendedor = User::query()->where('email', UsuariosDemoSeeder::VENDEDOR)->first();

        if ($web === null || $admin === null || $vendedor === null) {
            throw new RuntimeException('Faltan los usuarios demo: corré UsuariosDemoSeeder antes.');
        }

        $this->usuarioIds = ['admin' => $admin->id, 'vendedor' => $vendedor->id, 'web' => $web->id];

        mt_srand(2026);

        DB::transaction(function () use ($web): void {
            $this->cargarReferencias($web);

            $hoy = CarbonImmutable::now(config('app.timezone'));
            $planes = $this->planificarVentas($hoy);

            $this->registrarCompras($hoy, $planes);
            $this->registrarVentas($planes);
            $this->registrarPedidoPendiente($web, $hoy);
            $this->registrarGastosYMovimientos($hoy);
            $this->registrarVisitas($hoy);
        });
    }

    private function cargarReferencias(User $web): void
    {
        foreach (['EFECTIVO' => 'Efectivo', 'QR' => 'Pago con QR', 'TRANSFERENCIA' => 'Transferencia bancaria', 'TARJETA' => 'Tarjeta (Stripe)'] as $codigo => $nombre) {
            $this->metodos[$codigo] = MetodoPago::query()->firstOrCreate(['codigo' => $codigo], ['nombre' => $nombre])->id;
        }

        foreach (['Alquiler' => 'Alquiler mensual del local', 'Servicios Básicos' => 'Luz, agua, internet', 'Publicidad' => 'Publicidad y redes sociales', 'Viáticos' => 'Movilidad y gastos operativos varios'] as $nombre => $descripcion) {
            DB::table('categoria_gasto')->insertOrIgnore(['nombre' => $nombre, 'descripcion' => $descripcion]);
        }

        foreach (self::PROVEEDORES as [$nit, $razon, $comercial, $contacto, $telefono, $email]) {
            Proveedor::query()->firstOrCreate(['nit' => $nit], [
                'razon_social' => $razon,
                'nombre_comercial' => $comercial,
                'contacto' => $contacto,
                'telefono' => $telefono,
                'email' => $email,
            ]);
        }

        $this->clienteFinalId = Cliente::query()->firstOrCreate(
            ['numero_documento' => self::CLIENTE_FINAL],
            ['tipo_documento' => 'S/N', 'nombre' => 'Cliente Final', 'estado' => 'ACTIVO'],
        )->id;

        foreach (self::CLIENTES as [$documento, $tipo, $nombre, $telefono, $email]) {
            $this->clientesIds[] = Cliente::query()->firstOrCreate(['numero_documento' => $documento], [
                'tipo_documento' => $tipo,
                'nombre' => $nombre,
                'telefono' => $telefono,
                'email' => $email,
                'estado' => 'ACTIVO',
            ])->id;
        }

        $this->clienteWebId = (int) $web->cliente_id;

        foreach (CatalogoDemoSeeder::PRODUCTOS as [, , $nombre, $sku, , $precio]) {
            $this->skus[] = $sku;
            $this->nombres[$sku] = $nombre;
            $this->precios[$sku] = $precio;
            $this->varianteIds[$sku] = (int) VarianteProducto::query()->where('sku', $sku)->value('id');
        }
    }

    /**
     * Arma todas las ventas en memoria, ordenadas por fecha, antes de tocar la base.
     * Así se sabe cuántas unidades de cada variante hay que comprar.
     *
     * @return list<Plan>
     */
    private function planificarVentas(CarbonImmutable $hoy): array
    {
        $seguras = array_values(array_diff($this->skus, array_keys(self::STOCK_FINAL), self::SIN_COSTO));
        $conCosto = array_values(array_diff($this->skus, self::SIN_COSTO));
        $planes = [];

        foreach (range(0, 59) as $i) {
            $anular = $i % 10 === 4;
            $hoyMismo = $i < 3;

            $planes[] = [
                'fecha' => $hoyMismo ? $this->fechaDeHoy($hoy) : $this->fechaDeDiaHabil($hoy, mt_rand(1, 89)),
                'usuario' => $hoyMismo ? ($i === 2 ? 'admin' : 'vendedor') : (mt_rand(1, 100) <= 55 ? 'vendedor' : 'admin'),
                'cliente' => mt_rand(1, 100) <= 30 ? $this->clienteFinalId : $this->clienteAlAzar(),
                'lineas' => $this->lineasAleatorias($anular ? $seguras : $conCosto),
                'anular' => $anular,
                'web' => false,
            ];
        }

        foreach ([85, 80, 75, 70, 66, 63] as $dias) {
            $planes[] = $this->planEspecial($hoy, $dias, 'vendedor', [$this->linea($this->elegir($seguras), 1, 3, true)]);
        }

        foreach ([50, 33, 21, 9] as $indice => $dias) {
            $planes[] = $this->planEspecial($hoy, $dias, $indice % 2 === 0 ? 'admin' : 'vendedor', [
                $this->linea(self::SIN_COSTO[$indice % 2], mt_rand(1, 2)),
            ]);
        }

        foreach (array_keys(self::STOCK_FINAL) as $indice => $sku) {
            foreach ([58, 30] as $dias) {
                $planes[] = $this->planEspecial($hoy, $dias - $indice, 'vendedor', [$this->linea($sku, 2)]);
            }
        }

        $intermedias = array_values(array_filter(
            $seguras,
            fn (string $sku): bool => $this->precios[$sku] >= 60 && $this->precios[$sku] <= 400,
        ));

        foreach ([38, 27, 15, 6] as $dias) {
            $plan = $this->planEspecial($hoy, $dias, 'web', [
                $this->linea($this->elegir($intermedias), 1),
                $this->linea($this->elegir($intermedias), mt_rand(1, 2)),
            ]);
            $plan['cliente'] = $this->clienteWebId;
            $plan['web'] = true;
            $planes[] = $plan;
        }

        usort($planes, fn (array $a, array $b): int => $a['fecha']->getTimestamp() <=> $b['fecha']->getTimestamp());

        return $planes;
    }

    /**
     * @param  list<Linea>  $lineas
     * @return Plan
     */
    private function planEspecial(CarbonImmutable $hoy, int $dias, string $usuario, array $lineas): array
    {
        return [
            'fecha' => $this->fechaDeDiaHabil($hoy, $dias),
            'usuario' => $usuario,
            'cliente' => $this->clienteAlAzar(),
            'lineas' => $lineas,
            'anular' => false,
            'web' => false,
        ];
    }

    private function clienteAlAzar(): int
    {
        return $this->clientesIds[mt_rand(0, count($this->clientesIds) - 1)];
    }

    /**
     * @return Linea
     */
    private function linea(string $sku, int $cantidad, ?int $meses = null, bool $conSerie = false): array
    {
        $precio = $this->precios[$sku];

        return [
            'sku' => $sku,
            'cantidad' => $cantidad,
            'descuento' => mt_rand(1, 100) <= 15 ? round($cantidad * $precio * 0.05, 2) : 0.0,
            'meses' => $meses ?? ($precio >= 300 ? 12 : ($precio >= 120 ? 6 : 3)),
            'serie' => $conSerie || ($cantidad === 1 && mt_rand(1, 100) <= 35)
                ? sprintf('SN-%06d', ++$this->secuenciaSerie)
                : null,
        ];
    }

    /**
     * @param  list<string>  $candidatos
     * @return list<Linea>
     */
    private function lineasAleatorias(array $candidatos): array
    {
        $cuantas = $this->tirada([55, 35, 10]) + 1;
        $elegidos = [];

        while (count($elegidos) < $cuantas) {
            $sku = $this->elegir($candidatos);

            if (! in_array($sku, $elegidos, true)) {
                $elegidos[] = $sku;
            }
        }

        return array_map(
            fn (string $sku): array => $this->linea($sku, $this->tirada([70, 25, 5]) + 1),
            $elegidos,
        );
    }

    /**
     * Elige un SKU; los baratos salen más seguido, como pasa en una tienda de periféricos.
     *
     * @param  list<string>  $candidatos
     */
    private function elegir(array $candidatos): string
    {
        $pesos = array_map(
            fn (string $sku): int => $this->precios[$sku] < 100 ? 5 : ($this->precios[$sku] < 300 ? 3 : 1),
            $candidatos,
        );

        return $candidatos[$this->tirada($pesos)];
    }

    /**
     * @param  list<int>  $pesos
     */
    private function tirada(array $pesos): int
    {
        $punto = mt_rand(1, array_sum($pesos));

        foreach ($pesos as $indice => $peso) {
            $punto -= $peso;

            if ($punto <= 0) {
                return $indice;
            }
        }

        return 0;
    }

    private function fechaDeHoy(CarbonImmutable $hoy): CarbonImmutable
    {
        $minutos = $hoy->hour * 60 + $hoy->minute;

        return $hoy->startOfDay()->addMinutes(mt_rand(1, max(1, $minutos)));
    }

    /** La tienda cierra los domingos: esos días se corren al sábado. */
    private function fechaDeDiaHabil(CarbonImmutable $hoy, int $dias): CarbonImmutable
    {
        $fecha = $hoy->subDays($dias)->setTime(mt_rand(9, 18), mt_rand(0, 59));

        return $fecha->dayOfWeekIso === 7 ? $fecha->subDay() : $fecha;
    }

    /**
     * Compra por marca, a su proveedor, en dos tandas (60 % y el resto), con lo justo para
     * cubrir las ventas y dejar el stock final de cada variante.
     *
     * @param  list<Plan>  $planes
     */
    private function registrarCompras(CarbonImmutable $hoy, array $planes): void
    {
        $consumo = [];

        foreach ($planes as $plan) {
            if ($plan['anular']) {
                continue;
            }

            foreach ($plan['lineas'] as $linea) {
                $consumo[$linea['sku']] = ($consumo[$linea['sku']] ?? 0) + $linea['cantidad'];
            }
        }

        $primera = [];
        $segunda = [];
        $saldosIniciales = [];

        foreach (CatalogoDemoSeeder::PRODUCTOS as [, $marca, , $sku, , , $minimo]) {
            $final = self::STOCK_FINAL[$sku] ?? $minimo + mt_rand(2, 8);
            $total = ($consumo[$sku] ?? 0) + $final;

            if (in_array($sku, self::SIN_COSTO, true)) {
                $saldosIniciales[$sku] = $total;

                continue;
            }

            $tanda1 = (int) ceil($total * 0.6);
            $primera[$marca][$sku] = $tanda1;
            $segunda[$marca][$sku] = $total - $tanda1;
        }

        $this->registrarSaldoInicial($hoy->subDays(90), $saldosIniciales);

        foreach (self::PROVEEDOR_POR_MARCA as $marca => $nit) {
            $proveedorId = (int) Proveedor::query()->where('nit', $nit)->value('id');

            $this->registrarCompra($proveedorId, $hoy->subDays(mt_rand(89, 90)), $primera[$marca] ?? [], 1.0);
            $this->registrarCompra($proveedorId, $hoy->subDays(mt_rand(35, 50)), $segunda[$marca] ?? [], 1.04);
        }
    }

    /**
     * Las variantes que nunca se compraron entran con un ajuste inicial en el kardex.
     *
     * @param  array<string, int>  $saldos
     */
    private function registrarSaldoInicial(CarbonImmutable $fecha, array $saldos): void
    {
        $movimientoId = (int) DB::table('movimiento_inventario')->insertGetId([
            'usuario_id' => $this->usuarioIds['admin'],
            'tipo' => 'AJUSTE',
            'fecha' => $fecha,
            'referencia' => 'AJUSTE-INICIAL-001',
            'observacion' => 'Saldo inicial de variantes cargadas sin compra',
        ]);

        foreach ($saldos as $sku => $cantidad) {
            DB::table('detalle_movimiento_inventario')->insert([
                'movimiento_id' => $movimientoId,
                'variante_id' => $this->varianteIds[$sku],
                'cantidad' => $cantidad,
                'costo_unitario' => 0,
            ]);

            VarianteProducto::query()->whereKey($this->varianteIds[$sku])->update(['stock_actual' => $cantidad]);
        }
    }

    /**
     * @param  array<string, int>  $unidades
     */
    private function registrarCompra(int $proveedorId, CarbonImmutable $fecha, array $unidades, float $factorCosto): void
    {
        $unidades = array_filter($unidades, fn (int $cantidad): bool => $cantidad > 0);

        if ($unidades === []) {
            return;
        }

        $numero = sprintf('C-%d-%04d', $fecha->year, ++$this->secuenciaCompra);
        $usuarioId = $this->usuarioIds['admin'];

        $compraId = (int) DB::table('compra')->insertGetId([
            'proveedor_id' => $proveedorId,
            'usuario_id' => $usuarioId,
            'metodo_pago_id' => $this->metodos['TRANSFERENCIA'],
            'numero_compra' => $numero,
            'fecha_compra' => $fecha,
            'tipo_documento' => 'FACTURA',
            'numero_documento' => sprintf('F-%05d', 1000 + $this->secuenciaCompra),
        ]);

        $movimientoId = VentaKardexEmulador::activo()
            ? (int) DB::table('movimiento_inventario')->insertGetId([
                'usuario_id' => $usuarioId,
                'compra_id' => $compraId,
                'tipo' => 'ENTRADA',
                'fecha' => $fecha,
                'referencia' => $numero,
            ])
            : null;

        foreach ($unidades as $sku => $cantidad) {
            $costo = round(self::COSTOS[$sku] * $factorCosto, 2);

            DB::table('detalle_compra')->insert([
                'compra_id' => $compraId,
                'variante_id' => $this->varianteIds[$sku],
                'cantidad' => $cantidad,
                'costo_unitario' => $costo,
            ]);

            if ($movimientoId !== null) {
                DB::table('detalle_movimiento_inventario')->insert([
                    'movimiento_id' => $movimientoId,
                    'variante_id' => $this->varianteIds[$sku],
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                ]);

                VarianteProducto::query()->whereKey($this->varianteIds[$sku])->increment('stock_actual', $cantidad);
            }
        }
    }

    /**
     * @param  list<Plan>  $planes
     */
    private function registrarVentas(array $planes): void
    {
        $secuenciaPedido = 0;

        foreach ($planes as $plan) {
            $usuarioId = $this->usuarioIds[$plan['usuario']];
            $clienteId = $plan['cliente'];
            $total = $this->totalDe($plan['lineas']);
            $pedido = $plan['web'] ? $this->crearPedidoPagado($plan, ++$secuenciaPedido, $total) : null;

            $venta = Venta::query()->create([
                'cliente_id' => $clienteId,
                'pedido_id' => $pedido?->id,
                'usuario_id' => $usuarioId,
                'numero_venta' => Venta::siguienteNumero(),
                'fecha' => $plan['fecha'],
                'estado' => 'COMPLETADA',
                'descuento_global' => 0,
                'observacion' => $pedido !== null ? 'Venta en línea · pedido '.$pedido->numero_pedido : null,
            ]);

            foreach ($plan['lineas'] as $linea) {
                DetalleVenta::query()->create([
                    'venta_id' => $venta->id,
                    'variante_id' => $this->varianteIds[$linea['sku']],
                    'cantidad' => $linea['cantidad'],
                    'precio_unitario' => $this->precios[$linea['sku']],
                    'descuento' => $linea['descuento'],
                    'nombre_producto_snapshot' => $this->nombres[$linea['sku']],
                    'numero_serie' => $linea['serie'],
                    'meses_garantia' => $linea['meses'],
                    'estado' => 'VIGENTE',
                ]);
            }

            foreach ($this->pagosDe($plan, $total, $pedido) as [$metodo, $monto, $referencia]) {
                PagoVenta::query()->create([
                    'venta_id' => $venta->id,
                    'metodo_pago_id' => $this->metodos[$metodo],
                    'monto' => $monto,
                    'referencia' => $referencia,
                    'fecha' => $plan['fecha'],
                ]);
            }

            if ($plan['anular']) {
                $venta->update([
                    'estado' => 'ANULADA',
                    'fecha_anulacion' => $plan['fecha']->addHours(2),
                    'anulado_por' => $this->usuarioIds['admin'],
                    'motivo_anulacion' => 'Devolución del cliente',
                ]);
            }
        }
    }

    /**
     * @param  list<Linea>  $lineas
     */
    private function totalDe(array $lineas): float
    {
        $total = 0.0;

        foreach ($lineas as $linea) {
            $total += $linea['cantidad'] * $this->precios[$linea['sku']] - $linea['descuento'];
        }

        return round($total, 2);
    }

    /**
     * Pago único (efectivo, QR o transferencia), pago dividido en efectivo y QR, o tarjeta en las ventas en línea.
     *
     * @param  Plan  $plan
     * @return list<array{0: string, 1: float, 2: string|null}>
     */
    private function pagosDe(array $plan, float $total, ?Pedido $pedido): array
    {
        if ($pedido !== null) {
            return [['TARJETA', $total, $pedido->stripe_payment_intent_id]];
        }

        $tipo = $this->tirada([45, 30, 15, 10]);

        if ($tipo === 3) {
            $mitad = round($total / 2, 2);

            return [['EFECTIVO', $mitad, null], ['QR', round($total - $mitad, 2), null]];
        }

        $metodo = match ($tipo) {
            0 => 'EFECTIVO',
            1 => 'QR',
            default => 'TRANSFERENCIA',
        };

        return [[$metodo, $total, $metodo === 'EFECTIVO' ? null : sprintf('REF-%06d', mt_rand(100000, 999999))]];
    }

    /**
     * @param  Plan  $plan
     */
    private function crearPedidoPagado(array $plan, int $secuencia, float $total): Pedido
    {
        $pedido = Pedido::query()->create([
            'cliente_id' => $this->clienteWebId,
            'usuario_id' => $this->usuarioIds['web'],
            'numero_pedido' => Pedido::siguienteNumero(),
            'nombre_contacto' => 'Lucia Demo Cliente',
            'telefono_contacto' => '70000001',
            'metodo_entrega' => 'RETIRO_EN_TIENDA',
            'estado' => Pedido::PAGADO,
            'origen' => Pedido::ORIGEN_TIENDA,
            'fecha' => $plan['fecha'],
            'total' => $total,
            'stripe_payment_intent_id' => sprintf('pi_demo_%04d', $secuencia),
            'fecha_pago' => $plan['fecha'],
        ]);

        foreach ($plan['lineas'] as $linea) {
            DetallePedido::query()->create([
                'pedido_id' => $pedido->id,
                'variante_id' => $this->varianteIds[$linea['sku']],
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $this->precios[$linea['sku']],
                'descuento' => 0,
                'nombre_producto_snapshot' => $this->nombres[$linea['sku']],
                'variante_snapshot' => $linea['sku'],
            ]);
        }

        return $pedido;
    }

    private function registrarPedidoPendiente(User $web, CarbonImmutable $hoy): void
    {
        $sku = 'MOU-RED-COBRA-NEG';

        $pedido = Pedido::query()->create([
            'cliente_id' => $web->cliente_id,
            'usuario_id' => $web->id,
            'numero_pedido' => Pedido::siguienteNumero(),
            'nombre_contacto' => 'Lucia Demo Cliente',
            'telefono_contacto' => '70000001',
            'metodo_entrega' => 'RETIRO_EN_TIENDA',
            'estado' => Pedido::PENDIENTE_PAGO,
            'origen' => Pedido::ORIGEN_TIENDA,
            'fecha' => $hoy->subMinutes(20),
            'total' => $this->precios[$sku],
            'stripe_payment_intent_id' => 'pi_demo_pendiente',
        ]);

        DetallePedido::query()->create([
            'pedido_id' => $pedido->id,
            'variante_id' => $this->varianteIds[$sku],
            'cantidad' => 1,
            'precio_unitario' => $this->precios[$sku],
            'descuento' => 0,
            'nombre_producto_snapshot' => $this->nombres[$sku],
            'variante_snapshot' => $sku,
        ]);
    }

    private function registrarGastosYMovimientos(CarbonImmutable $hoy): void
    {
        $gastos = [
            ['Alquiler', 85, 'TRANSFERENCIA', 'Alquiler del mes', 1500.00],
            ['Alquiler', 55, 'TRANSFERENCIA', 'Alquiler del mes', 1500.00],
            ['Alquiler', 25, 'TRANSFERENCIA', 'Alquiler del mes', 1500.00],
            ['Servicios Básicos', 80, 'EFECTIVO', 'Pago de internet y luz', 320.00],
            ['Servicios Básicos', 50, 'EFECTIVO', 'Pago de internet y luz', 310.00],
            ['Servicios Básicos', 20, 'EFECTIVO', 'Pago de internet y luz', 330.00],
            ['Servicios Básicos', 70, 'EFECTIVO', 'Pago de agua', 60.00],
            ['Publicidad', 75, 'QR', 'Publicidad en redes sociales', 250.00],
            ['Publicidad', 45, 'QR', 'Impresión de catálogo físico', 150.00],
            ['Publicidad', 12, 'EFECTIVO', 'Impulso de publicaciones en Facebook', 100.00],
            ['Viáticos', 65, 'EFECTIVO', 'Movilidad para recojo de mercadería', 80.00],
            ['Viáticos', 38, 'EFECTIVO', 'Combustible para entregas', 120.00],
            ['Viáticos', 9, 'EFECTIVO', 'Movilidad para entregas', 90.00],
        ];

        foreach ($gastos as [$categoria, $dias, $metodo, $concepto, $monto]) {
            $this->registrarGasto($hoy->subDays($dias)->setTime(11, 0), $categoria, $metodo, $concepto, $monto);
        }

        $this->registrarGasto($hoy->subDays(30)->setTime(12, 0), 'Publicidad', 'QR', 'Banner duplicado', 200.00, true);

        foreach ([
            [89, MovimientoCaja::INGRESO, 'Aporte de capital', 20000.00, 'Aporte inicial para compra de mercadería'],
            [22, MovimientoCaja::INGRESO, 'Otros ingresos', 150.00, 'Venta de cajas y embalajes'],
            [35, MovimientoCaja::EGRESO, 'Retiro del propietario', 400.00, 'Retiro para gastos personales'],
        ] as [$dias, $tipo, $categoria, $monto, $descripcion]) {
            MovimientoCaja::query()->create([
                'usuario_id' => $this->usuarioIds['admin'],
                'tipo' => $tipo,
                'categoria' => $categoria,
                'monto' => $monto,
                'fecha' => $hoy->subDays($dias)->setTime(10, 0),
                'descripcion' => $descripcion,
                'estado' => 'ACTIVO',
            ]);
        }
    }

    private function registrarGasto(CarbonImmutable $fecha, string $categoria, string $metodo, string $concepto, float $monto, bool $anulado = false): void
    {
        $estado = $anulado ? 'ANULADO' : 'ACTIVO';
        $motivo = $anulado ? 'Registrado por error' : null;

        $gasto = Gasto::query()->create([
            'categoria_gasto_id' => DB::table('categoria_gasto')->where('nombre', $categoria)->value('id'),
            'metodo_pago_id' => $this->metodos[$metodo],
            'usuario_id' => $this->usuarioIds['admin'],
            'fecha' => $fecha,
            'concepto' => $concepto,
            'monto' => $monto,
            'estado' => $estado,
            'motivo_anulacion' => $motivo,
        ]);

        MovimientoCaja::query()->create([
            'usuario_id' => $this->usuarioIds['admin'],
            'gasto_id' => $gasto->id,
            'metodo_pago_id' => $this->metodos[$metodo],
            'tipo' => MovimientoCaja::EGRESO,
            'categoria' => $categoria,
            'monto' => $monto,
            'fecha' => $fecha,
            'descripcion' => $concepto,
            'estado' => $estado,
            'motivo_anulacion' => $motivo,
        ]);
    }

    private function registrarVisitas(CarbonImmutable $hoy): void
    {
        $productoIds = DB::table('producto')->orderBy('id')->pluck('id')->all();
        $pesos = array_map(fn (int $indice): int => $indice < 5 ? 8 : ($indice < 12 ? 4 : 1), array_keys($productoIds));
        $filas = [];

        foreach (range(1, 260) as $n) {
            $filas[] = [
                'producto_id' => $productoIds[$this->tirada($pesos)],
                'tipo_evento' => 'VISTA',
                'session_id' => substr(md5('visita-'.$n), 0, 24),
                'fecha_hora' => $hoy->subMinutes(mt_rand(5, 90 * 24 * 60)),
            ];
        }

        foreach (array_chunk($filas, 100) as $lote) {
            DB::table('evento_producto')->insert($lote);
        }
    }
}
