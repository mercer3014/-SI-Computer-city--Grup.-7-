<?php

namespace Database\Seeders;

use App\Models\Atributo;
use App\Models\Categoria;
use App\Models\Marca;
use App\Models\Producto;
use App\Models\ValorAtributo;
use App\Models\VarianteProducto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Categorías, marcas y 20 productos con una variante cada uno (el catálogo de poblacion.txt).
 * El stock se carga después, con las compras de OperacionesDemoSeeder.
 */
class CatalogoDemoSeeder extends Seeder
{
    /** @var array<string, array{0: string, 1: string}> */
    private const CATEGORIAS = [
        'mouse' => ['Mouse', 'Mouses alámbricos e inalámbricos'],
        'teclados' => ['Teclados', 'Teclados mecánicos y de membrana'],
        'audifonos' => ['Audífonos', 'Audífonos y diademas gamer'],
        'microfonos' => ['Micrófonos', 'Micrófonos para streaming y videollamadas'],
        'camaras-web' => ['Cámaras Web', 'Cámaras web para videollamadas y streaming'],
        'parlantes-barras-sonido' => ['Parlantes y Barras de Sonido', 'Parlantes, barras de sonido y equipos de audio'],
        'mousepads' => ['Mousepads', 'Mousepads y superficies para mouse'],
        'accesorios-adaptadores' => ['Accesorios y Adaptadores', 'Hubs, adaptadores, cargadores y accesorios varios'],
    ];

    /** @var array<string, string> */
    private const MARCAS = [
        'logitech' => 'Logitech',
        'redragon' => 'Redragon',
        'hyperx' => 'HyperX',
        'jbl' => 'JBL',
        'genius' => 'Genius',
    ];

    /**
     * categoría, marca, nombre, SKU, código de barras, precio de venta, stock mínimo.
     *
     * @var list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: float, 6: int}>
     */
    public const PRODUCTOS = [
        ['mouse', 'logitech', 'Mouse Logitech G203', 'MOU-LOG-G203-NEG', '750123450011', 120.00, 5],
        ['mouse', 'redragon', 'Mouse Redragon Cobra M711', 'MOU-RED-COBRA-NEG', '750123450012', 95.00, 5],
        ['mouse', 'genius', 'Mouse Genius DX-110', 'MOU-GEN-DX110-NEG', '750123450013', 60.00, 5],
        ['teclados', 'redragon', 'Teclado Mecánico Redragon K552', 'TEC-RED-K552-NEG', '750123450014', 260.00, 3],
        ['teclados', 'logitech', 'Teclado Logitech K120', 'TEC-LOG-K120-NEG', '750123450015', 90.00, 4],
        ['teclados', 'hyperx', 'Teclado HyperX Alloy Origins', 'TEC-HYX-ALORG-NEG', '750123450016', 620.00, 2],
        ['audifonos', 'hyperx', 'Audífonos HyperX Cloud Stinger', 'AUD-HYX-CSTG-NEG', '750123450017', 350.00, 4],
        ['audifonos', 'logitech', 'Audífonos Logitech H390', 'AUD-LOG-H390-NEG', '750123450018', 150.00, 4],
        ['audifonos', 'jbl', 'Audífonos JBL Tune 500', 'AUD-JBL-T500-NEG', '750123450019', 210.00, 3],
        ['microfonos', 'genius', 'Micrófono Genius MIC-01', 'MIC-GEN-MIC01-NEG', '750123450020', 180.00, 3],
        ['microfonos', 'hyperx', 'Micrófono HyperX SoloCast', 'MIC-HYX-SOLO-NEG', '750123450021', 380.00, 2],
        ['camaras-web', 'logitech', 'Cámara Web Logitech C920', 'CAM-LOG-C920-NEG', '750123450022', 480.00, 3],
        ['camaras-web', 'genius', 'Cámara Web Genius FaceCam', 'CAM-GEN-FACE-NEG', '750123450023', 210.00, 3],
        ['parlantes-barras-sonido', 'jbl', 'Barra de Sonido JBL Bar 2.0', 'PAR-JBL-BAR20-NEG', '750123450024', 690.00, 2],
        ['parlantes-barras-sonido', 'logitech', 'Parlante Logitech Z200', 'PAR-LOG-Z200-NEG', '750123450025', 160.00, 3],
        ['mousepads', 'redragon', 'Mousepad Redragon Archelon', 'MSP-RED-ARCH-NEG', '750123450026', 85.00, 5],
        ['accesorios-adaptadores', 'genius', 'Hub USB Genius GH-U', 'HUB-GEN-GHU-NEG', '750123450027', 70.00, 5],
        ['accesorios-adaptadores', 'logitech', 'Adaptador Bluetooth Logitech', 'ADP-LOG-BT01-NEG', '750123450028', 55.00, 5],
        ['accesorios-adaptadores', 'redragon', 'Cargador USB-C Redragon', 'CAR-RED-USBC-NEG', '750123450029', 65.00, 5],
        ['accesorios-adaptadores', 'jbl', 'Ring Light para Streaming JBL Stream', 'ACC-JBL-RING-NEG', '750123450030', 140.00, 4],
    ];

    /**
     * SKU y los valores de atributo que tiene esa variante.
     *
     * @var array<string, array<string, string>>
     */
    private const ATRIBUTOS = [
        'MOU-LOG-G203-NEG' => ['Color' => 'Negro', 'Conectividad' => 'USB Alámbrico'],
        'AUD-HYX-CSTG-NEG' => ['Color' => 'Negro'],
        'ADP-LOG-BT01-NEG' => ['Conectividad' => 'Bluetooth'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIAS as $slug => [$nombre, $descripcion]) {
            Categoria::query()->firstOrCreate(['slug' => $slug], [
                'nombre' => $nombre,
                'descripcion' => $descripcion,
            ]);
        }

        foreach (self::MARCAS as $slug => $nombre) {
            Marca::query()->firstOrCreate(['slug' => $slug], ['nombre' => $nombre]);
        }

        foreach (self::PRODUCTOS as [$categoria, $marca, $nombre, $sku, $codigoBarras, $precio, $stockMinimo]) {
            $producto = Producto::query()->firstOrCreate(['slug' => Str::slug($nombre)], [
                'categoria_id' => Categoria::query()->where('slug', $categoria)->value('id'),
                'marca_id' => Marca::query()->where('slug', $marca)->value('id'),
                'nombre' => $nombre,
                'descripcion' => $nombre,
            ]);

            VarianteProducto::query()->firstOrCreate(['sku' => $sku], [
                'producto_id' => $producto->id,
                'codigo_barras' => $codigoBarras,
                'precio_venta' => $precio,
                'stock_minimo' => $stockMinimo,
            ]);
        }

        $this->cargarAtributos();
    }

    private function cargarAtributos(): void
    {
        foreach (self::ATRIBUTOS as $sku => $valores) {
            $varianteId = VarianteProducto::query()->where('sku', $sku)->value('id');

            foreach ($valores as $atributoNombre => $valorNombre) {
                $atributo = Atributo::query()->firstOrCreate(['nombre' => $atributoNombre]);
                $valor = ValorAtributo::query()->firstOrCreate([
                    'atributo_id' => $atributo->id,
                    'valor' => $valorNombre,
                ]);

                $vinculo = ['variante_id' => $varianteId, 'valor_atributo_id' => $valor->id];

                if (! DB::table('variante_valor_atributo')->where($vinculo)->exists()) {
                    DB::table('variante_valor_atributo')->insert($vinculo);
                }
            }
        }
    }
}
