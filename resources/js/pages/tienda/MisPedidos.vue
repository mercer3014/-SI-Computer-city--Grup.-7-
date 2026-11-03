<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Package } from '@lucide/vue';
import { bolivianos } from '@/composables/useCarrito';

type Pedido = {
    numero: string;
    estado: string;
    fecha: string | null;
    total: number;
    venta: string | null;
    items: { cantidad: number }[];
};

defineProps<{ pedidos: Pedido[] }>();

const estados: Record<string, string> = {
    PAGADO: 'Pagado',
    PENDIENTE_PAGO: 'Pago pendiente',
    REEMBOLSADO: 'Reembolsado',
};
</script>

<template>
    <div class="cc-shop__section">
        <Head title="Mis pedidos" />

        <header class="cc-shop__section-head">
            <h1>Mis pedidos</h1>
        </header>

        <div v-if="pedidos.length === 0" class="cc-vacio">
            <Package :size="32" aria-hidden="true" />
            <strong>Todavía no hiciste pedidos</strong>
            <Link href="/tienda" class="cc-button cc-button--solid">Ir al catálogo</Link>
        </div>

        <ul v-else class="cc-pedidos">
            <li v-for="p in pedidos" :key="p.numero">
                <Link :href="`/tienda/pedidos/${p.numero}`" class="cc-pedido">
                    <span>
                        <strong>{{ p.numero }}</strong>
                        <small>{{ p.fecha }} · {{ p.items.reduce((s, i) => s + i.cantidad, 0) }} artículos</small>
                    </span>
                    <span class="cc-pedido__estado" :data-estado="p.estado">{{ estados[p.estado] ?? p.estado }}</span>
                    <strong>{{ bolivianos(p.total) }}</strong>
                </Link>
            </li>
        </ul>
    </div>
</template>
