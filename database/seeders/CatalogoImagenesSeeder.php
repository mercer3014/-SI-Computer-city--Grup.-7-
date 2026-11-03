<?php

namespace Database\Seeders;

use App\Models\ImagenProducto;
use App\Models\Producto;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Carga las fotos de /catalogo como imagen principal de cada producto.
 * Los archivos ya vienen en WebP aunque tengan extensión .png.
 *
 * php artisan db:seed --class=CatalogoImagenesSeeder
 */
class CatalogoImagenesSeeder extends Seeder
{
    /** Archivos cuyo nombre no coincide con el del producto. */
    private const ALIAS = [
        'redragonm711gamingmice' => 'mouse-redragon-cobra-m711',
    ];

    public function run(): void
    {
        $carpeta = base_path('catalogo');

        if (! File::isDirectory($carpeta)) {
            $this->command?->warn('No existe la carpeta catalogo/.');

            return;
        }

        $productos = Producto::query()->with('imagenes')->get();
        $porClave = $productos->keyBy(fn (Producto $p) => $this->clave($p->nombre));
        $porSlug = $productos->keyBy('slug');
        $cargadas = 0;

        foreach (File::files($carpeta) as $archivo) {
            $base = pathinfo($archivo->getFilename(), PATHINFO_FILENAME);
            $alias = self::ALIAS[Str::lower($base)] ?? null;

            /** @var Producto|null $producto */
            $producto = $alias !== null ? $porSlug->get($alias) : $porClave->get($this->clave($base));

            if ($producto === null) {
                $this->command?->warn("Sin producto para {$archivo->getFilename()}");

                continue;
            }

            $ruta = "catalogo/{$producto->id}/{$producto->slug}.webp";
            $url = '/storage/'.$ruta;

            Storage::disk('public')->put($ruta, File::get($archivo->getPathname()));

            if ($producto->imagenes->contains('url_webp', $url)) {
                continue;
            }

            ImagenProducto::query()->create([
                'producto_id' => $producto->id,
                'url_webp' => $url,
                'orden_visualizacion' => (int) $producto->imagenes->max('orden_visualizacion') + 1,
                'es_principal' => $producto->imagenes->isEmpty(),
                'texto_alternativo' => $producto->nombre,
            ]);

            $cargadas++;
        }

        $this->command?->info("Imágenes nuevas asociadas: {$cargadas}");
    }

    private function clave(string $texto): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($texto))) ?? '';
    }
}
