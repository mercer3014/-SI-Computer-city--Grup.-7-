<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $venta_id
 * @property int $variante_id
 * @property int $cantidad
 * @property float $precio_unitario
 * @property float|null $descuento
 * @property float|null $costo_unitario_snapshot
 * @property string|null $nombre_producto_snapshot
 * @property string|null $variante_snapshot
 * @property string|null $numero_serie
 * @property int $meses_garantia
 * @property Carbon|null $fecha_fin_garantia
 * @property string $estado
 */
class DetalleVenta extends Model
{
    public $timestamps = false;

    protected $table = 'detalle_venta';

    protected $fillable = [
        'venta_id',
        'variante_id',
        'cantidad',
        'precio_unitario',
        'descuento',
        'costo_unitario_snapshot',
        'nombre_producto_snapshot',
        'variante_snapshot',
        'numero_serie',
        'meses_garantia',
        'fecha_fin_garantia',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'float',
            'descuento' => 'float',
            'costo_unitario_snapshot' => 'float',
            'meses_garantia' => 'integer',
            'fecha_fin_garantia' => 'date',
        ];
    }

    public function subtotal(): float
    {
        return round(($this->cantidad * $this->precio_unitario) - (float) $this->descuento, 2);
    }

    /** @return BelongsTo<Venta, $this> */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    /** @return BelongsTo<VarianteProducto, $this> */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(VarianteProducto::class, 'variante_id');
    }
}
