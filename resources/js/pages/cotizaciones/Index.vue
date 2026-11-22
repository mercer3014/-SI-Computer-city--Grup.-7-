<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    ChevronLeft,
    ChevronRight,
    Eye,
    Mail,
    MessageCircle,
    Phone,
    RefreshCw,
    RotateCcw,
    Search,
    ShoppingCart,
    X,
} from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import GraficoReporte from '@/components/reportes/GraficoReporte.vue';
import TablaReporte from '@/components/reportes/TablaReporte.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { adminSpring } from '@/lib/adminMotion';
import type { ResultadoReporte } from '@/types/reportes';

type Estado = 'PENDIENTE' | 'ATENDIDA' | 'RECHAZADA' | 'VENCIDA' | 'CONVERTIDA';

type Fila = {
    id: number;
    codigo: string;
    cliente: string;
    estado: Estado;
    origen: string;
    fecha: string | null;
    vencimiento: string | null;
    total: number;
    venta: { id: number; numero: string } | null;
};

type Ficha = {
    id: number;
    codigo: string;
    estado: Estado;
    origen: string;
    fecha: string | null;
    vencimiento: string | null;
    vigente: boolean;
    total: number;
    observacion: string | null;
    fecha_estado: string | null;
    actualizado_por: string | null;
    cliente: {
        id: number;
        nombre: string;
        documento: string;
        telefono: string | null;
        email: string | null;
        whatsapp_url: string | null;
    };
    items: {
        descripcion: string;
        cantidad: number;
        precio_cotizado: number;
        precio_actual: number;
        subtotal: number;
        stock: number;
    }[];
    venta: { id: number; numero: string } | null;
    puede_convertir: boolean;
    motivo_bloqueo: string | null;
    puede_renovar: boolean;
    puede_cambiar_estado: boolean;
    puede_vender: boolean;
};

type Filtros = { estado: string; desde: string; hasta: string; q: string };

const props = defineProps<{
    tab: 'cotizaciones' | 'visitados';
    cotizaciones: {
        data: Fila[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    } | null;
    kpis: Record<string, number>;
    filtros: Filtros;
    ficha: Ficha | null;
    puedeConvertir: boolean;
    visitados: {
        resultado: ResultadoReporte;
        desde: string;
        hasta: string;
        error: string | null;
    } | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Cotizaciones', href: '/cotizaciones' }],
    },
});

const cantidad = new Intl.NumberFormat('es-BO');
const moneda = new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 2,
});

const ETIQUETAS: Record<Estado, string> = {
    PENDIENTE: 'Pendiente',
    ATENDIDA: 'Atendida',
    RECHAZADA: 'Rechazada',
    VENCIDA: 'Vencida',
    CONVERTIDA: 'Convertida',
};

function badge(estado: Estado): string {
    switch (estado) {
        case 'CONVERTIDA':
            return 'cc-log__badge--crear';
        case 'ATENDIDA':
            return 'cc-log__badge--editar';
        case 'RECHAZADA':
            return 'cc-log__badge--error';
        case 'VENCIDA':
            return 'cc-log__badge--neutro';
        default:
            return 'cc-log__badge--alerta';
    }
}

/* ── Listado ── */

const form = reactive<Filtros>({ ...props.filtros });
let busquedaTimer = 0;

watch(
    () => props.filtros,
    (filtros) => Object.assign(form, filtros),
);

onBeforeUnmount(() => window.clearTimeout(busquedaTimer));

function consulta(
    origen: Filtros,
    extra: Record<string, string | number> = {},
): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    (Object.keys(origen) as (keyof Filtros)[]).forEach((clave) => {
        if (origen[clave] !== '') {
            query[clave] = origen[clave];
        }
    });

    return { ...query, ...extra };
}

function aplicar(extra: Record<string, string | number> = {}): void {
    router.get('/cotizaciones', consulta(form, extra), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function buscarLuego(): void {
    window.clearTimeout(busquedaTimer);
    busquedaTimer = window.setTimeout(() => aplicar(), 320);
}

function ir(pagina: number): void {
    const meta = props.cotizaciones?.meta;

    if (!meta || pagina < 1 || pagina > meta.last_page) {
        return;
    }

    aplicar(pagina > 1 ? { page: pagina } : {});
}

function filtrarEstado(estado: string): void {
    form.estado = form.estado === estado ? '' : estado;
    aplicar();
}

function quitarFiltro(clave: keyof Filtros): void {
    form[clave] = '';
    aplicar();
}

function limpiar(): void {
    window.clearTimeout(busquedaTimer);
    Object.assign(form, { estado: '', desde: '', hasta: '', q: '' });
    aplicar();
}

const kpis = computed(() =>
    [
        { estado: 'PENDIENTE', label: 'Pendientes' },
        { estado: 'ATENDIDA', label: 'Atendidas' },
        { estado: 'RECHAZADA', label: 'Rechazadas' },
        { estado: 'VENCIDA', label: 'Vencidas' },
        { estado: 'CONVERTIDA', label: 'Convertidas' },
        { estado: '', label: 'Total' },
    ].map((k) => ({
        ...k,
        valor: cantidad.format(
            k.estado === ''
                ? (props.kpis.total ?? 0)
                : (props.kpis[k.estado] ?? 0),
        ),
    })),
);

const chips = computed(() => {
    const lista: { clave: keyof Filtros; texto: string }[] = [];

    if (form.q) lista.push({ clave: 'q', texto: `Búsqueda: ${form.q}` });
    if (form.estado) {
        lista.push({
            clave: 'estado',
            texto: `Estado: ${ETIQUETAS[form.estado as Estado] ?? form.estado}`,
        });
    }
    if (form.desde)
        lista.push({ clave: 'desde', texto: `Desde ${form.desde}` });
    if (form.hasta)
        lista.push({ clave: 'hasta', texto: `Hasta ${form.hasta}` });

    return lista;
});

const resumen = computed(() => {
    const meta = props.cotizaciones?.meta;

    if (!meta || !meta.total || meta.from === null || meta.to === null) {
        return 'Mostrando 0 cotizaciones';
    }

    return `Mostrando ${meta.from}–${meta.to} de ${cantidad.format(meta.total)} cotizaciones`;
});

/* ── Detalle (panel) ── */

function abrirFicha(id: number): void {
    aplicar({ ficha: id });
}

const fichaAbierta = computed({
    get: () => props.ficha !== null,
    set: (abierto: boolean) => {
        if (!abierto) {
            aplicar();
        }
    },
});

/* ── Cambiar estado ── */

const estadoAbierto = ref(false);
const formEstado = useForm({ estado: 'ATENDIDA', observacion: '' });

const ESTADOS_MANUALES = [
    { id: 'PENDIENTE', etiqueta: 'Pendiente' },
    { id: 'ATENDIDA', etiqueta: 'Atendida' },
    { id: 'RECHAZADA', etiqueta: 'Rechazada' },
] as const;

const errorGeneralEstado = computed(
    () => (formEstado.errors as Record<string, string | undefined>).estado,
);

function abrirEstado(): void {
    formEstado.reset();
    formEstado.clearErrors();
    formEstado.estado =
        props.ficha?.estado === 'ATENDIDA' ? 'PENDIENTE' : 'ATENDIDA';
    estadoAbierto.value = true;
}

const estadoDialogo = computed({
    get: () => estadoAbierto.value,
    set: (abierto: boolean) => {
        if (!abierto && !formEstado.processing) {
            estadoAbierto.value = false;
        }
    },
});

function guardarEstado(): void {
    if (!props.ficha) {
        return;
    }

    formEstado.patch(`/cotizaciones/${props.ficha.id}/estado`, {
        preserveScroll: true,
        onSuccess: () => {
            estadoAbierto.value = false;
        },
    });
}

/* ── Renovar y convertir ── */

const renovando = ref(false);

function renovar(): void {
    if (!props.ficha || renovando.value) {
        return;
    }

    renovando.value = true;
    router.post(
        `/cotizaciones/${props.ficha.id}/renovar`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                renovando.value = false;
            },
        },
    );
}

const motivoConversion = computed(() => {
    const ficha = props.ficha;

    if (!ficha) {
        return null;
    }

    if (!ficha.puede_vender) {
        return 'Necesitas el permiso para registrar ventas.';
    }

    return ficha.motivo_bloqueo;
});

/* ── Más visitados ── */

const rango = reactive({
    desde: props.visitados?.desde ?? '',
    hasta: props.visitados?.hasta ?? '',
});

watch(
    () => props.visitados,
    (visitados) => {
        if (visitados) {
            rango.desde = visitados.desde;
            rango.hasta = visitados.hasta;
        }
    },
);

function consultarVisitados(desde = rango.desde, hasta = rango.hasta): void {
    router.get(
        '/cotizaciones/mas-visitados',
        { desde, hasta },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

function ultimosDias(dias: number): void {
    const hasta = new Date();
    const desde = new Date();
    desde.setDate(hasta.getDate() - (dias - 1));
    const iso = (fecha: Date): string =>
        `${fecha.getFullYear()}-${String(fecha.getMonth() + 1).padStart(2, '0')}-${String(fecha.getDate()).padStart(2, '0')}`;

    rango.desde = iso(desde);
    rango.hasta = iso(hasta);
    consultarVisitados();
}
</script>

<template>
    <div>
        <Head title="Cotizaciones" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Ventas</p>
                    <h1>Cotizaciones</h1>
                    <p>
                        Da seguimiento a las cotizaciones de la tienda en línea,
                        conviértelas en ventas y revisa qué productos se miran
                        más.
                    </p>
                </motion.div>
            </motion.div>

            <nav class="cc-cot__tabs" aria-label="Secciones">
                <Link
                    href="/cotizaciones"
                    class="cc-cot__tab"
                    :class="{ 'is-active': tab === 'cotizaciones' }"
                    :aria-current="tab === 'cotizaciones' ? 'page' : undefined"
                    data-test="tab-cotizaciones"
                    >Cotizaciones</Link
                >
                <Link
                    href="/cotizaciones/mas-visitados"
                    class="cc-cot__tab"
                    :class="{ 'is-active': tab === 'visitados' }"
                    :aria-current="tab === 'visitados' ? 'page' : undefined"
                    data-test="tab-visitados"
                    >Más visitados</Link
                >
            </nav>

            <!-- ── Cotizaciones ── -->
            <template v-if="tab === 'cotizaciones' && cotizaciones">
                <motion.div
                    layout
                    class="cc-admin__kpis cc-admin__kpis--six"
                    aria-label="Filtrar por estado"
                    :transition="adminSpring"
                >
                    <button
                        v-for="kpi in kpis"
                        :key="kpi.label"
                        type="button"
                        class="cc-admin__kpi"
                        :class="{
                            'is-active':
                                form.estado === kpi.estado && kpi.estado !== '',
                        }"
                        :aria-pressed="
                            form.estado === kpi.estado && kpi.estado !== ''
                        "
                        :data-test="`kpi-${kpi.label.toLowerCase()}`"
                        @click="filtrarEstado(kpi.estado)"
                    >
                        <b>{{ kpi.valor }}</b>
                        <span>{{ kpi.label }}</span>
                    </button>
                </motion.div>

                <form class="cc-log__filters" @submit.prevent="aplicar()">
                    <div class="cc-log__row">
                        <label class="cc-log__control cc-log__search">
                            <Search :size="16" />
                            <input
                                v-model="form.q"
                                type="search"
                                placeholder="Buscar por código o cliente..."
                                maxlength="100"
                                data-test="buscar"
                                @input="buscarLuego"
                            />
                            <button
                                v-if="form.q"
                                type="button"
                                class="cc-log__clear"
                                aria-label="Limpiar búsqueda"
                                @click="quitarFiltro('q')"
                            >
                                <X :size="14" />
                            </button>
                        </label>
                        <div class="cc-log__dates-par">
                            <label class="cc-modal-field">
                                <span>Desde</span>
                                <span class="cc-log__control">
                                    <Calendar :size="16" />
                                    <input
                                        v-model="form.desde"
                                        type="date"
                                        @change="aplicar()"
                                    />
                                </span>
                            </label>
                            <label class="cc-modal-field">
                                <span>Hasta</span>
                                <span class="cc-log__control">
                                    <Calendar :size="16" />
                                    <input
                                        v-model="form.hasta"
                                        type="date"
                                        @change="aplicar()"
                                    />
                                </span>
                            </label>
                        </div>
                        <div class="cc-log__row-actions">
                            <button type="submit" class="cc-button">
                                <Search :size="16" />
                                Buscar
                            </button>
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="limpiar"
                            >
                                <RotateCcw :size="16" />
                                Limpiar
                            </button>
                        </div>
                    </div>
                    <div v-if="chips.length" class="cc-admin__chips">
                        <button
                            v-for="chip in chips"
                            :key="chip.clave"
                            type="button"
                            class="cc-admin__chip"
                            :aria-label="`Quitar filtro ${chip.texto}`"
                            @click="quitarFiltro(chip.clave)"
                        >
                            {{ chip.texto }}
                            <X :size="12" />
                        </button>
                    </div>
                </form>

                <motion.div
                    layout
                    class="cc-log__panel"
                    :transition="adminSpring"
                >
                    <div class="cc-log__table-wrap">
                        <table
                            class="cc-log__table"
                            data-test="tabla-cotizaciones"
                        >
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Cliente</th>
                                    <th>Emisión</th>
                                    <th>Vence</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="cotizaciones.data.length === 0">
                                    <td class="cc-log__empty" colspan="7">
                                        <strong>Sin cotizaciones</strong>
                                        No hay cotizaciones que coincidan con
                                        los filtros.
                                    </td>
                                </tr>
                                <tr
                                    v-for="fila in cotizaciones.data"
                                    :key="fila.id"
                                    :class="{
                                        'is-off': fila.estado === 'VENCIDA',
                                    }"
                                >
                                    <td>
                                        <strong>{{ fila.codigo }}</strong>
                                    </td>
                                    <td>{{ fila.cliente }}</td>
                                    <td>{{ fila.fecha }}</td>
                                    <td>{{ fila.vencimiento }}</td>
                                    <td>{{ moneda.format(fila.total) }}</td>
                                    <td>
                                        <span class="cc-log__when">
                                            <span
                                                class="cc-log__badge"
                                                :class="badge(fila.estado)"
                                            >
                                                {{ ETIQUETAS[fila.estado] }}
                                            </span>
                                            <Link
                                                v-if="fila.venta"
                                                :href="`/ventas?ficha=${fila.venta.id}`"
                                                class="cc-cot__venta"
                                                ><small>{{
                                                    fila.venta.numero
                                                }}</small></Link
                                            >
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-row-actions">
                                            <button
                                                type="button"
                                                class="cc-log__eye"
                                                title="Ver detalle"
                                                :aria-label="`Ver el detalle de ${fila.codigo}`"
                                                data-test="ver-detalle"
                                                @click="abrirFicha(fila.id)"
                                            >
                                                <Eye :size="15" />
                                            </button>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="cc-log-board">
                        <p
                            v-if="cotizaciones.data.length === 0"
                            class="cc-log__empty"
                        >
                            <strong>Sin cotizaciones</strong>
                            No hay cotizaciones que coincidan con los filtros.
                        </p>
                        <article
                            v-for="fila in cotizaciones.data"
                            :key="`pin-${fila.id}`"
                            class="cc-log-pin"
                            :class="{ 'is-off': fila.estado === 'VENCIDA' }"
                        >
                            <span class="cc-log-pin__cuerpo">
                                <strong>{{ fila.codigo }}</strong>
                                <p>{{ fila.cliente }}</p>
                                <small>
                                    {{ fila.fecha }} · vence
                                    {{ fila.vencimiento }} ·
                                    {{ moneda.format(fila.total) }}
                                </small>
                            </span>
                            <span class="cc-row-actions">
                                <span
                                    class="cc-log__badge"
                                    :class="badge(fila.estado)"
                                >
                                    {{ ETIQUETAS[fila.estado] }}
                                </span>
                                <button
                                    type="button"
                                    class="cc-log__eye"
                                    :aria-label="`Ver el detalle de ${fila.codigo}`"
                                    @click="abrirFicha(fila.id)"
                                >
                                    <Eye :size="15" />
                                </button>
                            </span>
                        </article>
                    </div>

                    <footer class="cc-log__foot">
                        <p>{{ resumen }}</p>
                        <div class="cc-log__pager">
                            <button
                                type="button"
                                aria-label="Página anterior"
                                :disabled="cotizaciones.meta.current_page <= 1"
                                @click="ir(cotizaciones.meta.current_page - 1)"
                            >
                                <ChevronLeft :size="16" />
                            </button>
                            <span>
                                Página {{ cotizaciones.meta.current_page }} de
                                {{ cotizaciones.meta.last_page }}
                            </span>
                            <button
                                type="button"
                                aria-label="Página siguiente"
                                :disabled="
                                    cotizaciones.meta.current_page >=
                                    cotizaciones.meta.last_page
                                "
                                @click="ir(cotizaciones.meta.current_page + 1)"
                            >
                                <ChevronRight :size="16" />
                            </button>
                        </div>
                    </footer>
                </motion.div>
            </template>

            <!-- ── Más visitados ── -->
            <template v-else-if="tab === 'visitados' && visitados">
                <form
                    class="cc-log__filters"
                    @submit.prevent="consultarVisitados()"
                >
                    <div class="cc-log__row">
                        <div class="cc-log__dates-par">
                            <label class="cc-modal-field">
                                <span>Desde</span>
                                <span class="cc-log__control">
                                    <Calendar :size="16" />
                                    <input
                                        v-model="rango.desde"
                                        type="date"
                                        required
                                    />
                                </span>
                            </label>
                            <label class="cc-modal-field">
                                <span>Hasta</span>
                                <span class="cc-log__control">
                                    <Calendar :size="16" />
                                    <input
                                        v-model="rango.hasta"
                                        type="date"
                                        required
                                    />
                                </span>
                            </label>
                        </div>
                        <div class="cc-log__row-actions">
                            <button type="submit" class="cc-button">
                                <Search :size="16" />
                                Consultar
                            </button>
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="ultimosDias(7)"
                            >
                                Últimos 7 días
                            </button>
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="ultimosDias(30)"
                            >
                                Últimos 30 días
                            </button>
                        </div>
                    </div>
                    <p
                        v-if="visitados.error"
                        class="cc-modal-error"
                        role="alert"
                    >
                        {{ visitados.error }}
                    </p>
                </form>

                <motion.div
                    layout
                    class="cc-log__panel cc-cot__visitas"
                    :transition="adminSpring"
                >
                    <p class="cc-cot__periodo">
                        Visitas al catálogo público del
                        {{ visitados.resultado.encabezado.periodo }}.
                    </p>

                    <div
                        v-if="visitados.resultado.vacio"
                        class="cc-cot__vacio"
                        data-test="sin-visitas"
                    >
                        <strong
                            >No hay datos de visitas para el período
                            seleccionado</strong
                        >
                        <span>Prueba con otro rango de fechas.</span>
                    </div>
                    <template v-else>
                        <GraficoReporte
                            :grafico="visitados.resultado.grafico"
                            :moneda="false"
                        />
                        <TablaReporte :resultado="visitados.resultado" />
                    </template>
                </motion.div>
            </template>
        </section>

        <!-- ── Detalle de la cotización ── -->
        <Dialog v-model:open="fichaAbierta">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog"
                style="max-width: 720px"
            >
                <template v-if="ficha">
                    <DialogHeader>
                        <DialogTitle>
                            Cotización {{ ficha.codigo }}
                            <span
                                class="cc-log__badge cc-cot__estado"
                                :class="badge(ficha.estado)"
                                data-test="ficha-estado"
                            >
                                {{ ETIQUETAS[ficha.estado] }}
                            </span>
                        </DialogTitle>
                        <DialogDescription>
                            Emitida el {{ ficha.fecha }} · vence el
                            {{ ficha.vencimiento }} ·
                            {{
                                ficha.origen === 'TIENDA_WEB'
                                    ? 'tienda en línea'
                                    : 'mostrador'
                            }}
                        </DialogDescription>
                    </DialogHeader>

                    <div class="cc-cot__ficha">
                        <section class="cc-cot__contacto">
                            <h3>Contacto</h3>
                            <p>
                                <strong>{{ ficha.cliente.nombre }}</strong>
                                <small v-if="ficha.cliente.documento">{{
                                    ficha.cliente.documento
                                }}</small>
                            </p>
                            <p class="cc-cot__enlaces">
                                <a
                                    v-if="ficha.cliente.telefono"
                                    :href="`tel:${ficha.cliente.telefono}`"
                                    data-test="enlace-telefono"
                                >
                                    <Phone :size="14" />
                                    {{ ficha.cliente.telefono }}
                                </a>
                                <a
                                    v-if="ficha.cliente.whatsapp_url"
                                    :href="ficha.cliente.whatsapp_url"
                                    target="_blank"
                                    rel="noopener"
                                    data-test="enlace-whatsapp"
                                >
                                    <MessageCircle :size="14" /> WhatsApp
                                </a>
                                <a
                                    v-if="ficha.cliente.email"
                                    :href="`mailto:${ficha.cliente.email}`"
                                >
                                    <Mail :size="14" />
                                    {{ ficha.cliente.email }}
                                </a>
                            </p>
                        </section>

                        <div class="cc-log__table-wrap">
                            <table class="cc-log__table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cant.</th>
                                        <th>Cotizado</th>
                                        <th>Actual</th>
                                        <th>Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(item, i) in ficha.items"
                                        :key="i"
                                    >
                                        <td>
                                            {{ item.descripcion }}
                                            <small
                                                v-if="
                                                    item.cantidad > item.stock
                                                "
                                                class="cc-cot__sinstock"
                                            >
                                                Stock actual: {{ item.stock }}
                                            </small>
                                        </td>
                                        <td>{{ item.cantidad }}</td>
                                        <td>
                                            {{
                                                moneda.format(
                                                    item.precio_cotizado,
                                                )
                                            }}
                                        </td>
                                        <td
                                            :class="{
                                                'cc-cot__cambio':
                                                    item.precio_actual !==
                                                    item.precio_cotizado,
                                            }"
                                        >
                                            {{
                                                moneda.format(
                                                    item.precio_actual,
                                                )
                                            }}
                                        </td>
                                        <td>
                                            {{ moneda.format(item.subtotal) }}
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="4">
                                            <strong>Total</strong>
                                        </td>
                                        <td>
                                            <strong data-test="ficha-total">{{
                                                moneda.format(ficha.total)
                                            }}</strong>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>

                        <p v-if="ficha.observacion" class="cc-cot__obs">
                            <strong>Observación:</strong>
                            {{ ficha.observacion }}
                        </p>
                        <p v-if="ficha.fecha_estado" class="cc-cot__obs">
                            Último cambio: {{ ficha.fecha_estado }}
                            <template v-if="ficha.actualizado_por">
                                por {{ ficha.actualizado_por }}
                            </template>
                        </p>
                        <p v-if="ficha.venta" class="cc-cot__obs">
                            Convertida en la venta
                            <Link :href="`/ventas?ficha=${ficha.venta.id}`">{{
                                ficha.venta.numero
                            }}</Link
                            >.
                        </p>
                        <p
                            v-if="motivoConversion"
                            class="cc-cot__motivo"
                            role="status"
                            data-test="motivo-bloqueo"
                        >
                            {{ motivoConversion }}
                        </p>
                    </div>

                    <div class="cc-modal-actions cc-cot__acciones">
                        <button
                            v-if="ficha.puede_cambiar_estado"
                            type="button"
                            class="cc-button cc-button--ghost"
                            data-test="cambiar-estado"
                            @click="abrirEstado"
                        >
                            <RefreshCw :size="16" />
                            Cambiar estado
                        </button>
                        <button
                            v-if="ficha.puede_renovar"
                            type="button"
                            class="cc-button cc-button--ghost"
                            :disabled="renovando"
                            data-test="renovar"
                            @click="renovar"
                        >
                            <RotateCcw :size="16" />
                            Renovar con precios actuales
                        </button>
                        <span :title="motivoConversion ?? undefined">
                            <Link
                                v-if="
                                    ficha.puede_convertir && ficha.puede_vender
                                "
                                :href="`/ventas?cotizacion=${ficha.id}`"
                                class="cc-button"
                                data-test="convertir"
                            >
                                <ShoppingCart :size="16" />
                                Convertir en venta
                            </Link>
                            <button
                                v-else
                                type="button"
                                class="cc-button"
                                disabled
                                data-test="convertir"
                            >
                                <ShoppingCart :size="16" />
                                Convertir en venta
                            </button>
                        </span>
                    </div>
                </template>
            </DialogContent>
        </Dialog>

        <!-- ── Cambiar estado ── -->
        <Dialog v-model:open="estadoDialogo">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog"
                style="max-width: 480px"
            >
                <DialogHeader>
                    <DialogTitle>Cambiar estado</DialogTitle>
                    <DialogDescription>
                        {{ ficha?.codigo }}: elige cómo va el seguimiento y deja
                        una observación. Rechazar exige el motivo.
                    </DialogDescription>
                </DialogHeader>

                <form
                    class="cc-modal-form"
                    novalidate
                    @submit.prevent="guardarEstado"
                >
                    <fieldset class="cc-cot__estados">
                        <legend class="cc-modal-field__titulo">Estado</legend>
                        <label
                            v-for="opcion in ESTADOS_MANUALES"
                            :key="opcion.id"
                        >
                            <input
                                v-model="formEstado.estado"
                                type="radio"
                                name="estado-cotizacion"
                                :value="opcion.id"
                                :disabled="opcion.id === ficha?.estado"
                            />
                            {{ opcion.etiqueta }}
                        </label>
                    </fieldset>

                    <label class="cc-modal-field">
                        <span>
                            Observación
                            <b
                                v-if="formEstado.estado === 'RECHAZADA'"
                                class="cc-req"
                                >*</b
                            >
                        </span>
                        <span class="cc-log__control cc-log__control--area">
                            <textarea
                                v-model="formEstado.observacion"
                                rows="3"
                                maxlength="500"
                                placeholder="Ej. El cliente pidió otro modelo"
                                data-test="observacion"
                            />
                        </span>
                        <p
                            v-if="formEstado.errors.observacion"
                            class="cc-modal-error"
                            role="alert"
                        >
                            {{ formEstado.errors.observacion }}
                        </p>
                    </label>

                    <p
                        v-if="errorGeneralEstado"
                        class="cc-modal-error"
                        role="alert"
                    >
                        {{ errorGeneralEstado }}
                    </p>

                    <div class="cc-modal-actions">
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            :disabled="formEstado.processing"
                            @click="estadoDialogo = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="cc-button"
                            :disabled="formEstado.processing"
                            data-test="guardar-estado"
                        >
                            Guardar
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.cc-cot__tabs {
    display: flex;
    gap: 6px;
    border-bottom: 1px solid var(--cc-border);
}

.cc-cot__tab {
    margin-bottom: -1px;
    padding: 10px 18px;
    border: 1px solid transparent;
    border-radius: 10px 10px 0 0;
    color: var(--cc-muted);
    font-weight: 600;
    text-decoration: none;
}

.cc-cot__tab:hover {
    color: var(--cc-text);
}

.cc-cot__tab.is-active {
    border-color: var(--cc-border);
    border-bottom-color: var(--cc-page, #fff);
    color: var(--cc-text);
}

.cc-cot__venta {
    color: var(--cc-accent);
    text-decoration: underline;
}

.cc-cot__estado {
    margin-left: 8px;
    vertical-align: middle;
}

.cc-cot__ficha {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.cc-cot__contacto h3 {
    margin: 0 0 4px;
    color: var(--cc-muted);
    font-size: 12px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.cc-cot__contacto p {
    display: flex;
    flex-wrap: wrap;
    gap: 4px 12px;
    align-items: baseline;
    margin: 0;
}

.cc-cot__enlaces a {
    display: inline-flex;
    gap: 4px;
    align-items: center;
    color: var(--cc-accent);
    text-decoration: underline;
}

.cc-cot__cambio {
    color: var(--cc-error);
    font-weight: 700;
}

.cc-cot__sinstock {
    display: block;
    color: var(--cc-error);
}

.cc-cot__obs {
    margin: 0;
    color: var(--cc-muted);
    font-size: 13px;
}

.cc-cot__obs a {
    color: var(--cc-accent);
    text-decoration: underline;
}

.cc-cot__motivo {
    margin: 0;
    padding: 8px 12px;
    border: 1px solid #f0c36d;
    border-radius: 8px;
    background: #fff7e0;
    color: #7a4a05;
    font-size: 13px;
}

.cc-cot__acciones {
    flex-wrap: wrap;
}

.cc-cot__estados {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    margin: 0;
    padding: 0;
    border: 0;
}

.cc-cot__estados label {
    display: inline-flex;
    gap: 6px;
    align-items: center;
}

.cc-cot__visitas {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 20px;
}

.cc-cot__periodo {
    margin: 0;
    color: var(--cc-muted);
    font-size: 13px;
}

.cc-cot__vacio {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 36px 16px;
    text-align: center;
    color: var(--cc-muted);
}

.cc-cot__vacio strong {
    color: var(--cc-text);
    font-size: 16px;
}

.cc-modal-form {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.cc-modal-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.cc-modal-error {
    margin: 0;
    color: var(--cc-error);
    font-size: 12px;
}

.cc-modal-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 8px;
}

.cc-req {
    color: var(--cc-error);
}
</style>
