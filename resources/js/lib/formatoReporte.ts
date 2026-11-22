export type TipoColumna =
    | 'texto'
    | 'entero'
    | 'moneda'
    | 'porcentaje'
    | 'fecha';

export type SerieGrafico = { nombre: string; valores: number[] };

export type DatosGrafico = {
    tipo: 'barra' | 'linea' | 'torta' | 'ninguno';
    etiquetas: string[];
    series: SerieGrafico[];
};

const decimales = new Intl.NumberFormat('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
    useGrouping: 'always',
});

const enteros = new Intl.NumberFormat('es-BO', {
    maximumFractionDigits: 0,
    useGrouping: 'always',
});

export const PALETA = [
    '#d97706',
    '#2563eb',
    '#059669',
    '#9333ea',
    '#dc2626',
    '#0891b2',
    '#ca8a04',
    '#be185d',
];

export function esNumerica(tipo: TipoColumna): boolean {
    return tipo === 'entero' || tipo === 'moneda' || tipo === 'porcentaje';
}

/** Mismo formato que el PDF y el Word: "Bs 1.234,50", "1.234", "40,00 %". */
export function formatearValor(
    valor: string | number | null | undefined,
    tipo: TipoColumna,
): string {
    if (valor === null || valor === undefined || valor === '') {
        return '';
    }

    if (typeof valor !== 'number') {
        return String(valor);
    }

    switch (tipo) {
        case 'moneda':
            return `Bs ${decimales.format(valor)}`;
        case 'entero':
            return enteros.format(valor);
        case 'porcentaje':
            return `${decimales.format(valor)} %`;
        default:
            return String(valor);
    }
}

export function formatearMonto(valor: number): string {
    return formatearValor(valor, 'moneda');
}

/** Para los ejes de los gráficos: 1,2 mil, 15 mil. */
export function abreviar(valor: number): string {
    const absoluto = Math.abs(valor);

    if (absoluto >= 1_000_000) {
        return `${(valor / 1_000_000).toLocaleString('es-BO', { maximumFractionDigits: 1 })} M`;
    }

    if (absoluto >= 1_000) {
        return `${(valor / 1_000).toLocaleString('es-BO', { maximumFractionDigits: 1 })} mil`;
    }

    return enteros.format(valor);
}

/** Máximo "redondo" para que las líneas guía caigan en números cómodos. */
export function techoRedondo(valor: number): number {
    if (valor <= 0) {
        return 1;
    }

    const potencia = 10 ** Math.floor(Math.log10(valor));
    const paso = [1, 2, 2.5, 5, 10].find((p) => p * potencia >= valor) ?? 10;

    return paso * potencia;
}
