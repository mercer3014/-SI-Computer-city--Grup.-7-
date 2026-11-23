<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class HorariosAtencionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->tienePermiso('configuracion.gestionar');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'horarios' => ['required', 'array', 'size:7'],
            'horarios.*.dia_semana' => ['required', 'integer', 'between:1,7', 'distinct'],
            'horarios.*.cerrado' => ['required', 'boolean'],
            'horarios.*.hora_apertura' => ['nullable', 'date_format:H:i'],
            'horarios.*.hora_cierre' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'horarios.size' => 'Cargá el horario de los 7 días de la semana.',
            'horarios.*.hora_apertura.date_format' => 'La hora de apertura debe tener formato HH:MM.',
            'horarios.*.hora_cierre.date_format' => 'La hora de cierre debe tener formato HH:MM.',
        ];
    }

    /**
     * El cierre tiene que ser posterior a la apertura (EX-02), salvo en los días cerrados.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $horarios = $this->input('horarios');

                if (! is_array($horarios)) {
                    return;
                }

                foreach ($horarios as $indice => $fila) {
                    if (! is_array($fila) || $fila['cerrado'] === true) {
                        continue;
                    }

                    $apertura = $fila['hora_apertura'] ?? null;
                    $cierre = $fila['hora_cierre'] ?? null;

                    if ($apertura === null) {
                        $validator->errors()->add("horarios.$indice.hora_apertura", 'Indicá la hora de apertura.');
                    }

                    if ($cierre === null) {
                        $validator->errors()->add("horarios.$indice.hora_cierre", 'Indicá la hora de cierre.');
                    }

                    if (is_string($apertura) && is_string($cierre)
                        && ! $validator->errors()->has("horarios.$indice.hora_cierre")
                        && ! $validator->errors()->has("horarios.$indice.hora_apertura")
                        && $cierre <= $apertura) {
                        $validator->errors()->add("horarios.$indice.hora_cierre", 'La hora de cierre debe ser posterior a la de apertura.');
                    }
                }
            },
        ];
    }

    /**
     * Un día cerrado se guarda sin horas (EX-05).
     *
     * @return list<array{dia_semana: int, cerrado: bool, hora_apertura: string|null, hora_cierre: string|null}>
     */
    public function horarios(): array
    {
        $filas = [];

        foreach ($this->validated('horarios') as $fila) {
            $cerrado = (bool) $fila['cerrado'];

            $filas[] = [
                'dia_semana' => (int) $fila['dia_semana'],
                'cerrado' => $cerrado,
                'hora_apertura' => $cerrado ? null : ($fila['hora_apertura'] ?? null),
                'hora_cierre' => $cerrado ? null : ($fila['hora_cierre'] ?? null),
            ];
        }

        usort($filas, fn (array $a, array $b): int => $a['dia_semana'] <=> $b['dia_semana']);

        return $filas;
    }

    protected function prepareForValidation(): void
    {
        $horarios = $this->input('horarios');

        if (! is_array($horarios)) {
            return;
        }

        foreach ($horarios as $indice => $fila) {
            if (! is_array($fila)) {
                continue;
            }

            $cerrado = filter_var($fila['cerrado'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $fila['cerrado'] = $cerrado;

            foreach (['hora_apertura', 'hora_cierre'] as $campo) {
                $valor = $fila[$campo] ?? null;
                $fila[$campo] = ! $cerrado && is_string($valor) && trim($valor) !== '' ? substr(trim($valor), 0, 5) : null;
            }

            $horarios[$indice] = $fila;
        }

        $this->merge(['horarios' => $horarios]);
    }
}
