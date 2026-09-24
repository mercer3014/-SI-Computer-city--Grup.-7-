<script setup lang="ts">
import ThemeToggle from '@/components/ThemeToggle.vue';
import UserChip from '@/components/UserChip.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header class="cc-appbar">
        <SidebarTrigger class="cc-appbar__trigger -ml-1" />
        <div class="cc-appbar__actions">
            <ThemeToggle />
            <UserChip />
        </div>
    </header>
</template>
