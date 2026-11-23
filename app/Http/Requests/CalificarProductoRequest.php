<?php

namespace App\Http\Requests;

use App\Models\Calificacion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CalificarProductoRequest extends FormRequest
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
            'producto_id' => ['required', 'integer'],
            'puntuacion' => ['required', 'integer', 'between:1,5'],
            'comentario' => ['nullable', 'string', 'max:'.Calificacion::MAXIMO_COMENTARIO],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'producto_id.required' => 'Elige el producto que quieres calificar.',
            'puntuacion.required' => 'Elige de 1 a 5 estrellas.',
            'puntuacion.integer' => 'Elige de 1 a 5 estrellas.',
            'puntuacion.between' => 'Elige de 1 a 5 estrellas.',
            'comentario.max' => 'El comentario admite hasta '.Calificacion::MAXIMO_COMENTARIO.' caracteres.',
        ];
    }
}
