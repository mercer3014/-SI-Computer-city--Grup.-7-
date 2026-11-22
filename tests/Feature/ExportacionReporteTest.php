<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reportes\Exportacion\ArchivoExportado;
use App\Services\Reportes\Exportacion\ExcelExportador;
use App\Services\Reportes\Exportacion\ExportacionFallida;
use App\Services\Reportes\Exportacion\ExportadorReporte;
use App\Services\Reportes\Exportacion\PdfExportador;
use App\Services\Reportes\Exportacion\WordExportador;
use App\Services\Reportes\ReporteResultado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\CreaAdministrador;
use Tests\Concerns\CreaEscenarioDeReportes;
use Tests\TestCase;
use ZipArchive;

class ExportacionReporteTest extends TestCase
{
    use CreaAdministrador;
    use CreaEscenarioDeReportes;
    use RefreshDatabase;

    private const PARAMETROS = [
        'tipo' => 'ventas_periodo',
        'periodo' => 'personalizado',
        'desde' => '2026-10-01',
        'hasta' => '2026-10-31',
        'agrupar' => 'dia',
    ];

    public function test_los_tres_formatos_se_descargan_con_su_tipo_y_nombre(): void
    {
        $this->escenarioDeReportes();
        $admin = $this->usuarioConReportes();

        $casos = [
            'pdf' => 'application/pdf',
            'xlsx' => ExcelExportador::MIME,
            'docx' => WordExportador::MIME,
        ];

        foreach ($casos as $formato => $mime) {
            $respuesta = $this->actingAs($admin)
                ->get(route('reportes.exportar', [...self::PARAMETROS, 'formato' => $formato]))
                ->assertOk()
                ->assertHeader('Content-Type', $mime);

            $this->assertSame(
                'attachment; filename="reporte-ventas_periodo-2026-10-01_2026-10-31.'.$formato.'"',
                $respuesta->headers->get('Content-Disposition'),
            );
            $this->assertNotSame('', $respuesta->getContent());
        }
    }

    public function test_el_pdf_es_un_pdf_valido(): void
    {
        $this->escenarioDeReportes();

        $contenido = $this->descargar('pdf');

        $this->assertStringStartsWith('%PDF', $contenido);
    }

    public function test_el_excel_trae_el_mismo_total_que_la_pantalla_y_la_hoja_de_advertencias(): void
    {
        $this->escenarioDeReportes();

        $hojas = $this->leerXlsx($this->descargar('xlsx'));

        $this->assertSame(['Reporte', 'Advertencias'], array_keys($hojas));

        $reporte = $hojas['Reporte'];
        $this->assertSame('Computer City', $reporte[0][0]);
        $this->assertContains(['Período', 'Ventas', 'Ingresos', 'Costo', 'Utilidad bruta'], $reporte);

        $total = end($reporte);
        $this->assertSame('Total', $total[0]);
        $this->assertEquals([3, 800.0, 420.0, 280.0], array_slice($total, 1, 4));

        $this->assertContains('05/10/2026', array_column($reporte, 0));
        $this->assertSame(
            ['1 línea de venta sin costo registrado: la utilidad puede estar incompleta.'],
            array_column(array_slice($hojas['Advertencias'], 1), 0),
        );
    }

    public function test_el_excel_sin_advertencias_no_trae_la_hoja_extra(): void
    {
        $e = $this->escenarioDeReportes();

        $hojas = $this->leerXlsx($this->descargar('xlsx', ['marca_id' => $e['marcas']['redragon']->id]));

        $this->assertSame(['Reporte'], array_keys($hojas));
        $this->assertEquals(200.0, end($hojas['Reporte'])[2]);
    }

    public function test_el_word_trae_el_titulo_la_tabla_y_el_total(): void
    {
        $this->escenarioDeReportes();

        $ruta = tempnam(sys_get_temp_dir(), 'docx');
        file_put_contents($ruta, $this->descargar('docx'));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta) === true);
        $xml = (string) $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($ruta);

        $this->assertStringContainsString('Ventas por período', $xml);
        $this->assertStringContainsString('Período: 01/10/2026 – 31/10/2026', $xml);
        $this->assertStringContainsString('05/10/2026', $xml);
        $this->assertStringContainsString('Bs 800,00', $xml);
        $this->assertStringContainsString('Advertencia: 1 línea de venta sin costo registrado', $xml);
    }

    public function test_la_exportacion_queda_en_la_bitacora_con_el_formato(): void
    {
        $this->escenarioDeReportes();

        $this->descargar('xlsx');

        $evento = DB::table('bitacora_auditoria')->where('accion', 'EXPORTAR_REPORTE')->first();
        $this->assertNotNull($evento);
        $this->assertSame('Reportes', $evento->modulo);
        $nuevo = json_decode((string) $evento->valor_nuevo, true);
        $this->assertSame('xlsx', $nuevo['formato']);
        $this->assertSame('ventas_periodo', $nuevo['tipo']);
    }

    public function test_un_periodo_sin_datos_no_se_exporta(): void
    {
        $this->escenarioDeReportes();

        $this->actingAs($this->usuarioConReportes())
            ->get(route('reportes.exportar', [...self::PARAMETROS, 'desde' => '2026-12-01', 'hasta' => '2026-12-31', 'formato' => 'pdf']))
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'No hay datos para el período seleccionado.']);

        $this->assertDatabaseMissing('bitacora_auditoria', ['accion' => 'EXPORTAR_REPORTE']);
    }

    public function test_si_falla_la_generacion_del_archivo_se_avisa_y_se_conservan_los_parametros(): void
    {
        $this->escenarioDeReportes();

        $this->app->bind(PdfExportador::class, fn (): ExportadorReporte => new class implements ExportadorReporte
        {
            public function exportar(ReporteResultado $reporte): ArchivoExportado
            {
                throw new ExportacionFallida;
            }
        });

        $respuesta = $this->actingAs($this->usuarioConReportes())
            ->get(route('reportes.exportar', [...self::PARAMETROS, 'formato' => 'pdf']))
            ->assertRedirect()
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'No se pudo generar el archivo. Intenta de nuevo o elige otro formato.']);

        $destino = (string) $respuesta->headers->get('Location');
        $this->assertStringContainsString('/reportes/generar?', $destino);
        $this->assertStringContainsString('tipo=ventas_periodo', $destino);
        $this->assertStringContainsString('desde=2026-10-01', $destino);
        $this->assertStringNotContainsString('formato', $destino);
        $this->assertDatabaseMissing('bitacora_auditoria', ['accion' => 'EXPORTAR_REPORTE']);
    }

    public function test_un_formato_desconocido_es_un_error_de_validacion(): void
    {
        $this->actingAs($this->usuarioConReportes())
            ->get(route('reportes.exportar', [...self::PARAMETROS, 'formato' => 'pptx']))
            ->assertSessionHasErrors('formato');
    }

    public function test_la_voz_devuelve_lo_entendido_y_no_genera_nada(): void
    {
        $this->escenarioDeReportes();

        $this->actingAs($this->usuarioConReportes())
            ->postJson(route('reportes.interpretar-voz'), ['texto' => 'productos más vendidos de octubre en excel'])
            ->assertOk()
            ->assertJson([
                'tipo' => 'productos_mas_vendidos',
                'periodo' => 'mensual',
                'formato' => 'xlsx',
                'faltantes' => [],
                'es_predefinido' => true,
            ]);

        $this->actingAs($this->usuarioConReportes())
            ->postJson(route('reportes.interpretar-voz'), ['texto' => 'dame algo'])
            ->assertOk()
            ->assertJson(['tipo' => null, 'faltantes' => ['tipo', 'periodo']]);

        $this->actingAs($this->usuarioConReportes())
            ->postJson(route('reportes.interpretar-voz'), ['texto' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('texto');

        $this->assertDatabaseMissing('bitacora_auditoria', ['accion' => 'GENERAR_REPORTE']);
    }

    /**
     * @param  array<string, int|string>  $extra
     */
    private function descargar(string $formato, array $extra = []): string
    {
        return (string) $this->actingAs($this->usuarioConReportes())
            ->get(route('reportes.exportar', [...self::PARAMETROS, ...$extra, 'formato' => $formato]))
            ->assertOk()
            ->getContent();
    }

    /**
     * @return array<string, list<list<mixed>>>
     */
    private function leerXlsx(string $contenido): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($ruta, $contenido);

        $lector = new Reader;
        $lector->open($ruta);
        $hojas = [];

        foreach ($lector->getSheetIterator() as $hoja) {
            foreach ($hoja->getRowIterator() as $fila) {
                $hojas[$hoja->getName()][] = $fila->toArray();
            }
        }

        $lector->close();
        @unlink($ruta);

        return $hojas;
    }

    private function usuarioConReportes(): User
    {
        $rol = $this->rolConPermisos('Rol reportes '.uniqid(), ['reportes.ver']);

        return User::factory()->create(['rol_id' => $rol->id, 'primer_login' => false]);
    }
}
