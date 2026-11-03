<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $producto_id
 * @property string $tipo_evento
 * @property string|null $session_id
 * @property Carbon|null $fecha_hora
 */
class EventoProducto extends Model
{
    public const VISTA = 'VISTA';

    public $timestamps = false;

    protected $table = 'evento_producto';

    protected $fillable = [
        'producto_id',
        'tipo_evento',
        'session_id',
        'fecha_hora',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
        ];
    }
}
