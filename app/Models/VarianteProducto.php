<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $producto_id
 * @property string $sku
 * @property string|null $codigo_barras
 * @property float $precio_venta
 * @property float|null $precio_anterior
 * @property string $estado
 * @property int $stock_actual
 * @property int $stock_minimo
 * @property Carbon|null $fecha_creacion
 * @property Carbon|null $fecha_actualizacion
 */
class VarianteProducto extends Model
{
    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    public const MESES_SIN_ROTACION = 6;

    /** Períodos (en meses) que el Administrador puede consultar en rotación. */
    public const PERIODOS_ROTACION = [3, 6, 12];

    /** Tablas de detalle que referencian a la variante; si hay filas, no se puede eliminar. */
    private const TABLAS_MOVIMIENTO = [
        'detalle_venta',
        'detalle_compra',
        'detalle_movimiento_inventario',
        'detalle_cotizacion',
        'detalle_pedido',
    ];

    protected $table = 'variante_producto';

    protected $fillable = [
        'producto_id',
        'sku',
        'codigo_barras',
        'precio_venta',
        'precio_anterior',
        'estado',
        'stock_actual',
        'stock_minimo',
    ];

    protected function casts(): array
    {
        return [
            'precio_venta' => 'float',
            'precio_anterior' => 'float',
            'stock_actual' => 'integer',
            'stock_minimo' => 'integer',
            'fecha_creacion' => 'datetime',
            'fecha_actualizacion' => 'datetime',
        ];
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    /** @return BelongsToMany<ValorAtributo, $this> */
    public function valores(): BelongsToMany
    {
        return $this->belongsToMany(
            ValorAtributo::class,
            'variante_valor_atributo',
            'variante_id',
            'valor_atributo_id',
        );
    }

    public function etiquetaAtributos(): string
    {
        $this->loadMissing('valores.atributo');

        return $this->valores
            ->map(fn ($valor) => trim(($valor->atributo?->nombre ?? '').': '.$valor->valor, ': '))
            ->filter()
            ->sort()
            ->values()
            ->implode(' · ');
    }

    /**
     * Mercadería activa con stock que no registró ventas (no anuladas) en N meses.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeSinRotacion(Builder $query, int $meses = self::MESES_SIN_ROTACION): Builder
    {
        $desde = now()->subMonths($this->mesesRotacion($meses));

        return $query
            ->where('variante_producto.estado', 'ACTIVO')
            ->where('variante_producto.stock_actual', '>', 0)
            ->whereNotExists(fn (QueryBuilder $ventas) => $ventas
                ->select(DB::raw(1))
                ->from('detalle_venta as dv')
                ->join('venta as v', 'v.id', '=', 'dv.venta_id')
                ->whereColumn('dv.variante_id', 'variante_producto.id')
                ->where('v.estado', '<>', 'ANULADA')
                ->where('v.fecha', '>=', $desde));
    }

    public function mesesRotacion(int $meses): int
    {
        return in_array($meses, self::PERIODOS_ROTACION, true)
            ? $meses
            : self::MESES_SIN_ROTACION;
    }

    public function tieneMovimientos(): bool
    {
        return in_array($this->id, self::idsConMovimientos([$this->id]), true);
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function idsConMovimientos(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return [];
        }

        $conMovimiento = [];

        foreach (self::TABLAS_MOVIMIENTO as $tabla) {
            $conMovimiento = array_merge(
                $conMovimiento,
                DB::table($tabla)->whereIn('variante_id', $ids)->pluck('variante_id')->all(),
            );
        }

        return array_values(array_unique(array_map('intval', $conMovimiento)));
    }

    /**
     * Agrega la columna `ultima_venta` (fecha de la venta no anulada más reciente).
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeConUltimaVenta(Builder $query): Builder
    {
        if ($query->getQuery()->columns === null) {
            $query->select('variante_producto.*');
        }

        return $query->addSelect([
            'ultima_venta' => DB::table('detalle_venta as dv')
                ->join('venta as v', 'v.id', '=', 'dv.venta_id')
                ->whereColumn('dv.variante_id', 'variante_producto.id')
                ->where('v.estado', '<>', 'ANULADA')
                ->selectRaw('max(v.fecha)'),
        ]);
    }

    /**
     * ¿Otra variante del producto ya usa exactamente esta combinación de valores?
     *
     * @param  list<int>  $valorIds
     */
    public static function combinacionExiste(int $productoId, array $valorIds, ?int $exceptoId = null): bool
    {
        $buscada = collect($valorIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        return self::query()
            ->where('producto_id', $productoId)
            ->when($exceptoId !== null, fn (Builder $q) => $q->whereKeyNot($exceptoId))
            ->with('valores:valor_atributo.id')
            ->get()
            ->contains(fn (self $variante) => $variante->valores
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all() === $buscada);
    }
}
