<?php

namespace App\Services\Asistente;

use Illuminate\Support\Facades\DB;

/**
 * Convierte los nombres de los filtros ("Redragon", "Teclados") en ids con consultas parametrizadas.
 * Un nombre que no existe da una lista vacía: la consulta devuelve "sin registros" en lugar de ignorar el filtro.
 */
class ResolutorDeFiltros
{
    /**
     * @param  array<string, string>  $filtros
     * @return array<string, list<int>|string> ids por filtro; "estado" queda como texto
     */
    public function resolver(string $metrica, array $filtros): array
    {
        $resueltos = [];

        foreach ($filtros as $clave => $texto) {
            if ($clave === 'estado') {
                $resueltos['estado'] = $texto;

                continue;
            }

            if ($clave === 'categoria' && $metrica === 'gastos') {
                $resueltos['categoria_gasto'] = $this->ids('categoria_gasto', ['nombre'], $texto);

                continue;
            }

            $resueltos[$clave] = match ($clave) {
                'categoria' => $this->ids('categoria', ['nombre'], $texto),
                'marca' => $this->ids('marca', ['nombre'], $texto),
                'proveedor' => $this->ids('proveedor', ['razon_social', 'nombre_comercial'], $texto),
                'producto' => $this->ids('producto', ['nombre'], $texto),
                default => $this->ids('metodo_pago', ['nombre', 'codigo'], $texto),
            };
        }

        return $resueltos;
    }

    /**
     * @param  literal-string  $tabla
     * @param  list<literal-string>  $columnas
     * @return list<int>
     */
    private function ids(string $tabla, array $columnas, string $texto): array
    {
        $operador = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $patron = '%'.str_replace(['%', '_', '\\'], '', $texto).'%';

        $consulta = DB::table($tabla)->select('id');

        $consulta->where(function ($donde) use ($columnas, $operador, $patron): void {
            foreach ($columnas as $columna) {
                $donde->orWhere($columna, $operador, $patron);
            }
        });

        $ids = [];

        foreach ($consulta->pluck('id') as $id) {
            $ids[] = (int) $id;
        }

        return $ids;
    }
}
