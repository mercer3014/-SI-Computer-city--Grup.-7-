<?php

namespace Tests\Unit;

use App\Models\Categoria;
use App\Models\Marca;
use App\Services\Reportes\InterpreteComandoVoz;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InterpreteComandoVozTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $hoy;

    private Categoria $teclados;

    private Marca $redragon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hoy = CarbonImmutable::parse('2026-11-04', 'America/La_Paz');
        $this->teclados = Categoria::query()->create(['nombre' => 'Teclados', 'slug' => 'teclados']);
        Categoria::query()->create(['nombre' => 'Mouse', 'slug' => 'mouse']);
        $this->redragon = Marca::query()->create(['nombre' => 'Redragon', 'slug' => 'redragon']);
        Marca::query()->create(['nombre' => 'Logitech', 'slug' => 'logitech']);
    }

    public function test_productos_mas_vendidos_de_octubre_en_excel(): void
    {
        $r = $this->interpretar('productos más vendidos de octubre en excel');

        $this->assertSame('productos_mas_vendidos', $r['tipo']);
        $this->assertSame('mensual', $r['periodo']);
        $this->assertSame(['2026-10-01', '2026-10-31'], [$r['desde'], $r['hasta']]);
        $this->assertSame('xlsx', $r['formato']);
        $this->assertSame([], $r['faltantes']);
        $this->assertTrue($r['es_predefinido']);
        $this->assertSame('Productos más vendidos · octubre de 2026 · Excel', $r['entendido']);
    }

    public function test_ventas_de_la_semana_pasada(): void
    {
        $r = $this->interpretar('Ventas de la semana pasada');

        $this->assertSame('ventas_periodo', $r['tipo']);
        $this->assertSame('semanal', $r['periodo']);
        $this->assertSame(['2026-10-26', '2026-11-01'], [$r['desde'], $r['hasta']]);
        $this->assertNull($r['formato']);
    }

    public function test_una_frase_sin_tipo_ni_periodo_pide_completar_los_dos(): void
    {
        $r = $this->interpretar('dame algo');

        $this->assertNull($r['tipo']);
        $this->assertNull($r['periodo']);
        $this->assertSame(['tipo', 'periodo'], $r['faltantes']);
        $this->assertFalse($r['es_predefinido']);
        $this->assertSame('', $r['entendido']);
    }

    public function test_solo_el_tipo_deja_el_periodo_como_faltante(): void
    {
        $r = $this->interpretar('quiero el reporte de gastos');

        $this->assertSame('gastos_flujo_caja', $r['tipo']);
        $this->assertSame(['periodo'], $r['faltantes']);
    }

    public function test_una_comparacion_no_es_un_reporte_predefinido(): void
    {
        $r = $this->interpretar('comparar ventas de teclados por marca contra septiembre');

        $this->assertSame('ventas_periodo', $r['tipo']);
        $this->assertFalse($r['es_predefinido']);
        $this->assertSame($this->teclados->id, $r['filtros']['categoria_id']);
        $this->assertSame('mensual', $r['periodo']);
        $this->assertSame('2026-09-01', $r['desde']);
    }

    public function test_periodos_relativos(): void
    {
        $casos = [
            'ventas de hoy' => ['diario', '2026-11-04', '2026-11-04'],
            'gastos de ayer' => ['diario', '2026-11-03', '2026-11-03'],
            'ventas de esta semana' => ['semanal', '2026-11-02', '2026-11-08'],
            'utilidad de este mes' => ['mensual', '2026-11-01', '2026-11-30'],
            'estado de resultados del mes pasado' => ['mensual', '2026-10-01', '2026-10-31'],
            'compras de los últimos 15 días' => ['personalizado', '2026-10-21', '2026-11-04'],
            'ventas del 1 al 15 de octubre' => ['personalizado', '2026-10-01', '2026-10-15'],
        ];

        foreach ($casos as $frase => [$periodo, $desde, $hasta]) {
            $r = $this->interpretar($frase);

            $this->assertSame([$periodo, $desde, $hasta], [$r['periodo'], $r['desde'], $r['hasta']], $frase);
        }
    }

    public function test_el_mes_con_anio_y_el_mes_que_todavia_no_llego(): void
    {
        $this->assertSame('2025-10-01', $this->interpretar('ventas de octubre de 2025')['desde']);
        $this->assertSame('2025-12-01', $this->interpretar('ventas de diciembre')['desde']);
        $this->assertSame('2026-03-01', $this->interpretar('ventas de marzo')['desde']);
    }

    public function test_formatos(): void
    {
        $this->assertSame('pdf', $this->interpretar('ventas de hoy en PDF')['formato']);
        $this->assertSame('xlsx', $this->interpretar('ventas de hoy en hoja de cálculo')['formato']);
        $this->assertSame('docx', $this->interpretar('ventas de hoy en Word')['formato']);
    }

    public function test_los_filtros_salen_de_las_categorias_y_marcas_que_existen(): void
    {
        $r = $this->interpretar('top 5 productos más vendidos de teclados Redragon este mes');

        $this->assertSame('productos_mas_vendidos', $r['tipo']);
        $this->assertSame([
            'categoria_id' => $this->teclados->id,
            'marca_id' => $this->redragon->id,
            'limite' => 5,
        ], $r['filtros']);

        $singular = $this->interpretar('ventas de teclado de hoy');
        $this->assertSame($this->teclados->id, $singular['filtros']['categoria_id']);

        $this->assertSame([], $this->interpretar('ventas de monitores de hoy')['filtros']);
    }

    /**
     * @return array<string, mixed>
     */
    private function interpretar(string $texto): array
    {
        return app(InterpreteComandoVoz::class)->interpretar($texto, $this->hoy);
    }
}
