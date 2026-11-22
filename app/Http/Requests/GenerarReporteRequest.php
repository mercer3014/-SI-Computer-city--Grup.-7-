<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Services\Reportes\AgrupadorFecha;
use App\Services\Reportes\CatalogoReportes;
use App\Services\Reportes\PeriodoReporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class GenerarReporteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->tienePermiso('reportes.ver');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $personalizado = $this->input('periodo') === 'personalizado';

        return [
            'tipo' => ['required', 'string', Rule::in(CatalogoReportes::claves())],
            'periodo' => ['required', 'string', Rule::in(PeriodoReporte::TIPOS)],
            'desde' => [$personalizado ? 'required' : 'nullable', 'date'],
            'hasta' => [
                $personalizado ? 'required' : 'nullable',
                'date',
                ...($personalizado ? ['after_or_equal:desde'] : []),
            ],
            'categoria_id' => ['nullable', 'integer', Rule::exists('categoria', 'id')],
            'marca_id' => ['nullable', 'integer', Rule::exists('marca', 'id')],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodo_pago', 'id')],
            'proveedor_id' => ['nullable', 'integer', Rule::exists('proveedor', 'id')],
            'categoria_gasto_id' => ['nullable', 'integer', Rule::exists('categoria_gasto', 'id')],
            'agrupar' => ['nullable', 'string', Rule::in(AgrupadorFecha::NIVELES)],
            'limite' => ['nullable', 'integer', 'between:1,50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tipo.required' => 'Elegí el tipo de reporte.',
            'tipo.in' => 'El reporte elegido no existe.',
            'periodo.required' => 'Elegí el período.',
            'desde.required' => 'Indicá la fecha de inicio.',
            'hasta.required' => 'Indicá la fecha de fin.',
            'hasta.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
            'limite.between' => 'La cantidad de resultados debe estar entre 1 y 50.',
        ];
    }

    /**
     * Revisa el período completo (por ejemplo, el máximo de días) con el mismo código que usa el reporte.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['periodo', 'desde', 'hasta'])) {
                    return;
                }

                try {
                    $this->periodo();
                } catch (InvalidArgumentException $e) {
                    $validator->errors()->add('hasta', $e->getMessage());
                }
            },
        ];
    }

    public function claveReporte(): string
    {
        return (string) $this->validated('tipo');
    }

    public function periodo(): PeriodoReporte
    {
        $datos = $this->validated();

        return PeriodoReporte::resolver(
            (string) $datos['periodo'],
            isset($datos['desde']) ? (string) $datos['desde'] : null,
            isset($datos['hasta']) ? (string) $datos['hasta'] : null,
        );
    }

    /**
     * Solo los filtros que el reporte elegido acepta.
     *
     * @return array<string, int|string>
     */
    public function filtros(): array
    {
        $datos = $this->validated();
        $filtros = [];

        foreach (CatalogoReportes::filtrosPermitidos($this->claveReporte()) as $clave) {
            if (isset($datos[$clave]) && $datos[$clave] !== '') {
                $filtros[$clave] = is_numeric($datos[$clave]) ? (int) $datos[$clave] : (string) $datos[$clave];
            }
        }

        return $filtros;
    }

    /**
     * Los mismos parámetros como query string, para volver a la pantalla del reporte.
     *
     * @return array<string, mixed>
     */
    public function parametros(): array
    {
        $datos = $this->validated();
        unset($datos['formato']);

        return array_filter($datos, fn (mixed $valor): bool => $valor !== null && $valor !== '');
    }
}
