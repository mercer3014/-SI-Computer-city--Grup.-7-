<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $cliente_id
 * @property int|null $pedido_id
 * @property int|null $cotizacion_id
 * @property int $usuario_id
 * @property string $numero_venta
 * @property Carbon|null $fecha
 * @property string|null $estado
 * @property float|null $descuento_global
 * @property string|null $observacion
 * @property Carbon|null $fecha_anulacion
 * @property int|null $anulado_por
 * @property string|null $motivo_anulacion
 */
class Venta extends Model
{
    public $timestamps = false;

    protected $table = 'venta';

    protected $fillable = [
        'cliente_id',
        'pedido_id',
        'cotizacion_id',
        'usuario_id',
        'numero_venta',
        'fecha',
        'estado',
        'descuento_global',
        'observacion',
        'fecha_anulacion',
        'anulado_por',
        'motivo_anulacion',
    ];

    protected function casts(): array
    {
        return [
            'descuento_global' => 'float',
            'fecha' => 'datetime',
            'fecha_anulacion' => 'datetime',
        ];
    }

    public function total(): float
    {
        $bruto = $this->detalles->sum(fn (DetalleVenta $linea) => $linea->subtotal());

        return round((float) $bruto - (float) $this->descuento_global, 2);
    }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    /**
     * Correlativo V-AAAA-0001. Se calcula con bloqueo de filas del año
     * para evitar duplicados dentro de DB::transaction().
     */
    public static function siguienteNumero(): string
    {
        $anio = (int) now()->year;
        $prefijo = 'V-'.$anio.'-';

        $ultimo = static::query()
            ->where('numero_venta', 'like', $prefijo.'%')
            ->orderByDesc('numero_venta')
            ->lockForUpdate()
            ->value('numero_venta');

        $secuencia = 1;

        if (is_string($ultimo) && preg_match('/^V-\d{4}-(\d+)$/', $ultimo, $partes) === 1) {
            $secuencia = (int) $partes[1] + 1;
        }

        return sprintf('V-%d-%04d', $anio, $secuencia);
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /** @return BelongsTo<User, $this> */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /** @return BelongsTo<User, $this> */
    public function anulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /** @return HasMany<DetalleVenta, $this> */
    public function detalles(): HasMany
    {
        return $this->hasMany(DetalleVenta::class, 'venta_id');
    }

    /** @return HasMany<PagoVenta, $this> */
    public function pagos(): HasMany
    {
        return $this->hasMany(PagoVenta::class, 'venta_id');
    }
}
