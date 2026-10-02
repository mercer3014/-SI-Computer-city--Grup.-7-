<script setup lang="ts">
import { motion } from 'motion-v';

defineProps<{
    brandSubtitle: string;
    infoTitle: string;
    infoText: string;
    mode: 'login' | 'register' | 'recover';
    brandSide: 'left' | 'right';
    step: number;
}>();

const spring = {
    type: 'spring',
    damping: 20,
    stiffness: 300,
} as const;
</script>

<template>
    <div class="cc-auth-stack">
        <section class="cc-auth-panel" :data-mode="mode" :data-brand="brandSide">
            <motion.div
                layout
                class="cc-auth-brand"
                :transition="spring"
            >
                <img
                    class="cc-auth-brand__logo"
                    src="/images/computer-city/logo.png?v=5"
                    alt="Computer City"
                />
                <span>{{ brandSubtitle }}</span>
            </motion.div>

            <motion.div
                layout
                class="cc-auth-form"
                :transition="spring"
            >
                <div class="cc-auth-form-track">
                    <div class="cc-auth-form-body" :data-step="step">
                        <slot />
                    </div>
                </div>
            </motion.div>
        </section>

        <aside class="cc-auth-info">
            <div>
                <strong>{{ infoTitle }}</strong>
                <p>{{ infoText }}</p>
            </div>
        </aside>
    </div>
</template>
