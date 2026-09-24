<script setup lang="ts">
import AuthPanel from '@/components/AuthPanel.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { useAuthChrome } from '@/composables/useAuthChrome';
import {
    bindAuthSurfaceHooks,
    originFor,
    persistSettled,
    rememberOutgoing,
    rememberVisual,
    type AuthSurface,
} from '@/composables/useAuthSurface';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
        formSide?: 'left' | 'right';
    }>(),
    {
        title: '',
        description: '',
        formSide: 'right',
    },
);

bindAuthSurfaceHooks();

const chrome = useAuthChrome();

function target(): AuthSurface {
    return {
        mode: chrome.value.mode,
        side: chrome.value.brandSide,
    };
}

function sameSurface(a: AuthSurface, b: AuthSurface): boolean {
    return a.mode === b.mode && a.side === b.side;
}

const dest = target();
const origin = originFor(dest);
const shownMode = ref(origin.mode);
const shownSide = ref(origin.side);

rememberVisual({ mode: origin.mode, side: origin.side });

function current(): AuthSurface {
    return { mode: shownMode.value, side: shownSide.value };
}

function apply(next: AuthSurface): void {
    shownMode.value = next.mode;
    shownSide.value = next.side;
    persistSettled(next);
    rememberVisual(next);
}

onMounted(() => {
    apply(target());
});

watch(
    () => [chrome.value.mode, chrome.value.brandSide] as const,
    ([mode, side]) => {
        apply({ mode, side });
    },
    { flush: 'sync' },
);

onBeforeUnmount(() => {
    rememberOutgoing(current());
});
</script>

<template>
    <main class="cc-theme cc-auth-page" :data-mode="shownMode">
        <ThemeToggle />

        <div class="cc-auth-stage">
            <AuthPanel
                :brand-subtitle="chrome.brandSubtitle"
                :info-title="chrome.infoTitle"
                :info-text="chrome.infoText"
                :mode="shownMode"
                :brand-side="shownSide"
                :step="chrome.registerStep"
            >
                <slot />
            </AuthPanel>
        </div>
    </main>
</template>
