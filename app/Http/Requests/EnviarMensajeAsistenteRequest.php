<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EnviarMensajeAsistenteRequest extends FormRequest
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
            'conversacion_id' => ['nullable', 'integer'],
            'contenido' => ['required', 'string', 'max:1000'],
            'origen' => ['required', Rule::in(['TEXTO', 'VOZ'])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contenido.required' => 'Escribe tu consulta.',
            'contenido.max' => 'La consulta no puede superar los 1000 caracteres.',
            'origen.in' => 'El origen del mensaje no es válido.',
        ];
    }

    public function conversacionId(): ?int
    {
        $id = $this->validated('conversacion_id');

        return $id === null ? null : (int) $id;
    }

    public function contenido(): string
    {
        return (string) $this->validated('contenido');
    }

    public function origen(): string
    {
        return (string) $this->validated('origen');
    }

    protected function prepareForValidation(): void
    {
        $contenido = $this->input('contenido');

        $this->merge([
            'contenido' => is_string($contenido) ? trim($contenido) : $contenido,
            'origen' => $this->input('origen', 'TEXTO'),
        ]);
    }
}
