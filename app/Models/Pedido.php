<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cliente_id
 * @property int|null $usuario_id
 * @property string $numero_pedido
 * @property string|null $nombre_contacto
 * @property string|null $telefono_contacto
 * @property string|null $metodo_entrega
 * @property string|null $direccion_entrega
 * @property string|null $estado
 * @property string|null $origen
 * @property string|null $observacion
 * @property Carbon|null $fecha
 * @property float|null $total
 * @property string|null $stripe_payment_intent_id
 * @property Carbon|null $fecha_pago
 * @property string|null $error_pago
 */
class Pedido extends Model
{
    public const PENDIENTE_PAGO = 'PENDIENTE_PAGO';

    public const PAGADO = 'PAGADO';

    public const CANCELADO = 'CANCELADO';

    public const REEMBOLSADO = 'REEMBOLSADO';

    public const ORIGEN_TIENDA = 'TIENDA_WEB';

    public $timestamps = false;

    protected $table = 'pedido';

    protected $fillable = [
        'cliente_id',
        'usuario_id',
        'numero_pedido',
        'nombre_contacto',
        'telefono_contacto',
        'metodo_entrega',
        'direccion_entrega',
        'estado',
        'origen',
        'observacion',
        'fecha',
        'total',
        'stripe_payment_intent_id',
        'fecha_pago',
        'error_pago',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'float',
            'fecha' => 'datetime',
            'fecha_pago' => 'datetime',
        ];
    }

    /**
     * Correlativo PED-AAAA-0001, calculado dentro de la transacción.
     */
    public static function siguienteNumero(): string
    {
        $prefijo = 'PED-'.now()->year.'-';

        $ultimo = static::query()
            ->where('numero_pedido', 'like', $prefijo.'%')
            ->orderByDesc('numero_pedido')
            ->lockForUpdate()
            ->value('numero_pedido');

        $secuencia = 1;

        if (is_string($ultimo) && preg_match('/-(\d+)$/', $ultimo, $partes) === 1) {
            $secuencia = (int) $partes[1] + 1;
        }

        return sprintf('%s%04d', $prefijo, $secuencia);
    }

    /** Monto en la unidad mínima de la moneda (centavos), como lo espera Stripe. */
    public function montoCentavos(): int
    {
        return (int) round((float) $this->total * 100);
    }

    public function estaPagado(): bool
    {
        return $this->estado === self::PAGADO;
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

    /** @return HasMany<DetallePedido, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetallePedido::class, 'pedido_id');
    }

    /** @return HasOne<Venta, $this> */
    public function venta(): HasOne
    {
        return $this->hasOne(Venta::class, 'pedido_id');
    }
}
