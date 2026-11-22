<?php

namespace App\Services\Asistente;

/**
 * Lo único que el asistente virtual puede consultar: métricas, dimensiones y filtros.
 * Todo lo que propone la IA se contrasta contra este catálogo antes de tocar la base de datos.
 *
 * @phpstan-type Metrica array{etiqueta: string, descripcion: string, dimensiones: list<string>, filtros: list<string>, tipo: 'entero'|'decimal'|'moneda', aditiva: bool}
 */
final class CatalogoAsistente
{
    public const TEMPORALES = ['dia', 'semana', 'mes'];

    public const DIMENSIONES = ['dia', 'semana', 'mes', 'producto', 'categoria', 'marca', 'proveedor', 'metodo_pago', 'categoria_gasto', 'estado'];

    public const FILTROS = ['categoria', 'marca', 'proveedor', 'producto', 'metodo_pago', 'estado'];

    public const GRAFICOS = ['barra', 'linea', 'torta', 'tabla'];

    public const ESTADOS_PEDIDO = ['PAGADO', 'PENDIENTE_PAGO', 'CANCELADO', 'REEMBOLSADO'];

    public const ESTADOS_COTIZACION = ['PENDIENTE', 'ATENDIDA', 'RECHAZADA', 'VENCIDA', 'CONVERTIDA'];

    public const ESTADOS_STOCK = ['bajo_minimo', 'agotado'];

    public const ETIQUETAS_DIMENSION = [
        'dia' => 'día',
        'semana' => 'semana',
        'mes' => 'mes',
        'producto' => 'producto',
        'categoria' => 'categoría',
        'marca' => 'marca',
        'proveedor' => 'proveedor',
        'metodo_pago' => 'método de pago',
        'categoria_gasto' => 'categoría de gasto',
        'estado' => 'estado',
    ];

    public const ETIQUETAS_FILTRO = [
        'categoria' => 'categoría',
        'marca' => 'marca',
        'proveedor' => 'proveedor',
        'producto' => 'producto',
        'metodo_pago' => 'método de pago',
        'estado' => 'estado',
    ];

    /**
     * Qué dimensiones y filtros admite cada métrica (la matriz métrica × dimensión).
     *
     * @return array<string, Metrica>
     */
    public static function metricas(): array
    {
        return [
            'ventas_monto' => [
                'etiqueta' => 'Ventas en Bs',
                'descripcion' => 'Monto vendido (sin ventas anuladas). Por método de pago es lo cobrado con cada método.',
                'dimensiones' => ['dia', 'semana', 'mes', 'producto', 'categoria', 'marca', 'metodo_pago'],
                'filtros' => ['categoria', 'marca', 'producto', 'metodo_pago'],
                'tipo' => 'moneda',
                'aditiva' => true,
            ],
            'ventas_cantidad' => [
                'etiqueta' => 'Cantidad de ventas',
                'descripcion' => 'Número de ventas registradas (sin anuladas).',
                'dimensiones' => ['dia', 'semana', 'mes', 'producto', 'categoria', 'marca', 'metodo_pago'],
                'filtros' => ['categoria', 'marca', 'producto', 'metodo_pago'],
                'tipo' => 'entero',
                'aditiva' => true,
            ],
            'unidades_vendidas' => [
                'etiqueta' => 'Unidades vendidas',
                'descripcion' => 'Unidades de producto vendidas (sin ventas anuladas).',
                'dimensiones' => ['dia', 'semana', 'mes', 'producto', 'categoria', 'marca'],
                'filtros' => ['categoria', 'marca', 'producto'],
                'tipo' => 'entero',
                'aditiva' => true,
            ],
            'utilidad_bruta' => [
                'etiqueta' => 'Utilidad bruta en Bs',
                'descripcion' => 'Ingresos menos el costo histórico de lo vendido.',
                'dimensiones' => ['dia', 'semana', 'mes', 'producto', 'categoria', 'marca'],
                'filtros' => ['categoria', 'marca', 'producto'],
                'tipo' => 'moneda',
                'aditiva' => true,
            ],
            'gastos' => [
                'etiqueta' => 'Gastos en Bs',
                'descripcion' => 'Gastos operativos (sin anulados). El filtro "categoria" es la categoría de gasto.',
                'dimensiones' => ['dia', 'semana', 'mes', 'categoria_gasto', 'metodo_pago'],
                'filtros' => ['categoria', 'metodo_pago'],
                'tipo' => 'moneda',
                'aditiva' => true,
            ],
            'compras' => [
                'etiqueta' => 'Compras en Bs',
                'descripcion' => 'Monto comprado a proveedores (sin compras anuladas).',
                'dimensiones' => ['dia', 'semana', 'mes', 'proveedor', 'producto'],
                'filtros' => ['proveedor', 'producto', 'categoria', 'marca'],
                'tipo' => 'moneda',
                'aditiva' => true,
            ],
            'saldo_caja' => [
                'etiqueta' => 'Saldo de caja en Bs',
                'descripcion' => 'Ingresos menos egresos de caja. Sin dimensiones muestra el saldo acumulado y el neto del período.',
                'dimensiones' => ['dia', 'semana', 'mes'],
                'filtros' => ['metodo_pago'],
                'tipo' => 'moneda',
                'aditiva' => true,
            ],
            'stock' => [
                'etiqueta' => 'Stock en unidades',
                'descripcion' => 'Existencias actuales. El filtro "estado" admite bajo_minimo o agotado. No depende del período.',
                'dimensiones' => ['producto', 'categoria', 'marca'],
                'filtros' => ['categoria', 'marca', 'producto', 'estado'],
                'tipo' => 'entero',
                'aditiva' => true,
            ],
            'pedidos' => [
                'etiqueta' => 'Pedidos en línea',
                'descripcion' => 'Pedidos de la tienda web. El filtro "estado" admite PAGADO, PENDIENTE_PAGO, CANCELADO o REEMBOLSADO.',
                'dimensiones' => ['dia', 'semana', 'mes', 'estado'],
                'filtros' => ['estado'],
                'tipo' => 'entero',
                'aditiva' => true,
            ],
            'cotizaciones' => [
                'etiqueta' => 'Cotizaciones',
                'descripcion' => 'Cotizaciones emitidas. El filtro "estado" admite PENDIENTE, ATENDIDA, RECHAZADA, VENCIDA o CONVERTIDA.',
                'dimensiones' => ['dia', 'semana', 'mes', 'estado'],
                'filtros' => ['estado'],
                'tipo' => 'entero',
                'aditiva' => true,
            ],
            'calificacion_promedio' => [
                'etiqueta' => 'Calificación promedio',
                'descripcion' => 'Promedio de estrellas de las calificaciones publicadas.',
                'dimensiones' => ['dia', 'semana', 'mes', 'producto'],
                'filtros' => ['producto', 'categoria', 'marca'],
                'tipo' => 'decimal',
                'aditiva' => false,
            ],
        ];
    }

    /** @return list<string> */
    public static function claves(): array
    {
        return array_keys(self::metricas());
    }

    public static function esTemporal(string $dimension): bool
    {
        return in_array($dimension, self::TEMPORALES, true);
    }
}
