<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $producto_id
 * @property string $url_webp
 * @property int $orden_visualizacion
 * @property bool $es_principal
 * @property string|null $texto_alternativo
 */
class ImagenProducto extends Model
{
    public $timestamps = false;

    protected $table = 'imagen_producto';

    protected $fillable = [
        'producto_id',
        'url_webp',
        'orden_visualizacion',
        'es_principal',
        'texto_alternativo',
    ];

    protected function casts(): array
    {
        return [
            'es_principal'        => 'boolean',
            'orden_visualizacion' => 'integer',
        ];
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
