<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\ImagenProducto;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\VarianteProducto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductoController extends Controller
{
    // ── Frontera: Listado / Índice ──────────────────────────────────────────

    /**
     * CU07-FR1: Listar productos con filtros y paginación.
     * Acceso: Administrador (gestión completa) y Vendedor (solo lectura).
     */
    public function index(Request $request): Response
    {
        $this->authorizeAny(['inventario.ver', 'productos.gestionar'], $request);

        $query = Producto::query()
            ->with(['categoria:id,nombre', 'marca:id,nombre', 'imagenes'])
            ->withCount('variantes')
            ->withSum('variantes as stock_total', 'stock_actual');

        // ── Filtros ──
        if ($q = $request->string('q')->trim()->value()) {
            $query->where(function ($sub) use ($q) {
                $sub->where('nombre', 'ilike', "%{$q}%")
                    ->orWhere('slug', 'ilike', "%{$q}%")
                    ->orWhere('descripcion', 'ilike', "%{$q}%");
            });
        }

        if ($categoriaId = $request->integer('categoria')) {
            $query->where('categoria_id', $categoriaId);
        }

        if ($marcaId = $request->integer('marca')) {
            $query->where('marca_id', $marcaId);
        }

        if ($estado = $request->string('estado')->value()) {
            $query->where('estado', $estado);
        }

        $productos = $query
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Producto $p) => $this->formatProducto($p));

        return Inertia::render('Productos', [
            'productos' => [
                'data' => $productos->items(),
                'meta' => [
                    'current_page' => $productos->currentPage(),
                    'last_page'    => $productos->lastPage(),
                    'total'        => $productos->total(),
                    'from'         => $productos->firstItem(),
                    'to'           => $productos->lastItem(),
                ],
            ],
            'filtros' => [
                'q'        => $request->string('q')->trim()->value(),
                'categoria' => $request->string('categoria')->value(),
                'marca'     => $request->string('marca')->value(),
                'estado'    => $request->string('estado')->value(),
            ],
            'opciones' => [
                'categorias' => Categoria::query()
                    ->where('activo', true)
                    ->orderBy('nombre')
                    ->get(['id', 'nombre', 'slug']),
                'marcas' => Marca::query()
                    ->where('estado', 'ACTIVO')
                    ->orderBy('nombre')
                    ->get(['id', 'nombre', 'slug']),
            ],
            'puedeGestionar' => $request->user()?->tienePermiso('productos.gestionar') ?? false,
        ]);
    }

    // ── Frontera: Crear ─────────────────────────────────────────────────────

    /**
     * CU07-FR2: Registrar nuevo producto.
     * Acceso: solo Administrador (productos.gestionar).
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('productos.gestionar', $request);

        $datos = $request->validate([
            'nombre'      => ['required', 'string', 'max:200'],
            'slug'        => ['nullable', 'string', 'max:250', 'unique:producto,slug'],
            'categoria_id' => ['required', 'integer', 'exists:categoria,id'],
            'marca_id'    => ['nullable', 'integer', 'exists:marca,id'],
            'descripcion' => ['nullable', 'string'],
            'estado'      => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ], [
            'slug.unique' => 'Ya existe un producto con ese slug. Usá uno diferente o dejá el campo vacío para generarlo automáticamente.',
        ]);

        $slug = $this->resolverSlug($datos['slug'] ?? null, $datos['nombre']);

        DB::transaction(function () use ($datos, $slug) {
            Producto::query()->create([
                'nombre'      => $datos['nombre'],
                'slug'        => $slug,
                'categoria_id' => $datos['categoria_id'],
                'marca_id'    => $datos['marca_id'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
                'estado'      => $datos['estado'],
            ]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto creado correctamente.']);

        return back();
    }

    // ── Frontera: Editar ────────────────────────────────────────────────────

    /**
     * CU07-FR3: Actualizar producto existente.
     * Acceso: solo Administrador (productos.gestionar).
     */
    public function update(Request $request, Producto $producto): RedirectResponse
    {
        $this->authorize('productos.gestionar', $request);

        $datos = $request->validate([
            'nombre'      => ['required', 'string', 'max:200'],
            'slug'        => ['nullable', 'string', 'max:250', Rule::unique('producto', 'slug')->ignore($producto->id)],
            'categoria_id' => ['required', 'integer', 'exists:categoria,id'],
            'marca_id'    => ['nullable', 'integer', 'exists:marca,id'],
            'descripcion' => ['nullable', 'string'],
            'estado'      => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ], [
            'slug.unique' => 'Ya existe un producto con ese slug. Usá uno diferente o dejá el campo vacío para generarlo automáticamente.',
        ]);

        $slug = $this->resolverSlug($datos['slug'] ?? null, $datos['nombre'], $producto->slug);

        DB::transaction(function () use ($producto, $datos, $slug) {
            $producto->forceFill([
                'nombre'      => $datos['nombre'],
                'slug'        => $slug,
                'categoria_id' => $datos['categoria_id'],
                'marca_id'    => $datos['marca_id'] ?? null,
                'descripcion' => $datos['descripcion'] ?? null,
                'estado'      => $datos['estado'],
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Producto actualizado correctamente.']);

        return back();
    }

    // ── Frontera: Cambiar Estado ────────────────────────────────────────────

    /**
     * CU07-FR4: Cambiar estado ACTIVO / INACTIVO (baja lógica).
     * Acceso: solo Administrador (productos.gestionar).
     */
    public function estado(Request $request, Producto $producto): RedirectResponse
    {
        $this->authorize('productos.gestionar', $request);

        $datos = $request->validate([
            'estado' => ['required', Rule::in(['ACTIVO', 'INACTIVO'])],
        ]);

        $producto->forceFill(['estado' => $datos['estado']])->save();

        $mensaje = $datos['estado'] === 'INACTIVO'
            ? 'Producto dado de baja.'
            : 'Producto reactivado.';

        Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje]);

        return back();
    }

    // ── Frontera: Gestión de Categorías ────────────────────────────────────

    /**
     * CU07-FR5: Crear o actualizar una categoría.
     * Acceso: solo Administrador (productos.gestionar).
     */
    public function storeCategoria(Request $request): RedirectResponse
    {
        $this->authorize('productos.gestionar', $request);

        $datos = $request->validate([
            'nombre'      => ['required', 'string', 'max:150'],
            'slug'        => ['nullable', 'string', 'max:180', 'unique:categoria,slug'],
            'descripcion' => ['nullable', 'string'],
        ], [
            'slug.unique' => 'Ya existe una categoría con ese slug.',
        ]);

        $slug = $this->resolverSlug($datos['slug'] ?? null, $datos['nombre']);

        Categoria::query()->create([
            'nombre'      => $datos['nombre'],
            'slug'        => $slug,
            'descripcion' => $datos['descripcion'] ?? null,
            'activo'      => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Categoría creada.']);

        return back();
    }

    /**
     * CU07-FR6: Crear o actualizar una marca.
     * Acceso: solo Administrador (productos.gestionar).
     */
    public function storeMarca(Request $request): RedirectResponse
    {
        $this->authorize('productos.gestionar', $request);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'slug'   => ['nullable', 'string', 'max:150', 'unique:marca,slug'],
        ], [
            'slug.unique' => 'Ya existe una marca con ese slug.',
        ]);

        $slug = $this->resolverSlug($datos['slug'] ?? null, $datos['nombre']);

        Marca::query()->create([
            'nombre' => $datos['nombre'],
            'slug'   => $slug,
            'estado' => 'ACTIVO',
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Marca creada.']);

        return back();
    }

    // ── Entidad: subir imagen de producto ─────────────────────────────────

    /**
     * CU07-FR7: Subir imagen para un producto.
     * Acceso: solo Administrador (productos.gestionar).
     */
    public function subirImagen(Request $request, Producto $producto): RedirectResponse
    {
        $this->authorize('productos.gestionar', $request);

        $request->validate([
            'imagen' => ['required', 'image', 'max:4096', 'mimes:jpeg,jpg,png,webp'],
            'es_principal' => ['boolean'],
        ]);

        $archivo = $request->file('imagen');
        $ruta = $archivo->store("catalogo/{$producto->id}", 'public');

        if ($request->boolean('es_principal')) {
            // Quitar principal anterior
            $producto->imagenes()->where('es_principal', true)->update(['es_principal' => false]);
        }

        $orden = $producto->imagenes()->max('orden_visualizacion') ?? 0;

        $producto->imagenes()->create([
            'url_webp'            => '/storage/' . $ruta,
            'es_principal'        => $request->boolean('es_principal', $orden === 0),
            'orden_visualizacion' => $orden + 1,
            'texto_alternativo'   => $producto->nombre,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Imagen subida correctamente.']);

        return back();
    }

    /**
     * CU07-FR8: Eliminar imagen de producto.
     */
    public function eliminarImagen(Request $request, ImagenProducto $imagen): RedirectResponse
    {
        $this->authorize('productos.gestionar', $request);

        // Eliminar archivo físico si existe en storage
        $ruta = str_replace('/storage/', '', $imagen->url_webp);
        if (Storage::disk('public')->exists($ruta)) {
            Storage::disk('public')->delete($ruta);
        }

        $imagen->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Imagen eliminada.']);

        return back();
    }

    // ── Control: Helpers internos ───────────────────────────────────────────

    /**
     * Formatea un Producto para enviar al frontend.
     *
     * @return array<string, mixed>
     */
    private function formatProducto(Producto $p): array
    {
        $variante = $p->variantes->first();
        $imagen   = $p->imagenes->firstWhere('es_principal', true) ?? $p->imagenes->first();

        return [
            'id'             => $p->id,
            'nombre'         => $p->nombre,
            'slug'           => $p->slug,
            'descripcion'    => $p->descripcion,
            'estado'         => $p->estado ?? 'ACTIVO',
            'categoria_id'   => $p->categoria_id,
            'categoria'      => $p->categoria?->nombre,
            'marca_id'       => $p->marca_id,
            'marca'          => $p->marca?->nombre,
            'variantes_count' => $p->variantes_count ?? 0,
            'stock_total'    => (int) ($p->stock_total ?? 0),
            'precio_venta'   => $variante?->precio_venta,
            'sku'            => $variante?->sku,
            'imagen_url'     => $imagen?->url_webp,
            'imagen_alt'     => $imagen?->texto_alternativo ?? $p->nombre,
            'imagenes'       => $p->imagenes->map(fn (ImagenProducto $img) => [
                'id'           => $img->id,
                'url_webp'     => $img->url_webp,
                'es_principal' => $img->es_principal,
                'orden'        => $img->orden_visualizacion,
                'alt'          => $img->texto_alternativo,
            ])->values(),
        ];
    }

    /**
     * Genera o valida el slug de un producto/categoría/marca.
     * Si el usuario envió un slug vacío, se autogenera.
     * Si el slug enviado es igual al actual, lo devuelve sin modificar.
     */
    private function resolverSlug(?string $slugEnviado, string $nombre, ?string $slugActual = null): string
    {
        if ($slugEnviado === null || $slugEnviado === '' || $slugEnviado === $slugActual) {
            return $slugActual ?? Str::slug($nombre);
        }

        return Str::slug($slugEnviado);
    }

    /**
     * Verificación de permiso (frontera de control de acceso).
     * Lanza 403 si el usuario no tiene NINGUNO de los permisos dados.
     */
    private function authorizeAny(array $claves, Request $request): void
    {
        $user = $request->user();

        foreach ($claves as $clave) {
            if ($user?->tienePermiso($clave)) {
                return;
            }
        }

        abort(403, 'No tenés permiso para acceder a esta sección.');
    }

    /**
     * Verificación de permiso único.
     */
    private function authorize(string $clave, Request $request): void
    {
        if (! $request->user()?->tienePermiso($clave)) {
            abort(403, 'Solo el Administrador puede realizar esta acción.');
        }
    }
}
