<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $categoria_id
 * @property int|null $marca_id
 * @property string $nombre
 * @property string $slug
 * @property string|null $descripcion
 * @property string $estado
 * @property \Illuminate\Support\Carbon|null $fecha_creacion
 * @property \Illuminate\Support\Carbon|null $fecha_actualizacion
 */
class Producto extends Model
{
    public const CREATED_AT = 'fecha_creacion';
    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'producto';

    protected $fillable = [
        'categoria_id',
        'marca_id',
        'nombre',
        'slug',
        'descripcion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_creacion'     => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    /** @return BelongsTo<Categoria, $this> */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class, 'categoria_id');
    }

    /** @return BelongsTo<Marca, $this> */
    public function marca(): BelongsTo
    {
        return $this->belongsTo(Marca::class, 'marca_id');
    }

    /** @return HasMany<VarianteProducto, $this> */
    public function variantes(): HasMany
    {
        return $this->hasMany(VarianteProducto::class, 'producto_id');
    }

    /** @return HasMany<ImagenProducto, $this> */
    public function imagenes(): HasMany
    {
        return $this->hasMany(ImagenProducto::class, 'producto_id')->orderBy('orden_visualizacion');
    }

    /** Imagen principal del producto. */
    public function imagenPrincipal(): ?ImagenProducto
    {
        return $this->imagenes->firstWhere('es_principal', true)
            ?? $this->imagenes->first();
    }
}
