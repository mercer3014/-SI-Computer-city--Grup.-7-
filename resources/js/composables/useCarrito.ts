import { computed, ref } from 'vue';

export type ItemCarrito = {
    variante_id: number;
    producto: string;
    slug: string;
    etiqueta: string | null;
    sku: string;
    precio: number;
    imagen: string | null;
    cantidad: number;
    stock: number;
};

export type VarianteVigente = {
    variante_id: number;
    producto: string | null;
    slug: string | null;
    etiqueta: string | null;
    sku: string;
    precio: number;
    stock: number;
    disponible: boolean;
    imagen: string | null;
};

const CLAVE = 'cc-carrito-v1';
export const MAXIMO_POR_PRODUCTO = 20;

const items = ref<ItemCarrito[]>([]);
let hidratado = false;

function enNavegador(): boolean {
    return typeof window !== 'undefined' && typeof localStorage !== 'undefined';
}

function hidratar(): void {
    if (hidratado || !enNavegador()) {
        return;
    }

    hidratado = true;

    try {
        const guardado = JSON.parse(localStorage.getItem(CLAVE) ?? '[]');
        items.value = Array.isArray(guardado) ? guardado : [];
    } catch {
        items.value = [];
    }

    window.addEventListener('storage', (evento) => {
        if (evento.key === CLAVE) {
            hidratado = false;
            hidratar();
        }
    });
}

function guardar(): void {
    if (enNavegador()) {
        localStorage.setItem(CLAVE, JSON.stringify(items.value));
    }
}

function tope(stock: number): number {
    return Math.max(0, Math.min(stock, MAXIMO_POR_PRODUCTO));
}

export function useCarrito() {
    hidratar();

    const cantidadTotal = computed(() =>
        items.value.reduce((suma, item) => suma + item.cantidad, 0),
    );

    const total = computed(() =>
        Math.round(
            items.value.reduce(
                (suma, item) => suma + item.precio * item.cantidad,
                0,
            ) * 100,
        ) / 100,
    );

    function agregar(item: Omit<ItemCarrito, 'cantidad'>, cantidad = 1): number {
        const existente = items.value.find(
            (actual) => actual.variante_id === item.variante_id,
        );
        const maximo = tope(item.stock);

        if (existente) {
            existente.cantidad = Math.min(existente.cantidad + cantidad, maximo);
            Object.assign(existente, { ...item, cantidad: existente.cantidad });
        } else if (maximo > 0) {
            items.value.push({ ...item, cantidad: Math.min(cantidad, maximo) });
        }

        guardar();

        return (
            items.value.find((actual) => actual.variante_id === item.variante_id)
                ?.cantidad ?? 0
        );
    }

    function cambiarCantidad(varianteId: number, cantidad: number): void {
        const item = items.value.find((actual) => actual.variante_id === varianteId);

        if (!item) {
            return;
        }

        item.cantidad = Math.max(1, Math.min(Math.floor(cantidad) || 1, tope(item.stock)));
        guardar();
    }

    function quitar(varianteId: number): void {
        items.value = items.value.filter((item) => item.variante_id !== varianteId);
        guardar();
    }

    function vaciar(): void {
        items.value = [];
        guardar();
    }

    /**
     * Aplica precio y stock vigentes. Devuelve avisos para mostrar al cliente.
     */
    function sincronizar(vigentes: VarianteVigente[]): string[] {
        const avisos: string[] = [];
        const porId = new Map(vigentes.map((v) => [v.variante_id, v]));

        items.value = items.value.flatMap((item) => {
            const actual = porId.get(item.variante_id);

            if (!actual || !actual.disponible || actual.stock <= 0) {
                avisos.push(`${item.producto} ya no está disponible y lo quitamos del carrito.`);

                return [];
            }

            if (actual.precio !== item.precio) {
                avisos.push(`El precio de ${item.producto} cambió a Bs ${actual.precio.toFixed(2)}.`);
            }

            const cantidad = Math.min(item.cantidad, tope(actual.stock));

            if (cantidad < item.cantidad) {
                avisos.push(`Solo quedan ${actual.stock} unidades de ${item.producto}.`);
            }

            return [
                {
                    ...item,
                    producto: actual.producto ?? item.producto,
                    slug: actual.slug ?? item.slug,
                    etiqueta: actual.etiqueta,
                    sku: actual.sku,
                    precio: actual.precio,
                    imagen: actual.imagen ?? item.imagen,
                    stock: actual.stock,
                    cantidad,
                },
            ];
        });

        guardar();

        return avisos;
    }

    return {
        items,
        cantidadTotal,
        total,
        agregar,
        cambiarCantidad,
        quitar,
        vaciar,
        sincronizar,
    };
}

export function bolivianos(monto: number): string {
    return `Bs ${new Intl.NumberFormat('es-BO', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(monto)}`;
}
