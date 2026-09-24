<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $personal_id
 * @property int $rol_id
 * @property string $email
 * @property string $password_hash
 * @property string|null $estado
 * @property int|null $intentos_fallidos
 * @property bool|null $bloqueado
 * @property bool|null $primer_login
 * @property Carbon|null $fecha_ultimo_login
 * @property Carbon|null $fecha_creacion
 * @property Carbon|null $fecha_bloqueo
 * @property int|null $nivel_bloqueo
 * @property-read string $name
 */
#[Table('usuario')]
#[Fillable([
    'personal_id',
    'rol_id',
    'email',
    'password_hash',
    'estado',
    'intentos_fallidos',
    'bloqueado',
    'primer_login',
    'nivel_bloqueo',
])]
#[Hidden(['password_hash'])]
#[Appends(['name'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = null;

    /**
     * Columna de contraseña en la tabla usuario.
     *
     * @var string
     */
    protected $authPasswordName = 'password_hash';

    /**
     * La tabla usuario no guarda un token de "recordarme".
     *
     * @var string
     */
    protected $rememberTokenName = '';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
            'bloqueado' => 'boolean',
            'primer_login' => 'boolean',
            'fecha_ultimo_login' => 'datetime',
            'fecha_creacion' => 'datetime',
            'fecha_bloqueo' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Personal, $this>
     */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    /**
     * @return BelongsTo<Rol, $this>
     */
    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function puedeIniciarSesion(): bool
    {
        $activo = $this->estado === null || $this->estado === 'ACTIVO';

        return $activo && ! $this->bloqueado;
    }

    /**
     * Nombre visible, tomado del personal vinculado.
     */
    protected function name(): Attribute
    {
        return Attribute::get(function (): string {
            if ($this->relationLoaded('personal')) {
                return $this->personal?->nombre_completo ?? '';
            }

            return (string) ($this->personal()->value('nombre_completo') ?? '');
        });
    }
}
