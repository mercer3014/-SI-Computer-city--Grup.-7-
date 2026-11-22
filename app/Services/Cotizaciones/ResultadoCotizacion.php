<?php

namespace App\Services\Cotizaciones;

use App\Models\Cotizacion;

final class ResultadoCotizacion
{
    /**
     * @param  list<string>  $avisos  cantidades ajustadas al stock y productos que se quitaron
     */
    public function __construct(
        public readonly Cotizacion $cotizacion,
        public readonly array $avisos,
    ) {}
}
