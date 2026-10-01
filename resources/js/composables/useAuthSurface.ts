import { router } from '@inertiajs/vue3';
import type { AuthMode } from '@/composables/useAuthChrome';

export type AuthSurface = {
    mode: AuthMode;
    side: 'left' | 'right';
};

export type AuthPanelSize = {
    width: number;
    aspectRatio: number;
};

const SURFACE_KEY = 'cc-auth-surface';
const SIZE_KEY = 'cc-auth-size';

let lastSettled: AuthSurface | null = readSurface();
let visual: AuthSurface | null = lastSettled;
let lastSize: AuthPanelSize | null = readSize();
let hooksBound = false;

if (typeof document !== 'undefined' && lastSettled) {
    document.documentElement.dataset.ccAuthMode = lastSettled.mode;
    document.documentElement.dataset.ccAuthSide = lastSettled.side;
}

function readSurface(): AuthSurface | null {
    if (typeof sessionStorage === 'undefined') {
        return null;
    }

    try {
        return JSON.parse(sessionStorage.getItem(SURFACE_KEY) ?? '') as AuthSurface;
    } catch {
        return null;
    }
}

function readSize(): AuthPanelSize | null {
    if (typeof sessionStorage === 'undefined') {
        return null;
    }

    try {
        return JSON.parse(sessionStorage.getItem(SIZE_KEY) ?? '') as AuthPanelSize;
    } catch {
        return null;
    }
}

function writeSurface(next: AuthSurface): void {
    lastSettled = next;
    visual = next;

    if (typeof sessionStorage === 'undefined') {
        return;
    }

    sessionStorage.setItem(SURFACE_KEY, JSON.stringify(next));

    if (typeof document !== 'undefined') {
        document.documentElement.dataset.ccAuthMode = next.mode;
        document.documentElement.dataset.ccAuthSide = next.side;
    }
}

function sameSurface(a: AuthSurface, b: AuthSurface): boolean {
    return a.mode === b.mode && a.side === b.side;
}

export function rememberVisual(next: AuthSurface): void {
    visual = next;
}

export function rememberOutgoing(next?: AuthSurface): void {
    writeSurface(next ?? visual ?? lastSettled ?? readSurface() ?? {
        mode: 'login',
        side: 'right',
    });
}

export function persistSettled(next: AuthSurface): void {
    writeSurface(next);
}

export function originFor(next: AuthSurface): AuthSurface {
    const remembered = lastSettled ?? visual ?? readSurface();

    if (remembered && !sameSurface(remembered, next)) {
        return remembered;
    }

    return remembered ?? next;
}

export function rememberPanelSize(next: AuthPanelSize): void {
    lastSize = next;

    if (typeof sessionStorage === 'undefined') {
        return;
    }

    sessionStorage.setItem(SIZE_KEY, JSON.stringify(next));
}

export function lastPanelSize(): AuthPanelSize | null {
    return lastSize ?? readSize();
}

export function bindAuthSurfaceHooks(): void {
    if (hooksBound || typeof window === 'undefined') {
        return;
    }

    hooksBound = true;

    router.on('before', () => {
        if (visual) {
            writeSurface(visual);
        }
    });

    window.addEventListener(
        'pointerdown',
        () => {
            if (visual) {
                writeSurface(visual);
            }
        },
        { capture: true },
    );
}
