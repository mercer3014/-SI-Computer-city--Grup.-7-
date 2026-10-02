<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <img
        src="/images/computer-city/logo.png?v=5"
        alt="Computer City"
        :class="className"
        v-bind="$attrs"
    />
</template>
