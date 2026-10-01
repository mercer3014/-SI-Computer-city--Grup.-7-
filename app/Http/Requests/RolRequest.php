<?php

namespace App\Http\Requests;

use App\Models\Rol;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RolRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->tienePermiso('roles.administrar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $actual = $this->route('rol');
        $actualId = $actual instanceof Rol ? $actual->id : null;

        return [
            'nombre' => [
                'required',
                'string',
                'max:100',
                function (string $atributo, mixed $valor, Closure $fail) use ($actualId): void {
                    $existe = DB::table('rol')
                        ->whereRaw('lower(nombre) = ?', [mb_strtolower(trim((string) $valor))])
                        ->when($actualId !== null, fn ($q) => $q->where('id', '!=', $actualId))
                        ->exists();

                    if ($existe) {
                        $fail('Ese nombre de rol ya está en uso.');
                    }
                },
            ],
            'descripcion' => ['required', 'string', 'max:500'],
            'activo' => ['sometimes', 'boolean'],
            'permisos' => ['present', 'array'],
            'permisos.*' => ['integer', 'distinct', Rule::exists('permiso', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresá el nombre del rol.',
            'descripcion.required' => 'Ingresá una descripción breve.',
            'permisos.present' => 'Enviá la lista de permisos.',
            'permisos.*.exists' => 'Uno de los permisos no existe.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nombre'))) {
            $this->merge(['nombre' => trim($this->input('nombre'))]);
        }
    }
}
