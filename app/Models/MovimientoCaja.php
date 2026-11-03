<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $usuario_id
 * @property int|null $gasto_id
 * @property int|null $metodo_pago_id
 * @property string $tipo
 * @property string|null $categoria
 * @property float $monto
 * @property Carbon|null $fecha
 * @property string|null $descripcion
 * @property string|null $referencia
 * @property string|null $estado
 * @property string|null $motivo_anulacion
 */
class MovimientoCaja extends Model
{
    public const INGRESO = 'INGRESO';

    public const EGRESO = 'EGRESO';

    public $timestamps = false;

    protected $table = 'movimiento_caja';

    protected $fillable = [
        'usuario_id',
        'gasto_id',
        'metodo_pago_id',
        'tipo',
        'categoria',
        'monto',
        'fecha',
        'descripcion',
        'referencia',
        'estado',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'float',
            'fecha' => 'datetime',
        ];
    }

    /** @return BelongsTo<Gasto, $this> */
    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class, 'gasto_id');
    }
}
