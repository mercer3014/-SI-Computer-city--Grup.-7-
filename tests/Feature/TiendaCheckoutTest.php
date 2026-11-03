<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\User;
use App\Models\VarianteProducto;
use App\Models\Venta;
use App\Services\Pagos\PasarelaPagos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Fakes\PasarelaFalsa;
use Tests\TestCase;

class TiendaCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private PasarelaFalsa $pasarela;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pasarela = new PasarelaFalsa;
        $this->app->instance(PasarelaPagos::class, $this->pasarela);
    }

    public function test_guest_is_sent_to_login_when_trying_to_pay(): void
    {
        $this->get('/tienda/checkout')->assertRedirect(route('login'));

        $this->get(route('login'))->assertInertia(fn (Assert $page) => $page->where('desdeTienda', true));
    }

    public function test_web_client_can_register_and_returns_to_checkout(): void
    {
        $this->get('/tienda/checkout');

        $this->post('/registro', [
            'nombre' => 'Lucía Vaca Aguilar',
            'tipo_documento' => 'CI',
            'numero_documento' => '7788990',
            'telefono' => '76543210',
            'email' => 'Lucia@Correo.com',
            'password' => 'ClaveSegura123!',
            'password_confirmation' => 'ClaveSegura123!',
        ])->assertRedirect('/tienda/checkout');

        $user = User::query()->where('email', 'lucia@correo.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->esClienteWeb());
        $this->assertFalse((bool) $user->primer_login);
        $this->assertDatabaseHas('cliente', ['id' => $user->cliente_id, 'numero_documento' => '7788990']);

        $this->get('/dashboard')->assertRedirect(route('tienda.index'));
    }

    public function test_document_with_existing_account_cannot_register_again(): void
    {
        $user = $this->clienteWeb();

        $this->post('/registro', [
            'nombre' => 'Otra Persona',
            'tipo_documento' => 'CI',
            'numero_documento' => $user->cliente->numero_documento,
            'email' => 'otra@correo.com',
            'password' => 'ClaveSegura123!',
            'password_confirmation' => 'ClaveSegura123!',
        ])->assertSessionHasErrors('numero_documento');
    }

    public function test_client_pays_with_card_and_order_becomes_a_sale(): void
    {
        $user = $this->clienteWeb();
        $variante = $this->variante(precio: 125, stock: 5);

        $this->actingAs($user)->post('/tienda/checkout', [
            'items' => [['variante_id' => $variante->id, 'cantidad' => 2]],
            'nombre' => 'Lucía Vaca',
            'telefono' => '76543210',
        ])->assertRedirect();

        $pedido = Pedido::query()->firstOrFail();
        $this->assertSame(Pedido::PENDIENTE_PAGO, $pedido->estado);
        $this->assertSame(250.0, $pedido->total);
        $this->assertSame('pi_test_'.$pedido->id, $pedido->stripe_payment_intent_id);
        $this->assertSame(5, $variante->fresh()->stock_actual);

        $this->actingAs($user)->get("/tienda/pedidos/{$pedido->numero_pedido}/pago")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tienda/Pago')
                ->where('stripe.clave', 'pk_test_falsa')
                ->where('stripe.clientSecret', $pedido->stripe_payment_intent_id.'_secret_abc'));

        $this->pasarela->aprobar($pedido->stripe_payment_intent_id);

        $this->actingAs($user)->post("/tienda/pedidos/{$pedido->numero_pedido}/confirmar")
            ->assertRedirect(route('tienda.pedidos.show', [$pedido->numero_pedido, 'nuevo' => 1]));

        $pedido->refresh();
        $venta = Venta::query()->where('pedido_id', $pedido->id)->firstOrFail();

        $this->assertSame(Pedido::PAGADO, $pedido->estado);
        $this->assertSame(250.0, $venta->total());
        $this->assertSame(3, $variante->fresh()->stock_actual);
        $this->assertDatabaseHas('pago_venta', [
            'venta_id' => $venta->id,
            'referencia' => $pedido->stripe_payment_intent_id,
        ]);

        $this->actingAs($user)->post("/tienda/pedidos/{$pedido->numero_pedido}/confirmar");
        $this->assertSame(1, Venta::query()->where('pedido_id', $pedido->id)->count());
    }

    public function test_declined_card_keeps_order_pending_and_allows_retry(): void
    {
        $user = $this->clienteWeb();
        $variante = $this->variante(precio: 90, stock: 3);

        $this->actingAs($user)->post('/tienda/checkout', [
            'items' => [['variante_id' => $variante->id, 'cantidad' => 1]],
            'nombre' => 'Lucía Vaca',
            'telefono' => '76543210',
        ]);

        $pedido = Pedido::query()->firstOrFail();
        $this->pasarela->rechazar($pedido->stripe_payment_intent_id, 'Tu tarjeta no tiene fondos suficientes.');

        $this->actingAs($user)->post("/tienda/pedidos/{$pedido->numero_pedido}/confirmar")
            ->assertSessionHasErrors(['pago' => 'Tu tarjeta no tiene fondos suficientes.']);

        $this->assertSame(Pedido::PENDIENTE_PAGO, $pedido->fresh()->estado);
        $this->assertSame(0, Venta::query()->count());
        $this->assertSame(3, $variante->fresh()->stock_actual);

        $this->actingAs($user)->get("/tienda/pedidos/{$pedido->numero_pedido}/pago")->assertOk();
    }

    public function test_checkout_rejects_quantities_above_stock(): void
    {
        $user = $this->clienteWeb();
        $variante = $this->variante(precio: 90, stock: 1);

        $this->actingAs($user)->post('/tienda/checkout', [
            'items' => [['variante_id' => $variante->id, 'cantidad' => 3]],
            'nombre' => 'Lucía Vaca',
            'telefono' => '76543210',
        ])->assertSessionHasErrors('items');

        $this->assertSame(0, Pedido::query()->count());
    }

    public function test_webhook_confirms_payment_when_browser_does_not_return(): void
    {
        $user = $this->clienteWeb();
        $variante = $this->variante(precio: 60, stock: 4);

        $this->actingAs($user)->post('/tienda/checkout', [
            'items' => [['variante_id' => $variante->id, 'cantidad' => 1]],
            'nombre' => 'Lucía Vaca',
            'telefono' => '76543210',
        ]);

        $pedido = Pedido::query()->firstOrFail();
        $this->pasarela->aprobar($pedido->stripe_payment_intent_id);
        auth()->logout();

        $payload = json_encode(['type' => 'payment_intent.succeeded', 'id' => $pedido->stripe_payment_intent_id]);

        $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'firma-mala'], $payload)
            ->assertStatus(400);

        $this->call('POST', '/stripe/webhook', [], [], [], ['HTTP_STRIPE_SIGNATURE' => 'firma-valida'], $payload)
            ->assertOk();

        $this->assertSame(Pedido::PAGADO, $pedido->fresh()->estado);
        $this->assertSame(3, $variante->fresh()->stock_actual);
    }

    public function test_orders_are_private_to_their_owner(): void
    {
        $duena = $this->clienteWeb();
        $otro = $this->clienteWeb('8899001', 'otro@correo.com');
        $variante = $this->variante(precio: 60, stock: 4);

        $this->actingAs($duena)->post('/tienda/checkout', [
            'items' => [['variante_id' => $variante->id, 'cantidad' => 1]],
            'nombre' => 'Lucía Vaca',
            'telefono' => '76543210',
        ]);

        $pedido = Pedido::query()->firstOrFail();

        $this->actingAs($otro)->get("/tienda/pedidos/{$pedido->numero_pedido}")->assertNotFound();
    }

    private function clienteWeb(string $documento = '7788990', string $email = 'lucia@correo.com'): User
    {
        $cliente = Cliente::query()->create([
            'tipo_documento' => 'CI',
            'numero_documento' => $documento,
            'nombre' => 'Lucía Vaca Aguilar',
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

    private function variante(float $precio, int $stock): VarianteProducto
    {
        $categoriaId = DB::table('categoria')->insertGetId([
            'nombre' => 'Mouse',
            'slug' => 'mouse-'.uniqid(),
            'activo' => true,
        ]);

        $producto = Producto::query()->create([
            'categoria_id' => $categoriaId,
            'nombre' => 'Mouse Logitech G203',
            'slug' => 'mouse-'.uniqid(),
            'estado' => 'ACTIVO',
        ]);

        return $producto->variantes()->create([
            'sku' => 'MOU-'.uniqid(),
            'precio_venta' => $precio,
            'stock_actual' => $stock,
            'stock_minimo' => 1,
            'estado' => 'ACTIVO',
        ]);
    }
}
