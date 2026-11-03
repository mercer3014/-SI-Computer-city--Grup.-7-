<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ImageOff, Lock, Minus, Plus, ShoppingCart, Trash2 } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import {
    bolivianos,
    MAXIMO_POR_PRODUCTO,
    useCarrito,
    type VarianteVigente,
} from '@/composables/useCarrito';

const page = usePage();
const { items, total, cantidadTotal, cambiarCantidad, quitar, sincronizar } = useCarrito();

const montado = ref(false);
const sincronizando = ref(false);
const avisos = ref<string[]>([]);

const invitado = computed(() => !page.props.auth?.user);

onMounted(async () => {
    montado.value = true;

    if (items.value.length === 0) {
        return;
    }

    sincronizando.value = true;

    try {
        const ids = items.value.map((item) => item.variante_id).join(',');
        const respuesta = await fetch(`/tienda/carrito/variantes?ids=${ids}`, {
            headers: { Accept: 'application/json' },
        });

        if (respuesta.ok) {
            const datos = (await respuesta.json()) as { variantes: VarianteVigente[] };
            avisos.value = sincronizar(datos.variantes);
        }
    } finally {
        sincronizando.value = false;
    }
});

function irAPagar(): void {
    router.visit('/tienda/checkout');
}
</script>

<template>
    <div class="cc-shop__section">
        <Head title="Tu carrito" />

        <header class="cc-shop__section-head">
            <div>
                <h1>Tu carrito</h1>
                <p v-if="montado">
                    {{ cantidadTotal }} {{ cantidadTotal === 1 ? 'artículo' : 'artículos' }}
                </p>
            </div>
        </header>

        <ul v-if="avisos.length" class="cc-avisos" role="status">
            <li v-for="aviso in avisos" :key="aviso">{{ aviso }}</li>
        </ul>

        <div v-if="!montado" class="cc-vacio">Cargando tu carrito…</div>

        <div v-else-if="items.length === 0" class="cc-vacio">
            <ShoppingCart :size="32" aria-hidden="true" />
            <strong>Tu carrito está vacío</strong>
            <p>Explorá el catálogo y agregá lo que necesites.</p>
            <Link href="/tienda" class="cc-button cc-button--solid">Ir al catálogo</Link>
        </div>

        <div v-else class="cc-checkout">
            <ul class="cc-lineas" :aria-busy="sincronizando">
                <li v-for="item in items" :key="item.variante_id" class="cc-linea">
                    <Link :href="`/tienda/producto/${item.slug}`" class="cc-linea__media">
                        <img v-if="item.imagen" :src="item.imagen" :alt="item.producto" width="88" height="88" />
                        <ImageOff v-else :size="24" aria-hidden="true" />
                    </Link>
                    <div class="cc-linea__info">
                        <Link :href="`/tienda/producto/${item.slug}`">
                            <strong>{{ item.producto }}</strong>
                        </Link>
                        <small>{{ item.etiqueta ?? item.sku }} · {{ bolivianos(item.precio) }} c/u</small>
                        <small v-if="item.stock <= 5" class="cc-linea__alerta">Quedan {{ item.stock }}</small>
                    </div>
                    <div class="cc-stepper" :aria-label="`Cantidad de ${item.producto}`">
                        <button
                            type="button"
                            aria-label="Quitar una"
                            :disabled="item.cantidad <= 1"
                            @click="cambiarCantidad(item.variante_id, item.cantidad - 1)"
                        >
                            <Minus :size="14" />
                        </button>
                        <output>{{ item.cantidad }}</output>
                        <button
                            type="button"
                            aria-label="Agregar una"
                            :disabled="item.cantidad >= Math.min(item.stock, MAXIMO_POR_PRODUCTO)"
                            @click="cambiarCantidad(item.variante_id, item.cantidad + 1)"
                        >
                            <Plus :size="14" />
                        </button>
                    </div>
                    <strong class="cc-linea__subtotal">{{ bolivianos(item.precio * item.cantidad) }}</strong>
                    <button
                        type="button"
                        class="cc-linea__quitar"
                        :aria-label="`Quitar ${item.producto}`"
                        @click="quitar(item.variante_id)"
                    >
                        <Trash2 :size="16" />
                    </button>
                </li>
            </ul>

            <aside class="cc-resumen" aria-label="Resumen">
                <h2>Resumen</h2>
                <dl>
                    <div><dt>Subtotal</dt><dd>{{ bolivianos(total) }}</dd></div>
                    <div><dt>Entrega</dt><dd>Retiro en tienda · gratis</dd></div>
                    <div class="cc-resumen__total"><dt>Total</dt><dd>{{ bolivianos(total) }}</dd></div>
                </dl>
                <button
                    type="button"
                    class="cc-button cc-button--solid cc-button--block"
                    :disabled="sincronizando"
                    @click="irAPagar"
                >
                    <Lock :size="16" />
                    Ir a pagar
                </button>
                <p v-if="invitado" class="cc-resumen__nota">
                    Para pagar te pediremos iniciar sesión o crear tu cuenta. Tu carrito se mantiene.
                </p>
                <Link href="/tienda" class="cc-resumen__seguir">Seguir comprando</Link>
            </aside>
        </div>
    </div>
</template>
