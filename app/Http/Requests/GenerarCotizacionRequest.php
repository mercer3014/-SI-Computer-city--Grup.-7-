<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * CU19: carrito con el precio que vio el cliente y datos de contacto confirmados.
 */
class GenerarCotizacionRequest extends FormRequest
{
    public const MAXIMO_POR_PRODUCTO = 20;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.variante_id' => ['required', 'integer', 'min:1'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:'.self::MAXIMO_POR_PRODUCTO],
            'items.*.precio_visto' => ['required', 'numeric', 'min:0'],
            'nombre' => ['required', 'string', 'min:3', 'max:200'],
            'telefono' => ['required', 'string', 'regex:/^[0-9+\-\s]{7,15}$/'],
            'email' => ['required', 'string', 'email', 'max:150'],
            'tipo_documento' => ['nullable', 'required_with:numero_documento', Rule::in(['CI', 'NIT', 'PASAPORTE'])],
            'numero_documento' => ['nullable', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Tu carrito está vacío.',
            'items.array' => 'Tu carrito está vacío.',
            'items.min' => 'Tu carrito está vacío.',
            'items.max' => 'Tu carrito tiene demasiados productos.',
            'items.*.cantidad.max' => 'Puedes cotizar hasta '.self::MAXIMO_POR_PRODUCTO.' unidades por producto.',
            'items.*.cantidad.min' => 'La cantidad mínima es 1.',
            'items.*.precio_visto.required' => 'Falta el precio de uno de los productos.',
            'nombre.required' => 'Indica tu nombre completo.',
            'nombre.min' => 'Indica tu nombre completo.',
            'telefono.required' => 'Indica un teléfono de contacto.',
            'telefono.regex' => 'El teléfono debe tener entre 7 y 15 dígitos.',
            'email.required' => 'Indica tu correo electrónico.',
            'email.email' => 'El correo no tiene un formato válido.',
            'tipo_documento.required_with' => 'Elige el tipo de documento.',
            'tipo_documento.in' => 'El tipo de documento debe ser CI, NIT o pasaporte.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'nombre' => $this->recortar('nombre'),
            'telefono' => $this->recortar('telefono'),
            'email' => mb_strtolower($this->recortar('email')),
            'numero_documento' => $this->recortar('numero_documento') ?: null,
            'tipo_documento' => $this->recortar('tipo_documento') ?: null,
        ]);
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                $documento = $this->input('numero_documento');

                if (! is_string($documento) || $documento === '' || $validador->errors()->has('numero_documento')) {
                    return;
                }

                $ajeno = DB::table('cliente')
                    ->whereRaw('lower(numero_documento) = ?', [mb_strtolower($documento)])
                    ->where('id', '<>', (int) $this->user()?->cliente_id)
                    ->exists();

                if ($ajeno) {
                    $validador->errors()->add('numero_documento', 'Ese documento ya está registrado con otra cuenta.');
                }
            },
        ];
    }

    /**
     * @return array{nombre: string, telefono: string, email: string|null, tipo_documento: string|null, numero_documento: string|null}
     */
    public function contacto(): array
    {
        $datos = $this->validated();

        return [
            'nombre' => (string) $datos['nombre'],
            'telefono' => (string) $datos['telefono'],
            'email' => isset($datos['email']) ? (string) $datos['email'] : null,
            'tipo_documento' => isset($datos['tipo_documento']) ? (string) $datos['tipo_documento'] : null,
            'numero_documento' => isset($datos['numero_documento']) ? (string) $datos['numero_documento'] : null,
        ];
    }

    /**
     * @return list<array{variante_id: int, cantidad: int, precio_visto: float}>
     */
    public function items(): array
    {
        $items = [];

        foreach ((array) $this->validated('items') as $item) {
            $items[] = [
                'variante_id' => (int) $item['variante_id'],
                'cantidad' => (int) $item['cantidad'],
                'precio_visto' => (float) $item['precio_visto'],
            ];
        }

        return $items;
    }

    private function recortar(string $campo): string
    {
        $valor = $this->input($campo);

        return is_scalar($valor) ? trim((string) $valor) : '';
    }
}
