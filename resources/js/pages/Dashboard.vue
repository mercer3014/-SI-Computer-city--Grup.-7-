<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import { computed, reactive } from 'vue';
import TarjetaIndicador from '@/components/dashboard/TarjetaIndicador.vue';
import GraficoBarras from '@/components/reportes/GraficoBarras.vue';
import { adminSpring } from '@/lib/adminMotion';
import { formatearMonto } from '@/lib/formatoReporte';
import { dashboard } from '@/routes';

type Periodo = {
    clave: 'hoy' | 'semana' | 'mes' | 'rango';
    desde: string;
    hasta: string;
    etiqueta: string;
};

type VentasDelDia = {
    cantidad: number;
    monto: number;
    propias: boolean;
    desde: string;
};

type AlertasStock = {
    agotadas: number;
    bajoMinimo: number;
    items: { sku: string; producto: string; stock: number; minimo: number }[];
};

type Garantias = {
    total: number;
    items: {
        producto: string;
        serie: string;
        venta: string;
        cliente: string;
        vence: string;
    }[];
};

type Pedidos = {
    total: number;
    items: { pedido: string; cliente: string; fecha: string; total: number }[];
};

type Cotizaciones = {
    total: number;
    porVencer: number;
    items: { codigo: string; cliente: string; vence: string }[];
};

/** null en un indicador = falló al calcularse; si la clave no viene, el usuario no tiene permiso. */
type Operativos = {
    ventasDelDia?: VentasDelDia | null;
    alertasStock?: AlertasStock | null;
    garantiasPorVencer?: Garantias | null;
    pedidosPendientes?: Pedidos | null;
    cotizaciones?: Cotizaciones | null;
};

type Financieros = {
    resumen: {
        ingresos: number;
        utilidad: number;
        utilidadIncompleta: boolean;
    } | null;
    saldoCaja: { saldo: number; neto: number } | null;
    calificacion: { promedio: number | null; total: number } | null;
    ventasPorDia: {
        nivel: string;
        etiquetas: string[];
        valores: number[];
    } | null;
    topProductos:
        | { sku: string; producto: string; unidades: number; total: number }[]
        | null;
};

const props = defineProps<{
    periodo: Periodo;
    hoy: string;
    operativos: Operativos;
    financieros?: Financieros;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Inicio', href: dashboard() }],
    },
});

const errores = computed(
    () => (usePage().props.errors ?? {}) as Record<string, string | undefined>,
);

const PERIODOS = [
    { id: 'hoy', etiqueta: 'Hoy' },
    { id: 'semana', etiqueta: 'Semana' },
    { id: 'mes', etiqueta: 'Mes' },
    { id: 'rango', etiqueta: 'Rango' },
] as const;

const rango = reactive({
    desde: props.periodo.desde,
    hasta: props.periodo.hasta,
});
const eligiendoRango = reactive({ activo: props.periodo.clave === 'rango' });

function cambiarPeriodo(clave: Periodo['clave']): void {
    if (clave === 'rango') {
        eligiendoRango.activo = true;

        return;
    }

    eligiendoRango.activo = false;
    router.get(
        dashboard().url,
        { periodo: clave },
        { preserveScroll: true, preserveState: true },
    );
}

function aplicarRango(): void {
    router.get(
        dashboard().url,
        { periodo: 'rango', desde: rango.desde, hasta: rango.hasta },
        { preserveScroll: true, preserveState: true },
    );
}

const entero = new Intl.NumberFormat('es-BO', { useGrouping: 'always' });

const consultaPeriodo = computed(
    () =>
        `periodo=personalizado&desde=${props.periodo.desde}&hasta=${props.periodo.hasta}`,
);

const tienePanel = computed(
    () =>
        Object.keys(props.operativos).length > 0 ||
        props.financieros !== undefined,
);

const textoVentas = computed(() =>
    props.operativos.ventasDelDia?.propias
        ? 'Mis ventas de hoy'
        : 'Ventas de hoy',
);

const hayVentasEnElGrafico = computed(() =>
    (props.financieros?.ventasPorDia?.valores ?? []).some((v) => v > 0),
);

const calificacion = computed(() => props.financieros?.calificacion);
</script>

<template>
    <div>
        <Head title="Inicio" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Inicio</p>
                    <h1>Panel</h1>
                    <p>
                        Resumen de la tienda según lo que puedes ver. Período:
                        {{ periodo.etiqueta }}.
                    </p>
                </motion.div>
            </motion.div>

            <div
                v-if="!tienePanel"
                class="cc-dash__vacio"
                data-test="panel-vacio"
            >
                <strong>Todavía no hay indicadores para tu cuenta</strong>
                <span>
                    Cuando tengas acceso a ventas, inventario, garantías o
                    reportes, aquí verás su resumen.
                </span>
            </div>

            <template v-else>
                <div
                    v-if="financieros"
                    class="cc-dash__periodo"
                    role="group"
                    aria-label="Período del panel"
                >
                    <div class="cc-dash__segmentos">
                        <button
                            v-for="item in PERIODOS"
                            :key="item.id"
                            type="button"
                            :class="{
                                'is-activo':
                                    item.id === 'rango'
                                        ? eligiendoRango.activo
                                        : !eligiendoRango.activo &&
                                          periodo.clave === item.id,
                            }"
                            :data-test="`periodo-${item.id}`"
                            @click="cambiarPeriodo(item.id)"
                        >
                            {{ item.etiqueta }}
                        </button>
                    </div>
                    <form
                        v-if="eligiendoRango.activo"
                        class="cc-dash__rango"
                        @submit.prevent="aplicarRango"
                    >
                        <label>
                            Desde
                            <span class="cc-log__control">
                                <input
                                    v-model="rango.desde"
                                    type="date"
                                    required
                                />
                            </span>
                        </label>
                        <label>
                            Hasta
                            <span class="cc-log__control">
                                <input
                                    v-model="rango.hasta"
                                    type="date"
                                    required
                                />
                            </span>
                        </label>
                        <button type="submit" class="cc-button">Aplicar</button>
                        <p v-if="errores.hasta" class="cc-dash__error">
                            {{ errores.hasta }}
                        </p>
                    </form>
                </div>

                <div class="cc-dash__tarjetas">
                    <TarjetaIndicador
                        v-if="'ventasDelDia' in operativos"
                        :titulo="textoVentas"
                        :valor="
                            operativos.ventasDelDia
                                ? formatearMonto(operativos.ventasDelDia.monto)
                                : null
                        "
                        :detalle="
                            operativos.ventasDelDia
                                ? `${operativos.ventasDelDia.cantidad} ${operativos.ventasDelDia.cantidad === 1 ? 'venta' : 'ventas'}`
                                : undefined
                        "
                        :en-cero="operativos.ventasDelDia?.cantidad === 0"
                        :enlace="`/ventas?desde=${hoy}&hasta=${hoy}`"
                    />

                    <template v-if="financieros">
                        <TarjetaIndicador
                            titulo="Ingresos del período"
                            :valor="
                                financieros.resumen
                                    ? formatearMonto(
                                          financieros.resumen.ingresos,
                                      )
                                    : null
                            "
                            :detalle="periodo.etiqueta"
                            :en-cero="financieros.resumen?.ingresos === 0"
                            :enlace="`/reportes/generar?tipo=ventas_periodo&${consultaPeriodo}`"
                        />
                        <TarjetaIndicador
                            titulo="Utilidad bruta"
                            :valor="
                                financieros.resumen
                                    ? formatearMonto(
                                          financieros.resumen.utilidad,
                                      )
                                    : null
                            "
                            :detalle="
                                financieros.resumen?.utilidadIncompleta
                                    ? 'Puede estar incompleta: hay ventas sin costo'
                                    : periodo.etiqueta
                            "
                            :en-cero="financieros.resumen?.utilidad === 0"
                            :enlace="`/reportes/generar?tipo=utilidad_bruta&${consultaPeriodo}`"
                        />
                        <TarjetaIndicador
                            titulo="Saldo de caja"
                            :valor="
                                financieros.saldoCaja
                                    ? formatearMonto(
                                          financieros.saldoCaja.saldo,
                                      )
                                    : null
                            "
                            :detalle="
                                financieros.saldoCaja
                                    ? `Neto del período: ${formatearMonto(financieros.saldoCaja.neto)}`
                                    : undefined
                            "
                            :en-cero="financieros.saldoCaja?.saldo === 0"
                            :enlace="`/caja?desde=${periodo.desde}&hasta=${periodo.hasta}`"
                        />
                        <TarjetaIndicador
                            titulo="Calificación promedio"
                            :valor="
                                calificacion
                                    ? calificacion.promedio !== null
                                        ? `${calificacion.promedio.toLocaleString('es-BO', { minimumFractionDigits: 1, maximumFractionDigits: 2 })} / 5`
                                        : 'Sin calificaciones'
                                    : null
                            "
                            :detalle="
                                calificacion
                                    ? `${calificacion.total} ${calificacion.total === 1 ? 'calificación publicada' : 'calificaciones publicadas'}`
                                    : undefined
                            "
                            :en-cero="calificacion?.promedio === null"
                            enlace="/calificaciones"
                        />
                    </template>
                </div>

                <div v-if="financieros" class="cc-dash__fila">
                    <motion.div
                        layout
                        class="cc-log__panel cc-dash__panel cc-dash__grafico"
                        :transition="adminSpring"
                    >
                        <h2>Ventas por día</h2>
                        <p
                            v-if="!financieros.ventasPorDia"
                            class="cc-dash__sin-datos"
                        >
                            No disponible
                        </p>
                        <p
                            v-else-if="!hayVentasEnElGrafico"
                            class="cc-dash__sin-datos"
                            data-test="grafico-sin-datos"
                        >
                            Sin datos para el período
                        </p>
                        <GraficoBarras
                            v-else
                            :etiquetas="financieros.ventasPorDia.etiquetas"
                            :series="[
                                {
                                    nombre: 'Ventas',
                                    valores: financieros.ventasPorDia.valores,
                                },
                            ]"
                            moneda
                        />
                    </motion.div>

                    <motion.div
                        layout
                        class="cc-log__panel cc-dash__panel"
                        :transition="adminSpring"
                    >
                        <h2>Más vendidos</h2>
                        <p
                            v-if="!financieros.topProductos"
                            class="cc-dash__sin-datos"
                        >
                            No disponible
                        </p>
                        <p
                            v-else-if="financieros.topProductos.length === 0"
                            class="cc-dash__sin-datos"
                        >
                            Sin datos para el período
                        </p>
                        <ol v-else class="cc-dash__ranking" data-test="ranking">
                            <li
                                v-for="producto in financieros.topProductos"
                                :key="producto.sku"
                            >
                                <div class="cc-dash__ranking-fila">
                                    <span>
                                        <strong>{{ producto.producto }}</strong>
                                        <small>{{ producto.sku }}</small>
                                    </span>
                                    <b
                                        >{{
                                            entero.format(producto.unidades)
                                        }}
                                        u.</b
                                    >
                                </div>
                            </li>
                        </ol>
                        <Link
                            v-if="financieros.topProductos?.length"
                            class="cc-dash__enlace"
                            :href="`/reportes/generar?tipo=productos_mas_vendidos&limite=5&${consultaPeriodo}`"
                        >
                            Ver el reporte completo
                        </Link>
                    </motion.div>
                </div>

                <motion.div
                    v-if="
                        Object.keys(operativos).some(
                            (k) => k !== 'ventasDelDia',
                        )
                    "
                    layout
                    class="cc-log__panel cc-dash__panel"
                    :transition="adminSpring"
                    data-test="alertas"
                >
                    <h2>Alertas</h2>
                    <div class="cc-dash__alertas">
                        <section v-if="'alertasStock' in operativos">
                            <header>
                                <h3>Stock</h3>
                                <Link
                                    v-if="operativos.alertasStock"
                                    href="/inventario?alerta=bajo"
                                    data-test="alerta-stock"
                                >
                                    {{
                                        operativos.alertasStock.agotadas +
                                        operativos.alertasStock.bajoMinimo
                                    }}
                                    por revisar
                                </Link>
                                <span v-else>No disponible</span>
                            </header>
                            <template v-if="operativos.alertasStock">
                                <p>
                                    <Link href="/inventario?alerta=cero">
                                        {{ operativos.alertasStock.agotadas }}
                                        agotadas
                                    </Link>
                                    ·
                                    {{ operativos.alertasStock.bajoMinimo }}
                                    bajo el mínimo
                                </p>
                                <ul>
                                    <li
                                        v-for="item in operativos.alertasStock
                                            .items"
                                        :key="item.sku"
                                    >
                                        {{ item.producto }}
                                        <small>
                                            {{ item.stock }} de
                                            {{ item.minimo }}
                                        </small>
                                    </li>
                                </ul>
                            </template>
                        </section>

                        <section v-if="'garantiasPorVencer' in operativos">
                            <header>
                                <h3>Garantías por vencer (30 días)</h3>
                                <Link
                                    v-if="operativos.garantiasPorVencer"
                                    href="/garantias?filtro=por_vencer"
                                    data-test="alerta-garantias"
                                >
                                    {{ operativos.garantiasPorVencer.total }}
                                </Link>
                                <span v-else>No disponible</span>
                            </header>
                            <ul v-if="operativos.garantiasPorVencer">
                                <li
                                    v-for="item in operativos.garantiasPorVencer
                                        .items"
                                    :key="item.serie"
                                >
                                    {{ item.producto }}
                                    <small>
                                        {{ item.serie }} · vence
                                        {{ item.vence }}
                                    </small>
                                </li>
                            </ul>
                        </section>

                        <section v-if="'pedidosPendientes' in operativos">
                            <header>
                                <h3>Pedidos web pagados</h3>
                                <Link
                                    v-if="operativos.pedidosPendientes"
                                    href="/ventas?origen=web"
                                    data-test="alerta-pedidos"
                                >
                                    {{ operativos.pedidosPendientes.total }}
                                </Link>
                                <span v-else>No disponible</span>
                            </header>
                            <ul v-if="operativos.pedidosPendientes">
                                <li
                                    v-for="item in operativos.pedidosPendientes
                                        .items"
                                    :key="item.pedido"
                                >
                                    {{ item.pedido }}
                                    <small>
                                        {{ item.cliente }} ·
                                        {{ formatearMonto(item.total) }}
                                    </small>
                                </li>
                            </ul>
                        </section>

                        <section v-if="'cotizaciones' in operativos">
                            <header>
                                <h3>Cotizaciones</h3>
                                <Link
                                    v-if="operativos.cotizaciones"
                                    href="/cotizaciones?estado=PENDIENTE"
                                    data-test="alerta-cotizaciones"
                                >
                                    {{ operativos.cotizaciones.total }}
                                    pendientes
                                </Link>
                                <span v-else>No disponible</span>
                            </header>
                            <template v-if="operativos.cotizaciones">
                                <p>
                                    {{ operativos.cotizaciones.porVencer }}
                                    por vencer en 3 días o menos
                                </p>
                                <ul>
                                    <li
                                        v-for="item in operativos.cotizaciones
                                            .items"
                                        :key="item.codigo"
                                    >
                                        {{ item.codigo }}
                                        <small>
                                            {{ item.cliente }} · vence
                                            {{ item.vence }}
                                        </small>
                                    </li>
                                </ul>
                            </template>
                        </section>
                    </div>
                </motion.div>
            </template>
        </section>
    </div>
</template>

<style scoped>
.cc-dash__vacio {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 48px 16px;
    text-align: center;
    color: var(--cc-muted);
}

.cc-dash__vacio strong {
    color: var(--cc-text);
    font-size: 17px;
}

.cc-dash__periodo {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

.cc-dash__segmentos {
    display: inline-flex;
    gap: 4px;
    padding: 4px;
    border: 1px solid var(--cc-border);
    border-radius: 999px;
}

.cc-dash__segmentos button {
    padding: 7px 16px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--cc-muted);
    font: inherit;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}

.cc-dash__segmentos button.is-activo {
    background: var(--cc-accent);
    color: #fff;
}

.cc-dash__rango {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
}

.cc-dash__rango label {
    display: flex;
    flex-direction: column;
    gap: 4px;
    color: var(--cc-muted);
    font-size: 12px;
}

.cc-dash__error {
    flex-basis: 100%;
    margin: 0;
    color: var(--cc-error);
    font-size: 12px;
}

.cc-dash__tarjetas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 14px;
}

.cc-dash__fila {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr);
    gap: 16px;
}

.cc-dash__panel {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 18px 20px;
}

.cc-dash__panel h2 {
    margin: 0;
    font-size: 16px;
}

.cc-dash__sin-datos {
    margin: 0;
    padding: 32px 0;
    text-align: center;
    color: var(--cc-muted);
}

.cc-dash__ranking {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin: 0;
    padding-left: 20px;
}

.cc-dash__ranking li {
    padding-right: 4px;
}

.cc-dash__ranking-fila {
    display: flex;
    gap: 10px;
    align-items: center;
    justify-content: space-between;
}

.cc-dash__ranking-fila span {
    display: flex;
    flex-direction: column;
}

.cc-dash__ranking small,
.cc-dash__alertas small {
    color: var(--cc-muted);
    font-size: 12px;
}

.cc-dash__ranking b {
    font-variant-numeric: tabular-nums;
}

.cc-dash__enlace,
.cc-dash__alertas a {
    color: var(--cc-accent);
    text-decoration: underline;
}

.cc-dash__alertas {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
}

.cc-dash__alertas header {
    display: flex;
    gap: 8px;
    align-items: baseline;
    justify-content: space-between;
}

.cc-dash__alertas h3 {
    margin: 0;
    font-size: 14px;
}

.cc-dash__alertas p {
    margin: 6px 0;
    font-size: 13px;
}

.cc-dash__alertas ul {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin: 6px 0 0;
    padding: 0;
    list-style: none;
    font-size: 13px;
}

.cc-dash__alertas li small {
    display: block;
}

@media (max-width: 860px) {
    .cc-dash__fila {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
