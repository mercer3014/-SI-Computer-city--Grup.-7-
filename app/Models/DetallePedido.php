<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pedido_id
 * @property int $variante_id
 * @property int $cantidad
 * @property float $precio_unitario
 * @property float|null $descuento
 * @property string|null $nombre_producto_snapshot
 * @property string|null $variante_snapshot
 */
class DetallePedido extends Model
{
    public $timestamps = false;

    protected $table = 'detalle_pedido';

    protected $fillable = [
        'pedido_id',
        'variante_id',
        'cantidad',
        'precio_unitario',
        'descuento',
        'nombre_producto_snapshot',
        'variante_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'precio_unitario' => 'float',
            'descuento' => 'float',
        ];
    }

    public function subtotal(): float
    {
        return round($this->cantidad * (float) $this->precio_unitario - (float) $this->descuento, 2);
    }

    /** @return BelongsTo<Pedido, $this> */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    /** @return BelongsTo<VarianteProducto, $this> */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(VarianteProducto::class, 'variante_id');
    }
}
