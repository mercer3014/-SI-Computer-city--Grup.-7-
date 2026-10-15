<?php

namespace App\Http\Requests;

use App\Models\Cliente;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->tienePermiso('clientes.gestionar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tipo_documento' => ['required', Rule::in(['CI', 'NIT'])],
            'numero_documento' => [
                'required',
                'string',
                'max:50',
                function (string $atributo, mixed $valor, Closure $fail): void {
                    $actual = $this->clienteActual()?->id;
                    $existe = DB::table('cliente')
                        ->whereRaw('lower(numero_documento) = ?', [mb_strtolower((string) $valor)])
                        ->when($actual !== null, fn ($q) => $q->where('id', '!=', $actual))
                        ->first();

                    if ($existe !== null) {
                        $this->merge(['duplicado_id' => (int) $existe->id]);
                        $fail('Ya existe un cliente registrado con el documento indicado.');
                    }
                },
            ],
            'nombre' => ['required', 'string', 'max:200'],
            'razon_social' => ['nullable', 'string', 'max:200'],
            'telefono' => ['nullable', 'string', 'max:50', 'regex:/^[0-9+\-\s]{7,15}$/'],
            'email' => ['nullable', 'string', 'max:150', 'email'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'estado' => ['sometimes', Rule::in(['ACTIVO', 'INACTIVO'])],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $id = $this->integer('duplicado_id');

                if ($id > 0 && $validator->errors()->has('numero_documento')) {
                    $validator->errors()->add('duplicado_id', (string) $id);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo_documento.required' => 'Elegí el tipo de documento (CI o NIT).',
            'tipo_documento.in' => 'El tipo de documento debe ser CI o NIT.',
            'numero_documento.required' => 'Ingresá el número de documento.',
            'nombre.required' => 'El nombre es obligatorio.',
            'telefono.regex' => 'El teléfono no tiene un formato válido.',
            'email.email' => 'El email no tiene un formato válido.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'tipo_documento' => $this->texto('tipo_documento', true),
            'numero_documento' => $this->texto('numero_documento'),
            'nombre' => $this->texto('nombre'),
            'razon_social' => $this->texto('razon_social'),
            'telefono' => $this->texto('telefono'),
            'email' => $this->texto('email', false, true),
            'direccion' => $this->texto('direccion'),
            'observacion' => $this->texto('observacion'),
        ]);
    }

    private function clienteActual(): ?Cliente
    {
        $actual = $this->route('cliente');

        return $actual instanceof Cliente ? $actual : null;
    }

    private function texto(string $campo, bool $mayusculas = false, bool $minusculas = false): ?string
    {
        $valor = $this->input($campo);

        if (! is_string($valor)) {
            return null;
        }

        $valor = trim($valor);

        if ($valor === '') {
            return null;
        }

        if ($mayusculas) {
            return mb_strtoupper($valor);
        }

        if ($minusculas) {
            return mb_strtolower($valor);
        }

        return $valor;
    }
}
