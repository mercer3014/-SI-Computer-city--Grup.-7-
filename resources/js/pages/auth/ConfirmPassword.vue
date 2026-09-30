<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthFieldHint from '@/components/AuthFieldHint.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

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

        <div class="cc-auth-actions">
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
        </div>
    </Form>
</template>
