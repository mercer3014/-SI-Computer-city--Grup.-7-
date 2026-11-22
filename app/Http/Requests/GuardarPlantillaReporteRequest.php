<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class GuardarPlantillaReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->puedeUsarAsistente();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mensaje_id' => ['required', 'integer'],
            'nombre' => ['required', 'string', 'min:3', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required' => 'Ponle un nombre a la plantilla.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre no puede superar los 100 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre');

        $this->merge(['nombre' => is_string($nombre) ? trim($nombre) : $nombre]);
    }
}
