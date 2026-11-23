<?php

namespace App\Services\Cotizaciones;

use App\Models\Cotizacion;
use App\Services\Tienda\ConfiguracionTiendaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentoPdf;
use Symfony\Component\HttpFoundation\Response;

/**
 * PDF de la cotización (CU19). El encabezado sale de la configuración de la tienda (CU17).
 */
class CotizacionPdf
{
    public function __construct(private ConfiguracionTiendaService $configuracion) {}

    public function documento(Cotizacion $cotizacion): DocumentoPdf
    {
        $cotizacion->loadMissing(['cliente', 'detalles']);

        return Pdf::loadView('pdf.cotizacion', [
            'cotizacion' => $cotizacion,
            'total' => $cotizacion->total(),
            'zona' => (string) config('app.timezone'),
            'tienda' => $this->configuracion->actual(),
            'logo' => $this->configuracion->logoParaPdf(),
        ])->setPaper('letter');
    }

    public function binario(Cotizacion $cotizacion): string
    {
        return $this->documento($cotizacion)->output();
    }

    public function descargar(Cotizacion $cotizacion): Response
    {
        return $this->documento($cotizacion)->download('cotizacion-'.$cotizacion->codigo_cotizacion.'.pdf');
    }
}
