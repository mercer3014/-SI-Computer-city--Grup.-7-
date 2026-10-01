<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * CU03/CU04/CU05: permisos propios para roles y bitácora.
 * El rol Administrador los recibe al crearse.
 */
return new class extends Migration
{
    /** @var array<string, string> */
    private const PERMISOS = [
        'roles.administrar' => 'Administrar roles y permisos',
        'bitacora.ver' => 'Consultar la bitácora de auditoría',
    ];

    public function up(): void
    {
        $adminId = DB::table('rol')->where('nombre', 'Administrador')->value('id');

        foreach (self::PERMISOS as $clave => $nombre) {
            $permisoId = DB::table('permiso')->where('clave', $clave)->value('id');

            if ($permisoId === null) {
                $permisoId = DB::table('permiso')->insertGetId([
                    'clave' => $clave,
                    'nombre' => $nombre,
                    'modulo' => 'Seguridad',
                    'estado' => 'ACTIVO',
                ]);
            }

            if ($adminId !== null) {
                $existe = DB::table('rol_permiso')
                    ->where('rol_id', $adminId)
                    ->where('permiso_id', $permisoId)
                    ->exists();

                if (! $existe) {
                    DB::table('rol_permiso')->insert([
                        'rol_id' => $adminId,
                        'permiso_id' => $permisoId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        DB::table('permiso')->whereIn('clave', array_keys(self::PERMISOS))->delete();
    }
};
