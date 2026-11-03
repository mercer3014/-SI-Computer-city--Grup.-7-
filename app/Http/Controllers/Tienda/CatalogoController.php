<?php

namespace App\Http\Controllers\Tienda;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\EventoProducto;
use App\Models\ImagenProducto;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\VarianteProducto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * CU18: catálogo público. No requiere sesión.
 */
class CatalogoController extends Controller
{
    private const ORDENES = ['relevancia', 'precio_asc', 'precio_desc', 'populares', 'nombre'];

    public function index(Request $request): Response
    {
        $filtros = $this->filtros($request);

        try {
            $productos = $this->consulta($filtros)
                ->paginate(12)
                ->withQueryString();

            $datos = [
                'productos' => [
                    'data' => collect($productos->items())
                        ->map(fn (Producto $p) => $this->tarjeta($p))
                        ->values(),
                    'meta' => [
                        'current_page' => $productos->currentPage(),
                        'last_page' => $productos->lastPage(),
                        'total' => $productos->total(),
                    ],
                ],
                'opciones' => $this->opciones(),
                'destacados' => $filtros === $this->filtros(new Request) ? $this->destacados() : [],
                'error' => false,
            ];
        } catch (Throwable $e) {
            report($e);

            $datos = [
                'productos' => ['data' => [], 'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 0]],
                'opciones' => ['categorias' => [], 'marcas' => [], 'precio' => ['min' => 0, 'max' => 0]],
                'destacados' => [],
                'error' => true,
            ];
        }

        return Inertia::render('tienda/Catalogo', [
            ...$datos,
            'filtros' => $filtros,
            'tienda' => $this->tienda(),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $producto = Producto::query()
            ->with([
                'categoria:id,nombre,slug',
                'marca:id,nombre,slug',
                'imagenes',
                'variantes' => fn ($q) => $q->where('estado', 'ACTIVO')->with('valores.atributo')->orderBy('precio_venta'),
            ])
            ->where('slug', $slug)
            ->where('estado', 'ACTIVO')
            ->firstOrFail();

        EventoProducto::query()->create([
            'producto_id' => $producto->id,
            'tipo_evento' => EventoProducto::VISTA,
            'session_id' => $request->hasSession() ? substr($request->session()->getId(), 0, 100) : null,
            'fecha_hora' => now(),
        ]);

        $relacionados = $this->consulta(['categoria' => $producto->categoria?->slug ?? ''] + $this->filtros(new Request))
            ->where('producto.id', '<>', $producto->id)
            ->limit(4)
            ->get()
            ->map(fn (Producto $p) => $this->tarjeta($p))
            ->values();

        return Inertia::render('tienda/Producto', [
            'producto' => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'slug' => $producto->slug,
                'descripcion' => $producto->descripcion,
                'categoria' => $producto->categoria?->only(['nombre', 'slug']),
                'marca' => $producto->marca?->only(['nombre', 'slug']),
                'visitas' => EventoProducto::query()
                    ->where('producto_id', $producto->id)
                    ->where('tipo_evento', EventoProducto::VISTA)
                    ->count(),
                'imagenes' => $producto->imagenes
                    ->map(fn (ImagenProducto $img) => [
                        'url' => $img->url_webp,
                        'alt' => $img->texto_alternativo ?: $producto->nombre,
                    ])
                    ->values(),
                'variantes' => $producto->variantes
                    ->map(fn (VarianteProducto $v) => [
                        'id' => $v->id,
                        'sku' => $v->sku,
                        'etiqueta' => $v->etiquetaAtributos() ?: 'Única',
                        'precio' => (float) $v->precio_venta,
                        'precio_anterior' => $v->precio_anterior !== null && $v->precio_anterior > $v->precio_venta
                            ? (float) $v->precio_anterior
                            : null,
                        'stock' => max(0, $v->stock_actual),
                        'disponibilidad' => $this->disponibilidad($v->stock_actual, $v->stock_minimo),
                    ])
                    ->values(),
            ],
            'relacionados' => $relacionados,
            'tienda' => $this->tienda(),
        ]);
    }

    /**
     * @return array{q: string, categoria: string, marca: string, precio_min: string, precio_max: string, orden: string, disponibles: bool}
     */
    private function filtros(Request $request): array
    {
        $orden = $request->string('orden')->value();

        return [
            'q' => mb_substr($request->string('q')->trim()->value(), 0, 100),
            'categoria' => $request->string('categoria')->value(),
            'marca' => $request->string('marca')->value(),
            'precio_min' => is_numeric($request->input('precio_min')) ? (string) max(0, (float) $request->input('precio_min')) : '',
            'precio_max' => is_numeric($request->input('precio_max')) ? (string) max(0, (float) $request->input('precio_max')) : '',
            'orden' => in_array($orden, self::ORDENES, true) ? $orden : 'relevancia',
            'disponibles' => $request->boolean('disponibles'),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Builder<Producto>
     */
    private function consulta(array $filtros): Builder
    {
        $like = DB::getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $precio = VarianteProducto::query()
            ->selectRaw('min(precio_venta)')
            ->whereColumn('variante_producto.producto_id', 'producto.id')
            ->where('variante_producto.estado', 'ACTIVO');

        $stock = VarianteProducto::query()
            ->selectRaw('coalesce(sum(case when stock_actual > 0 then stock_actual else 0 end), 0)')
            ->whereColumn('variante_producto.producto_id', 'producto.id')
            ->where('variante_producto.estado', 'ACTIVO');

        $stockMinimo = VarianteProducto::query()
            ->selectRaw('coalesce(sum(stock_minimo), 0)')
            ->whereColumn('variante_producto.producto_id', 'producto.id')
            ->where('variante_producto.estado', 'ACTIVO');

        $visitas = EventoProducto::query()
            ->selectRaw('count(*)')
            ->whereColumn('evento_producto.producto_id', 'producto.id')
            ->where('tipo_evento', EventoProducto::VISTA);

        $query = Producto::query()
            ->select('producto.*')
            ->selectSub($precio, 'precio_desde')
            ->selectSub($stock, 'stock_disponible')
            ->selectSub($stockMinimo, 'stock_minimo_total')
            ->selectSub($visitas, 'visitas')
            ->with(['categoria:id,nombre,slug', 'marca:id,nombre,slug', 'imagenes'])
            ->where('producto.estado', 'ACTIVO')
            ->whereHas('categoria', fn ($c) => $c->where('activo', true))
            ->whereHas('variantes', fn ($v) => $v->where('estado', 'ACTIVO')->where('precio_venta', '>', 0));

        if ($filtros['q'] !== '') {
            $busqueda = '%'.addcslashes($filtros['q'], '%_\\').'%';
            $query->where(function (Builder $sub) use ($busqueda, $like): void {
                $sub->where('producto.nombre', $like, $busqueda)
                    ->orWhere('producto.descripcion', $like, $busqueda)
                    ->orWhereHas('marca', fn ($m) => $m->where('nombre', $like, $busqueda))
                    ->orWhereHas('categoria', fn ($c) => $c->where('nombre', $like, $busqueda))
                    ->orWhereHas('variantes', fn ($v) => $v->where('sku', $like, $busqueda));
            });
        }

        if ($filtros['categoria'] !== '') {
            $query->whereHas('categoria', fn ($c) => $c->where('slug', $filtros['categoria']));
        }

        if ($filtros['marca'] !== '') {
            $query->whereHas('marca', fn ($m) => $m->where('slug', $filtros['marca']));
        }

        if ($filtros['precio_min'] !== '') {
            $query->whereRaw('('.$precio->toSql().') >= cast(? as decimal(12,2))', [...$precio->getBindings(), (float) $filtros['precio_min']]);
        }

        if ($filtros['precio_max'] !== '') {
            $query->whereRaw('('.$precio->toSql().') <= cast(? as decimal(12,2))', [...$precio->getBindings(), (float) $filtros['precio_max']]);
        }

        if ($filtros['disponibles']) {
            $query->whereRaw('('.$stock->toSql().') > 0', $stock->getBindings());
        }

        match ($filtros['orden']) {
            'precio_asc' => $query->orderBy('precio_desde')->orderBy('producto.nombre'),
            'precio_desc' => $query->orderByDesc('precio_desde')->orderBy('producto.nombre'),
            'populares' => $query->orderByDesc('visitas')->orderBy('producto.nombre'),
            'nombre' => $query->orderBy('producto.nombre'),
            default => $query
                ->orderByRaw('case when ('.$stock->toRawSql().') > 0 then 0 else 1 end')
                ->orderByDesc('visitas')
                ->orderBy('producto.nombre'),
        };

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function tarjeta(Producto $p): array
    {
        $imagen = $p->imagenPrincipal();
        $stock = (int) ($p->getAttribute('stock_disponible') ?? 0);

        return [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'slug' => $p->slug,
            'categoria' => $p->categoria?->nombre,
            'marca' => $p->marca?->nombre,
            'precio_desde' => round((float) $p->getAttribute('precio_desde'), 2),
            'stock' => $stock,
            'disponibilidad' => $this->disponibilidad($stock, (int) ($p->getAttribute('stock_minimo_total') ?? 0)),
            'imagen' => $imagen?->url_webp,
            'imagen_alt' => $imagen?->texto_alternativo ?: $p->nombre,
        ];
    }

    private function disponibilidad(int $stock, int $minimo): string
    {
        return match (true) {
            $stock <= 0 => 'AGOTADO',
            $stock <= max(1, $minimo) => 'ULTIMAS',
            default => 'DISPONIBLE',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function destacados(): array
    {
        return $this->consulta(['orden' => 'populares', 'disponibles' => true] + $this->filtros(new Request))
            ->whereHas('imagenes')
            ->limit(4)
            ->get()
            ->map(fn (Producto $p) => $this->tarjeta($p))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function opciones(): array
    {
        $activos = fn ($p) => $p->where('estado', 'ACTIVO');

        $precios = VarianteProducto::query()
            ->where('estado', 'ACTIVO')
            ->where('precio_venta', '>', 0)
            ->whereHas('producto', $activos)
            ->selectRaw('min(precio_venta) as minimo, max(precio_venta) as maximo')
            ->first();

        return [
            'categorias' => Categoria::query()
                ->where('activo', true)
                ->whereHas('productos', $activos)
                ->withCount(['productos' => $activos])
                ->orderBy('orden_visualizacion')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'slug'])
                ->map(fn (Categoria $c) => ['nombre' => $c->nombre, 'slug' => $c->slug, 'total' => $c->productos_count])
                ->values(),
            'marcas' => Marca::query()
                ->where('estado', 'ACTIVO')
                ->whereHas('productos', $activos)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'slug'])
                ->map(fn (Marca $m) => ['nombre' => $m->nombre, 'slug' => $m->slug])
                ->values(),
            'precio' => [
                'min' => (float) floor((float) ($precios?->minimo ?? 0)),
                'max' => (float) ceil((float) ($precios?->maximo ?? 0)),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function tienda(): array
    {
        try {
            $config = DB::table('configuracion_tienda')->orderBy('id')->first();
            $horarios = $config === null
                ? collect()
                : DB::table('horario_atencion')
                    ->where('configuracion_tienda_id', $config->id)
                    ->orderBy('dia_semana')
                    ->get();
        } catch (Throwable) {
            $config = null;
            $horarios = collect();
        }

        $dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

        return [
            'nombre' => $config->nombre_tienda ?? 'Computer City',
            'centro_comercial' => $config->centro_comercial ?? null,
            'direccion' => $config->direccion ?? null,
            'telefono' => $config->telefono ?? null,
            'whatsapp' => $config->whatsapp ?? null,
            'email' => $config->email ?? null,
            'maps' => $config->google_maps_url ?? null,
            'anuncio' => $config->anuncio ?? null,
            'moneda' => $config->moneda ?? 'BOB',
            'horarios' => $horarios
                ->map(fn (object $h) => [
                    'dia' => $dias[(int) $h->dia_semana] ?? (string) $h->dia_semana,
                    'cerrado' => (bool) $h->cerrado,
                    'horario' => $h->cerrado || $h->hora_apertura === null
                        ? null
                        : substr((string) $h->hora_apertura, 0, 5).' – '.substr((string) $h->hora_cierre, 0, 5),
                ])
                ->values(),
        ];
    }
}
