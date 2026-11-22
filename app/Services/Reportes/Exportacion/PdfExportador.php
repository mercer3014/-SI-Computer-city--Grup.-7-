<?php

namespace App\Services\Reportes\Exportacion;

use App\Services\Reportes\ReporteResultado;
use App\Services\Tienda\ConfiguracionTiendaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Throwable;

/**
 * PDF con el encabezado, la tabla, los totales y las advertencias.
 * El gráfico queda fuera: los totales y la tabla son los mismos que en pantalla y el
 * gráfico se arma en el navegador, así que dibujarlo aquí otra vez solo duplicaría código.
 */
class PdfExportador implements ExportadorReporte
{
    public function __construct(private ConfiguracionTiendaService $tienda) {}

    public function exportar(ReporteResultado $reporte): ArchivoExportado
    {
        try {
            $contenido = Pdf::loadView('pdf.reporte', [
                'r' => $reporte,
                'logo' => $this->tienda->logoParaPdf(),
            ])
                ->setPaper('letter', count($reporte->columnas) > 5 ? 'landscape' : 'portrait')
                ->output();
        } catch (Throwable $e) {
            report($e);

            throw new ExportacionFallida($e);
        }

        return new ArchivoExportado($contenido, $reporte->nombreArchivo('pdf'), 'application/pdf');
    }
}
