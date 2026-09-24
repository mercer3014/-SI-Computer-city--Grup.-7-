<script setup lang="ts">
import { useAppearance } from '@/composables/useAppearance';
import { LayoutGroup, motion } from 'motion-v';

const { resolvedAppearance, updateAppearance } = useAppearance();

const pillSpring = {
    type: 'spring',
    damping: 20,
    stiffness: 300,
} as const;
</script>

<template>
    <LayoutGroup>
        <div class="cc-theme-toggle" role="group" aria-label="Tema">
            <button
                type="button"
                :class="{ active: resolvedAppearance === 'dark' }"
                :aria-pressed="resolvedAppearance === 'dark'"
                aria-label="Usar tema nocturno"
                @click="updateAppearance('dark')"
            >
                <motion.span
                    v-if="resolvedAppearance === 'dark'"
                    layout-id="cc-theme-pill"
                    class="cc-theme-pill"
                    :transition="pillSpring"
                />
                <img
                    src="/images/computer-city/theme-moon.svg"
                    width="22"
                    height="22"
                    alt=""
                />
            </button>
            <button
                type="button"
                :class="{ active: resolvedAppearance === 'light' }"
                :aria-pressed="resolvedAppearance === 'light'"
                aria-label="Usar tema diurno"
                @click="updateAppearance('light')"
            >
                <motion.span
                    v-if="resolvedAppearance === 'light'"
                    layout-id="cc-theme-pill"
                    class="cc-theme-pill"
                    :transition="pillSpring"
                />
                <img
                    src="/images/computer-city/theme-sun.svg"
                    width="22"
                    height="22"
                    alt=""
                />
            </button>
        </div>
    </LayoutGroup>
</template>
