<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Roles del sistema y todos los permisos, con su asignación por rol.
 * Es idempotente: se puede correr varias veces sin duplicar nada.
 */
class RolesPermisosSeeder extends Seeder
{
    /** @var array<string, string> */
    private const ROLES = [
        'Administrador' => 'Acceso total al sistema: inventario, ventas, compras, clientes, proveedores, usuarios y reportes',
        'Vendedor' => 'Acceso a ventas, consulta de productos, clientes y garantías',
        'Cliente Web' => 'Cuenta de la tienda en línea: compra y consulta sus pedidos, sin acceso al panel',
    ];

    /** @var array<string, array{0: string, 1: string}> */
    private const PERMISOS = [
        'usuarios.administrar' => ['Administrar usuarios y roles', 'Seguridad'],
        'roles.administrar' => ['Administrar roles y permisos', 'Seguridad'],
        'bitacora.ver' => ['Consultar la bitácora de auditoría', 'Seguridad'],
        'productos.gestionar' => ['Gestionar productos y catálogo', 'Inventario'],
        'inventario.ver' => ['Consultar inventario', 'Inventario'],
        'inventario.ajustar' => ['Ajustar existencias manualmente', 'Inventario'],
        'ventas.crear' => ['Registrar ventas', 'Ventas'],
        'ventas.anular' => ['Anular ventas', 'Ventas'],
        'clientes.gestionar' => ['Gestionar clientes', 'Ventas'],
        'garantias.gestionar' => ['Gestionar garantías y devoluciones', 'Ventas'],
        'cotizaciones.gestionar' => ['Gestionar cotizaciones', 'Ventas'],
        'compras.crear' => ['Registrar compras a proveedores', 'Compras'],
        'proveedores.gestionar' => ['Gestionar proveedores', 'Compras'],
        'gastos.gestionar' => ['Registrar ingresos y gastos', 'Finanzas'],
        'reportes.ver' => ['Consultar reportes financieros', 'Reportes'],
        'asistente.usar' => ['Usar el asistente virtual', 'Reportes'],
        'calificaciones.gestionar' => ['Moderar calificaciones de clientes', 'Clientes'],
        'configuracion.gestionar' => ['Gestionar configuración de la tienda', 'Configuración'],
    ];

    /** @var list<string> */
    private const PERMISOS_VENDEDOR = [
        'inventario.ver',
        'ventas.crear',
        'clientes.gestionar',
        'garantias.gestionar',
        'cotizaciones.gestionar',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $nombre => $descripcion) {
            if (! DB::table('rol')->where('nombre', $nombre)->exists()) {
                DB::table('rol')->insert([
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'es_sistema' => true,
                    'estado' => 'ACTIVO',
                ]);
            }
        }

        foreach (self::PERMISOS as $clave => [$nombre, $modulo]) {
            if (! DB::table('permiso')->where('clave', $clave)->exists()) {
                DB::table('permiso')->insert([
                    'clave' => $clave,
                    'nombre' => $nombre,
                    'modulo' => $modulo,
                    'estado' => 'ACTIVO',
                ]);
            }
        }

        $this->asignar('Administrador', array_keys(self::PERMISOS));
        $this->asignar('Vendedor', self::PERMISOS_VENDEDOR);
        $this->quitarAsistenteAlVendedor();
    }

    /**
     * @param  list<string>  $claves
     */
    private function asignar(string $rol, array $claves): void
    {
        $rolId = DB::table('rol')->where('nombre', $rol)->value('id');
        $permisoIds = DB::table('permiso')->whereIn('clave', $claves)->pluck('id');

        foreach ($permisoIds as $permisoId) {
            $existe = DB::table('rol_permiso')
                ->where('rol_id', $rolId)
                ->where('permiso_id', $permisoId)
                ->exists();

            if (! $existe) {
                DB::table('rol_permiso')->insert(['rol_id' => $rolId, 'permiso_id' => $permisoId]);
            }
        }
    }

    private function quitarAsistenteAlVendedor(): void
    {
        $rolId = DB::table('rol')->where('nombre', 'Vendedor')->value('id');
        $permisoId = DB::table('permiso')->where('clave', 'asistente.usar')->value('id');

        DB::table('rol_permiso')->where('rol_id', $rolId)->where('permiso_id', $permisoId)->delete();
    }
}
