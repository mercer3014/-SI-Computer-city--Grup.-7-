<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clave de los usuarios de demostración
    |--------------------------------------------------------------------------
    |
    | La usa UsuariosDemoSeeder para el Administrador, el Vendedor y el Cliente Web.
    | Es solo para desarrollo: en producción el seeder no crea usuarios.
    |
    */

    'demo_password' => env('SEED_DEMO_PASSWORD', 'password'),

];
