<?php

namespace App\Http\Requests;

use App\Models\Proveedor;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->tienePermiso('proveedores.gestionar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nit' => [
                'required',
                'string',
                'max:50',
                function (string $atributo, mixed $valor, Closure $fail): void {
                    $actual = $this->proveedorActual()?->id;
                    $existe = DB::table('proveedor')
                        ->whereRaw('lower(nit) = ?', [mb_strtolower((string) $valor)])
                        ->when($actual !== null, fn ($q) => $q->where('id', '!=', $actual))
                        ->exists();

                    if ($existe) {
                        $fail('Ya existe un proveedor registrado con ese NIT.');
                    }
                },
            ],
            'razon_social' => ['required', 'string', 'max:200'],
            'nombre_comercial' => ['nullable', 'string', 'max:200'],
            'contacto' => ['nullable', 'string', 'max:150'],
            'telefono' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\-\s]{7,15}$/'],
            'email' => ['nullable', 'string', 'max:150', 'email'],
            'direccion' => ['nullable', 'string', 'max:1000'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'estado' => ['sometimes', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nit.required' => 'Ingresá el NIT.',
            'razon_social.required' => 'La razón social es obligatoria.',
            'telefono.regex' => 'El teléfono no tiene un formato válido.',
            'email.email' => 'El email no tiene un formato válido.',
        ];
    }

    /**
     * @return array{
     *     nit: string,
     *     razon_social: string,
     *     nombre_comercial: string|null,
     *     contacto: string|null,
     *     telefono: string|null,
     *     email: string|null,
     *     direccion: string|null,
     *     observacion: string|null
     * }
     */
    public function datos(): array
    {
        $validos = $this->validated();

        return [
            'nit' => $validos['nit'],
            'razon_social' => $validos['razon_social'],
            'nombre_comercial' => $validos['nombre_comercial'] ?? null,
            'contacto' => $validos['contacto'] ?? null,
            'telefono' => $validos['telefono'] ?? null,
            'email' => $validos['email'] ?? null,
            'direccion' => $validos['direccion'] ?? null,
            'observacion' => $validos['observacion'] ?? null,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nit' => $this->texto('nit'),
            'razon_social' => $this->texto('razon_social'),
            'nombre_comercial' => $this->texto('nombre_comercial'),
            'contacto' => $this->texto('contacto'),
            'telefono' => $this->texto('telefono'),
            'email' => $this->texto('email', true),
            'direccion' => $this->texto('direccion'),
            'observacion' => $this->texto('observacion'),
        ]);
    }

    private function proveedorActual(): ?Proveedor
    {
        $actual = $this->route('proveedor');

        return $actual instanceof Proveedor ? $actual : null;
    }

    private function texto(string $campo, bool $minusculas = false): ?string
    {
        $valor = $this->input($campo);

        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        return $minusculas ? mb_strtolower($valor) : $valor;
    }
}
