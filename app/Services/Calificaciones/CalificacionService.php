<?php

namespace App\Services\Calificaciones;

use App\Models\Calificacion;
use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\User;
use App\Services\BitacoraService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * CU24: calificar productos de pedidos pagados y moderarlos.
 *
 * El promedio y la cantidad de reseñas se calculan con una consulta agregada sobre las PUBLICADAS:
 * nunca hay una cifra guardada que pueda quedar desactualizada al moderar.
 */
class CalificacionService
{
    public const MODULO = 'Calificaciones';

    public const MOTIVO_MINIMO = 5;

    public const ULTIMAS_EN_CATALOGO = 10;

    /**
     * Pedido pagado, con el producto adentro y sin calificación previa de ese producto.
     */
    public function puedeCalificar(Pedido $pedido, int $productoId): bool
    {
        return $pedido->estaPagado()
            && $this->productoEnPedido($pedido, $productoId)
            && ! $this->yaCalificado($pedido, $productoId);
    }

    public function yaCalificado(Pedido $pedido, int $productoId): bool
    {
        return Calificacion::query()
            ->where('pedido_id', $pedido->id)
            ->where('producto_id', $productoId)
            ->exists();
    }

    public function productoEnPedido(Pedido $pedido, int $productoId): bool
    {
        return DetallePedido::query()
            ->where('pedido_id', $pedido->id)
            ->whereHas('variante', fn ($variante) => $variante->where('producto_id', $productoId))
            ->exists();
    }

    /**
     * La calificación nace PENDIENTE: el Administrador decide si se publica.
     *
     * @throws ValidationException EX-01 (pedido no pagado o producto ajeno al pedido) y EX-02 (ya calificado)
     */
    public function crear(User $usuario, Pedido $pedido, int $productoId, int $puntuacion, ?string $comentario): Calificacion
    {
        if (! $pedido->estaPagado()) {
            throw ValidationException::withMessages(['pedido' => 'Solo puedes calificar productos de pedidos pagados.']);
        }

        if (! $this->productoEnPedido($pedido, $productoId)) {
            throw ValidationException::withMessages(['producto_id' => 'Ese producto no es parte de este pedido.']);
        }

        $texto = $comentario === null ? null : trim($comentario);

        try {
            $calificacion = DB::transaction(function () use ($usuario, $pedido, $productoId, $puntuacion, $texto): Calificacion {
                if ($this->yaCalificado($pedido, $productoId)) {
                    throw ValidationException::withMessages(['producto_id' => 'Ya calificaste este producto.']);
                }

                return Calificacion::query()->create([
                    'cliente_id' => $pedido->cliente_id,
                    'usuario_id' => $usuario->id,
                    'pedido_id' => $pedido->id,
                    'producto_id' => $productoId,
                    'puntuacion' => $puntuacion,
                    'comentario' => $texto === '' ? null : $texto,
                    'estado' => Calificacion::PENDIENTE,
                    'fecha' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['producto_id' => 'Ya calificaste este producto.']);
        }

        BitacoraService::registrar(
            self::MODULO,
            'CALIFICAR_PRODUCTO',
            'calificacion',
            $calificacion->id,
            null,
            [
                'pedido' => $pedido->numero_pedido,
                'producto_id' => $productoId,
                'puntuacion' => $puntuacion,
                'comentario' => $calificacion->comentario,
                'estado' => Calificacion::PENDIENTE,
            ],
            'Calificación de un producto comprado',
            $usuario->id,
        );

        return $calificacion;
    }

    public function publicar(Calificacion $calificacion, User $moderador): Calificacion
    {
        if ($calificacion->estado === Calificacion::PUBLICADA) {
            throw ValidationException::withMessages(['estado' => 'La calificación ya está publicada.']);
        }

        $anterior = $calificacion->estado;

        $calificacion->forceFill([
            'estado' => Calificacion::PUBLICADA,
            'motivo_ocultamiento' => null,
            'moderado_por' => $moderador->id,
            'fecha_moderacion' => now(),
        ])->save();

        BitacoraService::registrar(
            self::MODULO,
            'PUBLICAR_CALIFICACION',
            'calificacion',
            $calificacion->id,
            ['estado' => $anterior],
            ['estado' => Calificacion::PUBLICADA, 'producto_id' => $calificacion->producto_id],
            'Calificación publicada en el catálogo',
            $moderador->id,
        );

        return $calificacion;
    }

    /**
     * @throws ValidationException EX-04: ocultar exige un motivo
     */
    public function ocultar(Calificacion $calificacion, User $moderador, string $motivo): Calificacion
    {
        $motivo = trim($motivo);

        if (mb_strlen($motivo) < self::MOTIVO_MINIMO) {
            throw ValidationException::withMessages(['motivo' => 'Indica el motivo para ocultarla (mínimo '.self::MOTIVO_MINIMO.' caracteres).']);
        }

        if ($calificacion->estado === Calificacion::OCULTA) {
            throw ValidationException::withMessages(['estado' => 'La calificación ya está oculta.']);
        }

        $anterior = $calificacion->estado;

        $calificacion->forceFill([
            'estado' => Calificacion::OCULTA,
            'motivo_ocultamiento' => $motivo,
            'moderado_por' => $moderador->id,
            'fecha_moderacion' => now(),
        ])->save();

        BitacoraService::registrar(
            self::MODULO,
            'OCULTAR_CALIFICACION',
            'calificacion',
            $calificacion->id,
            ['estado' => $anterior],
            ['estado' => Calificacion::OCULTA, 'producto_id' => $calificacion->producto_id, 'motivo' => $motivo],
            $motivo,
            $moderador->id,
        );

        return $calificacion;
    }

    /**
     * Promedio con un decimal y cantidad de las calificaciones PUBLICADAS de un producto.
     *
     * @return array{promedio: float|null, cantidad: int}
     */
    public function resumen(int $productoId): array
    {
        $fila = DB::table('calificacion')
            ->where('producto_id', $productoId)
            ->where('estado', Calificacion::PUBLICADA)
            ->selectRaw('count(*) as cantidad, avg(puntuacion) as promedio')
            ->first();

        $cantidad = (int) ($fila->cantidad ?? 0);

        return [
            'promedio' => $cantidad > 0 ? round((float) $fila->promedio, 1) : null,
            'cantidad' => $cantidad,
        ];
    }

    /**
     * Últimas calificaciones publicadas, con el nombre de pila del cliente.
     *
     * @return list<array{nombre: string, puntuacion: int, comentario: string|null, fecha: string|null}>
     */
    public function publicadas(int $productoId, int $limite = self::ULTIMAS_EN_CATALOGO): array
    {
        $calificaciones = Calificacion::query()
            ->with('cliente:id,nombre')
            ->where('producto_id', $productoId)
            ->where('estado', Calificacion::PUBLICADA)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit($limite)
            ->get();

        return array_values($calificaciones
            ->map(fn (Calificacion $c): array => [
                'nombre' => $this->nombreDePila($c->cliente->nombre),
                'puntuacion' => $c->puntuacion,
                'comentario' => $c->comentario,
                'fecha' => $c->fecha?->timezone((string) config('app.timezone'))->format('d/m/Y'),
            ])
            ->all());
    }

    /**
     * Promedio general (dos decimales) de las PUBLICADAS, para el dashboard de CU23.
     *
     * @return array{promedio: float|null, total: int}
     */
    public function promedioGeneral(): array
    {
        $fila = DB::table('calificacion')
            ->where('estado', Calificacion::PUBLICADA)
            ->selectRaw('count(*) as total, avg(puntuacion) as promedio')
            ->first();

        $total = (int) ($fila->total ?? 0);

        return [
            'promedio' => $total > 0 ? round((float) $fila->promedio, 2) : null,
            'total' => $total,
        ];
    }

    private function nombreDePila(string $nombre): string
    {
        $primero = explode(' ', trim($nombre))[0];

        return $primero !== '' ? $primero : 'Cliente';
    }
}
