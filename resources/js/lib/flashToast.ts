import { router } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import type { FlashToast } from '@/types/ui';

function esToast(valor: unknown): valor is FlashToast {
    if (!valor || typeof valor !== 'object') {
        return false;
    }

    const aviso = valor as FlashToast;

    return typeof aviso.type === 'string' && typeof aviso.message === 'string';
}

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | FlashToast[] | undefined;
        const avisos = Array.isArray(data) ? data : data ? [data] : [];

        avisos.filter(esToast).forEach((aviso, indice) => {
            window.setTimeout(() => {
                toast[aviso.type](aviso.message);
            }, indice * 280);
        });
    });
}
