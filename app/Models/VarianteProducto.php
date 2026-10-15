<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $producto_id
 * @property string $sku
 * @property string|null $codigo_barras
 * @property float $precio_venta
 * @property float|null $precio_anterior
 * @property string $estado
 * @property int $stock_actual
 * @property int $stock_minimo
 * @property \Illuminate\Support\Carbon|null $fecha_creacion
 * @property \Illuminate\Support\Carbon|null $fecha_actualizacion
 */
class VarianteProducto extends Model
{
    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'variante_producto';

    protected $fillable = [
        'producto_id',
        'sku',
        'codigo_barras',
        'precio_venta',
        'precio_anterior',
        'estado',
        'stock_actual',
        'stock_minimo',
    ];

    protected function casts(): array
    {
        return [
            'precio_venta'        => 'float',
            'precio_anterior'     => 'float',
            'stock_actual'        => 'integer',
            'stock_minimo'        => 'integer',
            'fecha_creacion'      => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
