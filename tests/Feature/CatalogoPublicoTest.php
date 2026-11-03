<?php

namespace Tests\Feature;

use App\Models\Producto;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogoPublicoTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_public_catalog_on_home_and_tienda(): void
    {
        $this->producto('Mouse Logitech G203', 'mouse', 125, 10);
        $this->producto('Teclado Inactivo', 'teclados', 90, 5, estado: 'INACTIVO');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tienda/Catalogo')
                ->has('productos.data', 1)
                ->where('productos.data.0.nombre', 'Mouse Logitech G203')
                ->where('productos.data.0.precio_desde', 125)
                ->where('error', false));

        $this->get('/tienda')->assertOk()->assertInertia(fn (Assert $page) => $page->component('tienda/Catalogo'));
    }

    public function test_catalog_filters_by_category_brand_price_and_stock(): void
    {
        $this->producto('Mouse Barato', 'mouse', 60, 3, marca: 'genius');
        $this->producto('Mouse Caro', 'mouse', 300, 0, marca: 'logitech');
        $this->producto('Teclado K120', 'teclados', 95, 4, marca: 'logitech');

        $this->get('/tienda?categoria=mouse')
            ->assertInertia(fn (Assert $page) => $page->has('productos.data', 2));

        $this->get('/tienda?marca=logitech&precio_max=200')
            ->assertInertia(fn (Assert $page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.nombre', 'Teclado K120'));

        $this->get('/tienda?categoria=mouse&disponibles=1')
            ->assertInertia(fn (Assert $page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.nombre', 'Mouse Barato'));

        $this->get('/tienda?q=caro&orden=precio_desc')
            ->assertInertia(fn (Assert $page) => $page
                ->has('productos.data', 1)
                ->where('productos.data.0.disponibilidad', 'AGOTADO'));
    }

    public function test_product_detail_registers_a_visit(): void
    {
        $producto = $this->producto('Audífonos HyperX', 'audifonos', 360, 8);

        $this->get('/tienda/producto/'.$producto->slug)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tienda/Producto')
                ->where('producto.nombre', 'Audífonos HyperX')
                ->where('producto.visitas', 1)
                ->has('producto.variantes', 1));

        $this->assertDatabaseHas('evento_producto', [
            'producto_id' => $producto->id,
            'tipo_evento' => 'VISTA',
        ]);

        $this->get('/tienda/producto/no-existe')->assertNotFound();
    }

    public function test_cart_endpoint_returns_current_price_and_stock(): void
    {
        $producto = $this->producto('Hub USB', 'accesorios', 70, 2);
        $variante = $producto->variantes()->first();

        $this->getJson('/tienda/carrito/variantes?ids='.$variante->id)
            ->assertOk()
            ->assertJsonPath('variantes.0.precio', 70)
            ->assertJsonPath('variantes.0.stock', 2)
            ->assertJsonPath('variantes.0.disponible', true);
    }

    private function producto(
        string $nombre,
        string $categoria,
        float $precio,
        int $stock,
        string $estado = 'ACTIVO',
        ?string $marca = null,
    ): Producto {
        $categoriaId = DB::table('categoria')->where('slug', $categoria)->value('id')
            ?? DB::table('categoria')->insertGetId(['nombre' => ucfirst($categoria), 'slug' => $categoria, 'activo' => true]);

        $marcaId = $marca === null ? null : (DB::table('marca')->where('slug', $marca)->value('id')
            ?? DB::table('marca')->insertGetId(['nombre' => ucfirst($marca), 'slug' => $marca, 'estado' => 'ACTIVO']));

        $producto = Producto::query()->create([
            'categoria_id' => $categoriaId,
            'marca_id' => $marcaId,
            'nombre' => $nombre,
            'slug' => str($nombre)->slug()->value(),
            'estado' => $estado,
        ]);

        $producto->variantes()->create([
            'sku' => strtoupper(str($nombre)->slug()->value()),
            'precio_venta' => $precio,
            'stock_actual' => $stock,
            'stock_minimo' => 2,
            'estado' => 'ACTIVO',
        ]);

        return $producto;
    }
}
