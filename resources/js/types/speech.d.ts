/**
 * Tipos mínimos de la Web Speech API (dictado por voz). TypeScript no los trae.
 * Se usan nombres propios para no chocar con los de la librería DOM.
 */
interface CcReconocimientoAlternativa {
    readonly transcript: string;
    readonly confidence: number;
}

interface CcReconocimientoResultado {
    readonly isFinal: boolean;
    readonly length: number;
    [indice: number]: CcReconocimientoAlternativa;
}

interface CcReconocimientoResultados {
    readonly length: number;
    [indice: number]: CcReconocimientoResultado;
}

interface CcReconocimientoEvento extends Event {
    readonly resultIndex: number;
    readonly results: CcReconocimientoResultados;
}

interface CcReconocimientoError extends Event {
    readonly error: string;
    readonly message: string;
}

interface CcReconocimientoVoz extends EventTarget {
    lang: string;
    continuous: boolean;
    interimResults: boolean;
    maxAlternatives: number;
    onresult: ((evento: CcReconocimientoEvento) => void) | null;
    onerror: ((evento: CcReconocimientoError) => void) | null;
    onend: (() => void) | null;
    start(): void;
    stop(): void;
    abort(): void;
}

interface CcReconocimientoConstructor {
    new (): CcReconocimientoVoz;
}

interface Window {
    SpeechRecognition?: CcReconocimientoConstructor;
    webkitSpeechRecognition?: CcReconocimientoConstructor;
    /** Solo para capturas y pruebas E2E: frases que useDictado emite en lugar de usar el micrófono. */
    __ccDictadoSimulado?: string[];
}
