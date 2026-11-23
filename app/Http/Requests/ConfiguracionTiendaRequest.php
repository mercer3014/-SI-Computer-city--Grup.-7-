<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConfiguracionTiendaRequest extends FormRequest
{
    public const MONEDAS = ['BOB', 'USD'];

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
            'nombre_tienda' => ['required', 'string', 'max:150'],
            'centro_comercial' => ['nullable', 'string', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:500'],
            'numero_pasillo' => ['nullable', 'string', 'max:20'],
            'numero_local' => ['nullable', 'string', 'max:20'],
            'telefono' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'whatsapp' => ['nullable', 'string', 'regex:/^\+?[0-9]{8,15}$/'],
            'email' => ['nullable', 'string', 'email', 'max:150'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'google_maps_url' => ['nullable', 'string', 'url', 'max:500'],
            'moneda' => ['required', Rule::in(self::MONEDAS)],
            'anuncio' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre_tienda.required' => 'Ingresá el nombre de la tienda.',
            'nombre_tienda.max' => 'El nombre de la tienda no puede superar los 150 caracteres.',
            'telefono.regex' => 'El teléfono no tiene un formato válido.',
            'whatsapp.regex' => 'El WhatsApp debe tener entre 8 y 15 dígitos, con + opcional al inicio.',
            'email.email' => 'El correo no tiene un formato válido.',
            'latitud.between' => 'La latitud debe estar entre -90 y 90.',
            'latitud.numeric' => 'La latitud debe ser un número.',
            'longitud.between' => 'La longitud debe estar entre -180 y 180.',
            'longitud.numeric' => 'La longitud debe ser un número.',
            'google_maps_url.url' => 'El enlace de Google Maps no es una URL válida.',
            'moneda.in' => 'Elegí BOB o USD.',
            'anuncio.max' => 'El anuncio no puede superar los 500 caracteres.',
        ];
    }

    /**
     * @return array{nombre_tienda: string, centro_comercial: string|null, direccion: string|null, numero_pasillo: string|null, numero_local: string|null, telefono: string|null, whatsapp: string|null, email: string|null, latitud: float|null, longitud: float|null, google_maps_url: string|null, moneda: string, anuncio: string|null}
     */
    public function datos(): array
    {
        $validos = $this->validated();

        return [
            'nombre_tienda' => (string) $validos['nombre_tienda'],
            'centro_comercial' => $this->opcional($validos, 'centro_comercial'),
            'direccion' => $this->opcional($validos, 'direccion'),
            'numero_pasillo' => $this->opcional($validos, 'numero_pasillo'),
            'numero_local' => $this->opcional($validos, 'numero_local'),
            'telefono' => $this->opcional($validos, 'telefono'),
            'whatsapp' => $this->opcional($validos, 'whatsapp'),
            'email' => $this->opcional($validos, 'email'),
            'latitud' => isset($validos['latitud']) ? (float) $validos['latitud'] : null,
            'longitud' => isset($validos['longitud']) ? (float) $validos['longitud'] : null,
            'google_maps_url' => $this->opcional($validos, 'google_maps_url'),
            'moneda' => (string) $validos['moneda'],
            'anuncio' => $this->opcional($validos, 'anuncio'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $campos = [
            'nombre_tienda', 'centro_comercial', 'direccion', 'numero_pasillo', 'numero_local',
            'telefono', 'whatsapp', 'email', 'latitud', 'longitud', 'google_maps_url', 'anuncio',
        ];

        $limpios = [];

        foreach ($campos as $campo) {
            $valor = $this->input($campo);
            $limpios[$campo] = is_string($valor) && trim($valor) !== '' ? trim($valor) : (is_numeric($valor) ? $valor : null);
        }

        if (is_string($limpios['whatsapp'])) {
            $limpios['whatsapp'] = preg_replace('/[\s\-().]+/', '', $limpios['whatsapp']);
        }

        $limpios['email'] = is_string($limpios['email']) ? mb_strtolower($limpios['email']) : null;
        $limpios['moneda'] = is_string($this->input('moneda')) ? strtoupper(trim((string) $this->input('moneda'))) : 'BOB';

        $this->merge($limpios);
    }

    /**
     * @param  array<string, mixed>  $validos
     */
    private function opcional(array $validos, string $campo): ?string
    {
        $valor = $validos[$campo] ?? null;

        return is_string($valor) && $valor !== '' ? $valor : null;
    }
}
