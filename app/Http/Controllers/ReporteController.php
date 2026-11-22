<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportarReporteRequest;
use App\Http\Requests\GenerarReporteRequest;
use App\Models\User;
use App\Services\BitacoraService;
use App\Services\Reportes\CatalogoReportes;
use App\Services\Reportes\Exportacion\ExportacionFallida;
use App\Services\Reportes\Exportacion\Exportadores;
use App\Services\Reportes\InterpreteComandoVoz;
use App\Services\Reportes\ServicioReportes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU16: reportes de gestión con formulario o voz, y exportación a PDF, Excel y Word.
 */
class ReporteController extends Controller
{
    public function __construct(private ServicioReportes $reportes) {}

    public function index(Request $request): Response
    {
        return $this->pantalla($request, null);
    }

    public function generar(GenerarReporteRequest $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $resultado = $this->reportes->generar($request->claveReporte(), $request->periodo(), $request->filtros(), $usuario);

        BitacoraService::registrar(
            'Reportes',
            'GENERAR_REPORTE',
            'reporte',
            null,
            null,
            $resultado->parametros,
            'Reporte '.$resultado->titulo,
        );

        return $this->pantalla($request, $resultado->aArray());
    }

    public function exportar(ExportarReporteRequest $request): HttpResponse|RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $resultado = $this->reportes->generar($request->claveReporte(), $request->periodo(), $request->filtros(), $usuario);
        $volver = redirect()->route('reportes.generar', $request->parametros());

        if ($resultado->vacio()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'No hay datos para el período seleccionado.']);

            return $volver;
        }

        try {
            $archivo = Exportadores::para($request->formato())->exportar($resultado);
        } catch (ExportacionFallida $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return $volver;
        }

        BitacoraService::registrar(
            'Reportes',
            'EXPORTAR_REPORTE',
            'reporte',
            null,
            null,
            [...$resultado->parametros, 'formato' => $request->formato()],
            'Exportación de '.$resultado->titulo.' a '.strtoupper($request->formato()),
        );

        return response($archivo->contenido, 200, [
            'Content-Type' => $archivo->mime,
            'Content-Disposition' => 'attachment; filename="'.$archivo->nombre.'"',
            'Content-Length' => (string) strlen($archivo->contenido),
        ]);
    }

    /**
     * La voz solo llena el formulario: devuelve lo que entendió y lo que falta.
     */
    public function interpretarVoz(Request $request, InterpreteComandoVoz $interprete): JsonResponse
    {
        $datos = $request->validate([
            'texto' => ['required', 'string', 'max:500'],
        ], [
            'texto.required' => 'No se entendió nada. Probá de nuevo o usá el formulario.',
        ]);

        return response()->json($interprete->interpretar($datos['texto']));
    }

    /**
     * @param  array<string, mixed>|null  $resultado
     */
    private function pantalla(Request $request, ?array $resultado): Response
    {
        $usuario = $request->user();

        return Inertia::render('reportes/Index', [
            'catalogo' => collect(CatalogoReportes::todos())
                ->map(fn (array $reporte, string $clave): array => [
                    'clave' => $clave,
                    'titulo' => $reporte['titulo'],
                    'descripcion' => $reporte['descripcion'],
                    'filtros' => $reporte['filtros'],
                ])
                ->values(),
            'opciones' => [
                'categorias' => $this->opciones('categoria', 'nombre', ['activo' => true]),
                'marcas' => $this->opciones('marca', 'nombre', ['estado' => 'ACTIVO']),
                'proveedores' => $this->opciones('proveedor', 'razon_social'),
                'categorias_gasto' => $this->opciones('categoria_gasto', 'nombre', ['estado' => 'ACTIVO']),
                'metodos_pago' => $this->opciones('metodo_pago', 'nombre', ['estado' => 'ACTIVO']),
            ],
            'parametros' => $resultado['parametros'] ?? null,
            'resultado' => $resultado,
            'hoy' => now((string) config('app.timezone'))->toDateString(),
            'puedeAsistente' => $usuario instanceof User && $usuario->puedeUsarAsistente(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $donde
     * @return array<int, array{id: int, nombre: string}>
     */
    private function opciones(string $tabla, string $columnaNombre, array $donde = []): array
    {
        return DB::table($tabla)
            ->where($donde)
            ->orderBy($columnaNombre)
            ->get(['id', $columnaNombre])
            ->map(fn (object $fila): array => ['id' => (int) $fila->id, 'nombre' => (string) $fila->{$columnaNombre}])
            ->values()
            ->all();
    }
}
