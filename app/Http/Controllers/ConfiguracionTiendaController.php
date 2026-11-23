<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConfiguracionTiendaRequest;
use App\Http\Requests\HorariosAtencionRequest;
use App\Models\HorarioAtencion;
use App\Services\BitacoraService;
use App\Services\Tienda\ConfiguracionTiendaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU17: datos generales de la tienda y horarios de atención.
 */
class ConfiguracionTiendaController extends Controller
{
    private const MODULO = 'Configuración';

    public function __construct(private ConfiguracionTiendaService $tienda) {}

    public function edit(): Response
    {
        $this->tienda->asegurar();

        return Inertia::render('configuracion/Tienda', [
            'configuracion' => $this->tienda->actual(),
            'monedas' => ConfiguracionTiendaRequest::MONEDAS,
        ]);
    }

    public function update(ConfiguracionTiendaRequest $request): RedirectResponse
    {
        $configuracion = $this->tienda->asegurar();
        $datos = $request->datos();
        $anterior = $this->resumen($configuracion->only(array_keys($datos)));

        $configuracion->forceFill($datos)->save();
        $this->tienda->olvidar();

        BitacoraService::registrar(
            self::MODULO,
            'ACTUALIZAR_CONFIGURACION_TIENDA',
            'configuracion_tienda',
            $configuracion->id,
            $anterior,
            $this->resumen($configuracion->only(array_keys($datos))),
            'Actualización de los datos de la tienda',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Datos de la tienda guardados.']);

        return back();
    }

    public function logo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
        ], [
            'logo.required' => 'Elegí una imagen para el logo.',
            'logo.image' => 'El logo debe ser una imagen PNG, JPG o WebP.',
            'logo.mimes' => 'El logo debe ser PNG, JPG o WebP.',
            'logo.max' => 'El logo no puede pesar más de 1 MB.',
        ]);

        $archivo = $request->file('logo');

        if (! $archivo instanceof UploadedFile) {
            throw ValidationException::withMessages(['logo' => 'Elegí una imagen para el logo.']);
        }

        $configuracion = $this->tienda->asegurar();
        $anterior = $configuracion->logo_url;

        $configuracion->forceFill([
            'logo_url' => $this->tienda->guardarLogo($archivo, $configuracion),
        ])->save();
        $this->tienda->olvidar();

        BitacoraService::registrar(
            self::MODULO,
            'ACTUALIZAR_CONFIGURACION_TIENDA',
            'configuracion_tienda',
            $configuracion->id,
            ['logo_url' => $anterior],
            ['logo_url' => $configuracion->logo_url],
            'Cambio del logo de la tienda',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo actualizado.']);

        return back();
    }

    public function horarios(HorariosAtencionRequest $request): RedirectResponse
    {
        $configuracion = $this->tienda->asegurar();
        $anterior = $this->resumenHorarios($this->tienda->actual()['horarios']);

        DB::transaction(function () use ($request, $configuracion): void {
            foreach ($request->horarios() as $fila) {
                HorarioAtencion::query()->updateOrCreate(
                    ['configuracion_tienda_id' => $configuracion->id, 'dia_semana' => $fila['dia_semana']],
                    [
                        'cerrado' => $fila['cerrado'],
                        'hora_apertura' => $fila['hora_apertura'],
                        'hora_cierre' => $fila['hora_cierre'],
                    ],
                );
            }
        });

        $this->tienda->olvidar();

        BitacoraService::registrar(
            self::MODULO,
            'ACTUALIZAR_HORARIOS',
            'configuracion_tienda',
            $configuracion->id,
            $anterior,
            $this->resumenHorarios($this->tienda->actual()['horarios']),
            'Actualización de los horarios de atención',
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Horarios guardados.']);

        return back();
    }

    /**
     * @param  array<string, mixed>  $campos
     * @return array<string, mixed>
     */
    private function resumen(array $campos): array
    {
        return array_filter($campos, fn (mixed $valor): bool => $valor !== null && $valor !== '');
    }

    /**
     * Un valor por día ("Lunes" => "09:00 – 19:00"), para que la bitácora muestre solo los días que cambiaron.
     *
     * @param  list<array{dia: string, horario: string|null}>  $horarios
     * @return array<string, string>
     */
    private function resumenHorarios(array $horarios): array
    {
        $resumen = [];

        foreach ($horarios as $dia) {
            $resumen[$dia['dia']] = $dia['horario'] ?? 'Cerrado';
        }

        return $resumen;
    }
}
