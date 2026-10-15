<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $categoria_padre_id
 * @property string $nombre
 * @property string $slug
 * @property string|null $descripcion
 * @property bool $es_promocion
 * @property string|null $texto_promocion
 * @property int $orden_visualizacion
 * @property bool $activo
 * @property string|null $icono
 * @property \Illuminate\Support\Carbon|null $fecha_creacion
 */
class Categoria extends Model
{
    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = null;

    protected $table = 'categoria';

    protected $fillable = [
        'categoria_padre_id',
        'nombre',
        'slug',
        'descripcion',
        'es_promocion',
        'texto_promocion',
        'orden_visualizacion',
        'activo',
        'icono',
    ];

    protected function casts(): array
    {
        return [
            'es_promocion'        => 'boolean',
            'activo'              => 'boolean',
            'orden_visualizacion' => 'integer',
            'fecha_creacion'      => 'datetime',
        ];
    }

    /** @return HasMany<Producto, $this> */
    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class, 'categoria_id');
    }

    /** @return HasMany<Categoria, $this> */
    public function hijos(): HasMany
    {
        return $this->hasMany(Categoria::class, 'categoria_padre_id');
    }
}
