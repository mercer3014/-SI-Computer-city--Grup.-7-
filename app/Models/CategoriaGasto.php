<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $estado
 */
class CategoriaGasto extends Model
{
    public $timestamps = false;

    protected $table = 'categoria_gasto';

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
    ];

    /** @return HasMany<Gasto, $this> */
    public function gastos(): HasMany
    {
        return $this->hasMany(Gasto::class, 'categoria_gasto_id');
    }
}
