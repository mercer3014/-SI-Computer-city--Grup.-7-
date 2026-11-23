<script setup lang="ts">
import { Star } from '@lucide/vue';

withDefaults(
    defineProps<{
        valor: number;
        tamano?: number;
    }>(),
    { tamano: 16 },
);
</script>

<template>
    <span
        class="cc-estrellas"
        role="img"
        :aria-label="`${valor} de 5 estrellas`"
    >
        <Star
            v-for="n in 5"
            :key="n"
            :size="tamano"
            :class="{ 'is-llena': n <= Math.round(valor) }"
            aria-hidden="true"
        />
    </span>
</template>
