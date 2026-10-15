<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $usuario_id
 * @property int|null $venta_id
 * @property int|null $compra_id
 * @property string $tipo
 * @property Carbon|null $fecha
 * @property string|null $referencia
 * @property string|null $observacion
 */
class MovimientoInventario extends Model
{
    public $timestamps = false;

    protected $table = 'movimiento_inventario';

    protected $fillable = [
        'usuario_id',
        'venta_id',
        'compra_id',
        'tipo',
        'fecha',
        'referencia',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return HasMany<DetalleMovimientoInventario, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleMovimientoInventario::class, 'movimiento_id');
    }
}
