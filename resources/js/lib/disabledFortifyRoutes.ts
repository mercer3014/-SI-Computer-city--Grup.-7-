const unusedRoute = (url = '#') => {
    const form = () => ({ action: url, method: 'post' as const });

    return Object.assign(() => ({ url, method: 'post' as const }), {
        url: (..._args: unknown[]) => url,
        form,
    });
};

export const store = unusedRoute();
export const send = unusedRoute();
export const disable = unusedRoute();
export const enable = unusedRoute();
export const confirm = unusedRoute();
export const regenerateRecoveryCodes = unusedRoute();
export const qrCode = unusedRoute();
export const recoveryCodes = unusedRoute();
export const secretKey = unusedRoute();
export const index = unusedRoute();
export const destroy = unusedRoute();
