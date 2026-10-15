<script setup lang="ts">
import { Plus } from '@lucide/vue';
import { AnimatePresence, motion } from 'motion-v';
import { computed, ref, type Component } from 'vue';
import { adminSpring } from '@/lib/adminMotion';

const props = defineProps<{
    acciones: { id: string; label: string; icon: Component }[];
}>();

const emit = defineEmits<{
    elegir: [id: string];
}>();

const abierto = ref(false);
const alto = 44;
const separacion = 12;

const orbes = computed(() => {
    const total = props.acciones.length;
    const base = 48 + separacion;

    return props.acciones.map((accion, i) => ({
        ...accion,
        x: 0,
        y: -(base + (total - 1 - i) * (alto + separacion)),
    }));
});

function elegir(id: string): void {
    abierto.value = false;
    emit('elegir', id);
}
</script>

<template>
    <div class="cc-theme cc-fab" :class="{ 'is-open': abierto }">
        <AnimatePresence>
            <motion.button
                v-if="abierto"
                key="velo"
                type="button"
                class="cc-fab__velo"
                aria-label="Cerrar menú"
                :initial="{ opacity: 0 }"
                :animate="{ opacity: 1 }"
                :exit="{ opacity: 0 }"
                :transition="{ duration: 0.18 }"
                @click="abierto = false"
            />
        </AnimatePresence>

        <AnimatePresence>
            <motion.button
                v-for="(orbe, i) in abierto ? orbes : []"
                :key="orbe.id"
                type="button"
                class="cc-button cc-fab__orbe"
                :initial="{ scale: 0.6, x: 0, y: 0, opacity: 0 }"
                :animate="{ scale: 1, x: orbe.x, y: orbe.y, opacity: 1 }"
                :exit="{ scale: 0.6, x: 0, y: 0, opacity: 0 }"
                :transition="{ ...adminSpring, delay: i * 0.05 }"
                :whilePress="{ scale: 0.96 }"
                @click="elegir(orbe.id)"
            >
                {{ orbe.label }}
                <component :is="orbe.icon" :size="16" />
            </motion.button>
        </AnimatePresence>

        <button
            type="button"
            class="cc-button cc-fab__nudo"
            :aria-expanded="abierto"
            aria-haspopup="true"
            aria-label="Acciones"
            @click="abierto = !abierto"
        >
            <motion.span
                :animate="{ rotate: abierto ? 45 : 0 }"
                :transition="adminSpring"
            >
                <Plus :size="22" stroke-width="2.25" />
            </motion.span>
        </button>
    </div>
</template>

<style>
.cc-fab {
    position: absolute;
    inset: auto 16px calc(20px + env(safe-area-inset-bottom, 0px)) auto;
    z-index: 2;
    display: block;
    width: 48px;
    height: 48px;
    margin: 0;
    pointer-events: none;
}

.cc-fab > * {
    pointer-events: auto;
}

.cc-fab__velo {
    position: fixed;
    inset: 0;
    z-index: 10040;
    border: 0;
    background: rgb(0 0 0 / 0.4);
}

.cc-fab .cc-fab__nudo,
.cc-fab .cc-fab__orbe {
    cursor: pointer;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
    box-shadow: none;
}

.cc-fab .cc-fab__nudo {
    position: relative;
    z-index: 10060;
    width: 48px;
    height: 48px;
    min-height: 48px;
    padding: 0;
    border-color: var(--cc-accent);
    border-radius: 4px;
    background: var(--cc-accent);
    color: var(--cc-on-accent);
    box-shadow: 0 10px 24px rgb(0 0 0 / 0.32);
}

.cc-fab .cc-fab__nudo::before {
    content: none;
}

.cc-fab .cc-fab__orbe {
    position: absolute;
    right: 0;
    bottom: 0;
    z-index: 10055;
    width: max-content;
    min-height: 44px;
    padding: 0 14px;
    border-radius: 4px;
    white-space: nowrap;
}

@media (min-width: 769px) {
    .cc-fab {
        display: none;
    }
}
</style>
