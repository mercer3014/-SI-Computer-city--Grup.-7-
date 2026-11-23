<script setup lang="ts">
import { Mic, MicOff } from '@lucide/vue';

defineProps<{
    escuchando: boolean;
    soportado: boolean;
}>();

defineEmits<{
    alternar: [];
}>();
</script>

<template>
    <button
        v-if="soportado"
        type="button"
        class="cc-mic"
        :class="{ 'is-escuchando': escuchando }"
        aria-label="Dictar por voz"
        :aria-pressed="escuchando"
        :title="escuchando ? 'Detener el dictado' : 'Dictar por voz'"
        @click="$emit('alternar')"
    >
        <MicOff v-if="escuchando" :size="18" />
        <Mic v-else :size="18" />
        <span class="cc-mic__texto">{{
            escuchando ? 'Escuchando…' : 'Dictar'
        }}</span>
    </button>
</template>

<style scoped>
.cc-mic {
    position: relative;
    display: inline-flex;
    gap: 8px;
    align-items: center;
    padding: 10px 16px;
    border: 1px solid var(--cc-border);
    border-radius: 999px;
    background: transparent;
    color: var(--cc-text);
    font: inherit;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition:
        border-color 140ms ease,
        background 140ms ease;
}

.cc-mic:hover,
.cc-mic:focus-visible {
    border-color: var(--cc-accent);
}

.cc-mic.is-escuchando {
    border-color: var(--cc-error);
    background: color-mix(in srgb, var(--cc-error) 10%, transparent);
    color: var(--cc-error);
    animation: cc-mic-pulso 1.2s ease-in-out infinite;
}

@keyframes cc-mic-pulso {
    0%,
    100% {
        box-shadow: 0 0 0 0 color-mix(in srgb, var(--cc-error) 35%, transparent);
    }

    50% {
        box-shadow: 0 0 0 8px transparent;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cc-mic.is-escuchando {
        animation: none;
    }
}
</style>
