<?php

namespace App\Services\Reportes\Exportacion;

use InvalidArgumentException;

/**
 * Formato de archivo (pdf, xlsx o docx) y la clase que lo genera.
 */
final class Exportadores
{
    /** @var array<string, class-string<ExportadorReporte>> */
    public const FORMATOS = [
        'pdf' => PdfExportador::class,
        'xlsx' => ExcelExportador::class,
        'docx' => WordExportador::class,
    ];

    /** @return list<string> */
    public static function formatos(): array
    {
        return array_keys(self::FORMATOS);
    }

    public static function para(string $formato): ExportadorReporte
    {
        $clase = self::FORMATOS[$formato] ?? throw new InvalidArgumentException('El formato elegido no existe.');

        return app($clase);
    }
}
