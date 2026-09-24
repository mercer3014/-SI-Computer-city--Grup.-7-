import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

export type AuthChrome = {
    brandSubtitle: string;
    infoTitle: string;
    infoText: string;
};

export type AuthMode = 'login' | 'register' | 'recover';

const defaultChrome: AuthChrome = {
    brandSubtitle: 'POS · periféricos',
    infoTitle: 'Computer City',
    infoText: 'POS de periféricos en Av. Chiriguano. Inventario, ventas, garantías y caja para el equipo de la tienda.',
};

const chromeByPage: Record<string, AuthChrome> = {
    'auth/Login': defaultChrome,
    'auth/Register': {
        brandSubtitle: 'Alta de usuario',
        infoTitle: 'Alta interna de la tienda',
        infoText: 'Este registro no es público: solo personal de Computer City. Si nadie te invitó, pedile el alta a un administrador.',
    },
    'auth/ForgotPassword': {
        brandSubtitle: 'Recuperar acceso',
        infoTitle: 'Computer City',
        infoText: 'Si olvidaste la clave, la recuperás con el correo de la tienda. Un admin también puede resetearte desde Usuarios.',
    },
    'auth/ResetPassword': {
        brandSubtitle: 'Recuperar acceso',
        infoTitle: 'Cambio de clave',
        infoText: 'Usá el código que recibiste por correo. El código vence después de 10 minutos.',
    },
};

const forgotOtpChrome: AuthChrome = {
    brandSubtitle: 'Recuperar acceso',
    infoTitle: 'Código de un solo uso',
    infoText:
        'Ingresá el código de 6 dígitos que enviamos al correo y definí una nueva clave.',
};

const registerDoneChrome: AuthChrome = {
    brandSubtitle: 'Alta de usuario',
    infoTitle: 'Ya estás en el equipo',
    infoText: 'Tu cuenta quedó lista. Entrá al POS con el correo que verificaste.',
};

const recoverDoneChrome: AuthChrome = {
    brandSubtitle: 'Recuperar acceso',
    infoTitle: 'Clave actualizada',
    infoText: 'Tu nueva clave ya está lista. Entrá al POS con el correo de la tienda.',
};

const registerStep = ref(1);

export function useRegisterStep() {
    return registerStep;
}

export function authModeFor(component: string): AuthMode {
    if (component === 'auth/Register') {
        return 'register';
    }

    if (
        component === 'auth/ForgotPassword' ||
        component === 'auth/ResetPassword'
    ) {
        return 'recover';
    }

    return 'login';
}

export function useAuthChrome() {
    const page = usePage();

    return computed(() => {
        const component = page.component;
        const mode = authModeFor(component);
        const registered = Boolean(page.props.registered);
        const recovered = Boolean(page.props.recovered);
        let chrome = chromeByPage[component] ?? defaultChrome;

        if (component === 'auth/ForgotPassword' && page.props.email) {
            chrome = forgotOtpChrome;
        }

        if (component === 'auth/ForgotPassword' && recovered) {
            chrome = recoverDoneChrome;
        }

        if (component === 'auth/Register' && registered) {
            chrome = registerDoneChrome;
        }

        return {
            ...chrome,
            mode,
            brandSide: mode === 'login' ? 'right' : 'left',
            registerStep: registerStep.value,
        };
    });
}
