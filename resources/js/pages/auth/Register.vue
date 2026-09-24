<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import AuthActionIcon from '@/components/AuthActionIcon.vue';
import AuthConfetti from '@/components/AuthConfetti.vue';
import AuthPasswordControl from '@/components/AuthPasswordControl.vue';
import InputError from '@/components/InputError.vue';
import { useRegisterStep } from '@/composables/useAuthChrome';
import TextLink from '@/components/TextLink.vue';
import { Spinner } from '@/components/ui/spinner';
import { dashboard, login } from '@/routes';
import { store } from '@/routes/register';

const props = defineProps<{
    passwordRules: string;
    registered?: boolean;
    status?: string;
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
const localError = ref('');

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

const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

function goTo(next: number) {
    localError.value = '';
    step.value = next;
}

function continuePersonal() {
    if (!form.nombre || !form.apellido || !form.ci || !form.cargo) {
        localError.value = 'Completá nombre, apellido, CI y cargo.';
        return;
    }

    goTo(2);
}

function sendOtp() {
    if (!form.email || sending.value) {
        return;
    }

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

function continueContact() {
    if (!form.telefono || !form.email || !form.token) {
        localError.value = 'Completá celular, correo y el código.';
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

onMounted(() => {
    step.value = props.registered ? 4 : 1;
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
        <p v-if="localError" class="cc-status" role="alert">{{ localError }}</p>

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
                <small>Como figura en tu documento</small>
                <InputError :message="errors.nombre" />
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
                <small>Como figura en tu documento</small>
                <InputError :message="errors.apellido" />
            </label>

            <label class="cc-field">
                <span>CI</span>
                <span class="cc-field__control">
                    <input v-model="form.ci" type="text" required />
                </span>
                <small>Documento de identidad</small>
                <InputError :message="errors.ci" />
            </label>

            <label class="cc-field">
                <span>Cargo</span>
                <span class="cc-field__control">
                    <input v-model="form.cargo" type="text" required />
                </span>
                <small>Tu rol en la tienda</small>
                <InputError :message="errors.cargo" />
            </label>

            <button type="submit" class="cc-button">
                <AuthActionIcon />
                <span>Continuar</span>
            </button>

            <TextLink :href="login()">Iniciar sesión</TextLink>
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
                        autocomplete="tel"
                        required
                        v-focus
                    />
                </span>
                <small>Con código de área</small>
                <InputError :message="errors.telefono" />
            </label>

            <label class="cc-field">
                <span>Correo electrónico</span>
                <span class="cc-field__control">
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        required
                    />
                </span>
                <label class="cc-verify">
                    <input
                        type="checkbox"
                        :checked="otpSent"
                        :disabled="sending || !form.email"
                        @click.prevent="sendOtp"
                    />
                    <span>{{ sending ? 'Enviando…' : 'Verificar' }}</span>
                </label>
                <InputError :message="errors.email" />
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
                    />
                </span>
                <small>6 dígitos · vence en 10 minutos</small>
                <InputError :message="errors.token" />
            </label>

            <div class="cc-auth-links">
                <button
                    type="submit"
                    class="cc-button"
                    :disabled="verifying || !otpSent"
                >
                    <Spinner v-if="verifying" />
                    <AuthActionIcon v-else />
                    <span>Continuar</span>
                </button>
                <button type="button" class="cc-button cc-button--ghost" @click="goTo(1)">
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
                <small>8 caracteres, mayúscula, minúscula, número y símbolo</small>
                <InputError :message="submitErrors.password" />
            </label>

            <label class="cc-field">
                <span>Confirmar contraseña</span>
                <AuthPasswordControl
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :passwordrules="passwordRules"
                />
                <small>Repetí la misma clave</small>
                <InputError :message="submitErrors.password_confirmation" />
            </label>

            <InputError :message="submitErrors.token" />
            <InputError :message="submitErrors.email" />

            <div class="cc-auth-links">
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
                <button type="button" class="cc-button cc-button--ghost" @click="goTo(2)">
                    Atrás
                </button>
            </div>
        </Form>
    </template>
</template>
