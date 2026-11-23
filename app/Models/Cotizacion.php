<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Cotización formal (CU19 la genera el Cliente Web; CU20 la gestiona el personal).
 *
 * @property int $id
 * @property int $cliente_id
 * @property int $usuario_id
 * @property string $codigo_cotizacion
 * @property Carbon|null $fecha_emision
 * @property Carbon|null $fecha_vencimiento
 * @property string|null $estado
 * @property string|null $observacion
 * @property Carbon|null $fecha_creacion
 * @property string $origen
 * @property Carbon|null $fecha_estado
 * @property int|null $actualizado_por
 */
class Cotizacion extends Model
{
    public const PENDIENTE = 'PENDIENTE';

    public const ATENDIDA = 'ATENDIDA';

    public const RECHAZADA = 'RECHAZADA';

    public const VENCIDA = 'VENCIDA';

    public const CONVERTIDA = 'CONVERTIDA';

    public const ESTADOS = [self::PENDIENTE, self::ATENDIDA, self::RECHAZADA, self::VENCIDA, self::CONVERTIDA];

    /** Estados que el personal puede fijar a mano (VENCIDA y CONVERTIDA los pone el sistema). */
    public const ESTADOS_MANUALES = [self::PENDIENTE, self::ATENDIDA, self::RECHAZADA];

    public const ORIGEN_TIENDA_WEB = 'TIENDA_WEB';

    public const ORIGEN_MOSTRADOR = 'MOSTRADOR';

    public $timestamps = false;

    protected $table = 'cotizacion';

    protected $fillable = [
        'cliente_id',
        'usuario_id',
        'codigo_cotizacion',
        'fecha_emision',
        'fecha_vencimiento',
        'estado',
        'observacion',
        'fecha_creacion',
        'origen',
        'fecha_estado',
        'actualizado_por',
    ];

    protected function casts(): array
    {
        return [
            'fecha_emision' => 'datetime',
            'fecha_vencimiento' => 'date',
            'fecha_creacion' => 'datetime',
            'fecha_estado' => 'datetime',
        ];
    }

    /**
     * Correlativo COT-AAAA-NNNNN, calculado con bloqueo dentro de la transacción.
     * El índice único cubre la carrera que quede: el servicio reintenta si choca.
     */
    public static function siguienteCodigo(): string
    {
        $anio = (int) now()->year;
        $prefijo = 'COT-'.$anio.'-';

        $ultimo = static::query()
            ->where('codigo_cotizacion', 'like', $prefijo.'%')
            ->orderByDesc('codigo_cotizacion')
            ->lockForUpdate()
            ->value('codigo_cotizacion');

        $secuencia = 1;

        if (is_string($ultimo) && preg_match('/^COT-\d{4}-(\d+)$/', $ultimo, $partes) === 1) {
            $secuencia = (int) $partes[1] + 1;
        }

        return sprintf('COT-%d-%05d', $anio, $secuencia);
    }

    public function total(): float
    {
        return round((float) $this->detalles->sum(fn (DetalleCotizacion $d) => $d->subtotal()), 2);
    }

    /**
     * Vigente hasta el final del día de vencimiento (en la zona de la aplicación).
     */
    public function vigente(?CarbonInterface $hoy = null): bool
    {
        if ($this->fecha_vencimiento === null) {
            return true;
        }

        $hoy ??= now((string) config('app.timezone'));

        return $this->fecha_vencimiento->toDateString() >= $hoy->toDateString();
    }

    /**
     * EX-01 y EX-02 de CU20: solo se convierte una cotización abierta y vigente.
     */
    public function puedeConvertirse(?CarbonInterface $hoy = null): bool
    {
        return in_array($this->estado, [self::PENDIENTE, self::ATENDIDA], true) && $this->vigente($hoy);
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

    /** @return BelongsTo<User, $this> */
    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    /** @return HasMany<DetalleCotizacion, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleCotizacion::class, 'cotizacion_id')->orderBy('id');
    }

    /** @return HasOne<Venta, $this> */
    public function venta(): HasOne
    {
        return $this->hasOne(Venta::class, 'cotizacion_id');
    }
}
