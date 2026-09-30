<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthConfetti from '@/components/AuthConfetti.vue';
import AuthFieldHint from '@/components/AuthFieldHint.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
import { useRegisterStep } from '@/composables/useAuthChrome';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, login } from '@/routes';
import { store } from '@/routes/register';

const props = defineProps<{
    passwordRules: string;
    registered?: boolean;
    status?: string;
    throttleSeconds?: number | null;
}>();

defineOptions({
    layout: {
        title: 'Crear cuenta',
        description: 'Completa tus datos para registrarte',
        formSide: 'left',
    },
});

const page = usePage();
const step = useRegisterStep();
const sending = ref(false);
const verifying = ref(false);
const otpSent = ref(false);
const remaining = ref(0);
let tick: number | null = null;

const form = reactive({
    nombre: '',
    apellido: '',
    ci: '',
    cargo: '',
    telefono: '',
    email: '',
    token: '',
    password: '',
    password_confirmation: '',
});

const localErrors = reactive<Record<string, string>>({
    nombre: '',
    apellido: '',
    ci: '',
    cargo: '',
    telefono: '',
    email: '',
    token: '',
});

const serverErrors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

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

function fieldError(key: string, liveThrottle = false): string | undefined {
    if (liveThrottle && throttled.value) {
        return throttleLabel.value;
    }

    return localErrors[key] || serverErrors.value[key] || undefined;
}

function clearLocalErrors(...keys: string[]): void {
    for (const key of keys) {
        localErrors[key] = '';
    }
}

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

function goTo(next: number): void {
    clearLocalErrors(
        'nombre',
        'apellido',
        'ci',
        'cargo',
        'telefono',
        'email',
        'token',
    );
    step.value = next;
}

function continuePersonal(): void {
    clearLocalErrors('nombre', 'apellido', 'ci', 'cargo');

    if (!form.nombre) {
        localErrors.nombre = 'Completá tu nombre.';
    }

    if (!form.apellido) {
        localErrors.apellido = 'Completá tu apellido.';
    }

    if (!form.ci) {
        localErrors.ci = 'Completá tu CI.';
    }

    if (!form.cargo) {
        localErrors.cargo = 'Completá tu cargo.';
    }

    if (
        localErrors.nombre ||
        localErrors.apellido ||
        localErrors.ci ||
        localErrors.cargo
    ) {
        return;
    }

    goTo(2);
}

function sendOtp(): void {
    if (!form.email || sending.value || throttled.value) {
        if (!form.email) {
            localErrors.email = 'Completá tu correo.';
        }

        return;
    }

    clearLocalErrors('email');
    sending.value = true;
    router.post(
        '/register/otp',
        { email: form.email },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                otpSent.value = true;
            },
            onFinish: () => {
                sending.value = false;
            },
        },
    );
}

function continueContact(): void {
    clearLocalErrors('telefono', 'email', 'token');

    if (!form.telefono) {
        localErrors.telefono = 'Completá tu celular.';
    } else if (!/^\d{7,15}$/.test(form.telefono)) {
        localErrors.telefono = 'Solo números, entre 7 y 15 dígitos.';
    }

    if (!form.email) {
        localErrors.email = 'Completá tu correo.';
    }

    if (!otpSent.value) {
        localErrors.email = localErrors.email || 'Verificá tu correo primero.';
    } else if (!form.token) {
        localErrors.token = 'Ingresá el código de 6 dígitos.';
    }

    if (localErrors.telefono || localErrors.email || localErrors.token) {
        return;
    }

    if (throttled.value) {
        return;
    }

    verifying.value = true;
    router.post(
        '/register/otp/verificar',
        { email: form.email, token: form.token },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                goTo(3);
            },
            onFinish: () => {
                verifying.value = false;
            },
        },
    );
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
    serverErrors,
    (errors) => {
        const msg = errors.email || errors.token || '';
        const match = msg.match(/(\d+)\s*(?:segundos?|seconds?)/i);

        if (match && remaining.value <= 0) {
            startCountdown(Number(match[1]));
        }
    },
    { immediate: true },
);

onMounted(() => {
    step.value = props.registered ? 4 : 1;

    if (props.throttleSeconds && props.throttleSeconds > 0) {
        startCountdown(props.throttleSeconds);
    }
});

watch(
    () => props.registered,
    (done) => {
        if (done) {
            step.value = 4;
        }
    },
);

onBeforeUnmount(() => {
    stopTick();
    step.value = 1;
});
</script>

<template>
    <Head title="Registro" />

    <div v-if="step === 4" class="cc-form cc-form--success">
        <AuthConfetti />
        <h1>Felicidades</h1>
        <p class="cc-success-copy">Estás registrado. Ya podés entrar al POS.</p>
        <TextLink :href="dashboard()">Ir al inicio</TextLink>
    </div>

    <template v-else>
        <div v-if="status" class="cc-status cc-status--success" role="status">
            {{ status }}
        </div>

        <form
            v-if="step === 1"
            class="cc-form"
            @submit.prevent="continuePersonal"
        >
            <h1>Crear cuenta</h1>

            <label class="cc-field">
                <span>Nombre</span>
                <span class="cc-field__control">
                    <input
                        v-model="form.nombre"
                        type="text"
                        autocomplete="given-name"
                        required
                        v-focus
                    />
                </span>
                <AuthFieldHint
                    hint="Como figura en tu documento"
                    :error="fieldError('nombre')"
                />
            </label>

            <label class="cc-field">
                <span>Apellido</span>
                <span class="cc-field__control">
                    <input
                        v-model="form.apellido"
                        type="text"
                        autocomplete="family-name"
                        required
                    />
                </span>
                <AuthFieldHint
                    hint="Como figura en tu documento"
                    :error="fieldError('apellido')"
                />
            </label>

            <label class="cc-field">
                <span>CI</span>
                <span class="cc-field__control">
                    <input v-model="form.ci" type="text" required />
                </span>
                <AuthFieldHint
                    hint="Documento de identidad"
                    :error="fieldError('ci')"
                />
            </label>

            <label class="cc-field">
                <span>Cargo</span>
                <span class="cc-field__control">
                    <input v-model="form.cargo" type="text" required />
                </span>
                <AuthFieldHint
                    hint="Tu rol en la tienda"
                    :error="fieldError('cargo')"
                />
            </label>

            <div class="cc-auth-actions">
                <button type="submit" class="cc-button">
                    <AuthActionIcon />
                    <span>Continuar</span>
                </button>

                <TextLink :href="login()">Iniciar sesión</TextLink>
            </div>
        </form>

        <form
            v-else-if="step === 2"
            class="cc-form"
            @submit.prevent="continueContact"
        >
            <h1>Contacto</h1>

            <label class="cc-field">
                <span>Número de celular</span>
                <span class="cc-field__control">
                    <input
                        v-model="form.telefono"
                        type="tel"
                        inputmode="numeric"
                        autocomplete="tel"
                        required
                        v-focus
                        :disabled="throttled"
                        @input="
                            form.telefono = form.telefono.replace(/\D/g, '').slice(0, 15)
                        "
                    />
                </span>
                <AuthFieldHint
                    hint="Solo números · 7 a 15 dígitos"
                    :error="fieldError('telefono')"
                />
            </label>

            <label class="cc-field">
                <span>Correo electrónico</span>
                <span class="cc-field__control">
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                        :disabled="throttled"
                    />
                </span>
                <label class="cc-verify">
                    <input
                        type="checkbox"
                        :checked="otpSent"
                        :disabled="sending || !form.email || throttled"
                        @click.prevent="sendOtp"
                    />
                    <span>
                        {{
                            throttled
                                ? `Esperá ${remaining}s`
                                : sending
                                  ? 'Enviando…'
                                  : 'Verificar'
                        }}
                    </span>
                </label>
                <AuthFieldHint
                    hint="Correo de la tienda"
                    :error="fieldError('email', !otpSent)"
                />
            </label>

            <label v-if="otpSent" class="cc-field">
                <span>Código</span>
                <span class="cc-field__control">
                    <input
                        v-model="form.token"
                        type="text"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        required
                        :disabled="throttled"
                    />
                </span>
                <AuthFieldHint
                    hint="6 dígitos · vence en 10 minutos"
                    :error="fieldError('token', true)"
                />
            </label>

            <div class="cc-auth-actions cc-auth-links">
                <button
                    type="submit"
                    class="cc-button"
                    :disabled="verifying || !otpSent || throttled"
                >
                    <Spinner v-if="verifying" />
                    <AuthActionIcon v-else />
                    <span>{{ throttled ? `Esperá ${remaining}s` : 'Continuar' }}</span>
                </button>
                <button
                    type="button"
                    class="cc-button cc-button--ghost"
                    @click="goTo(1)"
                >
                    Atrás
                </button>
            </div>
        </form>

        <Form
            v-else
            v-bind="store.form()"
            :reset-on-success="['password', 'password_confirmation']"
            v-slot="{ errors: submitErrors, processing }"
            class="cc-form"
        >
            <input type="hidden" name="nombre" :value="form.nombre" />
            <input type="hidden" name="apellido" :value="form.apellido" />
            <input type="hidden" name="ci" :value="form.ci" />
            <input type="hidden" name="cargo" :value="form.cargo" />
            <input type="hidden" name="telefono" :value="form.telefono" />
            <input type="hidden" name="email" :value="form.email" />
            <input type="hidden" name="token" :value="form.token" />

            <h1>Clave</h1>

            <label class="cc-field">
                <span>Contraseña</span>
                <AuthPasswordControl
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                    autofocus
                />
                <AuthFieldHint
                    hint="8 caracteres, mayúscula, minúscula, número y símbolo"
                    :error="submitErrors.password"
                />
            </label>

            <label class="cc-field">
                <span>Confirmar contraseña</span>
                <AuthPasswordControl
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <AuthFieldHint
                    hint="Repetí la misma clave"
                    :error="
                        submitErrors.password_confirmation ||
                        submitErrors.token ||
                        submitErrors.email
                    "
                />
            </label>

            <div class="cc-auth-actions cc-auth-links">
                <button
                    type="submit"
                    class="cc-button"
                    :disabled="processing"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing" />
                    <AuthActionIcon v-else />
                    <span>Registrarme</span>
                </button>
                <button
                    type="button"
                    class="cc-button cc-button--ghost"
                    @click="goTo(2)"
                >
                    Atrás
                </button>
            </div>
        </Form>
    </template>
</template>
