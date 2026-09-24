<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthConfetti from '@/components/AuthConfetti.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email as sendResetCode, update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Recuperar cuenta',
        description: 'Te enviaremos un código de 6 dígitos',
        formSide: 'left',
    },
});

const props = defineProps<{
    status?: string;
    email?: string;
    recovered?: boolean;
}>();
</script>

<template>
    <Head title="Recuperar cuenta" />

    <div v-if="props.recovered" class="cc-form cc-form--success">
        <AuthConfetti />
        <h1>Felicidades</h1>
        <p class="cc-success-copy">
            Ya recuperaste tu cuenta. Entrá al POS con tu nueva clave.
        </p>
        <TextLink :href="login()">Iniciar sesión</TextLink>
    </div>

    <template v-else>
        <div v-if="status" class="cc-status cc-status--success" role="status">
            {{ status }}
        </div>

        <Form
            v-if="!props.email"
            v-bind="sendResetCode.form()"
            v-slot="{ errors, processing }"
            class="cc-form"
        >
            <h1>Recuperar cuenta</h1>

            <label class="cc-field">
                <span>Correo</span>
                <span class="cc-field__control">
                    <input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="email"
                        v-focus
                        required
                    />
                </span>
                <small>El que figura en tu ficha</small>
                <InputError :message="errors.email" />
            </label>

            <button
                type="submit"
                class="cc-button"
                :disabled="processing"
                data-test="email-password-reset-link-button"
            >
                <Spinner v-if="processing" />
                <AuthActionIcon v-else />
                <span>Recuperar</span>
            </button>

            <TextLink :href="login()">Iniciar sesión</TextLink>
        </Form>

        <Form
            v-else
            v-bind="update.form()"
            :reset-on-success="['password', 'password_confirmation', 'token']"
            v-slot="{ errors, processing }"
            class="cc-form"
        >
            <input type="hidden" name="email" :value="props.email" />
            <h1>Confirmar correo</h1>

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
                        v-focus
                    />
                </span>
                <small>6 dígitos · vence en 10 minutos</small>
                <InputError :message="errors.token" />
                <InputError :message="errors.email" />
            </label>

            <label class="cc-field">
                <span>Nueva clave</span>
                <AuthPasswordControl
                    id="password"
                    name="password"
                    autocomplete="new-password"
                />
                <small>8 caracteres, mayúscula, minúscula, número y símbolo</small>
                <InputError :message="errors.password" />
            </label>

            <label class="cc-field">
                <span>Confirmar clave</span>
                <AuthPasswordControl
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
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

            <div class="cc-auth-links">
                <TextLink href="/forgot-password?otro=1">
                    Usar otro correo
                </TextLink>
                <TextLink :href="login()">Iniciar sesión</TextLink>
            </div>
        </Form>
    </template>
</template>
