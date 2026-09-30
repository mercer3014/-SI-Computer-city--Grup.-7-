<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, ref, watchEffect } from 'vue';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthFieldHint from '@/components/AuthFieldHint.vue';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/lib/disabledFortifyRoutes';
import type { TwoFactorConfigContent } from '@/types';

const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Código de recuperación',
            description:
                'Ingresá uno de tus códigos de emergencia para acceder.',
            buttonText: 'usar un código del autenticador',
        };
    }

    return {
        title: 'Código de autenticación',
        description:
            'Ingresá el código de tu app autenticadora.',
        buttonText: 'usar un código de recuperación',
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
        formSide: 'right',
    });
});

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};
</script>

<template>
    <Head title="Autenticación en dos pasos" />

    <template v-if="!showRecoveryInput">
        <Form
            v-bind="store.form()"
            class="cc-form"
            reset-on-error
            @error="code = ''"
            #default="{ errors, processing, clearErrors }"
        >
            <input type="hidden" name="code" :value="code" />
            <h1>Código 2FA</h1>

            <label class="cc-field">
                <span>Código</span>
                <span class="cc-field__control">
                    <input
                        id="otp"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        :value="code"
                        :disabled="processing"
                        required
                        v-focus
                        @input="
                            code = (
                                $event.target as HTMLInputElement
                            ).value.replace(/\D/g, '').slice(0, 6)
                        "
                    />
                </span>
                <AuthFieldHint
                    hint="6 dígitos de tu app autenticadora"
                    :error="errors.code"
                />
            </label>

            <button
                type="submit"
                class="cc-button"
                :disabled="processing || code.length < 6"
            >
                <Spinner v-if="processing" />
                <AuthActionIcon v-else />
                <span>Continuar</span>
            </button>

            <button
                type="button"
                class="cc-auth-toggle"
                @click="() => toggleRecoveryMode(clearErrors)"
            >
                O {{ authConfigContent.buttonText }}
            </button>
        </Form>
    </template>

    <template v-else>
        <Form
            v-bind="store.form()"
            class="cc-form"
            reset-on-error
            #default="{ errors, processing, clearErrors }"
        >
            <h1>Recuperación</h1>

            <label class="cc-field">
                <span>Código de emergencia</span>
                <span class="cc-field__control">
                    <input
                        name="recovery_code"
                        type="text"
                        autocomplete="one-time-code"
                        required
                        v-focus
                    />
                </span>
                <AuthFieldHint
                    hint="Uno de los códigos que guardaste al activar 2FA"
                    :error="errors.recovery_code"
                />
            </label>

            <button
                type="submit"
                class="cc-button"
                :disabled="processing"
            >
                <Spinner v-if="processing" />
                <AuthActionIcon v-else />
                <span>Continuar</span>
            </button>

            <button
                type="button"
                class="cc-auth-toggle"
                @click="() => toggleRecoveryMode(clearErrors)"
            >
                O {{ authConfigContent.buttonText }}
            </button>
        </Form>
    </template>
</template>
