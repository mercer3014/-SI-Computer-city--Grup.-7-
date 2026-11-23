<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnviarMensajeAsistenteRequest;
use App\Http\Requests\GuardarPlantillaReporteRequest;
use App\Models\ConversacionAsistente;
use App\Models\MensajeAsistente;
use App\Models\PlantillaReporte;
use App\Models\User;
use App\Services\Asistente\AsistenteService;
use App\Services\Asistente\EspecificacionInvalida;
use App\Services\BitacoraService;
use App\Services\Reportes\Exportacion\ExportacionFallida;
use App\Services\Reportes\Exportacion\Exportadores;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * CU22: asistente virtual del panel, exclusivo del Administrador.
 * Las rutas llevan `permiso:asistente.usar` y `puede.asistente` (rol Administrador).
 */
class AsistenteController extends Controller
{
    private const EJEMPLOS = [
        '¿Cuánto vendimos esta semana?',
        '¿Qué productos están bajo el stock mínimo?',
        'Ventas de teclados Redragon por semana en el último mes',
    ];

    public function __construct(private AsistenteService $servicio) {}

    public function index(Request $request): Response
    {
        return $this->pantalla($this->usuario($request), null);
    }

    public function mostrar(Request $request, ConversacionAsistente $conversacion): Response
    {
        $usuario = $this->usuario($request);

        abort_unless($conversacion->usuario_id === $usuario->id, 403, 'Esa conversación no es tuya.');

        return $this->pantalla($usuario, $conversacion);
    }

    public function nueva(Request $request): JsonResponse
    {
        $conversacion = ConversacionAsistente::query()->create([
            'usuario_id' => $this->usuario($request)->id,
            'titulo' => null,
            'fecha_inicio' => now(),
            'fecha_actualizacion' => now(),
        ]);

        return response()->json(['conversacion_id' => $conversacion->id], 201);
    }

    public function enviar(EnviarMensajeAsistenteRequest $request): JsonResponse
    {
        $resultado = $this->servicio->enviar(
            $this->usuario($request),
            $request->conversacionId(),
            $request->contenido(),
            $request->origen(),
        );

        return response()->json($resultado['cuerpo'], $resultado['estado']);
    }

    /**
     * Vuelve a ejecutar la especificación guardada del mensaje (nunca datos enviados por el navegador).
     */
    public function exportar(Request $request, MensajeAsistente $mensaje): HttpResponse|RedirectResponse
    {
        $usuario = $this->usuario($request);
        $conversacion = $mensaje->conversacion;

        abort_unless($conversacion !== null && $conversacion->usuario_id === $usuario->id, 403, 'Ese mensaje no es tuyo.');

        $formato = $request->validate([
            'formato' => ['required', 'string', Rule::in(Exportadores::formatos())],
        ])['formato'];

        $volver = redirect()->route('asistente.conversaciones.mostrar', $conversacion);

        try {
            $reporte = $this->servicio->reporteDe($usuario, $mensaje);
        } catch (EspecificacionInvalida) {
            Inertia::flash('toast', ['type' => 'error', 'message' => EspecificacionInvalida::MENSAJE_PARA_EL_USUARIO]);

            return $volver;
        }

        if ($reporte->vacio()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => AsistenteService::MENSAJE_SIN_REGISTROS]);

            return $volver;
        }

        try {
            $archivo = Exportadores::para($formato)->exportar($reporte);
        } catch (ExportacionFallida $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return $volver;
        }

        BitacoraService::registrar(
            'Asistente',
            'EXPORTAR_REPORTE_DINAMICO',
            'mensaje_asistente',
            $mensaje->id,
            null,
            ['formato' => $formato, 'especificacion' => $mensaje->especificacion],
            'Exportación de un reporte del asistente a '.strtoupper($formato),
        );

        return response($archivo->contenido, 200, [
            'Content-Type' => $archivo->mime,
            'Content-Disposition' => 'attachment; filename="'.$archivo->nombre.'"',
            'Content-Length' => (string) strlen($archivo->contenido),
        ]);
    }

    public function guardarPlantilla(GuardarPlantillaReporteRequest $request): JsonResponse
    {
        $usuario = $this->usuario($request);
        $datos = $request->validated();

        $mensaje = MensajeAsistente::query()->with('conversacion')->findOrFail((int) $datos['mensaje_id']);

        abort_unless($mensaje->conversacion?->usuario_id === $usuario->id, 403, 'Ese mensaje no es tuyo.');

        if (! in_array($mensaje->tipo, [MensajeAsistente::REPORTE, MensajeAsistente::CONSULTA], true) || ! is_array($mensaje->especificacion)) {
            throw ValidationException::withMessages(['mensaje_id' => 'Solo se pueden guardar consultas y reportes como plantilla.']);
        }

        $nombre = (string) $datos['nombre'];

        if (PlantillaReporte::query()->where('usuario_id', $usuario->id)->where('nombre', $nombre)->exists()) {
            throw ValidationException::withMessages(['nombre' => 'Ya tienes una plantilla con ese nombre.']);
        }

        $plantilla = PlantillaReporte::query()->create([
            'usuario_id' => $usuario->id,
            'nombre' => $nombre,
            'especificacion' => $mensaje->especificacion,
            'fecha_creacion' => now(),
        ]);

        BitacoraService::registrar(
            'Asistente',
            'GUARDAR_PLANTILLA_REPORTE',
            'plantilla_reporte',
            $plantilla->id,
            null,
            ['nombre' => $nombre, 'especificacion' => $mensaje->especificacion],
            'Plantilla de reporte frecuente',
        );

        return response()->json(['id' => $plantilla->id, 'nombre' => $plantilla->nombre], 201);
    }

    public function ejecutarPlantilla(Request $request, PlantillaReporte $plantilla): JsonResponse
    {
        $usuario = $this->usuario($request);

        abort_unless($plantilla->usuario_id === $usuario->id, 403, 'Esa plantilla no es tuya.');

        $conversacionId = $request->integer('conversacion_id');
        $resultado = $this->servicio->ejecutarPlantilla($usuario, $plantilla, $conversacionId > 0 ? $conversacionId : null);

        return response()->json($resultado['cuerpo'], $resultado['estado']);
    }

    public function eliminarPlantilla(Request $request, PlantillaReporte $plantilla): JsonResponse
    {
        abort_unless($plantilla->usuario_id === $this->usuario($request)->id, 403, 'Esa plantilla no es tuya.');

        $plantilla->delete();

        return response()->json(['eliminada' => true]);
    }

    private function pantalla(User $usuario, ?ConversacionAsistente $activa): Response
    {
        return Inertia::render('asistente/Index', [
            'conversacion' => $activa === null ? null : ['id' => $activa->id, 'titulo' => $activa->titulo],
            'mensajes' => $activa === null ? [] : $this->servicio->mensajesDe($usuario, $activa),
            'conversaciones' => ConversacionAsistente::query()
                ->de($usuario)
                ->whereHas('mensajes')
                ->orderByDesc('fecha_actualizacion')
                ->orderByDesc('id')
                ->limit(30)
                ->get()
                ->map(fn (ConversacionAsistente $c): array => [
                    'id' => $c->id,
                    'titulo' => $c->titulo ?? 'Conversación',
                    'fecha' => $c->fecha_actualizacion?->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
                ])
                ->values()
                ->all(),
            'plantillas' => PlantillaReporte::query()
                ->where('usuario_id', $usuario->id)
                ->orderBy('nombre')
                ->get(['id', 'nombre'])
                ->map(fn (PlantillaReporte $p): array => ['id' => $p->id, 'nombre' => $p->nombre])
                ->values()
                ->all(),
            'ejemplos' => self::EJEMPLOS,
            'limitePorMinuto' => (int) config('services.xai.limite_por_minuto'),
        ]);
    }

    private function usuario(Request $request): User
    {
        $usuario = $request->user();

        abort_unless($usuario instanceof User, 403);

        return $usuario;
    }
}
