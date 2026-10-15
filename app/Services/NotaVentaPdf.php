<?php

namespace App\Services;

use App\Models\Venta;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DocumentoPdf;
use Symfony\Component\HttpFoundation\Response;

class NotaVentaPdf
{
    public function documento(Venta $venta): DocumentoPdf
    {
        $venta->loadMissing([
            'cliente',
            'vendedor.personal',
            'detalles.variante.producto',
            'pagos.metodo',
        ]);

        return Pdf::loadView('pdf.nota-venta', [
            'venta' => $venta,
            'total' => $venta->total(),
            'zona' => config('app.timezone'),
        ])->setPaper('letter');
    }

    public function binario(Venta $venta): string
    {
        return $this->documento($venta)->output();
    }

    public function descargar(Venta $venta): Response
    {
        $nombre = 'nota-'.$venta->numero_venta.'.pdf';

        return $this->documento($venta)->download($nombre);
    }
}
