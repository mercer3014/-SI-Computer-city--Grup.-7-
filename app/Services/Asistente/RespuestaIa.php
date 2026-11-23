<?php

namespace App\Services\Asistente;

/**
 * Lo que devuelve el Servicio de IA: un texto o una especificación sin validar.
 * Hasta que ValidadorEspecificacion la aprueba, la especificación es solo una propuesta.
 */
final readonly class RespuestaIa
{
    public const TIPOS = ['respuesta', 'consulta', 'reporte', 'no_soportado'];

    /**
     * @param  'respuesta'|'consulta'|'reporte'|'no_soportado'  $tipo
     * @param  array<array-key, mixed>|null  $especificacion
     */
    public function __construct(
        public string $tipo,
        public string $mensaje,
        public ?array $especificacion = null,
    ) {}

    /**
     * Arma la respuesta desde el JSON que devolvió la IA.
     *
     * @param  array<array-key, mixed>  $datos
     *
     * @throws AsistenteNoDisponible si el JSON no tiene la forma esperada
     */
    public static function desdeArray(array $datos): self
    {
        $tipo = $datos['tipo'] ?? null;

        $tipoValido = match ($tipo) {
            'respuesta' => 'respuesta',
            'consulta' => 'consulta',
            'reporte' => 'reporte',
            'no_soportado' => 'no_soportado',
            default => throw new AsistenteNoDisponible('La IA devolvió un tipo de respuesta desconocido.'),
        };

        $mensaje = $datos['mensaje'] ?? '';
        $especificacion = $datos['especificacion'] ?? null;

        if (! is_string($mensaje)) {
            throw new AsistenteNoDisponible('La IA devolvió un mensaje que no es texto.');
        }

        return new self(
            $tipoValido,
            trim($mensaje),
            is_array($especificacion) ? $especificacion : null,
        );
    }
}
