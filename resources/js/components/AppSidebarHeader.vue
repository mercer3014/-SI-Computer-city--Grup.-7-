<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import UserChip from '@/components/UserChip.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

const props = withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const page = usePage();

const titulo = computed(() => {
    const ultimo = props.breadcrumbs.at(-1)?.title;

    if (ultimo) {
        return ultimo;
    }

    const ruta = page.url.split('?')[0] ?? '';

    if (ruta === '/dashboard' || ruta === '/') {
        return 'Inicio';
    }

    return 'Computer City';
});
</script>

<template>
    <header class="cc-appbar">
        <SidebarTrigger class="cc-appbar__trigger h-12 w-12 md:h-9 md:w-9" />
        <p class="cc-appbar__title">{{ titulo }}</p>
        <div class="cc-appbar__actions">
            <ThemeToggle />
            <UserChip />
        </div>
    </header>
</template>
