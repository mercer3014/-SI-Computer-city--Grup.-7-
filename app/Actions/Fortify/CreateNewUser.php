<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Personal;
use App\Models\Rol;
use App\Models\User;
use App\Services\RegisterOtpService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private RegisterOtpService $otp) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'ci' => ['required', 'string', 'max:30', Rule::unique(Personal::class, 'ci')],
            'cargo' => ['required', 'string', 'max:100'],
            'telefono' => ['required', 'string', 'max:30'],
            'email' => $this->emailRules(),
            'token' => ['required', 'digits:6'],
            'password' => $this->passwordRules(),
        ])->validate();

        if (! $this->otp->isVerified($input['email']) && ! $this->otp->verify($input['email'], $input['token'])) {
            throw ValidationException::withMessages([
                'token' => 'El código no es válido o ya venció.',
            ]);
        }

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

        $nombreCompleto = trim($input['nombre'].' '.$input['apellido']);

        $user = DB::transaction(function () use ($input, $rolId, $nombreCompleto): User {
            $personal = Personal::query()->create([
                'nombre_completo' => $nombreCompleto,
                'ci' => $input['ci'],
                'telefono' => $input['telefono'],
                'cargo' => $input['cargo'],
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

        $this->otp->forget($input['email']);

        return $user;
    }
}
