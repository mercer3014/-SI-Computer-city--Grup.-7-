/** Token CSRF de Laravel, que viaja en la cookie XSRF-TOKEN. */
export function tokenXsrf(): string {
    const coincidencia = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return coincidencia?.[1] ? decodeURIComponent(coincidencia[1]) : '';
}

/**
 * Llamada JSON al servidor con la cabecera CSRF. Devuelve la respuesta tal cual
 * para que cada pantalla maneje 403, 422, 429 o 503 a su manera.
 */
export async function enviarJson(
    url: string,
    cuerpo: unknown = {},
    metodo: 'POST' | 'DELETE' = 'POST',
): Promise<Response> {
    return fetch(url, {
        method: metodo,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': tokenXsrf(),
        },
        body: metodo === 'DELETE' ? undefined : JSON.stringify(cuerpo),
    });
}
