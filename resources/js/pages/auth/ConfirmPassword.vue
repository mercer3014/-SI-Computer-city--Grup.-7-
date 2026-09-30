<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthFieldHint from '@/components/AuthFieldHint.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/lib/disabledFortifyRoutes';

defineOptions({
    layout: {
        title: 'Confirmar clave',
        description: 'Zona segura: confirmá tu clave para continuar',
        formSide: 'right',
    },
});
</script>

<template>
    <Head title="Confirmar clave" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        label="Confirmar con passkey"
        loading-label="Confirmando..."
        separator="O confirmá con tu clave"
    />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        class="cc-form"
    >
        <h1>Confirmar clave</h1>

        <label class="cc-field">
            <span>Clave</span>
            <AuthPasswordControl
                id="password"
                name="password"
                autocomplete="current-password"
                autofocus
            />
            <AuthFieldHint
                hint="La misma con la que iniciás sesión"
                :error="errors.password"
            />
        </label>

        <button
            type="submit"
            class="cc-button"
            :disabled="processing"
            data-test="confirm-password-button"
        >
            <Spinner v-if="processing" />
            <AuthActionIcon v-else />
            <span>Confirmar</span>
        </button>
    </Form>
</template>
