<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Datos de la tienda y su horario (lunes a sábado de 09:00 a 19:00, domingo cerrado).
 * Si ya hay una configuración, no la toca.
 */
class ConfiguracionTiendaSeeder extends Seeder
{
    public function run(): void
    {
        $configuracionId = DB::table('configuracion_tienda')->value('id');

        if ($configuracionId === null) {
            $configuracionId = DB::table('configuracion_tienda')->insertGetId([
                'nombre_tienda' => 'Computer City',
                'centro_comercial' => 'Comercial Chiriguano',
                'direccion' => 'Santa Cruz de la Sierra, Bolivia',
                'telefono' => '77712345',
                'whatsapp' => '77712345',
                'email' => 'contacto@computercity.com',
                'moneda' => 'BOB',
            ]);
        }

        if (DB::table('horario_atencion')->where('configuracion_tienda_id', $configuracionId)->exists()) {
            return;
        }

        foreach (range(1, 7) as $dia) {
            $domingo = $dia === 7;

            DB::table('horario_atencion')->insert([
                'configuracion_tienda_id' => $configuracionId,
                'dia_semana' => $dia,
                'hora_apertura' => $domingo ? null : '09:00',
                'hora_cierre' => $domingo ? null : '19:00',
                'cerrado' => $domingo,
            ]);
        }
    }
}
