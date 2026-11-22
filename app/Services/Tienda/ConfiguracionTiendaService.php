<?php

namespace App\Services\Tienda;

use App\Models\ConfiguracionTienda;
use App\Models\HorarioAtencion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * CU17: datos de la tienda que se muestran en el catálogo, las notas de venta,
 * las cotizaciones, los reportes y el asistente. Se lee una vez y se guarda en caché
 * hasta que el Administrador cambia algo.
 *
 * @phpstan-type HorarioDia array{dia_semana: int, dia: string, cerrado: bool, hora_apertura: string|null, hora_cierre: string|null, horario: string|null}
 * @phpstan-type DatosTienda array{id: int|null, nombre: string, centro_comercial: string|null, direccion: string|null, numero_pasillo: string|null, numero_local: string|null, telefono: string|null, whatsapp: string|null, email: string|null, latitud: float|null, longitud: float|null, google_maps_url: string|null, maps: string|null, logo: string|null, moneda: string, anuncio: string|null, ubicacion: string, horarios: list<HorarioDia>}
 */
class ConfiguracionTiendaService
{
    public const CACHE = 'tienda.configuracion';

    public const NOMBRE_POR_DEFECTO = 'Computer City';

    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

    private const CARPETA_LOGO = 'tienda';

    /**
     * @return DatosTienda
     */
    public function actual(): array
    {
        $guardado = Cache::get(self::CACHE);

        if (is_array($guardado)) {
            /** @var DatosTienda $guardado */
            return $guardado;
        }

        try {
            $datos = $this->leer();
        } catch (Throwable) {
            return $this->porDefecto();
        }

        Cache::put(self::CACHE, $datos, now()->addHour());

        return $datos;
    }

    public function olvidar(): void
    {
        Cache::forget(self::CACHE);
    }

    /**
     * Siempre debe existir una fila: si no hay ninguna, se crea la de Computer City
     * con su horario de lunes a sábado y el domingo cerrado.
     */
    public function asegurar(): ConfiguracionTienda
    {
        $configuracion = ConfiguracionTienda::query()->orderBy('id')->first();

        if ($configuracion !== null) {
            return $configuracion;
        }

        $configuracion = ConfiguracionTienda::query()->create([
            'nombre_tienda' => self::NOMBRE_POR_DEFECTO,
            'moneda' => 'BOB',
        ]);

        foreach (array_keys(self::DIAS) as $dia) {
            $domingo = $dia === 7;

            HorarioAtencion::query()->create([
                'configuracion_tienda_id' => $configuracion->id,
                'dia_semana' => $dia,
                'hora_apertura' => $domingo ? null : '09:00',
                'hora_cierre' => $domingo ? null : '19:00',
                'cerrado' => $domingo,
            ]);
        }

        $this->olvidar();

        return $configuracion;
    }

    /**
     * Guarda el logo en el disco público y borra el anterior si lo había subido el sistema.
     * Devuelve la URL relativa que se guarda en logo_url.
     */
    public function guardarLogo(UploadedFile $archivo, ConfiguracionTienda $configuracion): string
    {
        $nombre = 'logo-'.Str::lower(Str::random(10)).'.'.$archivo->extension();
        $ruta = Storage::disk('public')->putFileAs(self::CARPETA_LOGO, $archivo, $nombre);

        if ($ruta === false) {
            throw new RuntimeException('No se pudo guardar el logo.');
        }

        $anterior = $configuracion->logo_url;

        if (is_string($anterior) && str_starts_with($anterior, '/storage/'.self::CARPETA_LOGO.'/')) {
            Storage::disk('public')->delete(Str::after($anterior, '/storage/'));
        }

        return '/storage/'.$ruta;
    }

    /**
     * El logo como data URI para el PDF. DomPDF necesita GD para dibujar imágenes:
     * sin esa extensión, el PDF sale sin logo en vez de fallar.
     */
    public function logoParaPdf(): ?string
    {
        $logo = $this->actual()['logo'];

        if ($logo === null || ! extension_loaded('gd') || ! str_starts_with($logo, '/storage/')) {
            return null;
        }

        $ruta = Str::after($logo, '/storage/');
        $disco = Storage::disk('public');

        if (! $disco->exists($ruta)) {
            return null;
        }

        return 'data:'.($disco->mimeType($ruta) ?: 'image/png').';base64,'.base64_encode((string) $disco->get($ruta));
    }

    /**
     * @return DatosTienda
     */
    private function leer(): array
    {
        $configuracion = ConfiguracionTienda::query()->orderBy('id')->first();

        if ($configuracion === null) {
            return $this->porDefecto();
        }

        return $this->normalizar($configuracion, $configuracion->horarios()->get());
    }

    /**
     * @param  Collection<int, HorarioAtencion>  $horarios
     * @return DatosTienda
     */
    private function normalizar(ConfiguracionTienda $c, Collection $horarios): array
    {
        $maps = $c->google_maps_url;

        if (($maps === null || $maps === '') && $c->latitud !== null && $c->longitud !== null) {
            $maps = 'https://www.google.com/maps?q='.$c->latitud.','.$c->longitud;
        }

        $partes = array_filter([
            $c->centro_comercial,
            $c->numero_pasillo ? 'Pasillo '.$c->numero_pasillo : null,
            $c->numero_local ? 'Local '.$c->numero_local : null,
            $c->direccion,
        ], fn (?string $parte): bool => $parte !== null && $parte !== '');

        return [
            'id' => $c->id,
            'nombre' => $c->nombre_tienda,
            'centro_comercial' => $c->centro_comercial,
            'direccion' => $c->direccion,
            'numero_pasillo' => $c->numero_pasillo,
            'numero_local' => $c->numero_local,
            'telefono' => $c->telefono,
            'whatsapp' => $c->whatsapp,
            'email' => $c->email,
            'latitud' => $c->latitud,
            'longitud' => $c->longitud,
            'google_maps_url' => $c->google_maps_url === '' ? null : $c->google_maps_url,
            'maps' => $maps === '' ? null : $maps,
            'logo' => $c->logo_url === '' ? null : $c->logo_url,
            'moneda' => $c->moneda ?: 'BOB',
            'anuncio' => $c->anuncio === '' ? null : $c->anuncio,
            'ubicacion' => implode(' · ', $partes),
            'horarios' => $this->armarHorarios($horarios),
        ];
    }

    /**
     * Siempre devuelve los 7 días; el que falta en la base aparece como cerrado.
     *
     * @param  Collection<int, HorarioAtencion>  $horarios
     * @return list<HorarioDia>
     */
    private function armarHorarios(Collection $horarios): array
    {
        $porDia = $horarios->keyBy('dia_semana');
        $lista = [];

        foreach (self::DIAS as $numero => $nombre) {
            $fila = $porDia->get($numero);
            $apertura = null;
            $cierre = null;

            if ($fila !== null && ! $fila->cerrado && $fila->hora_apertura !== null && $fila->hora_cierre !== null) {
                $apertura = substr($fila->hora_apertura, 0, 5);
                $cierre = substr($fila->hora_cierre, 0, 5);
            }

            $lista[] = [
                'dia_semana' => $numero,
                'dia' => $nombre,
                'cerrado' => $apertura === null,
                'hora_apertura' => $apertura,
                'hora_cierre' => $cierre,
                'horario' => $apertura !== null ? $apertura.' – '.$cierre : null,
            ];
        }

        return $lista;
    }

    /**
     * @return DatosTienda
     */
    private function porDefecto(): array
    {
        return [
            'id' => null,
            'nombre' => self::NOMBRE_POR_DEFECTO,
            'centro_comercial' => null,
            'direccion' => null,
            'numero_pasillo' => null,
            'numero_local' => null,
            'telefono' => null,
            'whatsapp' => null,
            'email' => null,
            'latitud' => null,
            'longitud' => null,
            'google_maps_url' => null,
            'maps' => null,
            'logo' => null,
            'moneda' => 'BOB',
            'anuncio' => null,
            'ubicacion' => '',
            'horarios' => $this->armarHorarios(new Collection),
        ];
    }
}
