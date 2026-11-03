<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ChevronLeft, CreditCard, Store } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { bolivianos, useCarrito } from '@/composables/useCarrito';

const props = defineProps<{
    cliente: {
        nombre: string | null;
        documento: string;
        telefono: string | null;
        email: string;
    };
    pasarelaLista: boolean;
}>();

const { items, total } = useCarrito();
const montado = ref(false);

const form = useForm({
    items: [] as { variante_id: number; cantidad: number }[],
    nombre: props.cliente.nombre ?? '',
    telefono: props.cliente.telefono ?? '',
    observacion: '',
});

onMounted(() => {
    montado.value = true;
});

const errorItems = computed(() => {
    const errores = form.errors as Record<string, string | undefined>;

    return (
        errores.items ??
        Object.entries(errores).find(([clave]) => clave.startsWith('items.'))?.[1] ??
        null
    );
});

function continuar(): void {
    form.items = items.value.map((item) => ({
        variante_id: item.variante_id,
        cantidad: item.cantidad,
    }));
    form.post('/tienda/checkout', { preserveScroll: true });
}
</script>

<template>
    <div class="cc-shop__section">
        <Head title="Finalizar compra" />

        <nav class="cc-migas" aria-label="Ruta">
            <Link href="/tienda/carrito"><ChevronLeft :size="14" /> Volver al carrito</Link>
        </nav>

        <ol class="cc-etapas" aria-label="Etapas de la compra">
            <li class="is-done">Carrito</li>
            <li class="is-current" aria-current="step">Datos de retiro</li>
            <li>Pago con tarjeta</li>
        </ol>

        <div v-if="montado && items.length === 0" class="cc-vacio">
            <strong>Tu carrito está vacío</strong>
            <Link href="/tienda" class="cc-button cc-button--solid">Ir al catálogo</Link>
        </div>

        <form v-else class="cc-checkout" @submit.prevent="continuar">
            <section class="cc-panel">
                <h1>¿Quién retira el pedido?</h1>
                <p class="cc-panel__nota">Comprás como <b>{{ cliente.email }}</b>
                    <template v-if="cliente.documento"> · {{ cliente.documento }}</template>.
                </p>

                <label class="cc-campo">
                    <span>Nombre completo <b class="cc-req">*</b></span>
                    <input v-model="form.nombre" type="text" maxlength="150" autocomplete="name" required />
                    <small v-if="form.errors.nombre" class="cc-error">{{ form.errors.nombre }}</small>
                </label>

                <label class="cc-campo">
                    <span>Teléfono / WhatsApp <b class="cc-req">*</b></span>
                    <input v-model="form.telefono" type="tel" maxlength="15" inputmode="tel" autocomplete="tel" required />
                    <small v-if="form.errors.telefono" class="cc-error">{{ form.errors.telefono }}</small>
                </label>

                <label class="cc-campo">
                    <span>Nota para la tienda</span>
                    <textarea v-model="form.observacion" rows="3" maxlength="500" placeholder="Opcional" />
                </label>

                <div class="cc-entrega">
                    <Store :size="20" />
                    <div>
                        <strong>Retiro en tienda</strong>
                        <p>Comercial Chiriguano, Santa Cruz. Te avisamos cuando esté listo.</p>
                    </div>
                </div>
            </section>

            <aside class="cc-resumen" aria-label="Resumen del pedido">
                <h2>Tu pedido</h2>
                <ul v-if="montado" class="cc-resumen__items">
                    <li v-for="item in items" :key="item.variante_id">
                        <span>{{ item.cantidad }} × {{ item.producto }}</span>
                        <span>{{ bolivianos(item.precio * item.cantidad) }}</span>
                    </li>
                </ul>
                <dl>
                    <div class="cc-resumen__total"><dt>Total</dt><dd>{{ bolivianos(montado ? total : 0) }}</dd></div>
                </dl>

                <p v-if="errorItems" class="cc-error cc-error--bloque" role="alert">{{ errorItems }}</p>
                <p v-if="!pasarelaLista" class="cc-error cc-error--bloque" role="alert">
                    La pasarela de pagos no está configurada.
                </p>

                <button
                    type="submit"
                    class="cc-button cc-button--solid cc-button--block"
                    :disabled="form.processing || !pasarelaLista || !montado"
                >
                    <CreditCard :size="16" />
                    {{ form.processing ? 'Preparando el pago…' : 'Continuar al pago' }}
                </button>
                <p class="cc-resumen__nota">Los precios y el stock se confirman al continuar.</p>
            </aside>
        </form>
    </div>
</template>
