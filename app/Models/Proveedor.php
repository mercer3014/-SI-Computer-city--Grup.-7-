<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $nit
 * @property string $razon_social
 * @property string|null $nombre_comercial
 * @property string|null $contacto
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $direccion
 * @property string|null $observacion
 * @property string|null $estado
 * @property Carbon|null $fecha_registro
 */
class Proveedor extends Model
{
    public const CREATED_AT = 'fecha_registro';

    public const UPDATED_AT = null;

    protected $table = 'proveedor';

    protected $fillable = [
        'nit',
        'razon_social',
        'nombre_comercial',
        'contacto',
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
}
