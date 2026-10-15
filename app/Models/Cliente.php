<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string|null $tipo_documento
 * @property string|null $numero_documento
 * @property string $nombre
 * @property string|null $razon_social
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $direccion
 * @property string|null $observacion
 * @property string|null $estado
 * @property Carbon|null $fecha_registro
 */
class Cliente extends Model
{
    public const CREATED_AT = 'fecha_registro';

    public const UPDATED_AT = null;

    protected $table = 'cliente';

    protected $fillable = [
        'tipo_documento',
        'numero_documento',
        'nombre',
        'razon_social',
        'telefono',
        'email',
        'direccion',
        'observacion',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_registro' => 'datetime',
        ];
    }

    public function tieneVentas(): bool
    {
        return DB::table('venta')->where('cliente_id', $this->id)->exists();
    }

    /** @return HasMany<Venta, $this> */
    public function ventas(): HasMany
    {
        return $this->hasMany(Venta::class, 'cliente_id');
    }
}
