import { onBeforeUnmount, ref } from 'vue';

export type ErrorDictado =
    | 'no-soportado'
    | 'permiso-denegado'
    | 'sin-audio'
    | 'no-reconocido'
    | 'red';

export const MENSAJES_DICTADO: Record<ErrorDictado, string> = {
    'no-soportado':
        'Tu navegador no permite dictado por voz; usa el formulario o la entrada de texto.',
    'permiso-denegado':
        'No hay permiso para usar el micrófono; usa el formulario o la entrada de texto.',
    'sin-audio':
        'No se escuchó nada. Revisa el micrófono e inténtalo de nuevo, o usa el formulario.',
    'no-reconocido':
        'No se entendió lo que dijiste. Inténtalo de nuevo o usa el formulario.',
    red: 'No se pudo conectar con el servicio de voz. Usa el formulario o inténtalo más tarde.',
};

const IDIOMAS = ['es-BO', 'es-ES'];
const PAUSA_SIMULADA_MS = 350;

/**
 * Dictado por voz con la Web Speech API: muestra el texto en vivo mientras se habla (`textoParcial`)
 * y deja el resultado en `textoFinal` para que la persona lo revise antes de enviarlo.
 *
 * Con VITE_DICTADO_SIMULADO=true y `window.__ccDictadoSimulado = ['frase']`, emite esa frase palabra
 * por palabra en lugar de usar el micrófono. Sirve para capturas y pruebas E2E; nunca se activa en producción.
 */
export function useDictado() {
    const Reconocimiento =
        window.SpeechRecognition ?? window.webkitSpeechRecognition;
    const simulado =
        import.meta.env.VITE_DICTADO_SIMULADO === 'true' &&
        Array.isArray(window.__ccDictadoSimulado);

    const soportado = ref(Boolean(Reconocimiento) || simulado);
    const escuchando = ref(false);
    const textoParcial = ref('');
    const textoFinal = ref('');
    const error = ref<ErrorDictado | null>(
        soportado.value ? null : 'no-soportado',
    );

    let reconocimiento: CcReconocimientoVoz | null = null;
    let temporizador = 0;
    let indiceIdioma = 0;

    function limpiar(): void {
        textoParcial.value = '';
        textoFinal.value = '';
        error.value = soportado.value ? null : 'no-soportado';
    }

    function errorDe(codigo: string): ErrorDictado {
        if (codigo === 'not-allowed' || codigo === 'service-not-allowed') {
            return 'permiso-denegado';
        }

        if (codigo === 'no-speech' || codigo === 'audio-capture') {
            return 'sin-audio';
        }

        if (codigo === 'network') {
            return 'red';
        }

        return 'no-reconocido';
    }

    function iniciarSimulado(): void {
        const frase = window.__ccDictadoSimulado?.[0] ?? '';
        const palabras = frase.split(/\s+/).filter(Boolean);
        let indice = 0;

        escuchando.value = true;
        window.clearInterval(temporizador);

        temporizador = window.setInterval(() => {
            indice += 1;
            textoParcial.value = palabras.slice(0, indice).join(' ');

            if (indice >= palabras.length) {
                window.clearInterval(temporizador);
                textoFinal.value = frase;
                textoParcial.value = '';
                escuchando.value = false;
            }
        }, PAUSA_SIMULADA_MS);
    }

    function iniciar(): void {
        limpiar();

        if (!soportado.value) {
            return;
        }

        if (simulado) {
            iniciarSimulado();

            return;
        }

        if (!Reconocimiento) {
            return;
        }

        reconocimiento = new Reconocimiento();
        reconocimiento.lang = IDIOMAS[indiceIdioma] ?? 'es-ES';
        reconocimiento.interimResults = true;
        reconocimiento.continuous = false;
        reconocimiento.maxAlternatives = 1;

        reconocimiento.onresult = (evento) => {
            let parcial = '';
            let final = '';

            for (let i = 0; i < evento.results.length; i++) {
                const resultado = evento.results[i];
                const texto = resultado?.[0]?.transcript ?? '';

                if (resultado?.isFinal) {
                    final += texto;
                } else {
                    parcial += texto;
                }
            }

            textoParcial.value = parcial.trim();

            if (final !== '') {
                textoFinal.value = final.trim();
            }
        };

        reconocimiento.onerror = (evento) => {
            if (
                evento.error === 'language-not-supported' &&
                indiceIdioma < IDIOMAS.length - 1
            ) {
                indiceIdioma += 1;
                iniciar();

                return;
            }

            error.value = errorDe(evento.error);

            if (error.value === 'permiso-denegado') {
                soportado.value = false;
            }
        };

        reconocimiento.onend = () => {
            escuchando.value = false;

            if (textoFinal.value === '' && textoParcial.value !== '') {
                textoFinal.value = textoParcial.value;
            }

            textoParcial.value = '';

            if (textoFinal.value === '' && error.value === null) {
                error.value = 'no-reconocido';
            }
        };

        try {
            reconocimiento.start();
            escuchando.value = true;
        } catch {
            error.value = 'no-reconocido';
        }
    }

    function detener(): void {
        window.clearInterval(temporizador);
        reconocimiento?.stop();

        if (simulado) {
            escuchando.value = false;
        }
    }

    onBeforeUnmount(() => {
        window.clearInterval(temporizador);
        reconocimiento?.abort();
    });

    return {
        soportado,
        escuchando,
        textoParcial,
        textoFinal,
        error,
        iniciar,
        detener,
        limpiar,
    };
}
