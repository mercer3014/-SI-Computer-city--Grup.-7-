<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Download, XCircle } from '@lucide/vue';
import { computed } from 'vue';
import { bolivianos } from '@/composables/useCarrito';

type Pedido = {
    numero: string;
    estado: string;
    fecha: string | null;
    fecha_pago: string | null;
    total: number;
    contacto: string | null;
    telefono: string | null;
    entrega: string;
    observacion: string | null;
    error_pago: string | null;
    venta: string | null;
    comprobante: string | null;
    items: { producto: string | null; variante: string | null; cantidad: number; precio: number; subtotal: number }[];
};

const props = defineProps<{ pedido: Pedido; nuevo: boolean }>();

const estado = computed(() => {
    switch (props.pedido.estado) {
        case 'PAGADO':
            return { icono: CheckCircle2, titulo: props.nuevo ? '¡Gracias por tu compra!' : 'Pedido pagado', clase: 'ok' };
        case 'PENDIENTE_PAGO':
            return { icono: Clock, titulo: 'Pago pendiente', clase: 'pendiente' };
        default:
            return { icono: XCircle, titulo: props.pedido.estado === 'REEMBOLSADO' ? 'Pago reembolsado' : 'Pedido cancelado', clase: 'error' };
    }
});
</script>

<template>
    <div class="cc-shop__section cc-comprobante-wrap">
        <Head :title="`Pedido ${pedido.numero}`" />

        <article class="cc-comprobante">
            <header class="cc-comprobante__head" :data-estado="estado.clase">
                <component :is="estado.icono" :size="40" aria-hidden="true" />
                <div>
                    <h1>{{ estado.titulo }}</h1>
                    <p v-if="pedido.estado === 'PAGADO'">
                        Tu pago fue aprobado. Te avisaremos al {{ pedido.telefono }} cuando el pedido esté listo para retirar.
                    </p>
                    <p v-else-if="pedido.estado === 'PENDIENTE_PAGO'">
                        Todavía no recibimos el pago de este pedido.
                    </p>
                    <p v-else-if="pedido.error_pago">{{ pedido.error_pago }}</p>
                </div>
            </header>

            <dl class="cc-comprobante__datos">
                <div><dt>Pedido</dt><dd>{{ pedido.numero }}</dd></div>
                <div v-if="pedido.venta"><dt>Comprobante</dt><dd>{{ pedido.venta }}</dd></div>
                <div><dt>Fecha</dt><dd>{{ pedido.fecha_pago ?? pedido.fecha }}</dd></div>
                <div><dt>Retira</dt><dd>{{ pedido.contacto }}</dd></div>
                <div><dt>Entrega</dt><dd>{{ pedido.entrega }}</dd></div>
                <div v-if="pedido.estado === 'PAGADO'"><dt>Pago</dt><dd>Tarjeta · Stripe</dd></div>
            </dl>

            <table class="cc-comprobante__tabla">
                <thead>
                    <tr><th>Producto</th><th>Cant.</th><th>Precio</th><th>Subtotal</th></tr>
                </thead>
                <tbody>
                    <tr v-for="(item, i) in pedido.items" :key="i">
                        <td>{{ item.producto }}<small v-if="item.variante">{{ item.variante }}</small></td>
                        <td>{{ item.cantidad }}</td>
                        <td>{{ bolivianos(item.precio) }}</td>
                        <td>{{ bolivianos(item.subtotal) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr><td colspan="3">Total</td><td>{{ bolivianos(pedido.total) }}</td></tr>
                </tfoot>
            </table>

            <footer class="cc-comprobante__acciones">
                <a v-if="pedido.comprobante" :href="pedido.comprobante" class="cc-button cc-button--solid">
                    <Download :size="16" /> Descargar comprobante (PDF)
                </a>
                <Link
                    v-if="pedido.estado === 'PENDIENTE_PAGO'"
                    :href="`/tienda/pedidos/${pedido.numero}/pago`"
                    class="cc-button cc-button--solid"
                >
                    Completar pago
                </Link>
                <Link href="/tienda/pedidos" class="cc-button cc-button--ghost">Mis pedidos</Link>
                <Link href="/tienda" class="cc-button cc-button--ghost">Seguir comprando</Link>
            </footer>
        </article>
    </div>
</template>
