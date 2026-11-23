<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    titulo: string;
    /** null = el indicador falló: se muestra "No disponible". */
    valor: string | null;
    detalle?: string;
    enlace?: string;
    /** Marca el valor como cero para atenuarlo. */
    enCero?: boolean;
}>();

const estado = computed(() => {
    if (props.valor === null) {
        return 'no-disponible';
    }

    return props.enCero ? 'en-cero' : 'normal';
});
</script>

<template>
    <component
        :is="enlace ? Link : 'div'"
        v-bind="enlace ? { href: enlace } : {}"
        class="cc-tarjeta"
        :class="`is-${estado}`"
        :data-estado="estado"
        data-test="tarjeta-indicador"
    >
        <span class="cc-tarjeta__titulo">{{ titulo }}</span>
        <b class="cc-tarjeta__valor">
            {{ valor ?? 'No disponible' }}
        </b>
        <small v-if="detalle" class="cc-tarjeta__detalle">{{ detalle }}</small>
    </component>
</template>

<style scoped>
.cc-tarjeta {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-height: 112px;
    padding: 16px 18px;
    border: 1px solid var(--cc-border);
    border-radius: 14px;
    background: color-mix(in srgb, var(--cc-text) 3%, transparent);
    color: var(--cc-text);
    text-decoration: none;
    transition:
        border-color 140ms ease,
        transform 140ms ease;
}

a.cc-tarjeta:hover,
a.cc-tarjeta:focus-visible {
    border-color: var(--cc-accent);
    transform: translateY(-2px);
}

.cc-tarjeta__titulo {
    color: var(--cc-muted);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.cc-tarjeta__valor {
    font-size: 26px;
    font-variant-numeric: tabular-nums;
    line-height: 1.15;
}

.cc-tarjeta__detalle {
    color: var(--cc-muted);
    font-size: 12px;
    line-height: 1.4;
}

.cc-tarjeta.is-en-cero .cc-tarjeta__valor {
    color: var(--cc-muted);
}

.cc-tarjeta.is-no-disponible {
    border-style: dashed;
}

.cc-tarjeta.is-no-disponible .cc-tarjeta__valor {
    color: var(--cc-muted);
    font-size: 17px;
    font-weight: 500;
}
</style>
