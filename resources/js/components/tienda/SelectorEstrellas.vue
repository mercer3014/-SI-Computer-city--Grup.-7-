<script setup lang="ts">
import { Star } from '@lucide/vue';
import { ref, useId } from 'vue';

defineProps<{
    modelValue: number;
    error?: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [valor: number];
}>();

const grupo = useId();
const encima = ref(0);
</script>

<template>
    <fieldset
        class="cc-selector-estrellas"
        :class="{ 'is-error': error }"
        data-test="selector-estrellas"
    >
        <legend class="sr-only">Puntuación de 1 a 5 estrellas</legend>
        <label
            v-for="n in 5"
            :key="n"
            :class="{ 'is-llena': n <= (encima || modelValue) }"
            @mouseenter="encima = n"
            @mouseleave="encima = 0"
        >
            <input
                type="radio"
                class="sr-only"
                :name="grupo"
                :value="n"
                :checked="modelValue === n"
                @change="emit('update:modelValue', n)"
            />
            <Star :size="32" aria-hidden="true" />
            <span class="sr-only"
                >{{ n }} {{ n === 1 ? 'estrella' : 'estrellas' }}</span
            >
        </label>
    </fieldset>
</template>
