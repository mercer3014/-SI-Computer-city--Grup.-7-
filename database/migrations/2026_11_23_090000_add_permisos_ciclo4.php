<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ciclo 4: permisos de configuración de la tienda, cotizaciones, calificaciones,
 * asistente virtual y reportes. Es idempotente y solo asigna a los roles que ya existen.
 *
 * `asistente.usar` es solo del Administrador; el Vendedor no lo recibe nunca.
 */
return new class extends Migration
{
    /** @var array<string, array{nombre: string, modulo: string, roles: list<string>}> */
    private const PERMISOS = [
        'configuracion.gestionar' => [
            'nombre' => 'Gestionar configuración de la tienda',
            'modulo' => 'Configuración',
            'roles' => ['Administrador'],
        ],
        'cotizaciones.gestionar' => [
            'nombre' => 'Gestionar cotizaciones',
            'modulo' => 'Ventas',
            'roles' => ['Administrador', 'Vendedor'],
        ],
        'calificaciones.gestionar' => [
            'nombre' => 'Moderar calificaciones de clientes',
            'modulo' => 'Clientes',
            'roles' => ['Administrador'],
        ],
        'asistente.usar' => [
            'nombre' => 'Usar el asistente virtual',
            'modulo' => 'Reportes',
            'roles' => ['Administrador'],
        ],
        'reportes.ver' => [
            'nombre' => 'Consultar reportes financieros',
            'modulo' => 'Reportes',
            'roles' => ['Administrador'],
        ],
    ];

    /** Permisos que esta migración crea y que `down()` puede borrar. `reportes.ver` ya existía. */
    private const PROPIOS = [
        'configuracion.gestionar',
        'cotizaciones.gestionar',
        'calificaciones.gestionar',
        'asistente.usar',
    ];

    public function up(): void
    {
        foreach (self::PERMISOS as $clave => $datos) {
            $permisoId = DB::table('permiso')->where('clave', $clave)->value('id');

            if ($permisoId === null) {
                $permisoId = DB::table('permiso')->insertGetId([
                    'clave' => $clave,
                    'nombre' => $datos['nombre'],
                    'modulo' => $datos['modulo'],
                    'estado' => 'ACTIVO',
                ]);
            }

            foreach ($datos['roles'] as $nombreRol) {
                $rolId = DB::table('rol')->where('nombre', $nombreRol)->value('id');

                if ($rolId === null) {
                    continue;
                }

                $yaAsignado = DB::table('rol_permiso')
                    ->where('rol_id', $rolId)
                    ->where('permiso_id', $permisoId)
                    ->exists();

                if (! $yaAsignado) {
                    DB::table('rol_permiso')->insert([
                        'rol_id' => $rolId,
                        'permiso_id' => $permisoId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permiso')->whereIn('clave', self::PROPIOS)->pluck('id')->all();

        DB::table('rol_permiso')->whereIn('permiso_id', $ids)->delete();
        DB::table('permiso')->whereIn('id', $ids)->delete();
    }
};
