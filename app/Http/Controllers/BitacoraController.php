<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class BitacoraController extends Controller
{
    private const POR_PAGINA = 10;

    /**
     * CU-05: consulta de solo lectura de la bitácora de auditoría.
     */
    public function index(Request $request): Response
    {
        $this->autorizar($request);

        $filtros = $this->filtros($request);

        try {
            $pagina = $this->consulta($filtros)
                ->paginate(self::POR_PAGINA)
                ->withQueryString();

            return Inertia::render('Bitacora', [
                'eventos' => [
                    'data' => $pagina->getCollection()
                        ->map(fn (object $fila) => $this->presentar($fila))
                        ->values(),
                    'meta' => [
                        'current_page' => $pagina->currentPage(),
                        'last_page' => max(1, $pagina->lastPage()),
                        'per_page' => $pagina->perPage(),
                        'total' => $pagina->total(),
                        'from' => $pagina->firstItem(),
                        'to' => $pagina->lastItem(),
                    ],
                ],
                'filtros' => $filtros,
                'opciones' => $this->opciones(),
                'error' => $request->session()->pull('bitacora_error'),
            ]);
        } catch (Throwable $excepcion) {
            report($excepcion);

            return Inertia::render('Bitacora', [
                'eventos' => [
                    'data' => [],
                    'meta' => [
                        'current_page' => 1,
                        'last_page' => 1,
                        'per_page' => self::POR_PAGINA,
                        'total' => 0,
                        'from' => null,
                        'to' => null,
                    ],
                ],
                'filtros' => $filtros,
                'opciones' => [
                    'usuarios' => [],
                    'modulos' => [],
                    'acciones' => [],
                ],
                'error' => 'No se pudo leer la bitácora. Revisá la conexión con la base de datos.',
            ]);
        }
    }

    public function exportar(Request $request): StreamedResponse|RedirectResponse
    {
        $this->autorizar($request);

        $filtros = $this->filtros($request);

        try {
            $consulta = $this->consulta($filtros);
            (clone $consulta)->limit(1)->get();
        } catch (Throwable $excepcion) {
            report($excepcion);

            return $this->exportacionFallida($filtros);
        }

        $nombre = 'bitacora-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($consulta): void {
            $salida = fopen('php://output', 'w');

            if ($salida === false) {
                return;
            }

            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, [
                'Fecha/hora',
                'Usuario',
                'Módulo',
                'Acción',
                'Dirección IP',
                'Descripción',
            ]);

            foreach ($consulta->cursor() as $fila) {
                $evento = $this->presentar($fila);

                fputcsv($salida, [
                    trim($evento['fecha'].' '.$evento['hora']),
                    $evento['usuario'],
                    $evento['modulo'],
                    $evento['accion'],
                    $evento['direccion_ip'] ?? '',
                    $evento['descripcion'],
                ]);
            }

            fclose($salida);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function autorizar(Request $request): void
    {
        $usuario = $request->user();

        abort_unless(
            $usuario instanceof User && $this->puedeConsultar($usuario),
            403,
            'No tenés permiso para consultar la bitácora.',
        );
    }

    private function puedeConsultar(User $usuario): bool
    {
        return $usuario->tienePermiso('bitacora.ver');
    }

    /**
     * @return array{q: string, usuario: string, modulo: string, accion: string, desde: string, hasta: string}
     */
    private function filtros(Request $request): array
    {
        $datos = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'usuario' => ['nullable', 'integer', 'min:1'],
            'modulo' => ['nullable', 'string', 'max:100'],
            'accion' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $desde = $datos['desde'] ?? '';
        $hasta = $datos['hasta'] ?? '';

        if ($desde !== '' && $hasta !== '' && $hasta < $desde) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        return [
            'q' => trim((string) ($datos['q'] ?? '')),
            'usuario' => isset($datos['usuario']) ? (string) $datos['usuario'] : '',
            'modulo' => trim((string) ($datos['modulo'] ?? '')),
            'accion' => trim((string) ($datos['accion'] ?? '')),
            'desde' => $desde,
            'hasta' => $hasta,
        ];
    }

    /**
     * @param  array{q: string, usuario: string, modulo: string, accion: string, desde: string, hasta: string}  $filtros
     */
    private function consulta(array $filtros): Builder
    {
        $operador = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $busqueda = $filtros['q'] === ''
            ? ''
            : '%'.addcslashes($filtros['q'], '%_\\').'%';

        return DB::table('bitacora_auditoria as b')
            ->leftJoin('usuario as u', 'u.id', '=', 'b.usuario_id')
            ->leftJoin('personal as p', 'p.id', '=', 'u.personal_id')
            ->select([
                'b.id',
                'b.fecha_hora',
                'b.usuario_id',
                'b.modulo',
                'b.accion',
                'b.tipo_entidad',
                'b.entidad_id',
                'b.valor_anterior',
                'b.valor_nuevo',
                'b.motivo',
                'b.direccion_ip',
                'p.nombre_completo',
                'u.email',
            ])
            ->when($busqueda !== '', function (Builder $query) use ($busqueda, $operador): void {
                $query->where('b.motivo', $operador, $busqueda);
            })
            ->when($filtros['usuario'] !== '', function (Builder $query) use ($filtros): void {
                $query->where('b.usuario_id', $filtros['usuario']);
            })
            ->when($filtros['modulo'] !== '', function (Builder $query) use ($filtros): void {
                $query->where('b.modulo', $filtros['modulo']);
            })
            ->when($filtros['accion'] !== '', function (Builder $query) use ($filtros): void {
                $query->where('b.accion', $filtros['accion']);
            })
            ->when($filtros['desde'] !== '', function (Builder $query) use ($filtros): void {
                $query->whereDate('b.fecha_hora', '>=', $filtros['desde']);
            })
            ->when($filtros['hasta'] !== '', function (Builder $query) use ($filtros): void {
                $query->whereDate('b.fecha_hora', '<=', $filtros['hasta']);
            })
            ->orderByDesc('b.fecha_hora')
            ->orderByDesc('b.id');
    }

    /**
     * @return array{
     *     usuarios: Collection<int, array{id: int, nombre: string}>,
     *     modulos: Collection<int, string>,
     *     acciones: Collection<int, string>
     * }
     */
    private function opciones(): array
    {
        $usuarios = DB::table('bitacora_auditoria as b')
            ->join('usuario as u', 'u.id', '=', 'b.usuario_id')
            ->leftJoin('personal as p', 'p.id', '=', 'u.personal_id')
            ->select('b.usuario_id', 'p.nombre_completo', 'u.email')
            ->distinct()
            ->orderBy('p.nombre_completo')
            ->get()
            ->map(fn (object $fila): array => [
                'id' => (int) $fila->usuario_id,
                'nombre' => $this->nombreUsuario($fila),
            ])
            ->unique('id')
            ->values();

        return [
            'usuarios' => $usuarios,
            'modulos' => DB::table('bitacora_auditoria')
                ->whereNotNull('modulo')
                ->distinct()
                ->orderBy('modulo')
                ->pluck('modulo')
                ->values(),
            'acciones' => DB::table('bitacora_auditoria')
                ->whereNotNull('accion')
                ->distinct()
                ->orderBy('accion')
                ->pluck('accion')
                ->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(object $fila): array
    {
        $momento = $this->momento($fila->fecha_hora ?? null);

        return [
            'id' => (int) $fila->id,
            'fecha' => $momento?->format('d/m/Y') ?? '',
            'hora' => $momento?->format('H:i:s') ?? '',
            'usuario' => $this->nombreUsuario($fila),
            'modulo' => (string) $fila->modulo,
            'accion' => (string) $fila->accion,
            'direccion_ip' => $fila->direccion_ip !== null && $fila->direccion_ip !== ''
                ? (string) $fila->direccion_ip
                : null,
            'descripcion' => $this->descripcion($fila),
            'tipo_entidad' => $fila->tipo_entidad !== null ? (string) $fila->tipo_entidad : null,
            'entidad_id' => $fila->entidad_id !== null ? (int) $fila->entidad_id : null,
            'valor_anterior' => $this->decodificar($fila->valor_anterior ?? null),
            'valor_nuevo' => $this->decodificar($fila->valor_nuevo ?? null),
        ];
    }

    private function momento(mixed $valor): ?CarbonInterface
    {
        if ($valor instanceof CarbonInterface) {
            return $valor->timezone(config('app.timezone'));
        }

        if (! is_string($valor) || trim($valor) === '') {
            return null;
        }

        return Carbon::parse($valor)->timezone(config('app.timezone'));
    }

    private function nombreUsuario(object $fila): string
    {
        $nombre = trim((string) ($fila->nombre_completo ?? ''));

        if ($nombre !== '') {
            return $nombre;
        }

        $email = trim((string) ($fila->email ?? ''));

        if ($email === '') {
            return 'Sistema';
        }

        $local = strstr($email, '@', true);

        return $local !== false && $local !== '' ? $local : $email;
    }

    private function descripcion(object $fila): string
    {
        $motivo = trim((string) ($fila->motivo ?? ''));

        if ($motivo !== '') {
            return $motivo;
        }

        $accion = str_replace('_', ' ', strtolower((string) $fila->accion));
        $entidad = $fila->tipo_entidad ? ' · '.$fila->tipo_entidad : '';

        return ucfirst($accion).$entidad;
    }

    private function decodificar(mixed $valor): mixed
    {
        if (! is_string($valor) || $valor === '') {
            return $valor;
        }

        $decodificado = json_decode($valor, true);

        return json_last_error() === JSON_ERROR_NONE ? $decodificado : $valor;
    }

    /**
     * @param  array{q: string, usuario: string, modulo: string, accion: string, desde: string, hasta: string}  $filtros
     */
    private function exportacionFallida(array $filtros): RedirectResponse
    {
        $parametros = array_filter(
            $filtros,
            fn (string $valor): bool => $valor !== '',
        );

        return redirect()
            ->route('bitacora.index', $parametros)
            ->with(
                'bitacora_error',
                'No se pudo exportar la bitácora. Revisá la conexión con la base de datos.',
            );
    }
}
