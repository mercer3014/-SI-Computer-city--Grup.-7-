<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class CompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->tienePermiso('compras.crear');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'proveedor_id' => [
                'required',
                'integer',
                Rule::exists('proveedor', 'id')->where(fn ($q) => $q->where('estado', 'ACTIVO')),
            ],
            'metodo_pago_id' => [
                'required',
                'integer',
                Rule::exists('metodo_pago', 'id')->where(fn ($q) => $q->where('estado', 'ACTIVO')),
            ],
            'numero_compra' => [
                'required',
                'string',
                'max:50',
                Rule::unique('compra', 'numero_compra'),
            ],
            'fecha_compra' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sku' => ['required', 'string', 'max:100', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/'],
            'items.*.cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.costo_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.numero_serie' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'proveedor_id.required' => 'Elegí un proveedor.',
            'proveedor_id.exists' => 'El proveedor no está activo.',
            'metodo_pago_id.required' => 'Elegí el método de pago.',
            'metodo_pago_id.exists' => 'El método de pago no está activo.',
            'numero_compra.required' => 'Ingresá el número de compra.',
            'numero_compra.unique' => 'Ya existe una compra con ese número.',
            'fecha_compra.required' => 'Ingresá la fecha de la compra.',
            'fecha_compra.date_format' => 'La fecha no es válida.',
            'fecha_compra.before_or_equal' => 'La fecha de compra no puede ser futura.',
            'items.required' => 'La compra debe incluir al menos un producto.',
            'items.min' => 'La compra debe incluir al menos un producto.',
            'items.*.sku.required' => 'Cada línea necesita un SKU.',
            'items.*.sku.regex' => 'El SKU no es válido.',
            'items.*.cantidad.required' => 'Ingresá la cantidad.',
            'items.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'items.*.costo_unitario.required' => 'Ingresá el costo unitario.',
            'items.*.costo_unitario.min' => 'El costo unitario no puede ser negativo.',
            'items.*.numero_serie.max' => 'El número de serie admite hasta 100 caracteres.',
        ];
    }

    /**
     * @return array{
     *     proveedor_id: int,
     *     metodo_pago_id: int,
     *     numero_compra: string,
     *     fecha_compra: string,
     *     items: list<array{sku: string, cantidad: int, costo_unitario: float, numero_serie: string|null}>
     * }
     */
    public function datos(): array
    {
        $validos = $this->validated();

        return [
            'proveedor_id' => (int) $validos['proveedor_id'],
            'metodo_pago_id' => (int) $validos['metodo_pago_id'],
            'numero_compra' => $validos['numero_compra'],
            'fecha_compra' => Carbon::parse($validos['fecha_compra'])->startOfDay()->format('Y-m-d H:i:s'),
            'items' => array_map(fn (array $item): array => [
                'sku' => $item['sku'],
                'cantidad' => (int) $item['cantidad'],
                'costo_unitario' => round((float) $item['costo_unitario'], 2),
                'numero_serie' => $item['numero_serie'] ?? null,
            ], $validos['items']),
        ];
    }

    protected function prepareForValidation(): void
    {
        $numero = $this->input('numero_compra');
        $items = $this->input('items');

        $this->merge([
            'numero_compra' => is_string($numero) ? trim($numero) : $numero,
            'items' => is_array($items)
                ? array_map(function (mixed $item): mixed {
                    if (! is_array($item)) {
                        return $item;
                    }

                    $sku = $item['sku'] ?? null;
                    $serie = $item['numero_serie'] ?? null;

                    return [
                        'sku' => is_string($sku) ? mb_strtoupper(trim($sku)) : $sku,
                        'cantidad' => $item['cantidad'] ?? null,
                        'costo_unitario' => $item['costo_unitario'] ?? null,
                        'numero_serie' => is_string($serie) && trim($serie) !== '' ? trim($serie) : null,
                    ];
                }, $items)
                : $items,
        ]);
    }
}
