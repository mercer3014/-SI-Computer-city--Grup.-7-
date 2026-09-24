<script setup lang="ts">
import { computed } from 'vue';
import { motion } from 'motion-v';

const props = defineProps<{
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

const brandOrder = computed(() => (props.brandSide === 'right' ? 2 : 1));
const formOrder = computed(() => (props.brandSide === 'right' ? 1 : 2));
</script>

<template>
    <div class="cc-auth-stack">
        <section class="cc-auth-panel" :data-mode="mode" :data-brand="brandSide">
            <motion.div
                layout
                class="cc-auth-brand"
                :transition="spring"
                :style="{
                    order: brandOrder,
                }"
            >
                <strong>Computer City</strong>
                <span>{{ brandSubtitle }}</span>
            </motion.div>

            <motion.div
                layout
                class="cc-auth-form"
                :transition="spring"
                :style="{
                    order: formOrder,
                }"
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
