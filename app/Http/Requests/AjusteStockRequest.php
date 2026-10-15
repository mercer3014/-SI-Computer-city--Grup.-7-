<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AjusteStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->tienePermiso('inventario.ajustar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:100'],
            'tipo' => ['required', Rule::in(['ENTRADA', 'SALIDA'])],
            'cantidad' => ['required', 'integer', 'min:1', 'max:100000'],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.required' => 'Indicá el SKU de la variante.',
            'tipo.required' => 'Elegí si el ajuste es entrada o salida.',
            'tipo.in' => 'El tipo de movimiento debe ser ENTRADA o SALIDA.',
            'cantidad.required' => 'Ingresá la cantidad a ajustar.',
            'cantidad.min' => 'La cantidad debe ser al menos 1.',
            'motivo.required' => 'El motivo del ajuste es obligatorio.',
            'motivo.min' => 'El motivo del ajuste es obligatorio.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $sku = $this->input('sku');
        $motivo = $this->input('motivo');

        $this->merge([
            'sku' => is_string($sku) ? mb_strtoupper(trim($sku)) : $sku,
            'motivo' => is_string($motivo) ? trim($motivo) : $motivo,
        ]);
    }
}
