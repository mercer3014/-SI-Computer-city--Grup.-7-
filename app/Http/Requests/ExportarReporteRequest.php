<?php

namespace App\Http\Requests;

use App\Services\Reportes\Exportacion\Exportadores;
use Illuminate\Validation\Rule;

class ExportarReporteRequest extends GenerarReporteRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'formato' => ['required', 'string', Rule::in(Exportadores::formatos())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...parent::messages(),
            'formato.required' => 'Elegí el formato del archivo.',
            'formato.in' => 'El formato elegido no existe.',
        ];
    }

    public function formato(): string
    {
        return (string) $this->validated('formato');
    }
}
