<?php

namespace App\Http\Requests;

use App\Models\Personal;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->tienePermiso('usuarios.administrar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $actual = $this->route('usuario');
        $actualId = $actual instanceof User ? $actual->id : null;
        $esAlta = $actualId === null;

        $reglas = [
            'email' => [
                'required',
                'string',
                'email',
                'max:150',
                function (string $atributo, mixed $valor, Closure $fail) use ($actualId): void {
                    $existe = DB::table('usuario')
                        ->whereRaw('lower(email) = ?', [mb_strtolower(trim((string) $valor))])
                        ->when($actualId !== null, fn ($q) => $q->where('id', '!=', $actualId))
                        ->exists();

                    if ($existe) {
                        $fail('Ese correo electrónico ya está registrado.');
                    }
                },
            ],
            'rol_id' => [
                'required',
                'integer',
                Rule::exists('rol', 'id')->where('estado', 'ACTIVO'),
            ],
        ];

        if ($esAlta) {
            return $reglas + [
                'personal_modo' => ['required', Rule::in(['existente', 'nuevo'])],
                'personal_id' => [
                    'required_if:personal_modo,existente',
                    'nullable',
                    'integer',
                    Rule::exists('personal', 'id'),
                    function (string $atributo, mixed $valor, Closure $fail): void {
                        if ($valor !== null && DB::table('usuario')->where('personal_id', $valor)->exists()) {
                            $fail('Ese personal ya tiene un usuario asignado.');
                        }
                    },
                ],
                'nombre_completo' => ['required_if:personal_modo,nuevo', 'nullable', 'string', 'max:200'],
                'ci' => [
                    'required_if:personal_modo,nuevo',
                    'nullable',
                    'string',
                    'max:30',
                    Rule::unique(Personal::class, 'ci'),
                ],
                'telefono' => ['nullable', 'string', 'digits_between:7,15'],
                'cargo' => ['required_if:personal_modo,nuevo', 'nullable', 'string', 'max:100'],
            ];
        }

        return $reglas + [
            'nombre_completo' => ['required', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'digits_between:7,15'],
            'cargo' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Ingresá el correo electrónico.',
            'email.email' => 'Ingresá un correo válido.',
            'rol_id.required' => 'Elegí un rol.',
            'rol_id.exists' => 'El rol elegido no está activo.',
            'personal_id.required_if' => 'Elegí el personal a vincular.',
            'nombre_completo.required' => 'Ingresá el nombre completo.',
            'nombre_completo.required_if' => 'Ingresá el nombre completo.',
            'ci.required_if' => 'Ingresá el CI.',
            'ci.unique' => 'Ya existe personal con ese CI.',
            'cargo.required_if' => 'Ingresá el cargo.',
            'telefono.digits_between' => 'El teléfono debe tener entre 7 y 15 dígitos.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => trim($this->input('email'))]);
        }
    }
}
