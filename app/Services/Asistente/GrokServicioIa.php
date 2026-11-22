<?php

namespace App\Services\Asistente;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Llama a la API de xAI (compatible con OpenAI) solo desde el servidor.
 *
 * La clave sale únicamente de config('services.xai.key'). No se imprime, no se registra en el log
 * y no viaja al navegador. Si falta la clave no se hace ninguna llamada.
 */
class GrokServicioIa implements ServicioIa
{
    public function __construct(private ConstructorPrompt $prompt) {}

    public function interpretar(array $historial, string $consulta): RespuestaIa
    {
        $clave = config('services.xai.key');

        if (! is_string($clave) || trim($clave) === '') {
            throw new AsistenteNoDisponible('Falta configurar XAI_API_KEY.');
        }

        $cuerpo = [
            'model' => config('services.xai.model'),
            'temperature' => 0.1,
            'messages' => [
                ['role' => 'system', 'content' => $this->prompt->sistema()],
                ...$historial,
                ['role' => 'user', 'content' => $consulta],
            ],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'respuesta_asistente', 'schema' => ConstructorPrompt::esquema()],
            ],
        ];

        try {
            $respuesta = Http::withToken($clave)
                ->acceptJson()
                ->asJson()
                ->timeout((int) config('services.xai.timeout', 20))
                ->retry(1, 300, throw: false)
                ->post(rtrim((string) config('services.xai.base_url'), '/').'/chat/completions', $cuerpo);
        } catch (ConnectionException $e) {
            Log::warning('Asistente: no se pudo conectar con xAI ('.class_basename($e).').');

            throw new AsistenteNoDisponible('Sin conexión con el servicio de IA.', $e);
        }

        if ($respuesta->failed()) {
            Log::warning('Asistente: xAI respondió con el estado '.$respuesta->status().'.');

            throw new AsistenteNoDisponible('El servicio de IA respondió con un error.');
        }

        $contenido = $respuesta->json('choices.0.message.content');

        if (! is_string($contenido)) {
            throw new AsistenteNoDisponible('El servicio de IA no devolvió contenido.');
        }

        $datos = json_decode($this->sinCercas($contenido), true);

        if (! is_array($datos)) {
            Log::warning('Asistente: xAI devolvió un JSON inválido.');

            throw new AsistenteNoDisponible('El servicio de IA devolvió un JSON inválido.');
        }

        return RespuestaIa::desdeArray($datos);
    }

    /** Algunos modelos envuelven el JSON en ```json ... ```. */
    private function sinCercas(string $texto): string
    {
        $texto = trim($texto);

        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $texto, $partes) === 1) {
            return $partes[1];
        }

        return $texto;
    }
}
