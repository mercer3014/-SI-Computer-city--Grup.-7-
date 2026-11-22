<?php

namespace Tests\Feature;

use App\Models\Calificacion;
use App\Models\Cliente;
use App\Models\DetallePedido;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Services\Calificaciones\CalificacionService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\CreaAdministrador;
use Tests\TestCase;

class CalificacionTest extends TestCase
{
    use CreaAdministrador;
    use RefreshDatabase;

    private int $secuenciaPedido = 0;

    /* ── Calificar (cliente) ──────────────────────────────────────────────── */

    public function test_el_cliente_califica_un_producto_de_su_pedido_pagado(): void
    {
        $cliente = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203');
        $pedido = $this->pedido($cliente, [$variante]);

        $this->actingAs($cliente)
            ->from(route('tienda.pedidos.show', $pedido->numero_pedido))
            ->post(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), [
                'producto_id' => $variante->producto_id,
                'puntuacion' => 5,
                'comentario' => '  Muy buen mouse  ',
            ])
            ->assertRedirect(route('tienda.pedidos.show', $pedido->numero_pedido))
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Gracias por tu calificación']);

        $calificacion = Calificacion::query()->firstOrFail();

        $this->assertSame(Calificacion::PENDIENTE, $calificacion->estado);
        $this->assertSame(5, $calificacion->puntuacion);
        $this->assertSame('Muy buen mouse', $calificacion->comentario);
        $this->assertSame($pedido->id, $calificacion->pedido_id);
        $this->assertSame($variante->producto_id, $calificacion->producto_id);
        $this->assertSame($cliente->id, $calificacion->usuario_id);
        $this->assertSame($cliente->cliente_id, $calificacion->cliente_id);
        $this->assertNull($calificacion->moderado_por);

        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'CALIFICAR_PRODUCTO',
            'tipo_entidad' => 'calificacion',
            'entidad_id' => $calificacion->id,
            'usuario_id' => $cliente->id,
        ]);
    }

    public function test_el_comentario_es_opcional(): void
    {
        $cliente = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203');
        $pedido = $this->pedido($cliente, [$variante]);

        $this->actingAs($cliente)
            ->post(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), [
                'producto_id' => $variante->producto_id,
                'puntuacion' => 3,
                'comentario' => '   ',
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull(Calificacion::query()->firstOrFail()->comentario);
    }

    public function test_el_invitado_va_al_login(): void
    {
        $this->post(route('tienda.pedidos.calificaciones.store', 'PED-2026-0001'), [])->assertRedirect(route('login'));
    }

    public function test_un_pedido_pendiente_de_pago_se_rechaza(): void
    {
        $cliente = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203');
        $pedido = $this->pedido($cliente, [$variante], Pedido::PENDIENTE_PAGO);

        $datos = ['producto_id' => $variante->producto_id, 'puntuacion' => 4];

        $this->actingAs($cliente)
            ->post(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), $datos)
            ->assertSessionHasErrors(['pedido' => 'Solo puedes calificar productos de pedidos pagados.']);

        $this->actingAs($cliente)
            ->postJson(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), $datos)
            ->assertStatus(422);

        $this->assertSame(0, Calificacion::query()->count());
    }

    public function test_un_pedido_de_otro_cliente_da_403(): void
    {
        $duena = $this->clienteWeb();
        $intruso = $this->clienteWeb('8899001', 'otro@correo.com', 'Pedro Gómez');
        $variante = $this->variante('Mouse Logitech G203');
        $pedido = $this->pedido($duena, [$variante]);

        $this->actingAs($intruso)
            ->post(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), [
                'producto_id' => $variante->producto_id,
                'puntuacion' => 1,
            ])
            ->assertForbidden();

        $this->assertSame(0, Calificacion::query()->count());
    }

    public function test_un_pedido_que_no_existe_da_404(): void
    {
        $this->actingAs($this->clienteWeb())
            ->post(route('tienda.pedidos.calificaciones.store', 'PED-2026-9999'), ['producto_id' => 1, 'puntuacion' => 5])
            ->assertNotFound();
    }

    public function test_un_producto_que_no_esta_en_el_pedido_da_422(): void
    {
        $cliente = $this->clienteWeb();
        $comprado = $this->variante('Mouse Logitech G203');
        $ajeno = $this->variante('Teclado Redragon K552');
        $pedido = $this->pedido($cliente, [$comprado]);

        $datos = ['producto_id' => $ajeno->producto_id, 'puntuacion' => 5];

        $this->actingAs($cliente)
            ->post(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), $datos)
            ->assertSessionHasErrors('producto_id');

        $this->actingAs($cliente)
            ->postJson(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), $datos)
            ->assertStatus(422);

        $this->assertSame(0, Calificacion::query()->count());
    }

    public function test_no_se_puede_calificar_dos_veces_el_mismo_producto_en_un_pedido(): void
    {
        $cliente = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203');
        $pedido = $this->pedido($cliente, [$variante]);
        $ruta = route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido);

        $this->actingAs($cliente)->post($ruta, ['producto_id' => $variante->producto_id, 'puntuacion' => 5])->assertSessionHasNoErrors();

        $this->actingAs($cliente)
            ->post($ruta, ['producto_id' => $variante->producto_id, 'puntuacion' => 1])
            ->assertSessionHasErrors(['producto_id' => 'Ya calificaste este producto.']);

        $this->assertSame(1, Calificacion::query()->count());
        $this->assertSame(5, Calificacion::query()->firstOrFail()->puntuacion);
    }

    public function test_el_mismo_producto_se_puede_calificar_en_otro_pedido(): void
    {
        $cliente = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203');
        $primero = $this->pedido($cliente, [$variante]);
        $segundo = $this->pedido($cliente, [$variante]);

        foreach ([$primero, $segundo] as $pedido) {
            $this->actingAs($cliente)
                ->post(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), ['producto_id' => $variante->producto_id, 'puntuacion' => 4])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, Calificacion::query()->count());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function datosInvalidos(): array
    {
        return [
            'puntuación 0' => [['puntuacion' => 0], 'puntuacion'],
            'puntuación 6' => [['puntuacion' => 6], 'puntuacion'],
            'puntuación vacía' => [['puntuacion' => ''], 'puntuacion'],
            'puntuación con texto' => [['puntuacion' => 'cinco'], 'puntuacion'],
            'comentario de 501 caracteres' => [['puntuacion' => 4, 'comentario' => str_repeat('a', 501)], 'comentario'],
        ];
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    #[DataProvider('datosInvalidos')]
    public function test_los_datos_invalidos_no_se_guardan(array $datos, string $campo): void
    {
        $cliente = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203');
        $pedido = $this->pedido($cliente, [$variante]);

        $this->actingAs($cliente)
            ->post(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), ['producto_id' => $variante->producto_id, ...$datos])
            ->assertSessionHasErrors($campo);

        $this->assertSame(0, Calificacion::query()->count());
    }

    public function test_un_comentario_de_500_caracteres_si_se_guarda(): void
    {
        $cliente = $this->clienteWeb();
        $variante = $this->variante('Mouse Logitech G203');
        $pedido = $this->pedido($cliente, [$variante]);

        $this->actingAs($cliente)
            ->post(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), [
                'producto_id' => $variante->producto_id,
                'puntuacion' => 5,
                'comentario' => str_repeat('á', 500),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(500, mb_strlen((string) Calificacion::query()->firstOrFail()->comentario));
    }

    public function test_el_pedido_indica_que_productos_se_pueden_calificar_y_muestra_la_calificacion_enviada(): void
    {
        $cliente = $this->clienteWeb();
        $mouse = $this->variante('Mouse Logitech G203');
        $teclado = $this->variante('Teclado Redragon K552');
        $pedido = $this->pedido($cliente, [$mouse, $teclado]);

        $this->actingAs($cliente)
            ->get(route('tienda.pedidos.show', $pedido->numero_pedido))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tienda/Pedido')
                ->where('pedido.items.0.producto_id', $mouse->producto_id)
                ->where('pedido.items.0.puede_calificar', true)
                ->where('pedido.items.0.calificacion', null)
                ->where('pedido.items.1.puede_calificar', true));

        $this->actingAs($cliente)->post(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), [
            'producto_id' => $mouse->producto_id,
            'puntuacion' => 4,
            'comentario' => 'Cumple',
        ]);

        $this->actingAs($cliente)
            ->get(route('tienda.pedidos.show', $pedido->numero_pedido))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pedido.items.0.puede_calificar', false)
                ->where('pedido.items.0.calificacion.puntuacion', 4)
                ->where('pedido.items.0.calificacion.comentario', 'Cumple')
                ->where('pedido.items.0.calificacion.estado', Calificacion::PENDIENTE)
                ->where('pedido.items.1.puede_calificar', true)
                ->where('pedido.items.1.calificacion', null));
    }

    public function test_un_pedido_sin_pagar_no_ofrece_calificar(): void
    {
        $cliente = $this->clienteWeb();
        $pedido = $this->pedido($cliente, [$this->variante('Mouse Logitech G203')], Pedido::PENDIENTE_PAGO);

        $this->actingAs($cliente)
            ->get(route('tienda.pedidos.show', $pedido->numero_pedido))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pedido.items.0.puede_calificar', false)
                ->where('pedido.items.0.calificacion', null));
    }

    public function test_si_el_pedido_repite_el_producto_con_otra_variante_solo_la_primera_linea_lo_ofrece(): void
    {
        $cliente = $this->clienteWeb();
        $negro = $this->variante('Mouse Logitech G203');
        $blanco = $negro->producto->variantes()->create([
            'sku' => 'MOU-BLANCO',
            'precio_venta' => 100,
            'stock_actual' => 10,
            'stock_minimo' => 1,
            'estado' => 'ACTIVO',
        ]);
        $pedido = $this->pedido($cliente, [$negro, $blanco]);

        $this->actingAs($cliente)
            ->get(route('tienda.pedidos.show', $pedido->numero_pedido))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pedido.items.0.puede_calificar', true)
                ->where('pedido.items.1.puede_calificar', false));
    }

    /* ── Moderar (administrador) ──────────────────────────────────────────── */

    public function test_el_administrador_publica_y_la_opinion_aparece_en_el_catalogo_con_el_promedio(): void
    {
        $variante = $this->variante('Mouse Logitech G203');
        $moderador = $this->moderador();

        $primera = $this->calificar('Lucía Vaca Aguilar', '7788990', $variante, 5, 'Excelente');
        $segunda = $this->calificar('Pedro Gómez', '8899001', $variante, 4, 'Muy bueno');

        $this->get(route('tienda.producto', $variante->producto->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('calificaciones.promedio', null)
                ->where('calificaciones.cantidad', 0)
                ->where('calificaciones.items', []));

        $this->actingAs($moderador)->patch(route('calificaciones.publicar', $primera))->assertRedirect();
        $this->actingAs($moderador)->patch(route('calificaciones.publicar', $segunda))->assertRedirect();

        $this->assertSame(Calificacion::PUBLICADA, $primera->fresh()->estado);
        $this->assertSame($moderador->id, $primera->fresh()->moderado_por);
        $this->assertNotNull($primera->fresh()->fecha_moderacion);

        auth()->logout();

        $this->get(route('tienda.producto', $variante->producto->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tienda/Producto')
                ->where('calificaciones.promedio', 4.5)
                ->where('calificaciones.cantidad', 2)
                ->has('calificaciones.items', 2)
                ->where('calificaciones.items.0.nombre', 'Pedro')
                ->where('calificaciones.items.0.puntuacion', 4)
                ->where('calificaciones.items.0.comentario', 'Muy bueno')
                ->where('calificaciones.items.1.nombre', 'Lucía'));

        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'PUBLICAR_CALIFICACION',
            'entidad_id' => $primera->id,
            'usuario_id' => $moderador->id,
        ]);
    }

    public function test_las_pendientes_no_se_ven_ni_cuentan(): void
    {
        $variante = $this->variante('Mouse Logitech G203');
        $publicada = $this->calificar('Lucía Vaca Aguilar', '7788990', $variante, 5, 'Excelente');
        $this->calificar('Pedro Gómez', '8899001', $variante, 1, 'Malo');

        $this->actingAs($this->moderador())->patch(route('calificaciones.publicar', $publicada));
        auth()->logout();

        $this->get(route('tienda.producto', $variante->producto->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('calificaciones.promedio', 5)
                ->where('calificaciones.cantidad', 1)
                ->has('calificaciones.items', 1));
    }

    public function test_ocultar_sin_motivo_da_error_y_no_cambia_nada(): void
    {
        $variante = $this->variante('Mouse Logitech G203');
        $calificacion = $this->calificar('Lucía Vaca Aguilar', '7788990', $variante, 2, 'Regular');
        $moderador = $this->moderador();

        $this->actingAs($moderador)->patch(route('calificaciones.ocultar', $calificacion), [])->assertSessionHasErrors('motivo');
        $this->actingAs($moderador)->patch(route('calificaciones.ocultar', $calificacion), ['motivo' => '    '])->assertSessionHasErrors('motivo');
        $this->actingAs($moderador)->patch(route('calificaciones.ocultar', $calificacion), ['motivo' => 'abc'])->assertSessionHasErrors('motivo');

        $this->assertSame(Calificacion::PENDIENTE, $calificacion->fresh()->estado);
        $this->assertDatabaseMissing('bitacora_auditoria', ['accion' => 'OCULTAR_CALIFICACION']);
    }

    public function test_ocultar_con_motivo_la_deja_oculta_y_deja_de_contar_en_el_promedio(): void
    {
        $variante = $this->variante('Mouse Logitech G203');
        $buena = $this->calificar('Lucía Vaca Aguilar', '7788990', $variante, 5, 'Excelente');
        $mala = $this->calificar('Pedro Gómez', '8899001', $variante, 1, 'Insulto al vendedor');
        $moderador = $this->moderador();

        $this->actingAs($moderador)->patch(route('calificaciones.publicar', $buena));
        $this->actingAs($moderador)->patch(route('calificaciones.publicar', $mala));

        $servicio = app(CalificacionService::class);
        $this->assertSame(['promedio' => 3.0, 'cantidad' => 2], $servicio->resumen($variante->producto_id));

        $this->actingAs($moderador)
            ->patch(route('calificaciones.ocultar', $mala), ['motivo' => 'Lenguaje ofensivo'])
            ->assertSessionHasNoErrors();

        $mala->refresh();
        $this->assertSame(Calificacion::OCULTA, $mala->estado);
        $this->assertSame('Lenguaje ofensivo', $mala->motivo_ocultamiento);
        $this->assertSame($moderador->id, $mala->moderado_por);

        $this->assertSame(['promedio' => 5.0, 'cantidad' => 1], $servicio->resumen($variante->producto_id));
        $this->assertSame(['promedio' => 5.0, 'total' => 1], $servicio->promedioGeneral());

        $this->assertDatabaseHas('bitacora_auditoria', [
            'accion' => 'OCULTAR_CALIFICACION',
            'entidad_id' => $mala->id,
            'motivo' => 'Lenguaje ofensivo',
        ]);
    }

    public function test_una_oculta_se_puede_publicar_de_nuevo(): void
    {
        $variante = $this->variante('Mouse Logitech G203');
        $calificacion = $this->calificar('Lucía Vaca Aguilar', '7788990', $variante, 4, 'Bien');
        $moderador = $this->moderador();

        $this->actingAs($moderador)->patch(route('calificaciones.ocultar', $calificacion), ['motivo' => 'Revisión pendiente']);
        $this->actingAs($moderador)->patch(route('calificaciones.publicar', $calificacion));

        $calificacion->refresh();
        $this->assertSame(Calificacion::PUBLICADA, $calificacion->estado);
        $this->assertNull($calificacion->motivo_ocultamiento);
    }

    public function test_publicar_dos_veces_no_duplica_la_bitacora(): void
    {
        $variante = $this->variante('Mouse Logitech G203');
        $calificacion = $this->calificar('Lucía Vaca Aguilar', '7788990', $variante, 4, 'Bien');
        $moderador = $this->moderador();

        $this->actingAs($moderador)->patch(route('calificaciones.publicar', $calificacion));
        $this->actingAs($moderador)->patch(route('calificaciones.publicar', $calificacion))->assertSessionHasErrors('estado');

        $this->assertSame(1, DB::table('bitacora_auditoria')->where('accion', 'PUBLICAR_CALIFICACION')->count());
    }

    public function test_el_vendedor_y_un_usuario_sin_permiso_reciben_403(): void
    {
        $variante = $this->variante('Mouse Logitech G203');
        $calificacion = $this->calificar('Lucía Vaca Aguilar', '7788990', $variante, 4, 'Bien');

        $vendedor = User::factory()->create([
            'rol_id' => $this->rolConPermisos('Vendedor', ['ventas.crear', 'cotizaciones.gestionar'])->id,
            'primer_login' => false,
        ]);
        $cliente = $this->clienteWeb('5566778', 'web@correo.com', 'Carla Rojas');

        foreach ([$vendedor, $cliente] as $usuario) {
            $this->actingAs($usuario)->get(route('calificaciones.index'))->assertForbidden();
            $this->actingAs($usuario)->patch(route('calificaciones.publicar', $calificacion))->assertForbidden();
            $this->actingAs($usuario)->patch(route('calificaciones.ocultar', $calificacion), ['motivo' => 'Sin permiso'])->assertForbidden();
        }

        $this->assertSame(Calificacion::PENDIENTE, $calificacion->fresh()->estado);

        auth()->logout();
        $this->get(route('calificaciones.index'))->assertRedirect(route('login'));
    }

    public function test_el_listado_filtra_por_estado_producto_puntuacion_y_fecha(): void
    {
        $mouse = $this->variante('Mouse Logitech G203');
        $teclado = $this->variante('Teclado Redragon K552');

        $a = $this->calificar('Lucía Vaca Aguilar', '7788990', $mouse, 5, 'Excelente');
        $b = $this->calificar('Pedro Gómez', '8899001', $mouse, 2, 'Regular');
        $c = $this->calificar('Carla Rojas', '5566778', $teclado, 5, 'Genial');

        $a->forceFill(['fecha' => '2026-10-01 10:00:00'])->save();
        $b->forceFill(['fecha' => '2026-10-15 10:00:00'])->save();
        $c->forceFill(['fecha' => '2026-10-20 10:00:00'])->save();

        $moderador = $this->moderador();
        $this->actingAs($moderador)->patch(route('calificaciones.publicar', $a));

        $ids = fn (array $filtros): array => $this->actingAs($moderador)
            ->get(route('calificaciones.index', $filtros))
            ->assertOk()
            ->viewData('page')['props']['calificaciones']['data'];

        $this->assertCount(3, $ids([]));
        $this->assertSame([$a->id], array_column($ids(['estado' => 'PUBLICADA']), 'id'));
        $this->assertEqualsCanonicalizing([$b->id, $c->id], array_column($ids(['estado' => 'PENDIENTE']), 'id'));
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_column($ids(['producto' => 'mouse']), 'id'));
        $this->assertEqualsCanonicalizing([$a->id, $c->id], array_column($ids(['puntuacion' => '5']), 'id'));
        $this->assertSame([$b->id], array_column($ids(['desde' => '2026-10-10', 'hasta' => '2026-10-16']), 'id'));
        $this->assertSame([], $ids(['estado' => 'OCULTA']));
        $this->assertCount(3, $ids(['estado' => 'invalido', 'puntuacion' => '9', 'desde' => 'ayer']));

        $this->actingAs($moderador)
            ->get(route('calificaciones.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('calificaciones/Index')
                ->where('kpis', ['total' => 3, 'pendientes' => 2, 'publicadas' => 1, 'ocultas' => 0])
                ->where('promedio', ['promedio' => 5, 'total' => 1]));
    }

    public function test_la_clave_unica_impide_calificar_dos_veces_aunque_se_salte_el_servicio(): void
    {
        $variante = $this->variante('Mouse Logitech G203');
        $calificacion = $this->calificar('Lucía Vaca Aguilar', '7788990', $variante, 4, 'Bien');

        $this->expectException(UniqueConstraintViolationException::class);

        Calificacion::query()->create([
            'cliente_id' => $calificacion->cliente_id,
            'usuario_id' => $calificacion->usuario_id,
            'pedido_id' => $calificacion->pedido_id,
            'producto_id' => $calificacion->producto_id,
            'puntuacion' => 3,
            'estado' => Calificacion::PENDIENTE,
            'fecha' => now(),
        ]);
    }

    public function test_postgresql_rechaza_puntuaciones_fuera_de_rango(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('El CHECK de puntuación solo existe en PostgreSQL.');
        }

        $variante = $this->variante('Mouse Logitech G203');
        $calificacion = $this->calificar('Lucía Vaca Aguilar', '7788990', $variante, 4, 'Bien');

        $this->expectException(QueryException::class);

        DB::table('calificacion')->where('id', $calificacion->id)->update(['puntuacion' => 6]);
    }

    /* ── Apoyo ────────────────────────────────────────────────────────────── */

    private function moderador(): User
    {
        return User::factory()->create([
            'rol_id' => $this->rolConPermisos('Moderador', ['calificaciones.gestionar'])->id,
            'primer_login' => false,
        ]);
    }

    private function clienteWeb(string $documento = '7788990', string $email = 'lucia@correo.com', string $nombre = 'Lucía Vaca Aguilar'): User
    {
        $cliente = Cliente::query()->create([
            'tipo_documento' => 'CI',
            'numero_documento' => $documento,
            'nombre' => $nombre,
            'email' => $email,
            'estado' => 'ACTIVO',
        ]);

        return User::factory()->create([
            'rol_id' => Rol::query()->where('nombre', Rol::CLIENTE_WEB)->value('id'),
            'cliente_id' => $cliente->id,
            'personal_id' => null,
            'email' => $email,
            'primer_login' => false,
        ]);
    }

    private function variante(string $nombre): VarianteProducto
    {
        $categoriaId = DB::table('categoria')->insertGetId([
            'nombre' => 'Categoría '.uniqid(),
            'slug' => 'cat-'.uniqid(),
            'activo' => true,
        ]);

        $producto = Producto::query()->create([
            'categoria_id' => $categoriaId,
            'nombre' => $nombre,
            'slug' => 'prod-'.uniqid(),
            'estado' => 'ACTIVO',
        ]);

        return $producto->variantes()->create([
            'sku' => 'SKU-'.uniqid(),
            'precio_venta' => 100,
            'stock_actual' => 10,
            'stock_minimo' => 1,
            'estado' => 'ACTIVO',
        ]);
    }

    /**
     * @param  list<VarianteProducto>  $variantes
     */
    private function pedido(User $cliente, array $variantes, string $estado = Pedido::PAGADO): Pedido
    {
        $pedido = Pedido::query()->create([
            'cliente_id' => $cliente->cliente_id,
            'usuario_id' => $cliente->id,
            'numero_pedido' => sprintf('PED-2026-%04d', ++$this->secuenciaPedido),
            'nombre_contacto' => 'Lucía Vaca',
            'telefono_contacto' => '76543210',
            'estado' => $estado,
            'origen' => Pedido::ORIGEN_TIENDA,
            'total' => 100 * count($variantes),
            'fecha' => now(),
            'fecha_pago' => $estado === Pedido::PAGADO ? now() : null,
        ]);

        foreach ($variantes as $variante) {
            DetallePedido::query()->create([
                'pedido_id' => $pedido->id,
                'variante_id' => $variante->id,
                'cantidad' => 1,
                'precio_unitario' => 100,
                'descuento' => 0,
                'nombre_producto_snapshot' => $variante->producto->nombre,
                'variante_snapshot' => null,
            ]);
        }

        return $pedido;
    }

    /**
     * Un cliente distinto compra y califica el producto: queda PENDIENTE.
     */
    private function calificar(string $nombre, string $documento, VarianteProducto $variante, int $puntuacion, string $comentario): Calificacion
    {
        $cliente = $this->clienteWeb($documento, strtolower(explode(' ', $nombre)[0]).$documento.'@correo.com', $nombre);
        $pedido = $this->pedido($cliente, [$variante]);

        $this->actingAs($cliente)
            ->post(route('tienda.pedidos.calificaciones.store', $pedido->numero_pedido), [
                'producto_id' => $variante->producto_id,
                'puntuacion' => $puntuacion,
                'comentario' => $comentario,
            ])
            ->assertSessionHasNoErrors();

        return Calificacion::query()->where('pedido_id', $pedido->id)->firstOrFail();
    }
}
