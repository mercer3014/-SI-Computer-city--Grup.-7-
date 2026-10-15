<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $movimiento_id
 * @property int $variante_id
 * @property int $cantidad
 * @property float|null $costo_unitario
 */
class DetalleMovimientoInventario extends Model
{
    public $timestamps = false;

    protected $table = 'detalle_movimiento_inventario';

    protected $fillable = [
        'movimiento_id',
        'variante_id',
        'cantidad',
        'costo_unitario',
    ];

    protected function casts(): array
    {
        return [
            'cantidad' => 'integer',
            'costo_unitario' => 'float',
        ];
    }

    /** @return BelongsTo<MovimientoInventario, $this> */
    public function movimiento(): BelongsTo
    {
        return $this->belongsTo(MovimientoInventario::class, 'movimiento_id');
    }

    /** @return BelongsTo<VarianteProducto, $this> */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(VarianteProducto::class, 'variante_id');
    }
}
