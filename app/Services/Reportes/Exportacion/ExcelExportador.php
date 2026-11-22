<?php

namespace App\Services\Reportes\Exportacion;

use App\Services\Reportes\ReporteResultado;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Color;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;
use Throwable;

/**
 * Excel (.xlsx) con la hoja "Reporte" (encabezado, columnas con formato, totales) y, si hay avisos, la hoja "Advertencias".
 * Guarda números reales con formato de celda, no texto, para que se puedan sumar y ordenar.
 *
 * @phpstan-import-type Columna from ReporteResultado
 */
class ExcelExportador implements ExportadorReporte
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

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

        return new ArchivoExportado($contenido, $reporte->nombreArchivo('xlsx'), self::MIME);
    }

    private function escribir(ReporteResultado $reporte, string $ruta): void
    {
        $opciones = new Options;

        foreach ($this->anchos($reporte) as $indice => $ancho) {
            $opciones->setColumnWidth($ancho, $indice + 1);
        }

        $escritor = new Writer($opciones);
        $escritor->openToFile($ruta);
        $escritor->getCurrentSheet()->setName('Reporte');

        $negrita = (new Style)->withFontBold(true);
        $titulo = (new Style)->withFontBold(true)->withFontSize(14);
        $cabecera = (new Style)->withFontBold(true)->withFontColor(Color::WHITE)->withBackgroundColor('D97706');
        $total = (new Style)->withFontBold(true)->withBackgroundColor('FDF3E1');

        $e = $reporte->encabezado;

        $escritor->addRow(Row::fromValuesWithStyle([$e['tienda']], $titulo));

        if ($e['ubicacion'] !== '') {
            $escritor->addRow(Row::fromValues([$e['ubicacion']]));
        }

        $escritor->addRow(Row::fromValuesWithStyle([$reporte->titulo], $negrita));
        $escritor->addRow(Row::fromValues(['Período: '.$e['periodo']]));
        $escritor->addRow(Row::fromValues(['Generado por '.$e['usuario'].' el '.$e['generado']]));

        foreach ($reporte->advertencias as $advertencia) {
            $escritor->addRow(Row::fromValues(['Advertencia: '.$advertencia]));
        }

        $escritor->addRow(Row::fromValues([]));
        $escritor->addRow(Row::fromValuesWithStyle(array_map(fn (array $c): string => $c['etiqueta'], $reporte->columnas), $cabecera));

        foreach ($reporte->filas as $fila) {
            $escritor->addRow($this->fila($reporte->columnas, $fila, null));
        }

        if ($reporte->totales !== []) {
            $escritor->addRow($this->fila($reporte->columnas, $reporte->totales, $total));
        }

        if ($reporte->advertencias !== []) {
            $escritor->addNewSheetAndMakeItCurrent()->setName('Advertencias');
            $escritor->addRow(Row::fromValuesWithStyle(['Advertencias del reporte'], $negrita));

            foreach ($reporte->advertencias as $advertencia) {
                $escritor->addRow(Row::fromValues([$advertencia]));
            }
        }

        $escritor->close();
    }

    /**
     * @param  list<Columna>  $columnas
     * @param  array<string, string|int|float|null>  $valores
     */
    private function fila(array $columnas, array $valores, ?Style $estiloFila): Row
    {
        $celdas = [];

        foreach ($columnas as $columna) {
            $estilo = ($estiloFila ?? new Style)->withFormat($this->formato($columna['tipo']));
            $celdas[] = Cell::fromValue($valores[$columna['clave']] ?? null, $estilo);
        }

        return new Row($celdas);
    }

    /** Formato de celda de Excel. Los porcentajes ya vienen como 12,34, no como 0,1234. */
    private function formato(string $tipo): string
    {
        return match ($tipo) {
            'moneda' => '"Bs" #,##0.00',
            'entero' => '#,##0',
            'porcentaje' => '0.00"%"',
            default => 'General',
        };
    }

    /**
     * Ancho de cada columna según lo más largo que tenga (títulos, datos y totales).
     *
     * @return list<float>
     */
    private function anchos(ReporteResultado $reporte): array
    {
        $anchos = [];

        foreach ($reporte->columnas as $columna) {
            $largo = mb_strlen($columna['etiqueta']);

            foreach ([...$reporte->filas, $reporte->totales] as $fila) {
                $valor = $fila[$columna['clave']] ?? '';
                $largo = max($largo, mb_strlen(is_float($valor) ? number_format($valor, 2) : (string) $valor));
            }

            $anchos[] = (float) min(60, max(12, $largo + 4));
        }

        return $anchos;
    }
}
