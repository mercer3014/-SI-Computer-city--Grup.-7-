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
                    'data' => $this->nombrarObjetivos(
                        $pagina->getCollection()
                            ->map(fn (object $fila) => $this->presentar($fila))
                            ->values(),
                    ),
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
                'Producto / registro',
                'Dirección IP',
                'Descripción',
            ]);

            $nombres = $this->mapaObjetivos(
                (clone $consulta)
                    ->reorder()
                    ->select('b.tipo_entidad', 'b.entidad_id')
                    ->distinct()
                    ->get(),
            );

            foreach ($consulta->cursor() as $fila) {
                $evento = $this->presentar($fila);
                $clave = $this->claveObjetivo(
                    $evento['tipo_entidad'] ?? null,
                    $evento['entidad_id'] ?? null,
                );

                fputcsv($salida, [
                    trim($evento['fecha'].' '.$evento['hora']),
                    $evento['usuario'],
                    $evento['modulo'],
                    $evento['accion'],
                    $clave !== null ? ($nombres[$clave] ?? '') : '',
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
                $query->where(function (Builder $grupo) use ($busqueda, $operador): void {
                    $grupo->where('b.motivo', $operador, $busqueda)
                        ->orWhere('p.nombre_completo', $operador, $busqueda)
                        ->orWhere('u.email', $operador, $busqueda)
                        ->orWhere('b.modulo', $operador, $busqueda)
                        ->orWhere('b.accion', $operador, $busqueda);
                });
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
     * @param  Collection<int, array<string, mixed>>  $eventos
     * @return Collection<int, array<string, mixed>>
     */
    private function nombrarObjetivos(Collection $eventos): Collection
    {
        $nombres = $this->mapaObjetivos($eventos);

        return $eventos->map(function (array $evento) use ($nombres): array {
            $clave = $this->claveObjetivo(
                $evento['tipo_entidad'] ?? null,
                $evento['entidad_id'] ?? null,
            );
            $evento['objetivo'] = $clave !== null ? ($nombres[$clave] ?? null) : null;

            return $evento;
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>|object>  $filas
     * @return array<string, string>
     */
    private function mapaObjetivos(Collection $filas): array
    {
        $idsPorTipo = [];

        foreach ($filas as $fila) {
            $tipo = is_array($fila)
                ? ($fila['tipo_entidad'] ?? null)
                : ($fila->tipo_entidad ?? null);
            $id = is_array($fila)
                ? ($fila['entidad_id'] ?? null)
                : ($fila->entidad_id ?? null);

            if (! is_string($tipo) || $tipo === '' || $id === null || $id === '') {
                continue;
            }

            $idsPorTipo[$tipo][(int) $id] = (int) $id;
        }

        $nombres = [];

        foreach ($idsPorTipo as $tipo => $ids) {
            foreach ($this->nombresDe($tipo, array_values($ids)) as $id => $nombre) {
                $nombres[$tipo.':'.$id] = $nombre;
            }
        }

        return $nombres;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombresDe(string $tipo, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        try {
            return match ($tipo) {
                'variante_producto' => $this->nombresVariante($ids),
                'producto' => $this->nombresProducto($ids),
                'usuario' => $this->nombresUsuarioEntidad($ids),
                'personal' => $this->nombresPersonal($ids),
                'rol' => $this->nombresRol($ids),
                default => [],
            };
        } catch (Throwable $excepcion) {
            report($excepcion);

            return [];
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombresVariante(array $ids): array
    {
        $filas = DB::table('variante_producto as vp')
            ->join('producto as p', 'p.id', '=', 'vp.producto_id')
            ->leftJoin('marca as m', 'm.id', '=', 'p.marca_id')
            ->whereIn('vp.id', $ids)
            ->get(['vp.id', 'p.nombre', 'm.nombre as marca', 'vp.sku']);

        $atributos = DB::table('variante_valor_atributo as vva')
            ->join('valor_atributo as va', 'va.id', '=', 'vva.valor_atributo_id')
            ->whereIn('vva.variante_id', $ids)
            ->orderBy('va.id')
            ->get(['vva.variante_id', 'va.valor']);

        $porVariante = [];

        foreach ($atributos as $atributo) {
            $valor = trim((string) $atributo->valor);

            if ($valor !== '') {
                $porVariante[(int) $atributo->variante_id][] = $valor;
            }
        }

        $nombres = [];

        foreach ($filas as $fila) {
            $nombres[(int) $fila->id] = $this->tituloProducto(
                (string) $fila->nombre,
                $fila->marca !== null ? (string) $fila->marca : null,
                $fila->sku !== null ? (string) $fila->sku : null,
                $porVariante[(int) $fila->id] ?? [],
            );
        }

        return $nombres;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombresProducto(array $ids): array
    {
        return DB::table('producto as p')
            ->leftJoin('marca as m', 'm.id', '=', 'p.marca_id')
            ->whereIn('p.id', $ids)
            ->get(['p.id', 'p.nombre', 'm.nombre as marca'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => $this->tituloProducto(
                    (string) $fila->nombre,
                    $fila->marca !== null ? (string) $fila->marca : null,
                    null,
                    [],
                ),
            ])
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombresUsuarioEntidad(array $ids): array
    {
        return DB::table('usuario as u')
            ->leftJoin('personal as p', 'p.id', '=', 'u.personal_id')
            ->whereIn('u.id', $ids)
            ->get(['u.id', 'p.nombre_completo', 'u.email'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => $this->nombreUsuario($fila),
            ])
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombresPersonal(array $ids): array
    {
        return DB::table('personal')
            ->whereIn('id', $ids)
            ->get(['id', 'nombre_completo'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => trim((string) $fila->nombre_completo),
            ])
            ->filter()
            ->all();
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, string>
     */
    private function nombresRol(array $ids): array
    {
        return DB::table('rol')
            ->whereIn('id', $ids)
            ->get(['id', 'nombre'])
            ->mapWithKeys(fn (object $fila): array => [
                (int) $fila->id => trim((string) $fila->nombre),
            ])
            ->filter()
            ->all();
    }

    /**
     * @param  list<string>  $atributos
     */
    private function tituloProducto(string $nombre, ?string $marca, ?string $sku, array $atributos): string
    {
        $nombre = trim($nombre);
        $marca = trim((string) $marca);
        $sku = trim((string) $sku);

        if ($marca !== '' && $nombre !== '' && ! str_contains(mb_strtolower($nombre), mb_strtolower($marca))) {
            $nombre = $marca.' '.$nombre;
        } elseif ($nombre === '' && $marca !== '') {
            $nombre = $marca;
        }

        $attrs = array_values(array_filter($atributos, fn (string $valor): bool => trim($valor) !== ''));

        if ($attrs !== []) {
            $nombre = trim($nombre.' · '.implode(' / ', $attrs));
        }

        if ($nombre !== '') {
            return $nombre;
        }

        return $sku !== '' ? $sku : 'Producto';
    }

    private function claveObjetivo(mixed $tipo, mixed $id): ?string
    {
        if (! is_string($tipo) || $tipo === '' || $id === null || $id === '') {
            return null;
        }

        return $tipo.':'.(int) $id;
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
            'objetivo' => null,
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
