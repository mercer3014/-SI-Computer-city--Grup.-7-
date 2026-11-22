<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { MessageCircle } from '@lucide/vue';
import { computed } from 'vue';

const page = usePage();

/** Solo el Administrador con el permiso ve el chat, y no hace falta en la propia pantalla del asistente. */
const visible = computed(
    () =>
        page.props.auth.user?.puede_asistente === true &&
        !page.url.startsWith('/asistente'),
);
</script>

<template>
    <Link
        v-if="visible"
        href="/asistente"
        class="cc-chat-fab"
        aria-label="Abrir el asistente virtual"
        title="Asistente virtual"
        data-test="asistente-fab"
    >
        <MessageCircle :size="22" />
    </Link>
</template>

<style scoped>
.cc-chat-fab {
    position: fixed;
    right: 22px;
    bottom: 22px;
    z-index: 40;
    display: grid;
    width: 52px;
    height: 52px;
    place-items: center;
    border-radius: 50%;
    background: var(--cc-accent);
    color: #fff;
    box-shadow: 0 10px 28px rgb(0 0 0 / 28%);
    transition: transform 140ms ease;
}

.cc-chat-fab:hover,
.cc-chat-fab:focus-visible {
    transform: scale(1.08);
    outline: 2px solid #fff;
    outline-offset: 2px;
}

@media (max-width: 768px) {
    .cc-chat-fab {
        right: auto;
        left: 18px;
        bottom: 18px;
    }
}
</style>
