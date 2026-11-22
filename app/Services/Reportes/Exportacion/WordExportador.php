<?php

namespace App\Services\Reportes\Exportacion;

use App\Services\Reportes\Formato;
use App\Services\Reportes\ReporteResultado;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use Throwable;

/**
 * Word (.docx) con el título, el encabezado, la tabla, los totales y las advertencias.
 */
class WordExportador implements ExportadorReporte
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function exportar(ReporteResultado $reporte): ArchivoExportado
    {
        $ruta = tempnam(sys_get_temp_dir(), 'rep');

        if ($ruta === false) {
            throw new ExportacionFallida;
        }

        try {
            $this->escribir($reporte, $ruta);
            $contenido = file_get_contents($ruta);

            if ($contenido === false) {
                throw new ExportacionFallida;
            }
        } catch (ExportacionFallida $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new ExportacionFallida($e);
        } finally {
            @unlink($ruta);
        }

        return new ArchivoExportado($contenido, $reporte->nombreArchivo('docx'), self::MIME);
    }

    private function escribir(ReporteResultado $reporte, string $ruta): void
    {
        Settings::setOutputEscapingEnabled(true);

        $documento = new PhpWord;
        $documento->setDefaultFontName('Arial');
        $documento->setDefaultFontSize(10);

        $seccion = $documento->addSection([
            'orientation' => count($reporte->columnas) > 4 ? 'landscape' : 'portrait',
            'marginLeft' => 900,
            'marginRight' => 900,
        ]);

        $e = $reporte->encabezado;

        $seccion->addText($e['tienda'], ['bold' => true, 'size' => 16]);

        if ($e['ubicacion'] !== '') {
            $seccion->addText($e['ubicacion'], ['color' => '6F6256']);
        }

        $seccion->addText($reporte->titulo, ['bold' => true, 'size' => 13]);
        $seccion->addText('Período: '.$e['periodo']);
        $seccion->addText('Generado por '.$e['usuario'].' el '.$e['generado'], ['color' => '6F6256']);

        foreach ($reporte->advertencias as $advertencia) {
            $seccion->addText('Advertencia: '.$advertencia, ['bold' => true, 'color' => 'B45309']);
        }

        $seccion->addTextBreak();

        $tabla = $seccion->addTable(['borderSize' => 6, 'borderColor' => 'D8CDBC', 'cellMargin' => 60]);

        $tabla->addRow();

        foreach ($reporte->columnas as $columna) {
            $tabla->addCell(null, ['bgColor' => 'D97706'])->addText(
                $columna['etiqueta'],
                ['bold' => true, 'color' => 'FFFFFF'],
                ['alignment' => $this->alineacion($columna['tipo'])],
            );
        }

        foreach ($reporte->filas as $fila) {
            $tabla->addRow();

            foreach ($reporte->columnas as $columna) {
                $tabla->addCell()->addText(
                    Formato::valor($fila[$columna['clave']] ?? null, $columna['tipo']),
                    [],
                    ['alignment' => $this->alineacion($columna['tipo'])],
                );
            }
        }

        if ($reporte->totales !== []) {
            $tabla->addRow();

            foreach ($reporte->columnas as $columna) {
                $tabla->addCell(null, ['bgColor' => 'FDF3E1'])->addText(
                    Formato::valor($reporte->totales[$columna['clave']] ?? null, $columna['tipo']),
                    ['bold' => true],
                    ['alignment' => $this->alineacion($columna['tipo'])],
                );
            }
        }

        IOFactory::createWriter($documento, 'Word2007')->save($ruta);
    }

    private function alineacion(string $tipo): string
    {
        return in_array($tipo, ['entero', 'moneda', 'porcentaje'], true) ? 'right' : 'left';
    }
}
