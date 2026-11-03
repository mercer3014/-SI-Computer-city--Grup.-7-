<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import type { Stripe, StripeElements } from '@stripe/stripe-js';
import { ChevronLeft, Lock, ShieldCheck } from '@lucide/vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useAppearance } from '@/composables/useAppearance';
import { bolivianos, useCarrito } from '@/composables/useCarrito';

type Pedido = {
    numero: string;
    total: number;
    contacto: string | null;
    telefono: string | null;
    entrega: string;
    error_pago: string | null;
    items: { producto: string | null; variante: string | null; cantidad: number; subtotal: number }[];
};

const props = defineProps<{
    pedido: Pedido;
    stripe: { clave: string; clientSecret: string | null };
    errorConexion: boolean;
}>();

const { resolvedAppearance } = useAppearance();
const { vaciar } = useCarrito();

const contenedor = ref<HTMLElement | null>(null);
const cargando = ref(true);
const procesando = ref(false);
const listo = ref(false);
const error = ref<string | null>(
    props.errorConexion || !props.stripe.clientSecret
        ? 'No pudimos conectar con la pasarela de pagos. Recargá la página para reintentar.'
        : null,
);

let stripe: Stripe | null = null;
let elements: StripeElements | null = null;

function estilos(): Record<string, string> {
    const raiz = getComputedStyle(contenedor.value ?? document.body);
    const variable = (nombre: string, respaldo: string) =>
        raiz.getPropertyValue(nombre).trim() || respaldo;

    return {
        colorPrimary: variable('--cc-accent', '#ff3d8f'),
        colorBackground: variable('--cc-surface', '#140c12'),
        colorText: variable('--cc-text', '#fdecf3'),
        colorTextSecondary: variable('--cc-muted', '#a88996'),
        colorDanger: variable('--cc-error', '#ff5c74'),
        fontFamily: '"IBM Plex Sans", system-ui, sans-serif',
        borderRadius: '6px',
    };
}

onMounted(async () => {
    if (!props.stripe.clientSecret || !props.stripe.clave) {
        cargando.value = false;

        return;
    }

    try {
        const { loadStripe } = await import('@stripe/stripe-js');
        stripe = await loadStripe(props.stripe.clave, { locale: 'es' });

        if (!stripe || !contenedor.value) {
            throw new Error('Stripe no disponible');
        }

        elements = stripe.elements({
            clientSecret: props.stripe.clientSecret,
            appearance: {
                theme: resolvedAppearance.value === 'dark' ? 'night' : 'stripe',
                variables: estilos(),
            },
        });

        const payment = elements.create('payment', {
            layout: 'tabs',
            fields: { billingDetails: { name: 'auto' } },
        });
        payment.on('ready', () => {
            cargando.value = false;
        });
        payment.on('change', (evento) => {
            listo.value = evento.complete;

            if (evento.complete) {
                error.value = null;
            }
        });
        payment.mount(contenedor.value);
    } catch {
        cargando.value = false;
        error.value = 'No pudimos cargar el formulario de pago. Revisá tu conexión y recargá la página.';
    }
});

onBeforeUnmount(() => {
    elements?.getElement('payment')?.destroy();
});

async function pagar(): Promise<void> {
    if (!stripe || !elements || procesando.value) {
        return;
    }

    procesando.value = true;
    error.value = null;

    const { error: rechazo, paymentIntent } = await stripe.confirmPayment({
        elements,
        redirect: 'if_required',
        confirmParams: {
            return_url: `${window.location.origin}/tienda/pedidos/${props.pedido.numero}`,
        },
    });

    if (rechazo) {
        error.value =
            rechazo.message ?? 'La tarjeta fue rechazada. Probá con otra tarjeta.';
        procesando.value = false;
        router.post(
            `/tienda/pedidos/${props.pedido.numero}/fallo`,
            { mensaje: error.value },
            { preserveScroll: true, preserveState: true },
        );

        return;
    }

    if (paymentIntent?.status !== 'succeeded' && paymentIntent?.status !== 'processing') {
        error.value = 'El pago no se completó. Intentá de nuevo.';
        procesando.value = false;

        return;
    }

    router.post(
        `/tienda/pedidos/${props.pedido.numero}/confirmar`,
        {},
        {
            onSuccess: () => vaciar(),
            onError: (errores) => {
                error.value = errores.pago ?? 'No pudimos confirmar el pago. Escribinos y lo revisamos.';
                procesando.value = false;
            },
        },
    );
}
</script>

<template>
    <div class="cc-shop__section">
        <Head :title="`Pagar pedido ${pedido.numero}`" />

        <nav class="cc-migas" aria-label="Ruta">
            <Link href="/tienda/checkout"><ChevronLeft :size="14" /> Cambiar datos de retiro</Link>
        </nav>

        <ol class="cc-etapas" aria-label="Etapas de la compra">
            <li class="is-done">Carrito</li>
            <li class="is-done">Datos de retiro</li>
            <li class="is-current" aria-current="step">Pago con tarjeta</li>
        </ol>

        <div class="cc-checkout">
            <section class="cc-panel">
                <h1>Pago con tarjeta</h1>
                <p class="cc-panel__nota">
                    Pedido <b>{{ pedido.numero }}</b>. Ingresá los datos de tu tarjeta de crédito o débito.
                </p>

                <p v-if="pedido.error_pago && !error" class="cc-error cc-error--bloque" role="status">
                    Intento anterior rechazado: {{ pedido.error_pago }}
                </p>

                <div class="cc-pago">
                    <div v-if="cargando" class="cc-pago__cargando">Cargando formulario seguro…</div>
                    <div ref="contenedor" class="cc-pago__elemento" :class="{ 'is-hidden': cargando }" />
                </div>

                <p v-if="error" class="cc-error cc-error--bloque" role="alert">{{ error }}</p>

                <button
                    type="button"
                    class="cc-button cc-button--solid cc-button--block"
                    :disabled="!listo || procesando || cargando"
                    @click="pagar"
                >
                    <Lock :size="16" />
                    {{ procesando ? 'Procesando pago…' : `Pagar ${bolivianos(pedido.total)}` }}
                </button>

                <p class="cc-pago__seguro">
                    <ShieldCheck :size="16" />
                    El cobro lo procesa Stripe. Computer City no guarda los datos de tu tarjeta.
                </p>
            </section>

            <aside class="cc-resumen" aria-label="Resumen del pedido">
                <h2>Pedido {{ pedido.numero }}</h2>
                <ul class="cc-resumen__items">
                    <li v-for="(item, i) in pedido.items" :key="i">
                        <span>{{ item.cantidad }} × {{ item.producto }}</span>
                        <span>{{ bolivianos(item.subtotal) }}</span>
                    </li>
                </ul>
                <dl>
                    <div><dt>Retira</dt><dd>{{ pedido.contacto }}</dd></div>
                    <div><dt>Entrega</dt><dd>{{ pedido.entrega }}</dd></div>
                    <div class="cc-resumen__total"><dt>Total</dt><dd>{{ bolivianos(pedido.total) }}</dd></div>
                </dl>
            </aside>
        </div>
    </div>
</template>
