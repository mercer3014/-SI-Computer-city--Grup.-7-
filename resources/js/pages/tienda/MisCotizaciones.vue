<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';
import { bolivianos } from '@/composables/useCarrito';

type Cotizacion = {
    codigo: string;
    estado: string;
    fecha: string | null;
    vencimiento: string | null;
    vigente: boolean;
    total: number;
    productos: number;
};

defineProps<{ cotizaciones: Cotizacion[] }>();

const estados: Record<string, string> = {
    PENDIENTE: 'Pendiente',
    ATENDIDA: 'Atendida',
    RECHAZADA: 'Rechazada',
    VENCIDA: 'Vencida',
    CONVERTIDA: 'Convertida',
};
</script>

<template>
    <div class="cc-shop__section">
        <Head title="Mis cotizaciones" />

        <header class="cc-shop__section-head">
            <h1>Mis cotizaciones</h1>
        </header>

        <div v-if="cotizaciones.length === 0" class="cc-vacio">
            <FileText :size="32" aria-hidden="true" />
            <strong>Todavía no generaste cotizaciones</strong>
            <p>Arma tu carrito y pide una cotización formal con un clic.</p>
            <Link href="/tienda" class="cc-button cc-button--solid"
                >Ir al catálogo</Link
            >
        </div>

        <ul v-else class="cc-pedidos" data-test="cotizaciones">
            <li v-for="c in cotizaciones" :key="c.codigo">
                <Link
                    :href="`/tienda/cotizaciones/${c.codigo}`"
                    class="cc-pedido"
                >
                    <span>
                        <strong>{{ c.codigo }}</strong>
                        <small
                            >{{ c.fecha }} · vence {{ c.vencimiento }} ·
                            {{ c.productos }}
                            {{
                                c.productos === 1 ? 'artículo' : 'artículos'
                            }}</small
                        >
                    </span>
                    <span class="cc-pedido__estado" :data-estado="c.estado">{{
                        estados[c.estado] ?? c.estado
                    }}</span>
                    <strong>{{ bolivianos(c.total) }}</strong>
                </Link>
            </li>
        </ul>
    </div>
</template>
