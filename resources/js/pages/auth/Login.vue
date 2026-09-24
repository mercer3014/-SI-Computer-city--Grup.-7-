<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Iniciar sesión',
        description: 'Ingresa con tu correo y contraseña',
        formSide: 'right',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canUsePasskeys?: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

    <div v-if="status" class="cc-status cc-status--success" role="status">
        {{ status }}
    </div>

        <PasskeyVerify v-if="canUsePasskeys" />

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="cc-form"
        >
            <h1>Entrar</h1>

            <label class="cc-field">
                <span>Correo</span>
                <span class="cc-field__control">
                    <input
                        id="email"
                        type="email"
                        name="email"
                        required
                        v-focus
                        tabindex="1"
                        autocomplete="email"
                    />
                </span>
                <small>Correo asignado en la tienda</small>
                <InputError :message="errors.email" />
            </label>

            <label class="cc-field">
                <span>Clave</span>
                <AuthPasswordControl
                    id="password"
                    name="password"
                    autocomplete="current-password"
                    tabindex="2"
                />
                <small>Mínimo 8 caracteres</small>
                <InputError :message="errors.password" />
            </label>

            <button
                type="submit"
                class="cc-button"
                tabindex="3"
                :disabled="processing"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                <AuthActionIcon v-else />
                <span>Ingresar</span>
            </button>

            <div class="cc-auth-links">
                <TextLink :href="register()" :tabindex="4">
                    Crear cuenta
                </TextLink>
                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    :tabindex="5"
                >
                    Recuperar cuenta
                </TextLink>
            </div>
        </Form>
</template>
