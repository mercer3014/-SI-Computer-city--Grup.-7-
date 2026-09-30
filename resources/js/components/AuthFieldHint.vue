<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{
    hint?: string;
    error?: string;
}>();

const root = ref<HTMLElement | null>(null);
let shakeTimer = 0;

const text = computed(() => props.error || props.hint || '');
const isError = computed(() => Boolean(props.error));

function isElement(value: unknown): value is HTMLElement {
    return typeof HTMLElement !== 'undefined' && value instanceof HTMLElement;
}

function fieldEls(): { field: HTMLElement; control: HTMLElement } | null {
    const field = root.value?.closest('.cc-field');

    if (!isElement(field)) {
        return null;
    }

    const control = field.querySelector('.cc-field__control');

    if (!isElement(control)) {
        return null;
    }

    return { field, control };
}

function clearShake(control: HTMLElement): void {
    control.classList.remove('is-shaking');

    if (shakeTimer) {
        window.clearTimeout(shakeTimer);
        shakeTimer = 0;
    }
}

function isCountdownTick(prev?: string, next?: string): boolean {
    if (!prev || !next) {
        return false;
    }

    return (
        /demasiados intentos/i.test(prev) && /demasiados intentos/i.test(next)
    );
}

function playShake(): void {
    const els = fieldEls();

    if (!els) {
        return;
    }

    const { field, control } = els;
    field.classList.toggle('is-error', isError.value);

    if (!isError.value) {
        clearShake(control);
        return;
    }

    clearShake(control);
    void control.offsetWidth;
    control.classList.add('is-shaking');

    shakeTimer = window.setTimeout(() => {
        control.classList.remove('is-shaking');
        shakeTimer = 0;
    }, 300);
}

watch(
    () => props.error,
    async (next, prev) => {
        await nextTick();

        const els = fieldEls();

        if (els) {
            els.field.classList.toggle('is-error', Boolean(next));
        }

        if (!next) {
            if (els) {
                clearShake(els.control);
            }

            return;
        }

        // El texto del conteo cambia cada segundo: no volver a sacudir.
        if (isCountdownTick(prev, next)) {
            return;
        }

        playShake();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    const els = fieldEls();

    if (els) {
        clearShake(els.control);
        els.field.classList.remove('is-error');
    }
});
</script>

<template>
    <small
        ref="root"
        class="cc-field__hint"
        :class="{ 'cc-field__hint--error': isError }"
        :role="isError ? 'alert' : undefined"
    >
        {{ text }}
    </small>
</template>
