<?php

namespace App\Services\Asistente;

use App\Models\MensajeAsistente;
use App\Services\Reportes\Fechas;
use App\Services\Reportes\PeriodoReporte;
use App\Services\Tienda\ConfiguracionTiendaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Arma lo que se le envía a la IA. Privacidad: solo viaja lo necesario. Nombres de categorías, marcas y
 * proveedores (no son datos personales), la fecha de hoy, la moneda, el nombre y los horarios de la tienda,
 * y el historial reciente. Nunca claves, datos de pago ni datos de clientes (CI, NIT, teléfono, email, dirección).
 */
class ConstructorPrompt
{
    public const MAXIMO_HISTORIAL = 10;

    private const MARCA_ESPECIFICACION = '[Especificación aplicada: ';

    public function __construct(private ConfiguracionTiendaService $tienda) {}

    public function sistema(?CarbonImmutable $hoy = null): string
    {
        $hoy ??= PeriodoReporte::hoy();
        $tienda = $this->tienda->actual();

        $horarios = implode('; ', array_map(
            fn (array $dia): string => $dia['dia'].': '.($dia['horario'] ?? 'cerrado'),
            $tienda['horarios'],
        ));

        $metricas = [];

        foreach (CatalogoAsistente::metricas() as $clave => $metrica) {
            $metricas[] = sprintf(
                '- %s: %s Dimensiones: %s. Filtros: %s.',
                $clave,
                $metrica['descripcion'],
                implode(', ', $metrica['dimensiones']),
                implode(', ', $metrica['filtros']),
            );
        }

        $semana = $hoy->startOfWeek();
        $mesPasado = $hoy->subMonthNoOverflow();

        return implode("\n", [
            "Eres el analista de datos de {$tienda['nombre']}, una tienda de periféricos y accesorios de computación en Bolivia.",
            'Respondes solo sobre los datos del negocio y siempre devuelves un único objeto JSON con el esquema indicado, sin texto fuera del JSON.',
            '',
            'Contexto:',
            '- Hoy es '.Fechas::corta($hoy).' ('.$hoy->toDateString().'), zona horaria America/La_Paz. Moneda: '.$tienda['moneda'].'.',
            '- Horarios de atención: '.$horarios.'.',
            '- Categorías: '.$this->nombres('categoria', 'nombre').'.',
            '- Marcas: '.$this->nombres('marca', 'nombre').'.',
            '- Proveedores: '.$this->nombres('proveedor', 'razon_social').'.',
            '',
            'Reglas:',
            '- Nunca escribes SQL y nunca inventas cifras: el sistema calcula los números al ejecutar tu especificación. Tu "mensaje" es corto y no trae cifras calculadas por ti.',
            '- tipo "consulta": un dato puntual (un número o una lista corta). tipo "reporte": cuando piden desglose, comparación, tabla o gráfico. tipo "respuesta": saludos o datos de la tienda (horarios, ubicación). tipo "no_soportado": todo lo demás.',
            '- Si piden contraseñas, datos personales de clientes o del personal (CI, NIT, teléfono, email, dirección), tarjetas, SQL o modificar o borrar datos, responde tipo "no_soportado" con un mensaje amable y ejemplos válidos.',
            '- Las fechas van en formato AAAA-MM-DD. "esta semana" = '.$semana->toDateString().' a '.$semana->addDays(6)->toDateString().'; "este mes" = '.$hoy->startOfMonth()->toDateString().' a '.$hoy->endOfMonth()->toDateString().'; "el último mes" o "el mes pasado" = '.$mesPasado->startOfMonth()->toDateString().' a '.$mesPasado->endOfMonth()->toDateString().'.',
            '- Para los filtros usa los nombres tal como aparecen arriba. En los gastos, el filtro "categoria" es la categoría de gasto.',
            '- Si la persona refina la consulta anterior ("ahora compáralo con septiembre"), parte de la última especificación aplicada del historial y cambia solo lo que pidió.',
            '- Máximo dos dimensiones y solo una de tiempo. Si no hay desglose, "dimensiones" va vacío.',
            '',
            'Métricas disponibles (clave: significado, dimensiones y filtros permitidos):',
            ...$metricas,
        ]);
    }

    /**
     * Esquema JSON de la respuesta (salida estructurada).
     *
     * @return array<string, mixed>
     */
    public static function esquema(): array
    {
        $periodo = [
            'type' => 'object',
            'properties' => [
                'desde' => ['type' => 'string', 'description' => 'AAAA-MM-DD'],
                'hasta' => ['type' => 'string', 'description' => 'AAAA-MM-DD'],
            ],
            'required' => ['desde', 'hasta'],
            'additionalProperties' => false,
        ];

        $filtro = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'properties' => [
                'tipo' => ['type' => 'string', 'enum' => RespuestaIa::TIPOS],
                'mensaje' => ['type' => 'string'],
                'especificacion' => [
                    'type' => ['object', 'null'],
                    'properties' => [
                        'metrica' => ['type' => 'string', 'enum' => CatalogoAsistente::claves()],
                        'dimensiones' => [
                            'type' => 'array',
                            'maxItems' => 2,
                            'items' => ['type' => 'string', 'enum' => CatalogoAsistente::DIMENSIONES],
                        ],
                        'filtros' => [
                            'type' => 'object',
                            'properties' => array_fill_keys(CatalogoAsistente::FILTROS, $filtro),
                            'additionalProperties' => false,
                        ],
                        'periodo' => $periodo,
                        'comparar_con' => [...$periodo, 'type' => ['object', 'null']],
                        'grafico' => ['type' => 'string', 'enum' => CatalogoAsistente::GRAFICOS],
                        'orden' => ['type' => 'string', 'enum' => ['desc', 'asc']],
                        'limite' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 50],
                    ],
                    'required' => ['metrica', 'dimensiones', 'periodo'],
                    'additionalProperties' => false,
                ],
            ],
            'required' => ['tipo', 'mensaje'],
            'additionalProperties' => false,
        ];
    }

    /**
     * Los últimos mensajes como los espera la API. En las respuestas del asistente se agrega la
     * especificación que se aplicó, para que la IA pueda refinarla ("ahora compáralo con septiembre").
     *
     * @param  iterable<MensajeAsistente>  $mensajes
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    public function historial(iterable $mensajes): array
    {
        $lista = [];

        foreach ($mensajes as $mensaje) {
            if ($mensaje->rol === MensajeAsistente::USUARIO) {
                $lista[] = ['role' => 'user', 'content' => $mensaje->contenido];

                continue;
            }

            $contenido = $mensaje->contenido;

            if (is_array($mensaje->especificacion)) {
                $contenido .= ' '.self::MARCA_ESPECIFICACION.json_encode($mensaje->especificacion, JSON_UNESCAPED_UNICODE).']';
            }

            $lista[] = ['role' => 'assistant', 'content' => $contenido];
        }

        return array_slice($lista, -self::MAXIMO_HISTORIAL);
    }

    /**
     * La última especificación aplicada que aparece en el historial, o null.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $historial
     * @return array<array-key, mixed>|null
     */
    public static function ultimaEspecificacion(array $historial): ?array
    {
        foreach (array_reverse($historial) as $mensaje) {
            if ($mensaje['role'] !== 'assistant') {
                continue;
            }

            $inicio = strpos($mensaje['content'], self::MARCA_ESPECIFICACION);

            if ($inicio === false) {
                continue;
            }

            $json = substr($mensaje['content'], $inicio + strlen(self::MARCA_ESPECIFICACION), -1);
            $datos = json_decode($json, true);

            if (is_array($datos)) {
                return $datos;
            }
        }

        return null;
    }

    private function nombres(string $tabla, string $columna): string
    {
        return DB::table($tabla)->orderBy($columna)->pluck($columna)->implode(', ');
    }
}
