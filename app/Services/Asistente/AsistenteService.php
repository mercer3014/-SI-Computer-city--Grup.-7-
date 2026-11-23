<?php

namespace App\Services\Asistente;

use App\Models\ConversacionAsistente;
use App\Models\MensajeAsistente;
use App\Models\PlantillaReporte;
use App\Models\User;
use App\Services\BitacoraService;
use App\Services\Reportes\Formato;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Reportes\ReporteResultado;
use App\Services\Tienda\ConfiguracionTiendaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * CU22: orquesta una consulta del asistente virtual.
 *
 *  1. filtro previo de palabras prohibidas (EX-03);
 *  2. el Servicio de IA propone una respuesta o una especificación;
 *  3. ValidadorEspecificacion la contrasta con la lista blanca (EX-04);
 *  4. ConsultasPermitidas la ejecuta, solo lectura;
 *  5. se arma el ReporteResultado, con el motor de reportes de CU16;
 *  6. se guardan los mensajes;
 *  7. se registra en la bitácora.
 *
 * Las cifras siempre las calcula el sistema; lo que diga la IA nunca se ejecuta como SQL ni se muestra como dato.
 *
 * @phpstan-import-type Especificacion from ValidadorEspecificacion
 * @phpstan-import-type Resultado from ConsultasPermitidas
 */
class AsistenteService
{
    public const MENSAJE_NO_SOPORTADO = 'No puedo ayudarte con eso. Puedo responder sobre ventas, utilidad, gastos, compras, caja, stock, pedidos, cotizaciones y calificaciones. Prueba con: «¿Cuánto vendimos esta semana?», «¿Qué productos están bajo el stock mínimo?» o «Ventas de teclados Redragon por semana en el último mes».';

    public const MENSAJE_SIN_REGISTROS = 'No existen registros para la consulta.';

    public const MENSAJE_FALLA = 'No pude calcular esa consulta. Inténtalo de nuevo en un momento.';

    private const MODULO = 'Asistente';

    public function __construct(
        private ServicioIa $ia,
        private ValidadorEspecificacion $validador,
        private ConsultasPermitidas $consultas,
        private ConstructorPrompt $prompt,
        private ConfiguracionTiendaService $tienda,
    ) {}

    /**
     * Procesa lo que escribió o dictó el Administrador. `estado` es el código HTTP: 503 si la IA no está disponible.
     *
     * @return array{estado: int, cuerpo: array<string, mixed>}
     */
    public function enviar(User $usuario, ?int $conversacionId, string $contenido, string $origen): array
    {
        $conversacion = $this->conversacion($usuario, $conversacionId, $contenido);
        $historial = $this->prompt->historial(
            $conversacion->mensajes()->reorder()->orderByDesc('id')->limit(ConstructorPrompt::MAXIMO_HISTORIAL)->get()->reverse(),
        );

        $mensajeUsuario = $this->guardar($conversacion, MensajeAsistente::USUARIO, MensajeAsistente::TEXTO, $contenido, $origen);

        if (PalabrasProhibidas::contiene($contenido)) {
            return $this->respuesta($conversacion, $mensajeUsuario, $this->guardar(
                $conversacion,
                MensajeAsistente::ASISTENTE,
                MensajeAsistente::NO_SOPORTADO,
                self::MENSAJE_NO_SOPORTADO,
            ), null, $contenido, $origen);
        }

        try {
            $propuesta = $this->ia->interpretar($historial, $contenido);
        } catch (AsistenteNoDisponible $e) {
            Log::warning('Asistente no disponible: '.$e->getMessage());

            $error = $this->guardar($conversacion, MensajeAsistente::ASISTENTE, MensajeAsistente::ERROR, AsistenteNoDisponible::MENSAJE);

            return [
                'estado' => 503,
                'cuerpo' => [
                    'message' => AsistenteNoDisponible::MENSAJE,
                    'ofrecer_reportes' => true,
                    'enlace' => '/reportes',
                    ...$this->cuerpo($conversacion, $mensajeUsuario, $error, null),
                ],
            ];
        }

        return match ($propuesta->tipo) {
            'no_soportado' => $this->respuesta($conversacion, $mensajeUsuario, $this->guardar(
                $conversacion,
                MensajeAsistente::ASISTENTE,
                MensajeAsistente::NO_SOPORTADO,
                self::MENSAJE_NO_SOPORTADO,
            ), null, $contenido, $origen),
            'respuesta' => $this->respuesta($conversacion, $mensajeUsuario, $this->guardar(
                $conversacion,
                MensajeAsistente::ASISTENTE,
                MensajeAsistente::TEXTO,
                $propuesta->mensaje !== '' ? $propuesta->mensaje : self::MENSAJE_NO_SOPORTADO,
            ), null, $contenido, $origen),
            default => $this->conEspecificacion($usuario, $conversacion, $mensajeUsuario, $propuesta->tipo, $propuesta->especificacion, $contenido, $origen),
        };
    }

    /**
     * Vuelve a ejecutar una plantilla guardada. La especificación se valida otra vez y se calcula con datos actuales.
     *
     * @return array{estado: int, cuerpo: array<string, mixed>}
     */
    public function ejecutarPlantilla(User $usuario, PlantillaReporte $plantilla, ?int $conversacionId): array
    {
        $texto = 'Ejecutar la plantilla «'.$plantilla->nombre.'»';
        $conversacion = $this->conversacion($usuario, $conversacionId, $texto);
        $mensajeUsuario = $this->guardar($conversacion, MensajeAsistente::USUARIO, MensajeAsistente::TEXTO, $texto);

        return $this->conEspecificacion($usuario, $conversacion, $mensajeUsuario, 'reporte', $plantilla->especificacion, $texto, 'TEXTO');
    }

    /**
     * Para exportar: vuelve a ejecutar la especificación guardada y validada del mensaje, nunca datos del cliente.
     *
     * @throws EspecificacionInvalida
     */
    public function reporteDe(User $usuario, MensajeAsistente $mensaje): ReporteResultado
    {
        $espec = $this->validador->validar($mensaje->especificacion);

        return $this->armarReporte($usuario, $espec, $this->consultas->ejecutar($espec));
    }

    /**
     * Mensajes de una conversación para la pantalla. Los reportes se vuelven a calcular (los últimos, para acotar el costo).
     *
     * @return list<array<string, mixed>>
     */
    public function mensajesDe(User $usuario, ConversacionAsistente $conversacion): array
    {
        $mensajes = $conversacion->mensajes()->get();
        $recientes = $mensajes->where('tipo', MensajeAsistente::REPORTE)->pluck('id')->take(-8)->all();
        $lista = [];

        foreach ($mensajes as $mensaje) {
            $resultado = null;

            if (in_array($mensaje->id, $recientes, true) && is_array($mensaje->especificacion)) {
                try {
                    $resultado = $this->reporteDe($usuario, $mensaje);
                } catch (Throwable $e) {
                    report($e);
                }
            }

            $lista[] = $this->mensaje($mensaje, $resultado);
        }

        return $lista;
    }

    /**
     * @param  array<array-key, mixed>|null  $cruda  especificación propuesta por la IA, sin validar
     * @return array{estado: int, cuerpo: array<string, mixed>}
     */
    private function conEspecificacion(User $usuario, ConversacionAsistente $conversacion, MensajeAsistente $mensajeUsuario, string $tipo, ?array $cruda, string $contenido, string $origen): array
    {
        try {
            $espec = $this->validador->validar($cruda);
        } catch (EspecificacionInvalida $e) {
            BitacoraService::registrar(
                self::MODULO,
                'ESPECIFICACION_RECHAZADA',
                'conversacion_asistente',
                $conversacion->id,
                null,
                ['consulta' => $contenido, 'motivo' => $e->getMessage()],
                'Especificación rechazada sin ejecutar consultas',
            );

            return $this->respuesta($conversacion, $mensajeUsuario, $this->guardar(
                $conversacion,
                MensajeAsistente::ASISTENTE,
                MensajeAsistente::ERROR,
                EspecificacionInvalida::MENSAJE_PARA_EL_USUARIO,
            ), null, $contenido, $origen);
        }

        try {
            $datos = $this->consultas->ejecutar($espec);
        } catch (Throwable $e) {
            report($e);

            return $this->respuesta($conversacion, $mensajeUsuario, $this->guardar(
                $conversacion,
                MensajeAsistente::ASISTENTE,
                MensajeAsistente::ERROR,
                self::MENSAJE_FALLA,
            ), null, $contenido, $origen);
        }

        $reporte = $this->armarReporte($usuario, $espec, $datos);
        $esReporte = $tipo === 'reporte';

        $mensajeAsistente = $this->guardar(
            $conversacion,
            MensajeAsistente::ASISTENTE,
            $esReporte ? MensajeAsistente::REPORTE : MensajeAsistente::CONSULTA,
            $esReporte ? $this->textoDeReporte($datos) : $this->textoDeConsulta($datos),
            null,
            $espec,
            $datos['interpretacion'],
            $datos['enlace'],
        );

        return $this->respuesta($conversacion, $mensajeUsuario, $mensajeAsistente, $esReporte ? $reporte : null, $contenido, $origen, $espec);
    }

    /**
     * @param  Especificacion|null  $espec
     * @return array{estado: int, cuerpo: array<string, mixed>}
     */
    private function respuesta(ConversacionAsistente $conversacion, MensajeAsistente $usuario, MensajeAsistente $asistente, ?ReporteResultado $reporte, string $contenido, string $origen, ?array $espec = null): array
    {
        BitacoraService::registrar(
            self::MODULO,
            'CONSULTA_ASISTENTE',
            'mensaje_asistente',
            $asistente->id,
            null,
            ['consulta' => $contenido, 'origen' => $origen, 'tipo' => $asistente->tipo, 'especificacion' => $espec],
            'Consulta al asistente virtual',
        );

        return ['estado' => 200, 'cuerpo' => $this->cuerpo($conversacion, $usuario, $asistente, $reporte)];
    }

    /**
     * @return array<string, mixed>
     */
    private function cuerpo(ConversacionAsistente $conversacion, MensajeAsistente $usuario, MensajeAsistente $asistente, ?ReporteResultado $reporte): array
    {
        return [
            'conversacion_id' => $conversacion->id,
            'mensaje_usuario' => $this->mensaje($usuario, null),
            'mensaje_asistente' => $this->mensaje($asistente, $reporte),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mensaje(MensajeAsistente $mensaje, ?ReporteResultado $reporte): array
    {
        return [
            'id' => $mensaje->id,
            'rol' => $mensaje->rol,
            'tipo' => $mensaje->tipo,
            'origen' => $mensaje->origen,
            'contenido' => $mensaje->contenido,
            'interpretacion' => $mensaje->interpretacion,
            'enlace' => $mensaje->enlace,
            'resultado' => $reporte?->aArray(),
            'fecha' => $mensaje->fecha?->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
        ];
    }

    /**
     * @param  Especificacion|null  $especificacion
     */
    private function guardar(
        ConversacionAsistente $conversacion,
        string $rol,
        string $tipo,
        string $contenido,
        ?string $origen = null,
        ?array $especificacion = null,
        ?string $interpretacion = null,
        ?string $enlace = null,
    ): MensajeAsistente {
        $mensaje = $conversacion->mensajes()->create([
            'rol' => $rol,
            'tipo' => $tipo,
            'contenido' => $contenido,
            'origen' => $origen ?? 'TEXTO',
            'especificacion' => $especificacion,
            'interpretacion' => $interpretacion,
            'enlace' => $enlace,
            'fecha' => now(),
        ]);

        $conversacion->forceFill(['fecha_actualizacion' => now()])->save();

        return $mensaje;
    }

    private function conversacion(User $usuario, ?int $id, string $primeraConsulta): ConversacionAsistente
    {
        if ($id !== null) {
            return ConversacionAsistente::query()->de($usuario)->findOrFail($id);
        }

        return ConversacionAsistente::query()->create([
            'usuario_id' => $usuario->id,
            'titulo' => Str::limit($primeraConsulta, 150, '…'),
            'fecha_inicio' => now(),
            'fecha_actualizacion' => now(),
        ]);
    }

    /**
     * @param  Especificacion  $espec
     * @param  Resultado  $datos
     */
    private function armarReporte(User $usuario, array $espec, array $datos): ReporteResultado
    {
        $tienda = $this->tienda->actual();
        $periodo = PeriodoReporte::resolver('personalizado', $espec['periodo']['desde'], $espec['periodo']['hasta']);
        $metrica = CatalogoAsistente::metricas()[$espec['metrica']];

        return new ReporteResultado(
            clave: 'asistente_'.$espec['metrica'],
            titulo: $metrica['etiqueta'],
            encabezado: [
                'tienda' => $tienda['nombre'],
                'ubicacion' => $tienda['ubicacion'],
                'desde' => $periodo->desde->format('d/m/Y'),
                'hasta' => $periodo->hasta->format('d/m/Y'),
                'periodo' => $espec['metrica'] === 'stock' ? 'Stock actual' : $periodo->etiqueta(),
                'usuario' => $usuario->name !== '' ? $usuario->name : $usuario->email,
                'generado' => CarbonImmutable::now((string) config('app.timezone'))->format('d/m/Y H:i'),
            ],
            columnas: $datos['columnas'],
            filas: $datos['filas'],
            totales: $datos['totales'],
            advertencias: $datos['advertencias'],
            grafico: $datos['grafico'],
            parametros: [
                'tipo' => 'asistente_'.$espec['metrica'],
                'periodo' => 'personalizado',
                'desde' => $periodo->desde->toDateString(),
                'hasta' => $periodo->hasta->toDateString(),
                'filtros' => $espec['filtros'],
            ],
        );
    }

    /**
     * @param  Resultado  $datos
     */
    private function textoDeReporte(array $datos): string
    {
        return $datos['filas'] === []
            ? self::MENSAJE_SIN_REGISTROS
            : 'Armé el reporte con lo que pediste.';
    }

    /**
     * Respuesta puntual en texto, con las cifras que calculó el sistema.
     *
     * @param  Resultado  $datos
     */
    private function textoDeConsulta(array $datos): string
    {
        $filas = $datos['filas'];

        if ($filas === []) {
            return self::MENSAJE_SIN_REGISTROS;
        }

        $columnas = $datos['columnas'];
        $valor = end($columnas);
        $tipo = $valor === false ? 'texto' : $valor['tipo'];
        $clave = $valor === false ? 'valor' : $valor['clave'];

        if (isset($filas[0]['concepto'])) {
            $lineas = array_map(
                fn (array $fila): string => $fila['concepto'].': '.Formato::valor($fila[$clave] ?? null, $tipo),
                $filas,
            );

            return implode(' · ', $lineas).' ('.$datos['interpretacion'].').';
        }

        $items = [];

        foreach (array_slice($filas, 0, 8) as $fila) {
            $etiqueta = (string) ($fila['dim1'] ?? '');
            $items[] = '• '.$etiqueta.($fila['dim2'] ?? null ? ' · '.$fila['dim2'] : '').': '.Formato::valor($fila[$clave] ?? null, $tipo);
        }

        $resto = count($filas) - count($items);

        return $datos['interpretacion'].":\n".implode("\n", $items).($resto > 0 ? "\ny $resto más." : '');
    }
}
