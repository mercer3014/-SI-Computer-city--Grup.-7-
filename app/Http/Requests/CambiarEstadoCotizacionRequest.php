<?php

namespace App\Http\Requests;

use App\Models\Cotizacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoCotizacionRequest extends FormRequest
{
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
            'estado' => ['required', 'string', Rule::in(Cotizacion::ESTADOS_MANUALES)],
            'observacion' => [
                Rule::requiredIf(fn (): bool => $this->input('estado') === Cotizacion::RECHAZADA),
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'estado.required' => 'Elige el nuevo estado.',
            'estado.in' => 'El estado debe ser pendiente, atendida o rechazada.',
            'observacion.required' => 'Indica el motivo del rechazo.',
            'observacion.max' => 'La observación admite hasta 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('observacion'))) {
            $this->merge(['observacion' => trim($this->input('observacion')) ?: null]);
        }
    }
}
