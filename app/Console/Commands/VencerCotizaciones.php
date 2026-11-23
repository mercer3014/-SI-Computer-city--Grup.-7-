<?php

namespace App\Console\Commands;

use App\Services\Cotizaciones\GestionCotizacionService;
use Illuminate\Console\Command;

/**
 * Marca VENCIDA toda cotización PENDIENTE o ATENDIDA cuya fecha de vencimiento ya pasó (CU20).
 * Corre cada día a las 00:10; el listado de cotizaciones también lo ejecuta al abrirse.
 */
class VencerCotizaciones extends Command
{
    protected $signature = 'cotizaciones:vencer';

    protected $description = 'Marca como VENCIDAS las cotizaciones cuya fecha de vencimiento ya pasó';

    public function handle(GestionCotizacionService $cotizaciones): int
    {
        $cantidad = $cotizaciones->vencer();

        $this->info($cantidad === 0
            ? 'No hay cotizaciones por vencer.'
            : "Cotizaciones marcadas como vencidas: {$cantidad}.");

        return self::SUCCESS;
    }
}
