<?php

namespace App\Services\Asistente;

/**
 * El Servicio de IA (xAI/Grok). Las pruebas lo reemplazan por tests/Fakes/ServicioIaFalso y la demo local
 * por ServicioIaSimulado, igual que PasarelaPagos con Stripe.
 *
 * La IA solo interpreta: devuelve texto o una especificación. Nunca ejecuta nada ni escribe SQL.
 */
interface ServicioIa
{
    /**
     * @param  list<array{role: 'user'|'assistant', content: string}>  $historial  los últimos mensajes de la conversación
     *
     * @throws AsistenteNoDisponible
     */
    public function interpretar(array $historial, string $consulta): RespuestaIa;
}
