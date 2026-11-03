<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $categoria_gasto_id
 * @property int|null $metodo_pago_id
 * @property int $usuario_id
 * @property Carbon $fecha
 * @property string $concepto
 * @property float $monto
 * @property string|null $referencia
 * @property string|null $observacion
 * @property string|null $estado
 * @property string|null $motivo_anulacion
 * @property Carbon|null $fecha_registro
 */
class Gasto extends Model
{
    public const CREATED_AT = 'fecha_registro';

    public const UPDATED_AT = null;

    protected $table = 'gasto';

    protected $fillable = [
        'categoria_gasto_id',
        'metodo_pago_id',
        'usuario_id',
        'fecha',
        'concepto',
        'monto',
        'referencia',
        'observacion',
        'estado',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'float',
            'fecha' => 'datetime',
            'fecha_registro' => 'datetime',
        ];
    }

    /** @return BelongsTo<CategoriaGasto, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaGasto::class, 'categoria_gasto_id');
    }

    /** @return HasOne<MovimientoCaja, $this> */
    public function movimiento(): HasOne
    {
        return $this->hasOne(MovimientoCaja::class, 'gasto_id');
    }
}
