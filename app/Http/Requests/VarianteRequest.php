<?php

namespace App\Http\Requests;

use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VarianteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->tienePermiso('productos.gestionar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => [
                'required',
                'string',
                'max:100',
                'regex:/^[A-Z0-9][A-Z0-9._-]*$/',
                function (string $atributo, mixed $valor, Closure $fail): void {
                    $existe = DB::table('variante_producto')
                        ->whereRaw('upper(sku) = ?', [(string) $valor])
                        ->exists();

                    if ($existe) {
                        $fail('El SKU ya existe.');
                    }
                },
            ],
            'codigo_barras' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('variante_producto', 'codigo_barras'),
            ],
            'precio_venta' => ['required', 'numeric', 'gt:0', 'max:9999999999.99', 'decimal:0,2'],
            'stock_minimo' => ['required', 'integer', 'min:0', 'max:100000'],
            'atributos' => ['present', 'array', 'max:10'],
            'atributos.*.atributo_id' => ['required', 'integer', 'distinct', Rule::exists('atributo', 'id')],
            'atributos.*.valor_atributo_id' => ['nullable', 'integer', Rule::exists('valor_atributo', 'id')],
            'atributos.*.valor' => ['nullable', 'required_without:atributos.*.valor_atributo_id', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                foreach ($this->input('atributos', []) as $indice => $item) {
                    $valorId = $item['valor_atributo_id'] ?? null;

                    if ($valorId === null) {
                        continue;
                    }

                    $pertenece = DB::table('valor_atributo')
                        ->where('id', $valorId)
                        ->where('atributo_id', $item['atributo_id'])
                        ->exists();

                    if (! $pertenece) {
                        $validator->errors()->add(
                            "atributos.{$indice}.valor_atributo_id",
                            'El valor elegido no corresponde a ese atributo.',
                        );
                    }
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
            'sku.required' => 'Ingresá el SKU de la variante.',
            'sku.regex' => 'El SKU solo admite letras, números, guiones, puntos y guion bajo.',
            'codigo_barras.unique' => 'El código de barras ya existe.',
            'precio_venta.required' => 'Ingresá el precio de venta.',
            'precio_venta.gt' => 'El precio de venta debe ser mayor a cero.',
            'precio_venta.decimal' => 'El precio admite como máximo 2 decimales.',
            'stock_minimo.required' => 'Ingresá el stock mínimo.',
            'stock_minimo.min' => 'El stock mínimo no puede ser negativo.',
            'atributos.*.atributo_id.distinct' => 'Cada atributo solo puede tener un valor por variante.',
            'atributos.*.valor.required_without' => 'Elegí o escribí un valor para el atributo.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $sku = $this->input('sku');
        $codigo = $this->input('codigo_barras');

        $atributos = collect($this->input('atributos', []))
            ->filter(fn ($item) => is_array($item))
            ->map(fn (array $item) => [
                'atributo_id' => $item['atributo_id'] ?? null,
                'valor_atributo_id' => filled($item['valor_atributo_id'] ?? null) ? $item['valor_atributo_id'] : null,
                'valor' => is_string($item['valor'] ?? null) && trim($item['valor']) !== '' ? trim($item['valor']) : null,
            ])
            ->filter(fn (array $item) => $item['valor_atributo_id'] !== null || $item['valor'] !== null)
            ->values()
            ->all();

        $this->merge([
            'sku' => is_string($sku) ? mb_strtoupper(trim($sku)) : $sku,
            'codigo_barras' => is_string($codigo) && trim($codigo) !== '' ? trim($codigo) : null,
            'atributos' => $atributos,
        ]);
    }
}
