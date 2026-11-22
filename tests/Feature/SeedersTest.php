<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesPermisosSeeder;
use Database\Seeders\UsuariosDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_crea_el_administrador_el_vendedor_y_el_cliente_web(): void
    {
        $this->seed(DatabaseSeeder::class);

        $esperados = [
            UsuariosDemoSeeder::ADMIN => 'Administrador',
            UsuariosDemoSeeder::VENDEDOR => 'Vendedor',
            UsuariosDemoSeeder::CLIENTE_WEB => 'Cliente Web',
        ];

        foreach ($esperados as $email => $rol) {
            $usuario = User::query()->where('email', $email)->firstOrFail();

            $this->assertSame($rol, $usuario->rol?->nombre);
            $this->assertFalse((bool) $usuario->primer_login);
            $this->assertTrue(Hash::check('password', $usuario->password_hash));
        }

        $web = User::query()->where('email', UsuariosDemoSeeder::CLIENTE_WEB)->firstOrFail();

        $this->assertNotNull($web->cliente_id);
        $this->assertNull($web->personal_id);
    }

    public function test_los_permisos_del_ciclo_4_se_asignan_segun_el_rol(): void
    {
        $this->seed(DatabaseSeeder::class);

        $permisosDe = fn (string $rol): array => DB::table('rol_permiso as rp')
            ->join('rol as r', 'r.id', '=', 'rp.rol_id')
            ->join('permiso as p', 'p.id', '=', 'rp.permiso_id')
            ->where('r.nombre', $rol)
            ->pluck('p.clave')
            ->all();

        $nuevos = ['configuracion.gestionar', 'cotizaciones.gestionar', 'calificaciones.gestionar', 'asistente.usar', 'reportes.ver'];

        foreach ($nuevos as $clave) {
            $this->assertContains($clave, $permisosDe('Administrador'));
        }

        $vendedor = $permisosDe('Vendedor');

        $this->assertContains('cotizaciones.gestionar', $vendedor);
        $this->assertNotContains('asistente.usar', $vendedor);
        $this->assertNotContains('reportes.ver', $vendedor);
        $this->assertNotContains('configuracion.gestionar', $vendedor);
        $this->assertNotContains('calificaciones.gestionar', $vendedor);
        $this->assertSame([], $permisosDe('Cliente Web'));
    }

    public function test_deja_datos_para_los_reportes_el_dashboard_y_el_asistente(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, DB::table('configuracion_tienda')->count());
        $this->assertSame(7, DB::table('horario_atencion')->count());
        $this->assertSame(20, DB::table('variante_producto')->count());
        $this->assertGreaterThan(50, DB::table('venta')->count());
        $this->assertGreaterThan(0, DB::table('venta')->where('estado', 'ANULADA')->count());
        $this->assertGreaterThan(0, DB::table('gasto')->where('estado', 'ANULADO')->count());
        $this->assertGreaterThan(0, DB::table('evento_producto')->count());
        $this->assertGreaterThanOrEqual(3, DB::table('compra')->distinct()->count('proveedor_id'));
        $this->assertGreaterThanOrEqual(3, DB::table('gasto')->distinct()->count('categoria_gasto_id'));

        $this->assertGreaterThan(0, DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->whereNull('dv.costo_unitario_snapshot')
            ->count());

        $this->assertGreaterThan(0, DB::table('detalle_venta as dv')
            ->join('venta as v', 'v.id', '=', 'dv.venta_id')
            ->where('v.estado', '<>', 'ANULADA')
            ->whereBetween('dv.fecha_fin_garantia', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->count());

        $this->assertSame(0, DB::table('variante_producto')->where('stock_actual', '<', 0)->count());
        $this->assertGreaterThan(0, DB::table('variante_producto')->where('stock_actual', 0)->count());
        $this->assertGreaterThan(0, DB::table('variante_producto')
            ->whereColumn('stock_actual', '<', 'stock_minimo')
            ->where('stock_actual', '>', 0)
            ->count());

        $this->assertSame(4, DB::table('pedido')->where('estado', 'PAGADO')->count());
        $this->assertSame(1, DB::table('pedido')->where('estado', 'PENDIENTE_PAGO')->count());
        $this->assertSame(4, DB::table('venta')->whereNotNull('pedido_id')->count());
    }

    public function test_correrlo_dos_veces_no_duplica_nada(): void
    {
        $this->seed(DatabaseSeeder::class);

        $contar = fn (): array => collect(['permiso', 'rol', 'usuario', 'cliente', 'producto', 'variante_producto', 'venta', 'compra', 'gasto', 'evento_producto', 'horario_atencion'])
            ->mapWithKeys(fn (string $tabla): array => [$tabla => DB::table($tabla)->count()])
            ->all();

        $antes = $contar();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($antes, $contar());
    }

    public function test_los_usuarios_demo_no_se_crean_en_produccion(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        (new RolesPermisosSeeder)->run();
        (new UsuariosDemoSeeder)->run();

        $this->assertSame(0, DB::table('usuario')->count());
    }
}
