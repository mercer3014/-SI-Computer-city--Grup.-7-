<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ImageOff } from '@lucide/vue';
import { bolivianos } from '@/composables/useCarrito';

export type ProductoTarjeta = {
    id: number;
    nombre: string;
    slug: string;
    categoria: string | null;
    marca: string | null;
    precio_desde: number;
    stock: number;
    disponibilidad: 'DISPONIBLE' | 'ULTIMAS' | 'AGOTADO';
    imagen: string | null;
    imagen_alt: string;
};

defineProps<{ producto: ProductoTarjeta }>();

const etiquetas: Record<ProductoTarjeta['disponibilidad'], string> = {
    DISPONIBLE: 'Disponible',
    ULTIMAS: 'Últimas unidades',
    AGOTADO: 'Agotado',
};
</script>

<template>
    <Link
        :href="`/tienda/producto/${producto.slug}`"
        class="cc-card"
        :class="{ 'is-agotado': producto.disponibilidad === 'AGOTADO' }"
    >
        <span class="cc-card__media">
            <img
                v-if="producto.imagen"
                :src="producto.imagen"
                :alt="producto.imagen_alt"
                loading="lazy"
                width="320"
                height="320"
            />
            <ImageOff v-else :size="32" aria-hidden="true" />
            <span class="cc-card__estado" :data-estado="producto.disponibilidad">
                {{ etiquetas[producto.disponibilidad] }}
            </span>
        </span>
        <span class="cc-card__body">
            <small>{{ [producto.marca, producto.categoria].filter(Boolean).join(' · ') }}</small>
            <strong>{{ producto.nombre }}</strong>
            <span class="cc-card__precio">{{ bolivianos(producto.precio_desde) }}</span>
        </span>
    </Link>
</template>
