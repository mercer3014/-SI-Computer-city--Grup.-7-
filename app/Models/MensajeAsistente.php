<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $conversacion_id
 * @property string $rol
 * @property string $contenido
 * @property string $origen
 * @property string $tipo
 * @property array<string, mixed>|null $especificacion
 * @property string|null $interpretacion
 * @property string|null $enlace
 * @property Carbon|null $fecha
 */
class MensajeAsistente extends Model
{
    public const USUARIO = 'USUARIO';

    public const ASISTENTE = 'ASISTENTE';

    public const TEXTO = 'TEXTO';

    public const CONSULTA = 'CONSULTA';

    public const REPORTE = 'REPORTE';

    public const ERROR = 'ERROR';

    public const NO_SOPORTADO = 'NO_SOPORTADO';

    public $timestamps = false;

    protected $table = 'mensaje_asistente';

    protected $fillable = [
        'conversacion_id',
        'rol',
        'contenido',
        'origen',
        'tipo',
        'especificacion',
        'interpretacion',
        'enlace',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'especificacion' => 'array',
            'fecha' => 'datetime',
        ];
    }

    /** @return BelongsTo<ConversacionAsistente, $this> */
    public function conversacion(): BelongsTo
    {
        return $this->belongsTo(ConversacionAsistente::class, 'conversacion_id');
    }
}
