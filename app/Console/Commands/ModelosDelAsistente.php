<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Lista los modelos que ofrece xAI con tu XAI_API_KEY, para elegir el valor de XAI_MODEL.
 * No imprime la clave.
 */
class ModelosDelAsistente extends Command
{
    protected $signature = 'asistente:modelos';

    protected $description = 'Lista los modelos de xAI disponibles con tu XAI_API_KEY (para elegir XAI_MODEL)';

    public function handle(): int
    {
        $clave = config('services.xai.key');

        if (! is_string($clave) || trim($clave) === '') {
            $this->error('Falta XAI_API_KEY en el .env.');

            return self::FAILURE;
        }

        try {
            $respuesta = Http::withToken($clave)
                ->acceptJson()
                ->timeout((int) config('services.xai.timeout', 20))
                ->get(rtrim((string) config('services.xai.base_url'), '/').'/models');
        } catch (ConnectionException) {
            $this->error('No se pudo conectar con xAI.');

            return self::FAILURE;
        }

        if ($respuesta->failed()) {
            $this->error('xAI respondió con el estado '.$respuesta->status().'.');

            return self::FAILURE;
        }

        $datos = $respuesta->json('data');
        $modelos = [];

        foreach (is_array($datos) ? $datos : [] as $modelo) {
            if (is_array($modelo) && is_string($modelo['id'] ?? null)) {
                $modelos[] = $modelo['id'];
            }
        }

        sort($modelos);

        foreach ($modelos as $modelo) {
            $this->line($modelo);
        }

        $this->info('Modelo en uso: '.config('services.xai.model').'. Cámbialo con XAI_MODEL en el .env.');

        return self::SUCCESS;
    }
}
