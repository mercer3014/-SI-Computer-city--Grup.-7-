<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property string $slug
 * @property string $estado
 */
class Marca extends Model
{
    public $timestamps = false;

    protected $table = 'marca';

    protected $fillable = [
        'nombre',
        'slug',
        'estado',
    ];

    /** @return HasMany<Producto, $this> */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'marca_id');
    }
}
