<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    CheckCircle2,
    Clock,
    Download,
    MessageCircle,
    Phone,
    XCircle,
} from '@lucide/vue';
import { computed, onMounted } from 'vue';
import { bolivianos, useCarrito } from '@/composables/useCarrito';

type Cotizacion = {
    codigo: string;
    estado: 'PENDIENTE' | 'ATENDIDA' | 'RECHAZADA' | 'VENCIDA' | 'CONVERTIDA';
    fecha: string | null;
    vencimiento: string | null;
    vigente: boolean;
    total: number;
    productos: number;
    contacto: {
        nombre: string | null;
        telefono: string | null;
        email: string | null;
    };
    items: {
        descripcion: string | null;
        cantidad: number;
        precio: number;
        subtotal: number;
    }[];
    pdf: string;
};

const props = defineProps<{
    cotizacion: Cotizacion;
    whatsapp_url: string | null;
    telefono_tienda: string | null;
    nueva: boolean;
}>();

const { vaciar } = useCarrito();

/** Decisión de diseño: al generar la cotización el carrito se vacía, porque ya quedó guardada. */
onMounted(() => {
    if (props.nueva) {
        vaciar();
    }
});

const ESTADOS: Record<Cotizacion['estado'], string> = {
    PENDIENTE: 'Pendiente',
    ATENDIDA: 'Atendida',
    RECHAZADA: 'Rechazada',
    VENCIDA: 'Vencida',
    CONVERTIDA: 'Convertida en compra',
};

const cabecera = computed(() => {
    switch (props.cotizacion.estado) {
        case 'RECHAZADA':
        case 'VENCIDA':
            return {
                icono: XCircle,
                clase: 'error',
                titulo: `Cotización ${ESTADOS[props.cotizacion.estado].toLowerCase()}`,
            };
        case 'PENDIENTE':
            return {
                icono: props.nueva ? CheckCircle2 : Clock,
                clase: props.nueva ? 'ok' : 'pendiente',
                titulo: props.nueva
                    ? 'Cotización generada'
                    : 'Cotización pendiente',
            };
        default:
            return {
                icono: CheckCircle2,
                clase: 'ok',
                titulo: `Cotización ${ESTADOS[props.cotizacion.estado].toLowerCase()}`,
            };
    }
});
</script>

<template>
    <div class="cc-shop__section cc-comprobante-wrap">
        <Head :title="`Cotización ${cotizacion.codigo}`" />

        <article class="cc-comprobante">
            <header class="cc-comprobante__head" :data-estado="cabecera.clase">
                <component :is="cabecera.icono" :size="40" aria-hidden="true" />
                <div>
                    <h1>{{ cabecera.titulo }}</h1>
                    <p
                        v-if="
                            cotizacion.estado === 'PENDIENTE' ||
                            cotizacion.estado === 'ATENDIDA'
                        "
                    >
                        Envíala por WhatsApp para que la tienda la confirme. Los
                        precios valen hasta el
                        {{ cotizacion.vencimiento }}.
                    </p>
                    <p v-else-if="cotizacion.estado === 'VENCIDA'">
                        Los precios ya no están vigentes. Arma una nueva desde
                        el carrito.
                    </p>
                </div>
            </header>

            <dl class="cc-comprobante__datos">
                <div>
                    <dt>Código</dt>
                    <dd data-test="codigo">{{ cotizacion.codigo }}</dd>
                </div>
                <div>
                    <dt>Estado</dt>
                    <dd data-test="estado">{{ ESTADOS[cotizacion.estado] }}</dd>
                </div>
                <div>
                    <dt>Emitida</dt>
                    <dd>{{ cotizacion.fecha }}</dd>
                </div>
                <div>
                    <dt>Vence</dt>
                    <dd>{{ cotizacion.vencimiento }}</dd>
                </div>
                <div>
                    <dt>Contacto</dt>
                    <dd>{{ cotizacion.contacto.nombre }}</dd>
                </div>
                <div>
                    <dt>Teléfono</dt>
                    <dd>{{ cotizacion.contacto.telefono ?? '—' }}</dd>
                </div>
            </dl>

            <table class="cc-comprobante__tabla">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Cant.</th>
                        <th>Precio</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, i) in cotizacion.items" :key="i">
                        <td>{{ item.descripcion }}</td>
                        <td>{{ item.cantidad }}</td>
                        <td>{{ bolivianos(item.precio) }}</td>
                        <td>{{ bolivianos(item.subtotal) }}</td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3">Total</td>
                        <td data-test="total">
                            {{ bolivianos(cotizacion.total) }}
                        </td>
                    </tr>
                </tfoot>
            </table>

            <p class="cc-comprobante__leyenda">
                Precios válidos hasta {{ cotizacion.vencimiento }}. No reserva
                stock.
            </p>

            <footer class="cc-comprobante__acciones">
                <a
                    :href="cotizacion.pdf"
                    class="cc-button cc-button--solid"
                    data-test="descargar-pdf"
                >
                    <Download :size="16" /> Descargar PDF
                </a>
                <a
                    v-if="whatsapp_url"
                    :href="whatsapp_url"
                    target="_blank"
                    rel="noopener"
                    class="cc-button"
                    data-test="whatsapp"
                >
                    <MessageCircle :size="16" /> Enviar por WhatsApp
                </a>
                <p
                    v-else-if="telefono_tienda"
                    class="cc-comprobante__contacto"
                    data-test="telefono-tienda"
                >
                    <Phone :size="16" /> Llama a la tienda al
                    <strong>{{ telefono_tienda }}</strong> y menciona la
                    cotización {{ cotizacion.codigo }}.
                </p>
                <Link
                    href="/tienda/cotizaciones"
                    class="cc-button cc-button--ghost"
                    >Mis cotizaciones</Link
                >
                <Link href="/tienda" class="cc-button cc-button--ghost"
                    >Seguir comprando</Link
                >
            </footer>
        </article>
    </div>
</template>

<style scoped>
.cc-comprobante__leyenda {
    margin: 14px 0 0;
    border: 1px dashed var(--cc-accent);
    border-radius: 10px;
    padding: 10px 12px;
    color: var(--cc-text);
    font-size: 13px;
    font-weight: 600;
}

.cc-comprobante__contacto {
    display: flex;
    flex-basis: 100%;
    gap: 8px;
    align-items: center;
    margin: 0;
    color: var(--cc-muted);
    font-size: 13px;
}
</style>
