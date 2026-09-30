<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthConfetti from '@/components/AuthConfetti.vue';
import AuthFieldHint from '@/components/AuthFieldHint.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
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
    throttleSeconds?: number | null;
}>();

const page = usePage();
const remaining = ref(0);
let tick: number | null = null;

const throttleLabel = computed(() => {
    if (remaining.value <= 0) {
        return '';
    }

    const m = Math.floor(remaining.value / 60);
    const s = remaining.value % 60;

    if (m > 0) {
        return `Demasiados intentos. Esperá ${m}:${String(s).padStart(2, '0')}.`;
    }

    return remaining.value === 1
        ? 'Demasiados intentos. Esperá 1 segundo.'
        : `Demasiados intentos. Esperá ${remaining.value} segundos.`;
});

const throttled = computed(() => remaining.value > 0);

function stopTick(): void {
    if (tick !== null) {
        window.clearInterval(tick);
        tick = null;
    }
}

function startCountdown(seconds: number): void {
    stopTick();
    remaining.value = Math.max(0, Math.floor(seconds));

    if (remaining.value <= 0) {
        return;
    }

    tick = window.setInterval(() => {
        remaining.value -= 1;

        if (remaining.value <= 0) {
            stopTick();
        }
    }, 1000);
}

function isThrottleMessage(message?: string): boolean {
    return Boolean(message && /demasiados intentos|too many/i.test(message));
}

watch(
    () => props.throttleSeconds,
    (value) => {
        if (typeof value === 'number' && value > 0) {
            startCountdown(value);
        }
    },
    { immediate: true },
);

watch(
    () => page.props.errors as Record<string, string> | undefined,
    (errors) => {
        const msg = errors?.email || errors?.token || '';
        const match = msg.match(/(\d+)\s*(?:segundos?|seconds?)/i);

        if (match && remaining.value <= 0) {
            startCountdown(Number(match[1]));
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    stopTick();
});
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
                        :disabled="throttled"
                    />
                </span>
                <AuthFieldHint
                    hint="El que figura en tu ficha"
                    :error="throttled ? throttleLabel : errors.email"
                />
            </label>

            <button
                type="submit"
                class="cc-button"
                :disabled="processing || throttled"
                data-test="email-password-reset-link-button"
            >
                <Spinner v-if="processing" />
                <AuthActionIcon v-else />
                <span>{{ throttled ? `Esperá ${remaining}s` : 'Recuperar' }}</span>
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
                        :disabled="throttled"
                    />
                </span>
                <AuthFieldHint
                    hint="6 dígitos · vence en 10 minutos"
                    :error="
                        throttled
                            ? throttleLabel
                            : isThrottleMessage(errors.email)
                              ? undefined
                              : errors.token || errors.email
                    "
                />
            </label>

            <label class="cc-field">
                <span>Nueva clave</span>
                <AuthPasswordControl
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    :disabled="throttled"
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
                    :disabled="throttled"
                />
                <AuthFieldHint
                    hint="Repetí la misma clave"
                    :error="errors.password_confirmation"
                />
            </label>

            <button
                type="submit"
                class="cc-button"
                :disabled="processing || throttled"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" />
                <AuthActionIcon v-else />
                <span>{{ throttled ? `Esperá ${remaining}s` : 'Guardar nueva clave' }}</span>
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
