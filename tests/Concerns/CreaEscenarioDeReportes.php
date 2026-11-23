<?php

namespace Tests\Concerns;

use App\Models\Categoria;
use App\Models\CategoriaGasto;
use App\Models\Cliente;
use App\Models\DetalleVenta;
use App\Models\Gasto;
use App\Models\Marca;
use App\Models\MetodoPago;
use App\Models\MovimientoCaja;
use App\Models\PagoVenta;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Datos de octubre de 2026 con resultados fáciles de verificar a mano.
 *
 * Ventas (sin anuladas): 05/10 = 400 (costo 240), 06/10 = 300 (costo 180) y 08/10 = 100 (sin costo).
 * Ingresos 800, costo 420 y utilidad 280. Hay una venta anulada de 1000 que no debe contar.
 * Compras: 1200 a "Prov A" y 1200 a "Prov B", más una anulada de 300 que no debe contar.
 * Gastos: Alquiler 500 y Publicidad 100 anulado. Aporte de capital manual de 1000.
 */
trait CreaEscenarioDeReportes
{
    private int $numeroVentaDePrueba = 0;

    /**
     * @return array{mouse: VarianteProducto, teclado: VarianteProducto, accesorio: VarianteProducto, categorias: array<string, Categoria>, marcas: array<string, Marca>, metodos: array<string, MetodoPago>, proveedores: array<string, Proveedor>, gastos: array<string, CategoriaGasto>}
     */
    protected function escenarioDeReportes(): array
    {
        $efectivo = MetodoPago::query()->create(['codigo' => 'EFECTIVO', 'nombre' => 'Efectivo', 'estado' => 'ACTIVO']);
        $qr = MetodoPago::query()->create(['codigo' => 'QR', 'nombre' => 'Pago con QR', 'estado' => 'ACTIVO']);

        $categorias = [
            'mouse' => Categoria::query()->create(['nombre' => 'Mouse', 'slug' => 'mouse']),
            'teclados' => Categoria::query()->create(['nombre' => 'Teclados', 'slug' => 'teclados']),
            'accesorios' => Categoria::query()->create(['nombre' => 'Accesorios', 'slug' => 'accesorios']),
        ];
        $marcas = [
            'logitech' => Marca::query()->create(['nombre' => 'Logitech', 'slug' => 'logitech']),
            'redragon' => Marca::query()->create(['nombre' => 'Redragon', 'slug' => 'redragon']),
        ];

        $mouse = $this->varianteDePrueba($categorias['mouse'], $marcas['logitech'], 'Mouse Logitech G203', 'MOU-1', 100);
        $teclado = $this->varianteDePrueba($categorias['teclados'], $marcas['redragon'], 'Teclado Redragon K552', 'TEC-1', 200);
        $accesorio = $this->varianteDePrueba($categorias['accesorios'], $marcas['logitech'], 'Hub USB', 'ACC-1', 50);

        $proveedores = [
            'a' => Proveedor::query()->create(['nit' => '1001', 'razon_social' => 'Prov A', 'estado' => 'ACTIVO']),
            'b' => Proveedor::query()->create(['nit' => '1002', 'razon_social' => 'Prov B', 'estado' => 'ACTIVO']),
        ];

        $this->compraDePrueba($proveedores['a'], 'C-1', '2026-10-01 09:00:00', 'COMPLETADO', [[$mouse, 20, 60]]);
        $this->compraDePrueba($proveedores['b'], 'C-2', '2026-10-02 09:00:00', 'COMPLETADO', [[$teclado, 10, 120]]);
        $this->compraDePrueba($proveedores['a'], 'C-3', '2026-10-03 09:00:00', 'ANULADA', [[$mouse, 5, 60]]);

        $this->ventaDePrueba('2026-10-05 10:00:00', [[$mouse, 2], [$teclado, 1]], [[$efectivo, 400]]);
        $this->ventaDePrueba('2026-10-06 11:00:00', [[$mouse, 3]], [[$qr, 300]]);
        $this->ventaDePrueba('2026-10-07 12:00:00', [[$teclado, 5]], [[$efectivo, 1000]], anulada: true);
        $this->ventaDePrueba('2026-10-08 13:00:00', [[$accesorio, 2]], [[$efectivo, 100]]);

        $gastos = [
            'alquiler' => CategoriaGasto::query()->create(['nombre' => 'Alquiler', 'estado' => 'ACTIVO']),
            'publicidad' => CategoriaGasto::query()->create(['nombre' => 'Publicidad', 'estado' => 'ACTIVO']),
        ];

        $this->gastoDePrueba($gastos['alquiler'], '2026-10-10 11:00:00', 500, 'ACTIVO', $efectivo);
        $this->gastoDePrueba($gastos['publicidad'], '2026-10-11 11:00:00', 100, 'ANULADO', $efectivo);

        MovimientoCaja::query()->create([
            'usuario_id' => $this->usuarioDePrueba()->id,
            'tipo' => MovimientoCaja::INGRESO,
            'categoria' => 'Aporte de capital',
            'monto' => 1000,
            'fecha' => '2026-10-02 09:00:00',
            'descripcion' => 'Aporte inicial',
            'estado' => 'ACTIVO',
        ]);

        return [
            'mouse' => $mouse,
            'teclado' => $teclado,
            'accesorio' => $accesorio,
            'categorias' => $categorias,
            'marcas' => $marcas,
            'metodos' => ['efectivo' => $efectivo, 'qr' => $qr],
            'proveedores' => $proveedores,
            'gastos' => $gastos,
        ];
    }

    private function usuarioDePrueba(): User
    {
        return User::query()->first() ?? User::factory()->create(['primer_login' => false]);
    }

    private function varianteDePrueba(Categoria $categoria, Marca $marca, string $nombre, string $sku, float $precio): VarianteProducto
    {
        $producto = Producto::query()->create([
            'categoria_id' => $categoria->id,
            'marca_id' => $marca->id,
            'nombre' => $nombre,
            'slug' => strtolower($sku),
            'estado' => 'ACTIVO',
        ]);

        return $producto->variantes()->create([
            'sku' => $sku,
            'precio_venta' => $precio,
            'stock_actual' => 100,
            'stock_minimo' => 2,
            'estado' => 'ACTIVO',
        ]);
    }

    /**
     * @param  list<array{0: VarianteProducto, 1: int, 2: int}>  $lineas
     */
    private function compraDePrueba(Proveedor $proveedor, string $numero, string $fecha, string $estado, array $lineas): void
    {
        $compraId = DB::table('compra')->insertGetId([
            'proveedor_id' => $proveedor->id,
            'usuario_id' => $this->usuarioDePrueba()->id,
            'numero_compra' => $numero,
            'fecha_compra' => $fecha,
            'estado' => $estado,
        ]);

        foreach ($lineas as [$variante, $cantidad, $costo]) {
            DB::table('detalle_compra')->insert([
                'compra_id' => $compraId,
                'variante_id' => $variante->id,
                'cantidad' => $cantidad,
                'costo_unitario' => $costo,
            ]);
        }
    }

    /**
     * @param  list<array{0: VarianteProducto, 1: int}>  $lineas
     * @param  list<array{0: MetodoPago, 1: float|int}>  $pagos
     */
    private function ventaDePrueba(string $fecha, array $lineas, array $pagos, bool $anulada = false): Venta
    {
        $usuario = $this->usuarioDePrueba();
        $cliente = Cliente::query()->first() ?? Cliente::query()->create([
            'tipo_documento' => 'CI',
            'numero_documento' => '4521230 SC',
            'nombre' => 'Juan Perez',
            'estado' => 'ACTIVO',
        ]);

        $venta = Venta::query()->create([
            'cliente_id' => $cliente->id,
            'usuario_id' => $usuario->id,
            'numero_venta' => sprintf('V-2026-%04d', ++$this->numeroVentaDePrueba),
            'fecha' => $fecha,
            'estado' => 'COMPLETADA',
            'descuento_global' => 0,
        ]);

        foreach ($lineas as [$variante, $cantidad]) {
            DetalleVenta::query()->create([
                'venta_id' => $venta->id,
                'variante_id' => $variante->id,
                'cantidad' => $cantidad,
                'precio_unitario' => $variante->precio_venta,
                'descuento' => 0,
                'nombre_producto_snapshot' => $variante->sku,
                'meses_garantia' => 3,
                'estado' => 'VIGENTE',
            ]);
        }

        foreach ($pagos as [$metodo, $monto]) {
            PagoVenta::query()->create([
                'venta_id' => $venta->id,
                'metodo_pago_id' => $metodo->id,
                'monto' => $monto,
                'fecha' => $fecha,
            ]);
        }

        if ($anulada) {
            $venta->update([
                'estado' => 'ANULADA',
                'fecha_anulacion' => $fecha,
                'anulado_por' => $usuario->id,
                'motivo_anulacion' => 'Prueba',
            ]);
        }

        return $venta;
    }

    private function gastoDePrueba(CategoriaGasto $categoria, string $fecha, float $monto, string $estado, MetodoPago $metodo): Gasto
    {
        return Gasto::query()->create([
            'categoria_gasto_id' => $categoria->id,
            'metodo_pago_id' => $metodo->id,
            'usuario_id' => $this->usuarioDePrueba()->id,
            'fecha' => $fecha,
            'concepto' => 'Gasto de '.$categoria->nombre,
            'monto' => $monto,
            'estado' => $estado,
        ]);
    }
}
