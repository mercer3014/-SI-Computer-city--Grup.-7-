<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $venta_id
 * @property int $metodo_pago_id
 * @property float $monto
 * @property string|null $referencia
 * @property Carbon|null $fecha
 * @property string|null $observacion
 */
class PagoVenta extends Model
{
    public $timestamps = false;

    protected $table = 'pago_venta';

    protected $fillable = [
        'venta_id',
        'metodo_pago_id',
        'monto',
        'referencia',
        'fecha',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'float',
            'fecha' => 'datetime',
        ];
    }

    /** @return BelongsTo<Venta, $this> */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    /** @return BelongsTo<MetodoPago, $this> */
    public function metodo(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }
}
