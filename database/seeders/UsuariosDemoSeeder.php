<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Personal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Un Administrador, un Vendedor y un Cliente Web para desarrollo y capturas.
 * La clave sale de SEED_DEMO_PASSWORD (config/seeders.php, por defecto "password") y es solo de desarrollo.
 * En producción no hace nada.
 */
class UsuariosDemoSeeder extends Seeder
{
    public const ADMIN = 'ruben.morales@computercity.com';

    public const VENDEDOR = 'maria.fernandez@computercity.com';

    public const CLIENTE_WEB = 'cliente.demo@example.com';

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $clave = (string) config('seeders.demo_password');

        $this->crearInterno(
            self::ADMIN,
            'Administrador',
            ['nombre_completo' => 'Ruben Morales', 'ci' => '5896321 SC', 'telefono' => '77712345', 'cargo' => 'Gerente General / Propietario'],
            $clave,
        );

        $this->crearInterno(
            self::VENDEDOR,
            'Vendedor',
            ['nombre_completo' => 'Maria Fernandez', 'ci' => '6541230 SC', 'telefono' => '76654321', 'cargo' => 'Vendedora'],
            $clave,
        );

        $this->crearClienteWeb($clave);
    }

    /**
     * @param  array{nombre_completo: string, ci: string, telefono: string, cargo: string}  $persona
     */
    private function crearInterno(string $email, string $rol, array $persona, string $clave): void
    {
        $personal = Personal::query()->firstOrCreate(['ci' => $persona['ci']], $persona);

        User::query()->updateOrCreate(['email' => $email], [
            'personal_id' => $personal->id,
            'rol_id' => $this->rolId($rol),
            'password_hash' => $clave,
            'estado' => 'ACTIVO',
            'bloqueado' => false,
            'primer_login' => false,
        ]);
    }

    private function crearClienteWeb(string $clave): void
    {
        $cliente = Cliente::query()->firstOrCreate(['numero_documento' => '7000001 SC'], [
            'tipo_documento' => 'CI',
            'nombre' => 'Lucia Demo Cliente',
            'telefono' => '70000001',
            'email' => self::CLIENTE_WEB,
            'estado' => 'ACTIVO',
        ]);

        User::query()->updateOrCreate(['email' => self::CLIENTE_WEB], [
            'cliente_id' => $cliente->id,
            'rol_id' => $this->rolId('Cliente Web'),
            'password_hash' => $clave,
            'estado' => 'ACTIVO',
            'bloqueado' => false,
            'primer_login' => false,
        ]);
    }

    private function rolId(string $nombre): int
    {
        return (int) DB::table('rol')->where('nombre', $nombre)->value('id');
    }
}
