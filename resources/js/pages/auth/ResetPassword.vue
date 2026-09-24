<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Nueva contraseña',
        description: 'Ingresa el código y tu nueva contraseña',
        formSide: 'left',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Nueva contraseña" />

        <Form
            v-bind="update.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors, processing }"
            class="cc-form"
        >
            <input type="hidden" name="email" :value="props.email" />
            <h1>Nueva contraseña</h1>

            <label class="cc-field">
                <span>Código</span>
                <span class="cc-field__control">
                    <input
                        id="token"
                        type="text"
                        name="token"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        required
                        :value="props.token"
                    />
                </span>
                <small>6 dígitos</small>
                <InputError :message="errors.token" />
                <InputError :message="errors.email" />
            </label>

            <label class="cc-field">
                <span>Nueva clave</span>
                <AuthPasswordControl
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                    autofocus
                />
                <small
                    >8 caracteres, mayúscula, minúscula, número y símbolo</small
                >
                <InputError :message="errors.password" />
            </label>

            <label class="cc-field">
                <span>Confirmar clave</span>
                <AuthPasswordControl
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <small>Repetí la misma clave</small>
                <InputError :message="errors.password_confirmation" />
            </label>

            <button
                type="submit"
                class="cc-button"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                <AuthActionIcon v-else />
                <span>Guardar nueva clave</span>
            </button>

            <TextLink :href="login()">Iniciar sesión</TextLink>
        </Form>
</template>
