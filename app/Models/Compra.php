<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $proveedor_id
 * @property int $usuario_id
 * @property int|null $metodo_pago_id
 * @property string $numero_compra
 * @property Carbon|null $fecha_compra
 * @property string|null $estado
 * @property Carbon|null $fecha_registro
 */
class Compra extends Model
{
    public const CREATED_AT = 'fecha_registro';

    public const UPDATED_AT = null;

    protected $table = 'compra';

    protected $fillable = [
        'proveedor_id',
        'usuario_id',
        'metodo_pago_id',
        'numero_compra',
        'fecha_compra',
        'tipo_documento',
        'numero_documento',
        'estado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_compra' => 'datetime',
            'fecha_registro' => 'datetime',
        ];
    }
}
