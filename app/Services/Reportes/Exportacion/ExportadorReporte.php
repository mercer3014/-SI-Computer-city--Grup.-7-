<?php

namespace App\Services\Reportes\Exportacion;

use App\Services\Reportes\ReporteResultado;

interface ExportadorReporte
{
    /**
     * @throws ExportacionFallida
     */
    public function exportar(ReporteResultado $reporte): ArchivoExportado;
}
