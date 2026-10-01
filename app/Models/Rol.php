<?php

namespace App\Models;

use Database\Factories\RolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property bool|null $es_sistema
 * @property string|null $estado
 */
#[Table('rol', timestamps: false)]
#[Fillable(['nombre', 'descripcion', 'es_sistema', 'estado'])]
class Rol extends Model
{
    /** @use HasFactory<RolFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_sistema' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Permiso, $this>
     */
    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permiso', 'rol_id', 'permiso_id');
    }
}
