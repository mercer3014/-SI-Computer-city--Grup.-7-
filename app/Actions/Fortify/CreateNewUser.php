<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $rolId = Rol::query()
            ->where('estado', 'ACTIVO')
            ->where('nombre', '!=', 'Administrador')
            ->orderBy('id')
            ->value('id');

        if ($rolId === null) {
            throw ValidationException::withMessages([
                'email' => 'No hay un rol activo para asignar al usuario.',
            ]);
        }

        return DB::transaction(function () use ($input, $rolId): User {
            $personal = Personal::query()->create([
                'nombre_completo' => $input['name'],
                'estado' => 'ACTIVO',
            ]);

            return User::query()->create([
                'personal_id' => $personal->id,
                'rol_id' => $rolId,
                'email' => $input['email'],
                'password_hash' => $input['password'],
                'estado' => 'ACTIVO',
                'bloqueado' => false,
                'primer_login' => true,
            ]);
        });
    }
}
