<?php

namespace Tests\Feature;

use App\Models\CategoriaGasto;
use App\Models\Gasto;
use App\Models\MovimientoCaja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class CajaTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    public function test_users_without_finance_permission_cannot_open_cash_flow(): void
    {
        $usuario = User::factory()->create(['primer_login' => false]);

        $this->actingAs($usuario)->get('/caja')->assertForbidden();
    }

    public function test_report_viewer_can_consult_but_not_register(): void
    {
        $lector = $this->usuarioCon(['reportes.ver']);

        $this->actingAs($lector)->get('/caja')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('CajaGastos')->where('puedeRegistrar', false));

        $this->actingAs($lector)->post('/caja/movimientos', [])->assertForbidden();
    }

    public function test_admin_registers_expense_and_income_and_sees_consolidated_flow(): void
    {
        $admin = $this->usuarioCon(['gastos.gestionar']);
        $alquiler = CategoriaGasto::query()->create(['nombre' => 'Alquiler', 'estado' => 'ACTIVO']);
        $hoy = now()->toDateString();

        $this->actingAs($admin)->post('/caja/movimientos', [
            'tipo' => 'EGRESO',
            'es_gasto' => true,
            'monto' => 1500,
            'fecha' => $hoy,
            'descripcion' => 'Alquiler del mes',
            'categoria_gasto_id' => $alquiler->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->post('/caja/movimientos', [
            'tipo' => 'INGRESO',
            'monto' => 400,
            'fecha' => $hoy,
            'descripcion' => 'Reparación de teclado',
            'categoria' => 'Servicio técnico',
        ])->assertSessionHasNoErrors();

        $gasto = Gasto::query()->firstOrFail();
        $this->assertSame('ACTIVO', $gasto->estado);
        $this->assertDatabaseHas('movimiento_caja', ['gasto_id' => $gasto->id, 'tipo' => 'EGRESO', 'categoria' => 'Alquiler']);
        $this->assertDatabaseHas('movimiento_caja', ['gasto_id' => null, 'tipo' => 'INGRESO', 'categoria' => 'Servicio técnico']);
        $this->assertDatabaseHas('bitacora_auditoria', ['accion' => 'REGISTRAR_GASTO']);

        $this->actingAs($admin)->get('/caja?desde='.$hoy.'&hasta='.$hoy)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('CajaGastos')
                ->where('resumen.ingresos', 400)
                ->where('resumen.egresos', 1500)
                ->where('resumen.neto', -1100)
                ->has('movimientos.data', 2));
    }

    public function test_invalid_amount_or_missing_fields_are_rejected(): void
    {
        $admin = $this->usuarioCon(['gastos.gestionar']);

        $this->actingAs($admin)->post('/caja/movimientos', [
            'tipo' => 'EGRESO',
            'monto' => 0,
            'fecha' => now()->toDateString(),
            'descripcion' => '',
        ])->assertSessionHasErrors(['monto', 'descripcion']);

        $this->actingAs($admin)->post('/caja/movimientos', [
            'tipo' => 'EGRESO',
            'es_gasto' => true,
            'monto' => 50,
            'fecha' => now()->toDateString(),
            'descripcion' => 'Sin categoría',
        ])->assertSessionHasErrors('categoria_gasto_id');

        $this->assertSame(0, Gasto::query()->count());
        $this->assertSame(0, MovimientoCaja::query()->count());
    }

    public function test_expense_can_be_voided_and_stops_counting(): void
    {
        $admin = $this->usuarioCon(['gastos.gestionar']);
        $categoria = CategoriaGasto::query()->create(['nombre' => 'Publicidad', 'estado' => 'ACTIVO']);
        $hoy = now()->toDateString();

        $this->actingAs($admin)->post('/caja/movimientos', [
            'tipo' => 'EGRESO',
            'es_gasto' => true,
            'monto' => 250,
            'fecha' => $hoy,
            'descripcion' => 'Publicidad en redes',
            'categoria_gasto_id' => $categoria->id,
        ]);

        $gasto = Gasto::query()->firstOrFail();

        $this->actingAs($admin)
            ->patch("/caja/GASTO/{$gasto->id}/anular", ['motivo' => 'Registrado por error'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ANULADO', $gasto->fresh()->estado);
        $this->assertSame('ANULADO', MovimientoCaja::query()->where('gasto_id', $gasto->id)->value('estado'));

        $this->actingAs($admin)->get('/caja?desde='.$hoy.'&hasta='.$hoy)
            ->assertInertia(fn (Assert $page) => $page->where('resumen.egresos', 0));
    }

    public function test_expense_category_names_are_unique(): void
    {
        $admin = $this->usuarioCon(['gastos.gestionar']);
        CategoriaGasto::query()->create(['nombre' => 'Servicios Básicos', 'estado' => 'ACTIVO']);

        $this->actingAs($admin)->post('/caja/categorias', ['nombre' => 'servicios básicos'])
            ->assertSessionHasErrors('nombre');

        $this->actingAs($admin)->post('/caja/categorias', ['nombre' => 'Mantenimiento'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('categoria_gasto', ['nombre' => 'Mantenimiento', 'estado' => 'ACTIVO']);
    }

    /**
     * @param  list<string>  $claves
     */
    private function usuarioCon(array $claves): User
    {
        $rol = $this->rolConPermisos('Rol '.implode('-', $claves), $claves);

        return User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);
    }
}
