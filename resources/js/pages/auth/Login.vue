<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthFieldHint from '@/components/AuthFieldHint.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Iniciar sesión',
        description: 'Ingresa con tu correo y contraseña',
        formSide: 'right',
    },
});

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    canUsePasskeys?: boolean;
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
        const msg = errors?.password || errors?.email || '';
        const match = msg.match(/(\d+)\s*(?:segundos?|seconds?)/i);

        if (match && remaining.value <= 0) {
            startCountdown(Number(match[1]));
        }
    },
    { immediate: true },
);

onMounted(() => {
    if (props.throttleSeconds && props.throttleSeconds > 0) {
        startCountdown(props.throttleSeconds);
    }
});

onBeforeUnmount(() => {
    stopTick();
});
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
                    autocomplete="username"
                    :disabled="throttled"
                />
            </span>
            <AuthFieldHint
                hint="Correo asignado en la tienda"
                :error="
                    isThrottleMessage(errors.email) ? undefined : errors.email
                "
            />
        </label>

        <label class="cc-field">
            <span>Clave</span>
            <AuthPasswordControl
                id="password"
                name="password"
                autocomplete="current-password"
                tabindex="2"
                :disabled="throttled"
            />
            <AuthFieldHint
                hint="La clave que te enviaron o la tuya"
                :error="throttled ? throttleLabel : errors.password"
            />
        </label>

        <div class="cc-auth-actions">
            <button
                type="submit"
                class="cc-button"
                tabindex="3"
                :disabled="processing || throttled"
                data-test="login-button"
            >
                <Spinner v-if="processing" />
                <AuthActionIcon v-else />
                <span>{{
                    throttled
                        ? remaining >= 60
                            ? `Esperá ${Math.floor(remaining / 60)}:${String(remaining % 60).padStart(2, '0')}`
                            : `Esperá ${remaining}s`
                        : 'Ingresar'
                }}</span>
            </button>

            <div class="cc-auth-links">
                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    :tabindex="5"
                >
                    Recuperar cuenta
                </TextLink>
            </div>
        </div>
    </Form>
</template>
