<?php

namespace App\Http\Controllers;

use App\Http\Requests\OcultarCalificacionRequest;
use App\Models\Calificacion;
use App\Models\User;
use App\Services\Calificaciones\CalificacionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * CU24: moderación de calificaciones. Las rutas llevan `permiso:calificaciones.gestionar`.
 */
class CalificacionController extends Controller
{
    private const POR_PAGINA = 15;

    public function __construct(private CalificacionService $calificaciones) {}

    public function index(Request $request): Response
    {
        $filtros = $this->filtros($request);

        $pagina = $this->consulta($filtros)
            ->with(['producto:id,nombre', 'cliente:id,nombre', 'pedido:id,numero_pedido', 'moderador.personal:id,nombre_completo'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA)
            ->withQueryString();

        $zona = (string) config('app.timezone');

        return Inertia::render('calificaciones/Index', [
            'calificaciones' => [
                'data' => $pagina->getCollection()
                    ->map(fn (Calificacion $c): array => [
                        'id' => $c->id,
                        'producto' => $c->producto->nombre,
                        'producto_id' => $c->producto_id,
                        'cliente' => $c->cliente->nombre,
                        'pedido' => $c->pedido->numero_pedido,
                        'puntuacion' => $c->puntuacion,
                        'comentario' => $c->comentario,
                        'estado' => $c->estado,
                        'motivo_ocultamiento' => $c->motivo_ocultamiento,
                        'fecha' => $c->fecha?->timezone($zona)->format('d/m/Y H:i'),
                        'fecha_moderacion' => $c->fecha_moderacion?->timezone($zona)->format('d/m/Y H:i'),
                        'moderador' => $c->moderador === null ? null : ($c->moderador->name !== '' ? $c->moderador->name : $c->moderador->email),
                    ])
                    ->values(),
                'meta' => [
                    'current_page' => $pagina->currentPage(),
                    'last_page' => max(1, $pagina->lastPage()),
                    'total' => $pagina->total(),
                    'from' => $pagina->firstItem(),
                    'to' => $pagina->lastItem(),
                ],
            ],
            'kpis' => $this->kpis(),
            'filtros' => $filtros,
            'promedio' => $this->calificaciones->promedioGeneral(),
        ]);
    }

    public function publicar(Request $request, Calificacion $calificacion): RedirectResponse
    {
        $this->calificaciones->publicar($calificacion, $this->moderador($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Calificación publicada en el catálogo.']);

        return back();
    }

    public function ocultar(OcultarCalificacionRequest $request, Calificacion $calificacion): RedirectResponse
    {
        $this->calificaciones->ocultar($calificacion, $this->moderador($request), (string) $request->validated('motivo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Calificación oculta. Ya no cuenta en el promedio.']);

        return back();
    }

    /**
     * @return array{estado: string, producto: string, puntuacion: string, desde: string, hasta: string}
     */
    private function filtros(Request $request): array
    {
        $estado = $request->string('estado')->value();
        $puntuacion = $request->string('puntuacion')->value();

        return [
            'estado' => in_array($estado, Calificacion::ESTADOS, true) ? $estado : '',
            'producto' => mb_substr($request->string('producto')->trim()->value(), 0, 100),
            'puntuacion' => in_array($puntuacion, ['1', '2', '3', '4', '5'], true) ? $puntuacion : '',
            'desde' => $this->fecha($request->string('desde')->value()),
            'hasta' => $this->fecha($request->string('hasta')->value()),
        ];
    }

    private function fecha(string $valor): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return '';
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)?->format('Y-m-d') === $valor ? $valor : '';
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @param  array{estado: string, producto: string, puntuacion: string, desde: string, hasta: string}  $filtros
     * @return Builder<Calificacion>
     */
    private function consulta(array $filtros): Builder
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $zona = (string) config('app.timezone');
        $consulta = Calificacion::query();

        if ($filtros['estado'] !== '') {
            $consulta->where('estado', $filtros['estado']);
        }

        if ($filtros['puntuacion'] !== '') {
            $consulta->where('puntuacion', (int) $filtros['puntuacion']);
        }

        if ($filtros['producto'] !== '') {
            $busqueda = '%'.addcslashes($filtros['producto'], '%_\\').'%';
            $consulta->whereHas('producto', fn ($p) => $p->where('nombre', $like, $busqueda));
        }

        if ($filtros['desde'] !== '') {
            $consulta->where('fecha', '>=', Carbon::parse($filtros['desde'], $zona)->startOfDay());
        }

        if ($filtros['hasta'] !== '') {
            $consulta->where('fecha', '<=', Carbon::parse($filtros['hasta'], $zona)->endOfDay());
        }

        return $consulta;
    }

    /**
     * @return array{total: int, pendientes: int, publicadas: int, ocultas: int}
     */
    private function kpis(): array
    {
        $conteo = Calificacion::query()
            ->selectRaw('estado, count(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return [
            'total' => (int) $conteo->sum(),
            'pendientes' => (int) ($conteo[Calificacion::PENDIENTE] ?? 0),
            'publicadas' => (int) ($conteo[Calificacion::PUBLICADA] ?? 0),
            'ocultas' => (int) ($conteo[Calificacion::OCULTA] ?? 0),
        ];
    }

    private function moderador(Request $request): User
    {
        $usuario = $request->user();

        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
