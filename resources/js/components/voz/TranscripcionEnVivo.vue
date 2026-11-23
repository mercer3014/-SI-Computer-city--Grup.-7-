<script setup lang="ts">
defineProps<{
    parcial: string;
    final: string;
    escuchando: boolean;
}>();
</script>

<template>
    <p
        v-if="escuchando || parcial || final"
        class="cc-transcripcion"
        role="status"
        aria-live="polite"
        data-test="transcripcion"
    >
        <span class="cc-transcripcion__etiqueta">
            {{ escuchando ? 'Escuchando' : 'Dictado' }}
        </span>
        <span v-if="final && !parcial">{{ final }}</span>
        <span v-else>
            {{ parcial
            }}<i v-if="escuchando" class="cc-transcripcion__cursor" />
        </span>
        <span v-if="escuchando && !parcial" class="cc-transcripcion__espera">
            Habla ahora…
        </span>
    </p>
</template>

<style scoped>
.cc-transcripcion {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: baseline;
    min-height: 24px;
    margin: 0;
    color: var(--cc-text);
    font-size: 15px;
}

.cc-transcripcion__etiqueta {
    padding: 2px 8px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--cc-accent) 16%, transparent);
    color: var(--cc-accent);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.cc-transcripcion__espera {
    color: var(--cc-muted);
}

.cc-transcripcion__cursor {
    display: inline-block;
    width: 2px;
    height: 1em;
    margin-left: 2px;
    background: var(--cc-accent);
    vertical-align: text-bottom;
    animation: cc-cursor 900ms steps(1) infinite;
}

@keyframes cc-cursor {
    50% {
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cc-transcripcion__cursor {
        animation: none;
    }
}
</style>
