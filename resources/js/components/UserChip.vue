<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
</script>

<template>
    <DropdownMenu v-if="user">
        <DropdownMenuTrigger class="cc-user-chip" data-test="user-chip">
            <span class="cc-user-chip__avatar" aria-hidden="true" />
            <span class="cc-user-chip__who">
                <strong>{{ user.name }}</strong>
                <small v-if="user.rol">{{ user.rol }}</small>
            </span>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            align="end"
            class="min-w-56 border-[var(--cc-border)] bg-[var(--cc-surface)] text-[var(--cc-text)]"
        >
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
