<?php

namespace App\Http\Requests;

use App\Services\Calificaciones\CalificacionService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OcultarCalificacionRequest extends FormRequest
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
            'motivo' => ['required', 'string', 'min:'.CalificacionService::MOTIVO_MINIMO, 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'motivo.required' => 'Indica el motivo para ocultar la calificación.',
            'motivo.min' => 'El motivo debe tener al menos '.CalificacionService::MOTIVO_MINIMO.' caracteres.',
            'motivo.max' => 'El motivo admite hasta 500 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('motivo'))) {
            $this->merge(['motivo' => trim($this->input('motivo'))]);
        }
    }
}
