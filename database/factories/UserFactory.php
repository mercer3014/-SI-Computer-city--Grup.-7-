<?php

namespace Database\Factories;

use App\Models\Personal;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'personal_id' => Personal::factory(),
            'rol_id' => Rol::factory(),
            'email' => fake()->unique()->safeEmail(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'estado' => 'ACTIVO',
            'bloqueado' => false,
            'primer_login' => false,
            'intentos_fallidos' => 0,
        ];
    }
}
