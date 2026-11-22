<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Horario de un día de la semana (1 = lunes ... 7 = domingo).
 *
 * @property int $id
 * @property int $configuracion_tienda_id
 * @property int $dia_semana
 * @property string|null $hora_apertura
 * @property string|null $hora_cierre
 * @property bool $cerrado
 */
class HorarioAtencion extends Model
{
    public $timestamps = false;

    protected $table = 'horario_atencion';

    protected $fillable = [
        'configuracion_tienda_id',
        'dia_semana',
        'hora_apertura',
        'hora_cierre',
        'cerrado',
    ];

    protected function casts(): array
    {
        return [
            'dia_semana' => 'integer',
            'cerrado' => 'boolean',
        ];
    }

    /** @return BelongsTo<ConfiguracionTienda, $this> */
    public function configuracion(): BelongsTo
    {
        return $this->belongsTo(ConfiguracionTienda::class, 'configuracion_tienda_id');
    }
}
