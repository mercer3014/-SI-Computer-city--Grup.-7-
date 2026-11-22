<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $usuario_id
 * @property string $nombre
 * @property array<string, mixed> $especificacion
 * @property Carbon|null $fecha_creacion
 */
class PlantillaReporte extends Model
{
    public $timestamps = false;

    protected $table = 'plantilla_reporte';

    protected $fillable = [
        'usuario_id',
        'nombre',
        'especificacion',
        'fecha_creacion',
    ];

    protected function casts(): array
    {
        return [
            'especificacion' => 'array',
            'fecha_creacion' => 'datetime',
        ];
    }
}
