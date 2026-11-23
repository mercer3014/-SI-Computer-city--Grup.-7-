<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $usuario_id
 * @property string|null $titulo
 * @property Carbon|null $fecha_inicio
 * @property Carbon|null $fecha_actualizacion
 */
class ConversacionAsistente extends Model
{
    public $timestamps = false;

    protected $table = 'conversacion_asistente';

    protected $fillable = [
        'usuario_id',
        'titulo',
        'fecha_inicio',
        'fecha_actualizacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    /**
     * @param  Builder<ConversacionAsistente>  $consulta
     * @return Builder<ConversacionAsistente>
     */
    public function scopeDe(Builder $consulta, User $usuario): Builder
    {
        return $consulta->where('usuario_id', $usuario->id);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return HasMany<MensajeAsistente, $this> */
    public function mensajes(): HasMany
    {
        return $this->hasMany(MensajeAsistente::class, 'conversacion_id')->orderBy('id');
    }
}
