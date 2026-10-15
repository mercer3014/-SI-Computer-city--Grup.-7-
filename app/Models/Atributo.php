<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $estado
 */
class Atributo extends Model
{
    public $timestamps = false;

    protected $table = 'atributo';

    protected $fillable = [
        'nombre',
        'estado',
    ];

    /** @return HasMany<ValorAtributo, $this> */
    public function valores(): HasMany
    {
        return $this->hasMany(ValorAtributo::class, 'atributo_id')
            ->orderBy('orden_visualizacion')
            ->orderBy('valor');
    }
}
