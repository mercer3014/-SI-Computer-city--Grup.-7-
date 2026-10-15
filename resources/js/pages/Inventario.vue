<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import {
    AlertTriangle,
    ChevronLeft,
    ChevronRight,
    History,
    PackageSearch,
    Pencil,
    RotateCcw,
    ScanLine,
    Search,
    SlidersHorizontal,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { adminSpring } from '@/lib/adminMotion';

type Variante = {
    id: number;
    sku: string;
    codigo_barras: string | null;
    producto_id: number;
    producto: string | null;
    atributos: string;
    stock_actual: number;
    stock_minimo: number;
    stock_bajo: boolean;
};

type Movimiento = {
    id: number;
    fecha: string;
    hora: string;
    tipo: string;
    sku: string | null;
    producto: string | null;
    referencia: string | null;
    motivo: string | null;
    cantidad: number;
    direccion: 'ENTRADA' | 'SALIDA';
    usuario: string;
};

type LineaKardex = {
    id: number;
    fecha: string;
    hora: string;
    tipo: string;
    referencia: string | null;
    motivo: string | null;
    entrada: number | null;
    salida: number | null;
    saldo: number;
    usuario: string;
};

type Filtros = {
    q: string;
    alerta: string;
    tab: string;
    variante: string;
    desde: string;
    hasta: string;
    serie: string;
};

const props = defineProps<{
    variantes: {
        data: Variante[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    movimientos: Movimiento[];
    kardex: {
        variante: {
            id: number;
            sku: string;
            producto: string | null;
            stock_actual: number;
        } | null;
        lineas: LineaKardex[];
    };
    serie: {
        busqueda: string;
        encontrada: boolean;
        compra: {
            numero: string;
            fecha: string | null;
            sku: string;
            producto: string;
            serie: string;
            origen: string | null;
        } | null;
        venta: {
            numero: string;
            fecha: string | null;
            sku: string;
            producto: string;
            serie: string;
            cliente: string | null;
            estado: string | null;
        } | null;
    };
    kpis: { alertas: number; sin_stock: number; total: number };
    filtros: Filtros;
    opciones: { variantes: { id: number; sku: string }[] };
    puedeAjustar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Productos', href: '/productos' },
            { title: 'Inventario', href: '/inventario' },
        ],
    },
});

const page = usePage();
const esAdmin = computed(
    () =>
        props.puedeAjustar &&
        ((
            page.props.auth as { user?: { permisos?: string[] } }
        )?.user?.permisos?.includes('inventario.ajustar') ??
            false),
);

const cantidad = new Intl.NumberFormat('es-BO');
const form = reactive<Filtros>({ ...props.filtros });
let busquedaTimer = 0;

watch(
    () => props.filtros,
    (filtros) => Object.assign(form, filtros),
);

onBeforeUnmount(() => window.clearTimeout(busquedaTimer));

function consulta(
    origen: Filtros,
    pagina?: number,
): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    (Object.keys(origen) as (keyof Filtros)[]).forEach((clave) => {
        if (origen[clave] !== '') {
            query[clave] = origen[clave];
        }
    });

    if (pagina && pagina > 1) {
        query.page = pagina;
    }

    return query;
}

function aplicar(extra: Partial<Filtros> = {}, pagina?: number): void {
    router.get('/inventario', consulta({ ...form, ...extra }, pagina), {
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
    if (pagina < 1 || pagina > props.variantes.meta.last_page) return;
    aplicar({}, pagina);
}

const pestana = computed(() => props.filtros.tab || 'stock');

function irTab(tab: string): void {
    aplicar({ tab });
}

const kpis = computed(() => [
    { label: 'Variantes activas', valor: cantidad.format(props.kpis.total) },
    { label: 'Stock bajo', valor: cantidad.format(props.kpis.alertas) },
    { label: 'Sin stock', valor: cantidad.format(props.kpis.sin_stock) },
    {
        label: 'En esta página',
        valor: cantidad.format(props.variantes.data.length),
    },
]);

const opcionesAlerta = [
    { value: '', label: 'Todas las existencias' },
    { value: 'bajo', label: 'Stock bajo' },
    { value: 'cero', label: 'Sin stock' },
];

const chips = computed(() => {
    const lista: { clave: 'q' | 'alerta'; texto: string }[] = [];

    if (form.q) {
        lista.push({ clave: 'q', texto: `Buscar: ${form.q}` });
    }

    if (form.alerta) {
        const alerta = opcionesAlerta.find((opcion) => opcion.value === form.alerta);
        lista.push({ clave: 'alerta', texto: alerta?.label ?? form.alerta });
    }

    return lista;
});

function quitarFiltro(clave: 'q' | 'alerta'): void {
    form[clave] = '';
    aplicar();
}

const opcionesKardex = computed(() => [
    { value: '', label: 'Elegí un SKU' },
    ...props.opciones.variantes.map((v) => ({
        value: String(v.id),
        label: v.sku,
    })),
]);

const resumen = computed(() => {
    const { from, to, total } = props.variantes.meta;
    if (!total || from === null || to === null) return 'Mostrando 0 variantes';
    return `Mostrando ${from}–${to} de ${cantidad.format(total)} variantes`;
});

function badgeStock(v: Variante): string {
    if (v.stock_actual === 0) return 'cc-log__badge--error';
    if (v.stock_bajo) return 'cc-log__badge--alerta';
    return 'cc-log__badge--crear';
}

function tonoTipo(tipo: string, direccion?: string): string {
    if (tipo === 'SALIDA' || direccion === 'SALIDA')
        return 'cc-log__badge--error';
    if (tipo === 'AJUSTE') return 'cc-log__badge--ajustar';
    return 'cc-log__badge--crear';
}

// ── Ajuste de stock ───────────────────────────────────────────────────────

const objetivoAjuste = ref<Variante | null>(null);
const ajusteAbierto = computed({
    get: () => objetivoAjuste.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) objetivoAjuste.value = null;
    },
});

const formAjuste = useForm({
    sku: '',
    tipo: 'ENTRADA' as 'ENTRADA' | 'SALIDA',
    cantidad: 1 as number | string,
    motivo: '',
});

function ajustarStock(variante: Variante): void {
    formAjuste.reset();
    formAjuste.clearErrors();
    formAjuste.sku = variante.sku;
    formAjuste.tipo = 'ENTRADA';
    formAjuste.cantidad = 1;
    objetivoAjuste.value = variante;
}

const stockResultante = computed(() => {
    if (!objetivoAjuste.value) return null;
    const qty = Number(formAjuste.cantidad);
    if (!Number.isFinite(qty) || qty < 1) return null;
    const delta = formAjuste.tipo === 'SALIDA' ? -qty : qty;
    return objetivoAjuste.value.stock_actual + delta;
});

function confirmarAjuste(): void {
    formAjuste.post('/inventario/ajustar', {
        preserveScroll: true,
        onSuccess: () => {
            objetivoAjuste.value = null;
        },
    });
}

// ── Stock mínimo ──────────────────────────────────────────────────────────

const objetivoMinimo = ref<Variante | null>(null);
const minimoAbierto = computed({
    get: () => objetivoMinimo.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) objetivoMinimo.value = null;
    },
});

const formMinimo = useForm({ stock_minimo: 0 as number | string });

function abrirMinimo(variante: Variante): void {
    formMinimo.clearErrors();
    formMinimo.stock_minimo = variante.stock_minimo;
    objetivoMinimo.value = variante;
}

function guardarMinimo(): void {
    if (!objetivoMinimo.value) return;
    formMinimo.patch(
        `/inventario/variantes/${objetivoMinimo.value.id}/minimo`,
        {
            preserveScroll: true,
            onSuccess: () => {
                objetivoMinimo.value = null;
            },
        },
    );
}

function abrirKardex(variante: Variante): void {
    aplicar({ tab: 'kardex', variante: String(variante.id) });
}
</script>

<template>
    <div>
        <Head title="Inventario" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Kardex</p>
                    <h1>Inventario</h1>
                    <p>
                        Consultá existencias, alertas de reposición y el
                        historial de cada movimiento.
                        <template v-if="esAdmin">
                            El stock inicial se carga con un ajuste
                            manual.</template
                        >
                    </p>
                </motion.div>
            </motion.div>

            <motion.div
                layout
                class="cc-admin__kpis"
                aria-label="Resumen de inventario"
                :transition="adminSpring"
            >
                <motion.div
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    layout
                    class="cc-admin__kpi"
                    :transition="adminSpring"
                >
                    <b>{{ kpi.valor }}</b>
                    <span>{{ kpi.label }}</span>
                </motion.div>
            </motion.div>

            <div
                class="cc-inv__tabs"
                role="tablist"
                aria-label="Secciones de inventario"
            >
                <button
                    type="button"
                    role="tab"
                    class="cc-inv__tab"
                    :class="{ 'is-activa': pestana === 'stock' }"
                    @click="irTab('stock')"
                >
                    Existencias
                </button>
                <button
                    type="button"
                    role="tab"
                    class="cc-inv__tab"
                    :class="{ 'is-activa': pestana === 'movimientos' }"
                    @click="irTab('movimientos')"
                >
                    Movimientos
                    <span>{{ cantidad.format(movimientos.length) }}</span>
                </button>
                <button
                    type="button"
                    role="tab"
                    class="cc-inv__tab"
                    :class="{ 'is-activa': pestana === 'kardex' }"
                    @click="irTab('kardex')"
                >
                    Kardex
                </button>
                <button
                    type="button"
                    role="tab"
                    class="cc-inv__tab"
                    :class="{ 'is-activa': pestana === 'serie' }"
                    @click="irTab('serie')"
                >
                    N° de serie
                </button>
            </div>

            <!-- ── Existencias ── -->
            <template v-if="pestana === 'stock'">
                <motion.div layout :transition="adminSpring">
                    <form class="cc-log__filters" @submit.prevent="aplicar()">
                        <div class="cc-log__row">
                            <label class="cc-log__control cc-log__search">
                                <Search :size="16" />
                                <input
                                    v-model="form.q"
                                    type="search"
                                    placeholder="Buscar por SKU, código o nombre..."
                                    maxlength="150"
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
                            <CcRevealSelect
                                v-model="form.alerta"
                                etiqueta="Todas las existencias"
                                :opciones="opcionesAlerta"
                                @change="aplicar()"
                            />
                            <div class="cc-log__row-actions">
                                <button type="submit" class="cc-button">
                                    <Search :size="16" />
                                    Buscar
                                </button>
                                <button
                                    type="button"
                                    class="cc-button cc-button--ghost"
                                    @click="
                                        form.q = '';
                                        form.alerta = '';
                                        aplicar({ q: '', alerta: '' });
                                    "
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
                </motion.div>

                <motion.div
                    layout
                    class="cc-log__panel"
                    :transition="adminSpring"
                >
                    <div class="cc-log__table-wrap">
                        <table class="cc-log__table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Producto</th>
                                    <th>Stock actual</th>
                                    <th>Stock mínimo</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="variantes.data.length === 0">
                                    <td class="cc-log__empty" colspan="5">
                                        <strong>Sin variantes</strong>
                                        No hay existencias que coincidan con la
                                        búsqueda.
                                    </td>
                                </tr>
                                <tr
                                    v-for="variante in variantes.data"
                                    :key="variante.id"
                                    :class="{
                                        'is-off': variante.stock_actual === 0,
                                    }"
                                >
                                    <td>
                                        <span class="cc-log__when">
                                            <code class="cc-inv__sku">{{
                                                variante.sku
                                            }}</code>
                                            <small>{{
                                                variante.codigo_barras ??
                                                'Sin código'
                                            }}</small>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-log__when">
                                            {{ variante.producto ?? '—' }}
                                            <small>{{
                                                variante.atributos
                                            }}</small>
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="cc-log__badge"
                                            :class="badgeStock(variante)"
                                        >
                                            <AlertTriangle
                                                v-if="variante.stock_bajo"
                                                :size="12"
                                            />
                                            {{
                                                cantidad.format(
                                                    variante.stock_actual,
                                                )
                                            }}
                                            uds.
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-inv__minimo">
                                            {{
                                                cantidad.format(
                                                    variante.stock_minimo,
                                                )
                                            }}
                                            <button
                                                v-if="esAdmin"
                                                type="button"
                                                class="cc-log__eye"
                                                title="Cambiar stock mínimo"
                                                @click="abrirMinimo(variante)"
                                            >
                                                <Pencil :size="14" />
                                            </button>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-row-actions">
                                            <button
                                                type="button"
                                                class="cc-log__eye"
                                                title="Ver kardex"
                                                @click="abrirKardex(variante)"
                                            >
                                                <History :size="15" />
                                            </button>
                                            <button
                                                v-if="esAdmin"
                                                type="button"
                                                class="cc-log__eye"
                                                title="Ajustar stock"
                                                :aria-label="`Ajustar stock de ${variante.sku}`"
                                                @click="ajustarStock(variante)"
                                            >
                                                <SlidersHorizontal :size="15" />
                                            </button>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="cc-log-board">
                        <p v-if="variantes.data.length === 0" class="cc-log__empty">
                            <strong>Sin variantes</strong>
                            No hay existencias que coincidan con la búsqueda.
                        </p>
                        <article
                            v-for="variante in variantes.data"
                            :key="`pin-${variante.id}`"
                            class="cc-log-pin"
                            :class="{ 'is-off': variante.stock_actual === 0 }"
                        >
                            <button
                                type="button"
                                class="cc-log-pin__abrir"
                                @click="abrirKardex(variante)"
                            >
                                <span class="cc-log-pin__cuerpo">
                                    <strong>{{ variante.producto ?? variante.sku }}</strong>
                                    <p>
                                        <code class="cc-inv__sku">{{ variante.sku }}</code>
                                        · {{ variante.atributos }}
                                    </p>
                                    <small>
                                        Mínimo
                                        {{ cantidad.format(variante.stock_minimo) }}
                                        ·
                                        {{
                                            variante.codigo_barras ?? 'Sin código'
                                        }}
                                    </small>
                                </span>
                            </button>
                            <span class="cc-row-actions">
                                <span
                                    class="cc-log__badge"
                                    :class="badgeStock(variante)"
                                >
                                    {{ cantidad.format(variante.stock_actual) }}
                                </span>
                                <button
                                    v-if="esAdmin"
                                    type="button"
                                    class="cc-log__eye"
                                    :aria-label="`Ajustar stock de ${variante.sku}`"
                                    @click="ajustarStock(variante)"
                                >
                                    <SlidersHorizontal :size="15" />
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
                                :disabled="variantes.meta.current_page <= 1"
                                @click="ir(variantes.meta.current_page - 1)"
                            >
                                <ChevronLeft :size="16" />
                            </button>
                            <span>
                                Página {{ variantes.meta.current_page }} de
                                {{ variantes.meta.last_page }}
                            </span>
                            <button
                                type="button"
                                aria-label="Página siguiente"
                                :disabled="
                                    variantes.meta.current_page >=
                                    variantes.meta.last_page
                                "
                                @click="ir(variantes.meta.current_page + 1)"
                            >
                                <ChevronRight :size="16" />
                            </button>
                        </div>
                    </footer>
                </motion.div>
            </template>

            <!-- ── Movimientos ── -->
            <motion.div
                v-else-if="pestana === 'movimientos'"
                layout
                class="cc-log__panel"
                :transition="adminSpring"
            >
                <div class="cc-log__table-wrap">
                    <table class="cc-log__table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>SKU</th>
                                <th>Cantidad</th>
                                <th>Referencia</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="movimientos.length === 0">
                                <td class="cc-log__empty" colspan="6">
                                    <strong>Sin movimientos</strong>
                                    Todavía no hay entradas, salidas ni ajustes.
                                    El stock inicial se carga con un ajuste.
                                </td>
                            </tr>
                            <tr v-for="mov in movimientos" :key="mov.id">
                                <td>
                                    <span class="cc-log__when">
                                        {{ mov.fecha }}
                                        <small>{{ mov.hora }}</small>
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="cc-log__badge"
                                        :class="
                                            tonoTipo(mov.tipo, mov.direccion)
                                        "
                                    >
                                        {{
                                            mov.tipo === 'AJUSTE'
                                                ? `AJUSTE · ${mov.direccion}`
                                                : mov.tipo
                                        }}
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        <code class="cc-inv__sku">{{
                                            mov.sku
                                        }}</code>
                                        <small>{{ mov.producto }}</small>
                                    </span>
                                </td>
                                <td>
                                    {{ mov.direccion === 'SALIDA' ? '−' : '+'
                                    }}{{ cantidad.format(mov.cantidad) }}
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        {{ mov.referencia ?? '—' }}
                                        <small>{{ mov.motivo }}</small>
                                    </span>
                                </td>
                                <td>{{ mov.usuario }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="cc-log-board">
                    <p v-if="movimientos.length === 0" class="cc-log__empty">
                        <strong>Sin movimientos</strong>
                        Todavía no hay entradas, salidas ni ajustes.
                    </p>
                    <article
                        v-for="mov in movimientos"
                        :key="`mov-${mov.id}`"
                        class="cc-log-pin"
                    >
                        <span class="cc-log-pin__cuerpo">
                            <strong>
                                {{ mov.sku }}
                                ·
                                {{
                                    mov.direccion === 'SALIDA' ? '−' : '+'
                                }}{{ cantidad.format(mov.cantidad) }}
                            </strong>
                            <p>
                                {{
                                    mov.tipo === 'AJUSTE'
                                        ? `Ajuste ${mov.direccion}`
                                        : mov.tipo
                                }}
                                · {{ mov.producto }}
                            </p>
                            <small>
                                {{ mov.fecha }} {{ mov.hora }} ·
                                {{ mov.usuario }}
                                · {{ mov.referencia ?? mov.motivo }}
                            </small>
                        </span>
                    </article>
                </div>
            </motion.div>

            <!-- ── Kardex ── -->
            <motion.div
                v-else-if="pestana === 'kardex'"
                layout
                class="cc-log__panel"
                :transition="adminSpring"
            >
                <form
                    class="cc-log__filters"
                    @submit.prevent="aplicar({ tab: 'kardex' })"
                >
                    <div class="cc-log__row">
                        <CcRevealSelect
                            v-model="form.variante"
                            etiqueta="Elegí un SKU"
                            :opciones="opcionesKardex"
                        />
                        <div class="cc-log__dates-par">
                            <label class="cc-modal-field">
                                <span>Desde</span>
                                <span class="cc-log__control">
                                    <input v-model="form.desde" type="date" />
                                </span>
                            </label>
                            <label class="cc-modal-field">
                                <span>Hasta</span>
                                <span class="cc-log__control">
                                    <input v-model="form.hasta" type="date" />
                                </span>
                            </label>
                        </div>
                        <div class="cc-log__row-actions">
                            <button type="submit" class="cc-button">
                                Consultar
                            </button>
                        </div>
                    </div>
                </form>

                <p v-if="kardex.variante" class="cc-inv__nota">
                    Kardex de
                    <code class="cc-inv__sku">{{ kardex.variante.sku }}</code> ·
                    {{ kardex.variante.producto }} · Stock actual
                    {{ cantidad.format(kardex.variante.stock_actual) }} uds.
                </p>

                <div class="cc-log__table-wrap">
                    <table class="cc-log__table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th>Entrada</th>
                                <th>Salida</th>
                                <th>Saldo</th>
                                <th>Usuario / motivo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!kardex.variante">
                                <td class="cc-log__empty" colspan="6">
                                    <PackageSearch :size="20" />
                                    <strong>Elegí un SKU</strong>
                                    Mostramos los movimientos en orden
                                    cronológico.
                                </td>
                            </tr>
                            <tr v-else-if="kardex.lineas.length === 0">
                                <td class="cc-log__empty" colspan="6">
                                    <strong
                                        >Sin movimientos en el período</strong
                                    >
                                    No hay kardex para ese SKU y rango de
                                    fechas.
                                </td>
                            </tr>
                            <tr v-for="linea in kardex.lineas" :key="linea.id">
                                <td>
                                    <span class="cc-log__when">
                                        {{ linea.fecha }}
                                        <small>{{ linea.hora }}</small>
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="cc-log__badge"
                                        :class="tonoTipo(linea.tipo)"
                                        >{{ linea.tipo }}</span
                                    >
                                </td>
                                <td>
                                    {{
                                        linea.entrada !== null
                                            ? cantidad.format(linea.entrada)
                                            : '—'
                                    }}
                                </td>
                                <td>
                                    {{
                                        linea.salida !== null
                                            ? cantidad.format(linea.salida)
                                            : '—'
                                    }}
                                </td>
                                <td>
                                    <b>{{ cantidad.format(linea.saldo) }}</b>
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        {{ linea.usuario }}
                                        <small>{{
                                            linea.motivo ?? linea.referencia
                                        }}</small>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="cc-log-board">
                    <p v-if="!kardex.variante" class="cc-log__empty">
                        <strong>Elegí un SKU</strong>
                        Mostramos los movimientos en orden cronológico.
                    </p>
                    <p v-else-if="kardex.lineas.length === 0" class="cc-log__empty">
                        <strong>Sin movimientos en el período</strong>
                        No hay kardex para ese SKU y rango de fechas.
                    </p>
                    <article
                        v-for="linea in kardex.lineas"
                        :key="`kdx-${linea.id}`"
                        class="cc-log-pin"
                    >
                        <span class="cc-log-pin__cuerpo">
                            <strong>
                                Saldo {{ cantidad.format(linea.saldo) }}
                            </strong>
                            <p>
                                {{ linea.tipo }}
                                ·
                                entrada
                                {{
                                    linea.entrada !== null
                                        ? cantidad.format(linea.entrada)
                                        : '—'
                                }}
                                · salida
                                {{
                                    linea.salida !== null
                                        ? cantidad.format(linea.salida)
                                        : '—'
                                }}
                            </p>
                            <small>
                                {{ linea.fecha }} {{ linea.hora }} ·
                                {{ linea.usuario }}
                                · {{ linea.motivo ?? linea.referencia }}
                            </small>
                        </span>
                    </article>
                </div>
            </motion.div>

            <!-- ── Serie ── -->
            <motion.div
                v-else
                layout
                class="cc-log__panel"
                :transition="adminSpring"
            >
                <form
                    class="cc-log__filters"
                    @submit.prevent="aplicar({ tab: 'serie' })"
                >
                    <div class="cc-log__row">
                        <label class="cc-log__control cc-log__search">
                            <ScanLine :size="16" />
                            <input
                                v-model="form.serie"
                                type="search"
                                placeholder="Número de serie o últimos dígitos..."
                                maxlength="100"
                            />
                        </label>
                        <div class="cc-log__row-actions">
                            <button type="submit" class="cc-button">
                                Rastrear
                            </button>
                        </div>
                    </div>
                </form>

                <div
                    v-if="serie.busqueda && !serie.encontrada"
                    class="cc-log__empty cc-inv__serie-vacia"
                >
                    <strong>No hay registros para ese número de serie</strong>
                    Probá con los últimos dígitos o el código completo.
                </div>

                <dl
                    v-else-if="serie.encontrada"
                    class="cc-log-detalle__datos cc-inv__serie"
                >
                    <div v-if="serie.compra">
                        <dt>Ingreso (compra)</dt>
                        <dd>
                            {{ serie.compra.numero }} ·
                            {{ serie.compra.fecha }} ·
                            <code>{{ serie.compra.sku }}</code> ·
                            {{ serie.compra.producto }}
                            <small v-if="serie.compra.origen">
                                · {{ serie.compra.origen }}</small
                            >
                        </dd>
                    </div>
                    <div v-if="serie.venta">
                        <dt>Salida (venta)</dt>
                        <dd>
                            {{ serie.venta.numero }} · {{ serie.venta.fecha }} ·
                            <code>{{ serie.venta.sku }}</code> ·
                            {{ serie.venta.cliente ?? 'Cliente' }} ·
                            {{ serie.venta.estado }}
                        </dd>
                    </div>
                    <div v-if="serie.compra && !serie.venta">
                        <dt>Estado</dt>
                        <dd>
                            Ingresó por compra y todavía no figura en una venta.
                        </dd>
                    </div>
                </dl>
            </motion.div>
        </section>

        <!-- Modal ajuste -->
        <Dialog v-if="esAdmin" v-model:open="ajusteAbierto">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog"
                style="max-width: 460px"
            >
                <template v-if="objetivoAjuste">
                    <DialogHeader>
                        <DialogTitle>Ajustar stock</DialogTitle>
                        <DialogDescription>
                            <code class="cc-inv__sku">{{
                                objetivoAjuste.sku
                            }}</code>
                            · {{ objetivoAjuste.producto }}. Stock actual
                            {{ cantidad.format(objetivoAjuste.stock_actual) }}
                            uds.
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        class="cc-modal-form"
                        @submit.prevent="confirmarAjuste"
                    >
                        <div class="cc-modal-field">
                            <span
                                >Tipo de movimiento
                                <b class="cc-req">*</b></span
                            >
                            <div class="cc-inv__tipos">
                                <button
                                    type="button"
                                    class="cc-button"
                                    :class="
                                        formAjuste.tipo === 'ENTRADA'
                                            ? ''
                                            : 'cc-button--ghost'
                                    "
                                    @click="formAjuste.tipo = 'ENTRADA'"
                                >
                                    Entrada
                                </button>
                                <button
                                    type="button"
                                    class="cc-button"
                                    :class="
                                        formAjuste.tipo === 'SALIDA'
                                            ? 'cc-button--danger'
                                            : 'cc-button--ghost'
                                    "
                                    @click="formAjuste.tipo = 'SALIDA'"
                                >
                                    Salida
                                </button>
                            </div>
                        </div>

                        <label class="cc-modal-field">
                            <span>Cantidad <b class="cc-req">*</b></span>
                            <span class="cc-log__control">
                                <input
                                    v-model="formAjuste.cantidad"
                                    type="number"
                                    min="1"
                                    step="1"
                                    required
                                />
                            </span>
                            <p
                                v-if="formAjuste.errors.cantidad"
                                class="cc-modal-error"
                            >
                                {{ formAjuste.errors.cantidad }}
                            </p>
                            <p
                                v-if="formAjuste.errors.sku"
                                class="cc-modal-error"
                            >
                                {{ formAjuste.errors.sku }}
                            </p>
                            <p
                                v-if="stockResultante !== null"
                                class="cc-inv__preview"
                            >
                                Quedará en
                                <b :class="{ 'is-neg': stockResultante < 0 }">
                                    {{ cantidad.format(stockResultante) }} uds.
                                </b>
                            </p>
                        </label>

                        <label class="cc-modal-field">
                            <span>Motivo <b class="cc-req">*</b></span>
                            <span class="cc-log__control cc-log__control--area">
                                <textarea
                                    v-model="formAjuste.motivo"
                                    rows="3"
                                    maxlength="500"
                                    required
                                    placeholder="Ej. Conteo físico, merma, stock inicial"
                                />
                            </span>
                            <p
                                v-if="formAjuste.errors.motivo"
                                class="cc-modal-error"
                            >
                                {{ formAjuste.errors.motivo }}
                            </p>
                        </label>

                        <div class="cc-modal-actions">
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="objetivoAjuste = null"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                class="cc-button"
                                :class="{ 'is-load': formAjuste.processing }"
                                :disabled="formAjuste.processing"
                            >
                                <span
                                    v-if="formAjuste.processing"
                                    class="cc-admin__spin"
                                />
                                Registrar ajuste
                            </button>
                        </div>
                    </form>
                </template>
            </DialogContent>
        </Dialog>

        <!-- Modal mínimo -->
        <Dialog v-if="esAdmin" v-model:open="minimoAbierto">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog"
                style="max-width: 380px"
            >
                <template v-if="objetivoMinimo">
                    <DialogHeader>
                        <DialogTitle>Stock mínimo</DialogTitle>
                        <DialogDescription>
                            Alerta de reposición para
                            <code class="cc-inv__sku">{{
                                objetivoMinimo.sku
                            }}</code
                            >.
                        </DialogDescription>
                    </DialogHeader>
                    <form class="cc-modal-form" @submit.prevent="guardarMinimo">
                        <label class="cc-modal-field">
                            <span>Unidades mínimas <b class="cc-req">*</b></span>
                            <span class="cc-log__control">
                                <input
                                    v-model="formMinimo.stock_minimo"
                                    type="number"
                                    min="0"
                                    step="1"
                                    required
                                />
                            </span>
                            <p
                                v-if="formMinimo.errors.stock_minimo"
                                class="cc-modal-error"
                            >
                                {{ formMinimo.errors.stock_minimo }}
                            </p>
                        </label>
                        <div class="cc-modal-actions">
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="objetivoMinimo = null"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                class="cc-button"
                                :class="{ 'is-load': formMinimo.processing }"
                            >
                                Guardar
                            </button>
                        </div>
                    </form>
                </template>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.cc-inv__tabs {
    display: flex;
    gap: 4px;
    overflow-x: auto;
    border-bottom: 1px solid var(--cc-border);
}

.cc-inv__tab {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    margin-bottom: -1px;
    min-height: 44px;
    padding: 10px 14px;
    border: 0;
    border-bottom: 2px solid transparent;
    background: none;
    color: var(--cc-muted);
    font: inherit;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    cursor: pointer;
}

.cc-inv__tab span {
    padding: 1px 7px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--cc-muted) 14%, transparent);
    font-size: 11px;
}

.cc-inv__tab.is-activa {
    border-bottom-color: var(--cc-accent);
    color: var(--cc-text);
}

.cc-inv__sku {
    padding: 2px 6px;
    border-radius: 4px;
    background: color-mix(in srgb, var(--cc-muted) 10%, transparent);
    font-family: 'IBM Plex Mono', monospace;
    font-size: 12px;
}

.cc-inv__minimo {
    display: inline-flex;
    gap: 6px;
    align-items: center;
}

.cc-inv__nota,
.cc-inv__preview {
    margin: 0;
    padding: 12px 16px 0;
    color: var(--cc-muted);
    font-size: 13px;
}

.cc-inv__preview {
    padding: 4px 0 0;
}

.cc-inv__preview .is-neg {
    color: var(--cc-error);
}

.cc-inv__tipos {
    display: flex;
    gap: 8px;
}

.cc-inv__serie {
    padding: 16px;
}

.cc-inv__serie-vacia {
    display: block;
    padding: 32px 16px;
    text-align: center;
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
