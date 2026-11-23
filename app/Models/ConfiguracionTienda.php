<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Datos generales de la tienda. Siempre hay una sola fila.
 *
 * @property int $id
 * @property string $nombre_tienda
 * @property string|null $centro_comercial
 * @property string|null $direccion
 * @property string|null $numero_pasillo
 * @property string|null $numero_local
 * @property string|null $telefono
 * @property string|null $whatsapp
 * @property string|null $email
 * @property float|null $latitud
 * @property float|null $longitud
 * @property string|null $google_maps_url
 * @property string|null $logo_url
 * @property string|null $moneda
 * @property string|null $anuncio
 */
class ConfiguracionTienda extends Model
{
    public $timestamps = false;

    protected $table = 'configuracion_tienda';

    protected $fillable = [
        'nombre_tienda',
        'centro_comercial',
        'direccion',
        'numero_pasillo',
        'numero_local',
        'telefono',
        'whatsapp',
        'email',
        'latitud',
        'longitud',
        'google_maps_url',
        'logo_url',
        'moneda',
        'anuncio',
    ];

    protected function casts(): array
    {
        return [
            'latitud' => 'float',
            'longitud' => 'float',
        ];
    }

    /** @return HasMany<HorarioAtencion, $this> */
    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioAtencion::class, 'configuracion_tienda_id')->orderBy('dia_semana');
    }
}
