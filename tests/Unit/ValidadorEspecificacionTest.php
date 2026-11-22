<?php

namespace Tests\Unit;

use App\Services\Asistente\EspecificacionInvalida;
use App\Services\Asistente\ValidadorEspecificacion;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class ValidadorEspecificacionTest extends TestCase
{
    private CarbonImmutable $hoy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hoy = CarbonImmutable::parse('2026-11-04', 'America/La_Paz');
    }

    public function test_una_especificacion_minima_se_completa_con_los_valores_por_defecto(): void
    {
        $espec = $this->validar([
            'metrica' => 'ventas_monto',
            'dimensiones' => [],
            'periodo' => ['desde' => '2026-10-01', 'hasta' => '2026-10-31'],
        ]);

        $this->assertSame([
            'metrica' => 'ventas_monto',
            'dimensiones' => [],
            'filtros' => [],
            'periodo' => ['desde' => '2026-10-01', 'hasta' => '2026-10-31'],
            'comparar_con' => null,
            'grafico' => 'tabla',
            'orden' => 'desc',
            'limite' => 20,
        ], $espec);
    }

    public function test_el_grafico_por_defecto_depende_de_las_dimensiones(): void
    {
        $this->assertSame('linea', $this->validar($this->base(['dimensiones' => ['semana']]))['grafico']);
        $this->assertSame('linea', $this->validar($this->base(['dimensiones' => ['marca', 'mes']]))['grafico']);
        $this->assertSame('barra', $this->validar($this->base(['dimensiones' => ['marca']]))['grafico']);
        $this->assertSame('torta', $this->validar($this->base(['dimensiones' => ['marca'], 'grafico' => 'torta']))['grafico']);
    }

    public function test_los_filtros_nulos_se_descartan_y_el_texto_se_recorta(): void
    {
        $espec = $this->validar($this->base([
            'filtros' => ['categoria' => '  Teclados ', 'marca' => 'Redragon', 'producto' => null, 'metodo_pago' => null],
        ]));

        $this->assertSame(['categoria' => 'Teclados', 'marca' => 'Redragon'], $espec['filtros']);
    }

    public function test_la_comparacion_se_valida_como_un_periodo(): void
    {
        $espec = $this->validar($this->base([
            'comparar_con' => ['desde' => '2026-09-01', 'hasta' => '2026-09-30'],
        ]));

        $this->assertSame(['desde' => '2026-09-01', 'hasta' => '2026-09-30'], $espec['comparar_con']);
    }

    public function test_cada_metrica_admite_las_dimensiones_de_la_matriz(): void
    {
        $matriz = [
            'ventas_monto' => ['dia', 'semana', 'mes', 'producto', 'categoria', 'marca', 'metodo_pago'],
            'ventas_cantidad' => ['dia', 'semana', 'mes', 'producto', 'categoria', 'marca', 'metodo_pago'],
            'unidades_vendidas' => ['dia', 'semana', 'mes', 'producto', 'categoria', 'marca'],
            'utilidad_bruta' => ['dia', 'semana', 'mes', 'producto', 'categoria', 'marca'],
            'gastos' => ['dia', 'semana', 'mes', 'categoria_gasto', 'metodo_pago'],
            'compras' => ['dia', 'semana', 'mes', 'proveedor', 'producto'],
            'saldo_caja' => ['dia', 'semana', 'mes'],
            'stock' => ['producto', 'categoria', 'marca'],
            'pedidos' => ['dia', 'semana', 'mes', 'estado'],
            'cotizaciones' => ['dia', 'semana', 'mes', 'estado'],
            'calificacion_promedio' => ['dia', 'semana', 'mes', 'producto'],
        ];

        foreach ($matriz as $metrica => $dimensiones) {
            foreach ($dimensiones as $dimension) {
                $espec = $this->validar($this->base(['metrica' => $metrica, 'dimensiones' => [$dimension]]));

                $this->assertSame([$dimension], $espec['dimensiones'], "$metrica × $dimension");
            }

            foreach (array_diff(['dia', 'semana', 'mes', 'producto', 'categoria', 'marca', 'proveedor', 'metodo_pago', 'categoria_gasto', 'estado'], $dimensiones) as $prohibida) {
                $this->assertRechaza($this->base(['metrica' => $metrica, 'dimensiones' => [$prohibida]]), "$metrica × $prohibida");
            }
        }
    }

    public function test_los_estados_se_normalizan_segun_la_metrica(): void
    {
        $this->assertSame('PAGADO', $this->validar($this->base(['metrica' => 'pedidos', 'filtros' => ['estado' => 'pagado']]))['filtros']['estado']);
        $this->assertSame('PENDIENTE', $this->validar($this->base(['metrica' => 'cotizaciones', 'filtros' => ['estado' => 'pendiente']]))['filtros']['estado']);
        $this->assertSame('bajo_minimo', $this->validar($this->base(['metrica' => 'stock', 'filtros' => ['estado' => 'BAJO_MINIMO']]))['filtros']['estado']);
    }

    public function test_se_rechaza_lo_que_no_esta_en_la_lista_blanca(): void
    {
        $periodo = ['desde' => '2026-10-01', 'hasta' => '2026-10-31'];

        $this->assertRechaza('ventas_monto', 'no es un objeto');
        $this->assertRechaza($this->base(['metrica' => 'usuarios']), 'métrica desconocida');
        $this->assertRechaza($this->base(['metrica' => null]), 'sin métrica');
        $this->assertRechaza($this->base(['sql' => 'DROP TABLE venta']), 'clave extra');
        $this->assertRechaza($this->base(['dimensiones' => ['dia', 'semana']]), 'dos de tiempo');
        $this->assertRechaza($this->base(['dimensiones' => ['marca', 'marca']]), 'repetida');
        $this->assertRechaza($this->base(['dimensiones' => ['marca', 'producto', 'categoria']]), 'tres dimensiones');
        $this->assertRechaza($this->base(['dimensiones' => 'semana']), 'dimensiones no es lista');
        $this->assertRechaza($this->base(['dimensiones' => ['cliente']]), 'dimensión desconocida');
        $this->assertRechaza($this->base(['filtros' => ['sql' => 'DROP TABLE venta']]), 'filtro extra');
        $this->assertRechaza($this->base(['filtros' => ['proveedor' => 'ABC']]), 'filtro no permitido para la métrica');
        $this->assertRechaza($this->base(['filtros' => ['marca' => ['a', 'b']]]), 'filtro que no es texto');
        $this->assertRechaza($this->base(['filtros' => ['marca' => str_repeat('x', 81)]]), 'filtro muy largo');
        $this->assertRechaza($this->base(['filtros' => ['marca' => "Red\0dragon"]]), 'filtro con caracteres de control');
        $this->assertRechaza($this->base(['metrica' => 'pedidos', 'filtros' => ['estado' => 'ENTREGADO']]), 'estado inexistente');
        $this->assertRechaza($this->base(['periodo' => null]), 'sin período');
        $this->assertRechaza($this->base(['periodo' => ['desde' => '2026-10-01']]), 'período incompleto');
        $this->assertRechaza($this->base(['periodo' => [...$periodo, 'extra' => 1]]), 'período con claves de más');
        $this->assertRechaza($this->base(['periodo' => ['desde' => '01/10/2026', 'hasta' => '31/10/2026']]), 'fecha con otro formato');
        $this->assertRechaza($this->base(['periodo' => ['desde' => '2026-02-31', 'hasta' => '2026-03-05']]), 'fecha inexistente');
        $this->assertRechaza($this->base(['periodo' => ['desde' => '2026-10-31', 'hasta' => '2026-10-01']]), 'fin anterior al inicio');
        $this->assertRechaza($this->base(['periodo' => ['desde' => '2025-01-01', 'hasta' => '2026-10-31']]), 'más de 366 días');
        $this->assertRechaza($this->base(['periodo' => ['desde' => '2026-12-20', 'hasta' => '2026-12-31']]), 'demasiado al futuro');
        $this->assertRechaza($this->base(['grafico' => 'radar']), 'gráfico desconocido');
        $this->assertRechaza($this->base(['orden' => 'azar']), 'orden desconocido');
        $this->assertRechaza($this->base(['limite' => 0]), 'límite 0');
        $this->assertRechaza($this->base(['limite' => 51]), 'límite 51');
        $this->assertRechaza($this->base(['limite' => '10']), 'límite como texto');
    }

    public function test_el_periodo_acepta_366_dias_y_pocos_dias_hacia_adelante(): void
    {
        $this->assertNotEmpty($this->validar($this->base(['periodo' => ['desde' => '2025-11-04', 'hasta' => '2026-11-04']])));
        $this->assertNotEmpty($this->validar($this->base(['periodo' => ['desde' => '2026-11-01', 'hasta' => '2026-12-04']])));
    }

    public function test_la_comparacion_solo_va_con_una_dimension_y_no_con_stock(): void
    {
        $comparar = ['desde' => '2026-09-01', 'hasta' => '2026-09-30'];

        $this->assertRechaza($this->base(['dimensiones' => ['semana', 'marca'], 'comparar_con' => $comparar]), 'comparar con dos dimensiones');
        $this->assertRechaza($this->base(['metrica' => 'stock', 'dimensiones' => ['marca'], 'comparar_con' => $comparar]), 'comparar el stock');
        $this->assertRechaza($this->base(['comparar_con' => ['desde' => '2026-09-30', 'hasta' => '2026-09-01']]), 'comparación al revés');
    }

    public function test_por_metodo_de_pago_solo_se_combina_con_el_tiempo(): void
    {
        $this->assertSame(
            ['semana', 'metodo_pago'],
            $this->validar($this->base(['dimensiones' => ['semana', 'metodo_pago'], 'filtros' => ['metodo_pago' => 'QR']]))['dimensiones'],
        );

        $this->assertRechaza($this->base(['dimensiones' => ['metodo_pago', 'categoria']]), 'método de pago con categoría');
        $this->assertRechaza($this->base(['dimensiones' => ['metodo_pago'], 'filtros' => ['categoria' => 'Mouse']]), 'método de pago con filtro de categoría');
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function base(array $cambios = []): array
    {
        return [
            'metrica' => 'ventas_monto',
            'dimensiones' => [],
            'periodo' => ['desde' => '2026-10-01', 'hasta' => '2026-10-31'],
            ...$cambios,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(mixed $especificacion): array
    {
        return app(ValidadorEspecificacion::class)->validar($especificacion, $this->hoy);
    }

    private function assertRechaza(mixed $especificacion, string $caso): void
    {
        try {
            $this->validar($especificacion);
        } catch (EspecificacionInvalida) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail("Se esperaba rechazar: $caso.");
    }
}
