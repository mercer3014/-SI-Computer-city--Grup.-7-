<?php

namespace App\Services\Reportes\Exportacion;

final readonly class ArchivoExportado
{
    public function __construct(
        public string $contenido,
        public string $nombre,
        public string $mime,
    ) {}
}
