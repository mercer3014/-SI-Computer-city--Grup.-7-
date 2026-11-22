<?php

namespace App\Services\Reportes;

use App\Services\Reportes\Generadores\ComprasProveedor;
use App\Services\Reportes\Generadores\EstadoResultados;
use App\Services\Reportes\Generadores\GastosFlujoCaja;
use App\Services\Reportes\Generadores\ProductosMasVendidos;
use App\Services\Reportes\Generadores\UtilidadBruta;
use App\Services\Reportes\Generadores\VentasPeriodo;

/**
 * Los 6 reportes predefinidos de CU16, con los filtros que acepta cada uno.
 */
final class CatalogoReportes
{
    /**
     * @return array<string, array{titulo: string, descripcion: string, filtros: list<string>, generador: class-string<GeneradorReporte>}>
     */
    public static function todos(): array
    {
        return [
            'ventas_periodo' => [
                'titulo' => 'Ventas por período',
                'descripcion' => 'Cantidad de ventas, ingresos, costo y utilidad bruta, con desglose por día, semana o mes.',
                'filtros' => ['categoria_id', 'marca_id', 'metodo_pago_id', 'agrupar'],
                'generador' => VentasPeriodo::class,
            ],
            'productos_mas_vendidos' => [
                'titulo' => 'Productos más vendidos',
                'descripcion' => 'Ranking por unidades vendidas, con el monto de cada producto.',
                'filtros' => ['categoria_id', 'marca_id', 'limite'],
                'generador' => ProductosMasVendidos::class,
            ],
            'utilidad_bruta' => [
                'titulo' => 'Utilidad bruta',
                'descripcion' => 'Ingresos menos el costo de lo vendido, por categoría.',
                'filtros' => ['categoria_id', 'marca_id'],
                'generador' => UtilidadBruta::class,
            ],
            'gastos_flujo_caja' => [
                'titulo' => 'Gastos y flujo de caja',
                'descripcion' => 'Ingresos, egresos y saldo del período, y los gastos por categoría.',
                'filtros' => ['categoria_gasto_id', 'metodo_pago_id'],
                'generador' => GastosFlujoCaja::class,
            ],
            'compras_proveedor' => [
                'titulo' => 'Compras por proveedor',
                'descripcion' => 'Cantidad de compras y monto comprado a cada proveedor.',
                'filtros' => ['proveedor_id'],
                'generador' => ComprasProveedor::class,
            ],
            'estado_resultados' => [
                'titulo' => 'Estado de resultados',
                'descripcion' => 'Por mes: ingresos, costo de ventas, utilidad bruta, gastos operativos y utilidad neta.',
                'filtros' => [],
                'generador' => EstadoResultados::class,
            ],
        ];
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::todos());
    }

    public static function existe(string $clave): bool
    {
        return array_key_exists($clave, self::todos());
    }

    /** @return list<string> */
    public static function filtrosPermitidos(string $clave): array
    {
        return self::todos()[$clave]['filtros'] ?? [];
    }

    public static function titulo(string $clave): string
    {
        return self::todos()[$clave]['titulo'] ?? $clave;
    }
}
