<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { usePasskeyVerify } from '@laravel/passkeys/vue';
import { KeyRound } from '@lucide/vue';
import AuthFieldHint from '@/components/AuthFieldHint.vue';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    routes?: {
        options: UrlMethodPair;
        submit: UrlMethodPair;
    };
    label?: string;
    loadingLabel?: string;
    separator?: string;
};

const props = defineProps<Props>();

const { verify, isLoading, error, isSupported } = usePasskeyVerify({
    ...(props.routes
        ? {
              routes: {
                  options: props.routes.options.url,
                  submit: props.routes.submit.url,
              },
          }
        : {}),
    onSuccess: (response) => {
        router.visit(response.redirect ?? '/dashboard');
    },
});
</script>

<template>
    <div v-if="isSupported" class="cc-passkey">
        <button
            type="button"
            class="cc-button cc-button--ghost"
            :disabled="isLoading"
            @click="verify"
        >
            <Spinner v-if="isLoading" />
            <KeyRound v-else class="cc-passkey__icon" />
            <span>
                {{
                    isLoading
                        ? (props.loadingLabel ?? 'Autenticando...')
                        : (props.label ?? 'Entrar con passkey')
                }}
            </span>
        </button>

        <label v-if="error" class="cc-field">
            <AuthFieldHint hint="" :error="error" />
        </label>

        <p class="cc-passkey__or">
            {{ props.separator ?? 'O continuá con correo' }}
        </p>
    </div>
</template>
