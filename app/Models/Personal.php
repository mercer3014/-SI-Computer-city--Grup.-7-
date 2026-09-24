<?php

namespace App\Models;

use Database\Factories\PersonalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $nombre_completo
 * @property string|null $ci
 * @property string|null $telefono
 * @property string|null $cargo
 * @property string|null $estado
 */
#[Table('personal', timestamps: false)]
#[Fillable(['nombre_completo', 'ci', 'telefono', 'cargo', 'estado'])]
class Personal extends Model
{
    /** @use HasFactory<PersonalFactory> */
    use HasFactory;
}
