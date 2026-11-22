<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Datos de demostración: roles, usuarios, tienda, catálogo y 90 días de operaciones.
     *
     * php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        $this->call([
            RolesPermisosSeeder::class,
            UsuariosDemoSeeder::class,
            ConfiguracionTiendaSeeder::class,
            CatalogoDemoSeeder::class,
            CatalogoImagenesSeeder::class,
            OperacionesDemoSeeder::class,
        ]);
    }
}
