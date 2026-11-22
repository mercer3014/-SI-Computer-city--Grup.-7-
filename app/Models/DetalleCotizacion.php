<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cotizacion_id
 * @property int $variante_id
 * @property int $cantidad
 * @property float $precio_unitario
 * @property float|null $descuento
 * @property string|null $descripcion
 */
class DetalleCotizacion extends Model
{
    public $timestamps = false;

    protected $table = 'detalle_cotizacion';

    protected $fillable = [
        'cotizacion_id',
        'variante_id',
        'cantidad',
        'precio_unitario',
        'descuento',
        'descripcion',
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

    /** @return BelongsTo<Cotizacion, $this> */
    public function cotizacion(): BelongsTo
    {
        return $this->belongsTo(Cotizacion::class, 'cotizacion_id');
    }

    /** @return BelongsTo<VarianteProducto, $this> */
    public function variante(): BelongsTo
    {
        return $this->belongsTo(VarianteProducto::class, 'variante_id');
    }
}
