<?php

namespace App\Http\Requests;

use App\Services\Reportes\PeriodoReporte;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

/**
 * Período del dashboard: hoy, semana, mes o un rango de fechas. Sin parámetros es el mes actual.
 * Con "rango", la fecha de fin no puede ser anterior a la de inicio: se rechaza con un error, no se corrige sola.
 */
class PeriodoDashboardRequest extends FormRequest
{
    public const PERIODOS = ['hoy', 'semana', 'mes', 'rango'];

    private const EQUIVALENTE = [
        'hoy' => 'diario',
        'semana' => 'semanal',
        'mes' => 'mensual',
        'rango' => 'personalizado',
    ];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rango = $this->input('periodo') === 'rango';

        return [
            'periodo' => ['nullable', 'string', Rule::in(self::PERIODOS)],
            'desde' => [$rango ? 'required' : 'nullable', 'date'],
            'hasta' => [$rango ? 'required' : 'nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periodo.in' => 'El período elegido no existe.',
            'desde.required' => 'Indicá la fecha de inicio.',
            'hasta.required' => 'Indicá la fecha de fin.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
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

    public function clave(): string
    {
        $periodo = $this->validated('periodo');

        return is_string($periodo) && $periodo !== '' ? $periodo : 'mes';
    }

    public function periodo(): PeriodoReporte
    {
        $datos = $this->validated();

        return PeriodoReporte::resolver(
            self::EQUIVALENTE[$this->clave()],
            isset($datos['desde']) ? (string) $datos['desde'] : null,
            isset($datos['hasta']) ? (string) $datos['hasta'] : null,
        );
    }
}
