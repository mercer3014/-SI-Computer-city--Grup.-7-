import type { DatosGrafico, TipoColumna } from '@/lib/formatoReporte';

export type Celda = string | number | null;

export type ParametrosReporte = {
    tipo: string;
    periodo: string;
    desde: string;
    hasta: string;
    filtros: Record<string, number | string>;
};

/** Reporte listo para mostrarse: lo devuelven Reportes (CU16) y el Asistente (CU22). */
export type ResultadoReporte = {
    clave: string;
    titulo: string;
    encabezado: {
        tienda: string;
        ubicacion: string;
        periodo: string;
        usuario: string;
        generado: string;
    };
    columnas: { clave: string; etiqueta: string; tipo: TipoColumna }[];
    filas: Record<string, Celda>[];
    totales: Record<string, Celda>;
    advertencias: string[];
    grafico: DatosGrafico;
    parametros: ParametrosReporte;
    vacio: boolean;
};
