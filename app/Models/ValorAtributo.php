<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $atributo_id
 * @property string $valor
 * @property int|null $orden_visualizacion
 * @property string|null $estado
 */
class ValorAtributo extends Model
{
    public $timestamps = false;

    protected $table = 'valor_atributo';

    protected $fillable = [
        'atributo_id',
        'valor',
        'orden_visualizacion',
        'estado',
    ];

    /** @return BelongsTo<Atributo, $this> */
    public function atributo(): BelongsTo
    {
        return $this->belongsTo(Atributo::class, 'atributo_id');
    }
}
