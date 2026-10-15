<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property string|null $estado
 */
class MetodoPago extends Model
{
    public $timestamps = false;

    protected $table = 'metodo_pago';

    protected $fillable = [
        'codigo',
        'nombre',
        'estado',
    ];

    /** @return HasMany<PagoVenta, $this> */
    public function pagos(): HasMany
    {
        return $this->hasMany(PagoVenta::class, 'metodo_pago_id');
    }
}
