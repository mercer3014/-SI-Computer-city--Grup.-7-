<?php

namespace Tests\Fakes;

use App\Services\Asistente\RespuestaIa;
use App\Services\Asistente\ServicioIa;
use LogicException;
use Throwable;

/**
 * Servicio de IA para las pruebas: devuelve las respuestas que se le encolan y recuerda lo que recibió.
 * Ninguna prueba llama a la API real.
 */
class ServicioIaFalso implements ServicioIa
{
    /** @var list<RespuestaIa|Throwable> */
    private array $cola = [];

    /** @var list<array{historial: list<array{role: 'user'|'assistant', content: string}>, consulta: string}> */
    public array $llamadas = [];

    public function encolar(RespuestaIa|Throwable ...$respuestas): static
    {
        foreach ($respuestas as $respuesta) {
            $this->cola[] = $respuesta;
        }

        return $this;
    }

    /**
     * @param  array<array-key, mixed>  $especificacion
     */
    public static function consulta(array $especificacion): RespuestaIa
    {
        return new RespuestaIa('consulta', 'Esto es lo que encontré.', $especificacion);
    }

    /**
     * @param  array<array-key, mixed>  $especificacion
     */
    public static function reporte(array $especificacion): RespuestaIa
    {
        return new RespuestaIa('reporte', 'Armé el reporte.', $especificacion);
    }

    public function interpretar(array $historial, string $consulta): RespuestaIa
    {
        $this->llamadas[] = ['historial' => $historial, 'consulta' => $consulta];

        $siguiente = array_shift($this->cola) ?? throw new LogicException('ServicioIaFalso no tiene respuestas encoladas.');

        if ($siguiente instanceof Throwable) {
            throw $siguiente;
        }

        return $siguiente;
    }
}
