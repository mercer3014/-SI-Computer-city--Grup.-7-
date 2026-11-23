<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * CU24: calificación de un producto de un pedido pagado, con moderación del Administrador.
 *
 * @property int $id
 * @property int $cliente_id
 * @property int $usuario_id
 * @property int $pedido_id
 * @property int $producto_id
 * @property int $puntuacion
 * @property string|null $comentario
 * @property string $estado
 * @property string|null $motivo_ocultamiento
 * @property int|null $moderado_por
 * @property Carbon|null $fecha_moderacion
 * @property Carbon|null $fecha
 */
class Calificacion extends Model
{
    public const PENDIENTE = 'PENDIENTE';

    public const PUBLICADA = 'PUBLICADA';

    public const OCULTA = 'OCULTA';

    public const ESTADOS = [self::PENDIENTE, self::PUBLICADA, self::OCULTA];

    public const MAXIMO_COMENTARIO = 500;

    public $timestamps = false;

    protected $table = 'calificacion';

    protected $fillable = [
        'cliente_id',
        'usuario_id',
        'pedido_id',
        'producto_id',
        'puntuacion',
        'comentario',
        'estado',
        'motivo_ocultamiento',
        'moderado_por',
        'fecha_moderacion',
        'fecha',
    ];

    protected function casts(): array
    {
        return [
            'puntuacion' => 'integer',
            'fecha' => 'datetime',
            'fecha_moderacion' => 'datetime',
        ];
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsTo<Pedido, $this> */
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /** @return BelongsTo<User, $this> */
    public function moderador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderado_por');
    }
}
