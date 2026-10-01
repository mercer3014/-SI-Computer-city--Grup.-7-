<?php

namespace Tests\Concerns;

use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;

trait CreaAdministrador
{
    /**
     * @param  list<string>  $claves
     */
    protected function rolConPermisos(string $nombre, array $claves): Rol
    {
        $rol = Rol::factory()->create(['nombre' => $nombre]);

        foreach ($claves as $clave) {
            $permiso = Permiso::query()->firstOrCreate(
                ['clave' => $clave],
                ['nombre' => $clave, 'modulo' => 'Seguridad'],
            );
            $rol->permisos()->attach($permiso->id);
        }

        return $rol;
    }

    protected function administrador(): User
    {
        $rol = $this->rolConPermisos('Administrador', [
            'usuarios.administrar',
            'roles.administrar',
            'bitacora.ver',
        ]);

        return User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);
    }
}
