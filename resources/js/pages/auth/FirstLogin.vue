<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthConfetti from '@/components/AuthConfetti.vue';
import AuthFieldHint from '@/components/AuthFieldHint.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
import { Spinner } from '@/components/ui/spinner';

const props = defineProps<{
    nombre: string;
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Primer ingreso',
        description: 'Definí tu clave antes de entrar al POS',
        formSide: 'right',
    },
});

const listo = ref(false);
</script>

<template>
    <Head title="Primer ingreso" />

    <div v-if="!listo" class="cc-form cc-form--success">
        <AuthConfetti />
        <h1>Bienvenido al equipo</h1>
        <p class="cc-success-copy">{{ props.nombre }}</p>
        <p class="cc-success-copy">
            Tu cuenta ya está en Computer City. Antes de entrar, definí tu
            clave.
        </p>
        <button type="button" class="cc-button" @click="listo = true">
            <AuthActionIcon />
            <span>Continuar</span>
        </button>
    </div>

    <Form
        v-else
        action="/primer-ingreso"
        method="post"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="cc-form"
    >
        <h1>Clave nueva</h1>
        <p class="cc-success-copy">
            Por favor colocá tu contraseña nueva antes de entrar.
        </p>

        <label class="cc-field">
            <span>Nueva clave</span>
            <AuthPasswordControl
                id="password"
                name="password"
                autocomplete="new-password"
                :passwordrules="passwordRules"
                autofocus
            />
            <AuthFieldHint
                hint="8 caracteres, mayúscula, minúscula, número y símbolo"
                :error="errors.password"
            />
        </label>

        <label class="cc-field">
            <span>Confirmar clave</span>
            <AuthPasswordControl
                id="password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                :passwordrules="passwordRules"
            />
            <AuthFieldHint
                hint="Repetí la misma clave"
                :error="errors.password_confirmation"
            />
        </label>

        <div class="cc-auth-actions">
            <button
                type="submit"
                class="cc-button"
                :disabled="processing"
                data-test="first-login-button"
            >
                <Spinner v-if="processing" />
                <AuthActionIcon v-else />
                <span>Entrar al POS</span>
            </button>
        </div>
    </Form>
</template>
