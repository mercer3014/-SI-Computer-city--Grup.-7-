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
            class="cc-theme cc-user-menu min-w-56 border-[#e8dccb] bg-[#fffdf8] text-[#1c1410] shadow-xl dark:border-[#5c3348] dark:bg-[#2a1622] dark:text-[#fdecf3]"
        >
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
