<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import {
    ChevronLeft,
    ChevronRight,
    Eye,
    PackagePlus,
    Plus,
    RotateCcw,
    Search,
    ShoppingCart,
    Trash2,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import CcRadialFab from '@/components/admin/CcRadialFab.vue';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { adminSpring } from '@/lib/adminMotion';

type ProveedorOpcion = {
    id: number;
    razon_social: string;
    nombre_comercial: string | null;
    nit: string | null;
};

type Metodo = { id: number; codigo: string; nombre: string };

type VarianteCompra = {
    sku: string;
    codigo_barras: string | null;
    producto: string | null;
    atributos: string;
    stock_actual: number;
    stock_minimo: number;
    agotado: boolean;
    sugerido: number;
    proveedor_id: number | null;
    proveedor: string | null;
    costo_ultimo: number | null;
    ultima_compra: string | null;
    ultima_fecha: string | null;
};

type LineaCompra = {
    sku: string;
    producto: string | null;
    cantidad: number;
    costo_unitario: number;
    numero_serie: string | null;
    subtotal: number;
};

type CompraFila = {
    id: number;
    numero_compra: string;
    fecha: string | null;
    estado: string;
    proveedor_id: number;
    proveedor: string;
    nombre_comercial: string | null;
    lineas: number;
    unidades: number;
    total: number;
    items: LineaCompra[];
};

type Filtros = { q: string; proveedor: string };

type LineaCarrito = {
    clave: string;
    sku: string;
    producto: string;
    atributos: string;
    cantidad: number;
    costo: string;
    numero_serie: string;
    proveedor: string | null;
};

type ItemCompra = {
    sku: string;
    cantidad: number;
    costo_unitario: number;
    numero_serie: string | null;
};

const props = defineProps<{
    compras: {
        data: CompraFila[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    reposicion: VarianteCompra[];
    proveedores: ProveedorOpcion[];
    metodos: Metodo[];
    catalogo: VarianteCompra[];
    kpis: { compras: number; agotados: number; bajos: number; total_mes: number };
    filtros: Filtros;
    numeroSugerido: string;
    fechaHoy: string;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Compras', href: '/compras' }],
    },
});

const cantidadFmt = new Intl.NumberFormat('es-BO');
const moneda = new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 2,
});

const filtros = reactive<Filtros>({ ...props.filtros });
let busquedaTimer = 0;
const enCliente = ref(false);
const ancho = ref(1024);
const qRepo = ref('');
const vistaRepo = ref('');
const seleccion = ref<string[]>([]);
const cantidades = reactive<Record<string, number>>({});
const compraAbierta = ref(false);
const ficha = ref<CompraFila | null>(null);
const busqueda = ref('');
const avisoLote = ref('');
const carrito = ref<LineaCarrito[]>([]);

const form = useForm({
    proveedor_id: '',
    metodo_pago_id: '',
    numero_compra: props.numeroSugerido,
    fecha_compra: props.fechaHoy,
    items: [] as ItemCompra[],
});

watch(
    () => props.filtros,
    (valor) => Object.assign(filtros, valor),
);

watch(
    () => props.numeroSugerido,
    (numero) => {
        if (!compraAbierta.value) form.numero_compra = numero;
    },
);

onMounted(() => {
    enCliente.value = true;
    ancho.value = window.innerWidth;
    window.addEventListener('resize', alRedimensionar);
});

onBeforeUnmount(() => {
    window.clearTimeout(busquedaTimer);
    window.removeEventListener('resize', alRedimensionar);
});

function alRedimensionar(): void {
    ancho.value = window.innerWidth;
}

const opcionesProveedor = computed(() => [
    { value: '', label: 'Todos los proveedores' },
    ...props.proveedores.map((proveedor) => ({
        value: String(proveedor.id),
        label: proveedor.nombre_comercial
            ? `${proveedor.razon_social} · ${proveedor.nombre_comercial}`
            : proveedor.razon_social,
    })),
]);

const opcionesAlta = computed(() =>
    opcionesProveedor.value.filter((opcion) => opcion.value !== ''),
);

const opcionesMetodo = computed(() =>
    props.metodos.map((metodo) => ({
        value: String(metodo.id),
        label: metodo.nombre,
    })),
);

const opcionesVista = [
    { value: '', label: 'Agotados y bajos' },
    { value: 'agotados', label: 'Solo agotados' },
    { value: 'bajos', label: 'Solo bajo mínimo' },
];

const kpis = computed(() => [
    { label: 'Compras', valor: cantidadFmt.format(props.kpis.compras) },
    { label: 'Agotados', valor: cantidadFmt.format(props.kpis.agotados) },
    { label: 'Bajo mínimo', valor: cantidadFmt.format(props.kpis.bajos) },
    { label: 'Total del mes', valor: moneda.format(props.kpis.total_mes) },
]);

const reposicionFiltrada = computed(() => {
    const q = qRepo.value.trim().toLowerCase();

    return props.reposicion.filter((item) => {
        if (vistaRepo.value === 'agotados' && !item.agotado) return false;
        if (vistaRepo.value === 'bajos' && item.agotado) return false;
        if (q === '') return true;

        return (
            item.sku.toLowerCase().includes(q) ||
            (item.producto ?? '').toLowerCase().includes(q) ||
            (item.proveedor ?? '').toLowerCase().includes(q) ||
            (item.codigo_barras ?? '').toLowerCase().includes(q)
        );
    });
});

const todosVisibles = computed(
    () =>
        reposicionFiltrada.value.length > 0 &&
        reposicionFiltrada.value.every((item) => seleccion.value.includes(item.sku)),
);

const catalogoFiltrado = computed(() => {
    const q = busqueda.value.trim().toLowerCase();
    if (q === '') return [];

    return props.catalogo
        .filter(
            (item) =>
                item.sku.toLowerCase().includes(q) ||
                (item.codigo_barras ?? '').toLowerCase().includes(q) ||
                (item.producto ?? '').toLowerCase().includes(q) ||
                (item.proveedor ?? '').toLowerCase().includes(q),
        )
        .slice(0, 6);
});

const unidades = computed(() =>
    carrito.value.reduce((acc, linea) => acc + Number(linea.cantidad || 0), 0),
);

function subtotal(linea: LineaCarrito): number {
    const costo = Number(linea.costo);
    if (!Number.isFinite(costo) || costo < 0) return 0;
    return Math.round(linea.cantidad * costo * 100) / 100;
}

const total = computed(() =>
    Math.round(carrito.value.reduce((acc, linea) => acc + subtotal(linea), 0) * 100) / 100,
);

const errores = computed(() => Object.values(form.errors));

const puedeRegistrar = computed(() => {
    if (form.proveedor_id === '' || form.metodo_pago_id === '') return false;
    if (form.numero_compra.trim() === '' || form.fecha_compra === '') return false;
    if (carrito.value.length === 0) return false;

    return carrito.value.every((linea) => {
        const costo = Number(linea.costo);
        return (
            Number.isInteger(linea.cantidad) &&
            linea.cantidad >= 1 &&
            linea.costo !== '' &&
            Number.isFinite(costo) &&
            costo >= 0
        );
    });
});

const resumen = computed(() => {
    const { from, to, total: cantidad } = props.compras.meta;
    if (!cantidad || from === null || to === null) return 'Mostrando 0 compras';
    return `Mostrando ${from}–${to} de ${cantidadFmt.format(cantidad)} compras`;
});

function consulta(origen: Filtros, pagina?: number): Record<string, string | number> {
    const query: Record<string, string | number> = {};
    if (origen.q !== '') query.q = origen.q;
    if (origen.proveedor !== '') query.proveedor = origen.proveedor;
    if (pagina && pagina > 1) query.page = pagina;
    return query;
}

function aplicar(extra: Partial<Filtros> = {}, pagina?: number): void {
    router.get('/compras', consulta({ ...filtros, ...extra }, pagina), {
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
    if (pagina < 1 || pagina > props.compras.meta.last_page) return;
    aplicar({}, pagina);
}

function limpiar(): void {
    window.clearTimeout(busquedaTimer);
    filtros.q = '';
    filtros.proveedor = '';
    aplicar({ q: '', proveedor: '' });
}

function uid(): string {
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

function cantidadDe(item: VarianteCompra): number {
    return cantidades[item.sku] ?? item.sugerido;
}

function fijarCantidad(item: VarianteCompra, evento: Event): void {
    const valor = Number((evento.target as HTMLInputElement).value);
    cantidades[item.sku] = Number.isFinite(valor) && valor >= 1 ? Math.floor(valor) : 1;
}

function marcar(sku: string): void {
    seleccion.value = seleccion.value.includes(sku)
        ? seleccion.value.filter((actual) => actual !== sku)
        : [...seleccion.value, sku];
}

function alternarVisibles(): void {
    if (todosVisibles.value) {
        const visibles = new Set(reposicionFiltrada.value.map((item) => item.sku));
        seleccion.value = seleccion.value.filter((sku) => !visibles.has(sku));
        return;
    }

    const set = new Set(seleccion.value);
    reposicionFiltrada.value.forEach((item) => set.add(item.sku));
    seleccion.value = [...set];
}

function marcarAgotados(): void {
    const set = new Set(seleccion.value);
    props.reposicion.filter((item) => item.agotado).forEach((item) => set.add(item.sku));
    seleccion.value = [...set];
    vistaRepo.value = 'agotados';
}

function lineaDesde(item: VarianteCompra, cantidad: number): LineaCarrito {
    return {
        clave: uid(),
        sku: item.sku,
        producto: item.producto ?? item.sku,
        atributos: item.atributos,
        cantidad,
        costo: item.costo_ultimo === null ? '' : String(item.costo_ultimo),
        numero_serie: '',
        proveedor: item.proveedor,
    };
}

function abrirNueva(): void {
    carrito.value = [];
    avisoLote.value = '';
    busqueda.value = '';
    form.clearErrors();
    form.proveedor_id = '';
    form.metodo_pago_id = '';
    form.numero_compra = props.numeroSugerido;
    form.fecha_compra = props.fechaHoy;
    compraAbierta.value = true;
}

function comprarLote(): void {
    const elegidos = props.reposicion.filter((item) => seleccion.value.includes(item.sku));
    if (elegidos.length === 0) return;

    carrito.value = elegidos.map((item) => lineaDesde(item, cantidadDe(item)));
    const origenes = [
        ...new Set(elegidos.map((item) => item.proveedor_id).filter((id): id is number => id !== null)),
    ];
    form.clearErrors();
    form.proveedor_id = origenes.length === 1 ? String(origenes[0]) : '';
    form.metodo_pago_id = '';
    form.numero_compra = props.numeroSugerido;
    form.fecha_compra = props.fechaHoy;
    avisoLote.value =
        origenes.length > 1
            ? 'El lote junta productos comprados a proveedores distintos. Elegí a quién le comprás ahora.'
            : origenes.length === 0
              ? 'Estos productos todavía no tienen una compra anterior.'
              : `Lote del proveedor ${elegidos.find((item) => item.proveedor)?.proveedor ?? ''}.`;
    busqueda.value = '';
    compraAbierta.value = true;
}

function agregar(item: VarianteCompra): void {
    const existente = carrito.value.find(
        (linea) => linea.sku === item.sku && linea.numero_serie.trim() === '',
    );

    if (existente) {
        existente.cantidad += item.agotado || item.stock_actual <= item.stock_minimo ? item.sugerido : 1;
        busqueda.value = '';
        return;
    }

    const cantidad = item.stock_actual <= item.stock_minimo ? item.sugerido : 1;
    carrito.value.push(lineaDesde(item, cantidad));
    busqueda.value = '';
}

function quitar(clave: string): void {
    carrito.value = carrito.value.filter((linea) => linea.clave !== clave);
}

function ajustarCantidad(linea: LineaCarrito): void {
    if (!Number.isFinite(linea.cantidad) || linea.cantidad < 1) linea.cantidad = 1;
    linea.cantidad = Math.floor(linea.cantidad);
}

function registrar(): void {
    form.items = carrito.value.map((linea) => ({
        sku: linea.sku,
        cantidad: linea.cantidad,
        costo_unitario: Number(linea.costo),
        numero_serie: linea.numero_serie.trim() === '' ? null : linea.numero_serie.trim(),
    }));
    form.numero_compra = form.numero_compra.trim();
    form.post('/compras', {
        preserveScroll: true,
        onSuccess: () => {
            compraAbierta.value = false;
            carrito.value = [];
            seleccion.value = [];
            busqueda.value = '';
            avisoLote.value = '';
        },
    });
}

function badgeRepo(item: VarianteCompra): string {
    return item.agotado ? 'cc-log__badge--error' : 'cc-log__badge--alerta';
}

const fabAcciones = [{ id: 'crear', label: 'Nueva compra', icon: Plus }];

function fabElegir(id: string): void {
    if (id === 'crear') abrirNueva();
}
</script>

<template>
    <div>
        <Head title="Compras" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Compras</p>
                    <h1>Compras</h1>
                    <p>
                        Revisá lo que ya compraste y armá un lote con los
                        productos agotados. Cada uno muestra de qué proveedor vino.
                    </p>
                </motion.div>
                <motion.div layout class="cc-log__actions" :transition="adminSpring">
                    <button type="button" class="cc-button" @click="abrirNueva">
                        <Plus :size="16" />
                        Nueva compra
                    </button>
                </motion.div>
            </motion.div>

            <motion.div layout class="cc-admin__kpis cc-compra-kpis" :transition="adminSpring">
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

            <form class="cc-log__filters" @submit.prevent="aplicar()">
                <div class="cc-log__row">
                    <label class="cc-log__control cc-log__search">
                        <Search :size="16" />
                        <input
                            v-model="filtros.q"
                            type="search"
                            placeholder="Número, proveedor o SKU..."
                            maxlength="100"
                            @input="buscarLuego"
                        />
                        <button
                            v-if="filtros.q"
                            type="button"
                            class="cc-log__clear"
                            aria-label="Limpiar búsqueda"
                            @click="filtros.q = ''; aplicar()"
                        >
                            <X :size="14" />
                        </button>
                    </label>
                    <CcRevealSelect
                        v-model="filtros.proveedor"
                        etiqueta="Todos los proveedores"
                        :opciones="opcionesProveedor"
                        @change="aplicar()"
                    />
                    <div class="cc-log__row-actions">
                        <button type="submit" class="cc-button">
                            <Search :size="16" />
                            Buscar
                        </button>
                        <button type="button" class="cc-button cc-button--ghost" @click="limpiar">
                            <RotateCcw :size="16" />
                            Limpiar
                        </button>
                    </div>
                </div>
            </form>

            <motion.div layout class="cc-log__panel" :transition="adminSpring">
                <div class="cc-log__table-wrap">
                    <table class="cc-log__table">
                        <thead>
                            <tr>
                                <th>Compra</th>
                                <th>Fecha</th>
                                <th>Proveedor</th>
                                <th>Ítems</th>
                                <th>Total</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="compras.data.length === 0">
                                <td class="cc-log__empty" colspan="6">
                                    <strong>Sin compras</strong>
                                    Todavía no hay compras con ese filtro.
                                </td>
                            </tr>
                            <tr v-for="compra in compras.data" :key="compra.id">
                                <td>
                                    <span class="cc-log__when">
                                        <strong>{{ compra.numero_compra }}</strong>
                                        <small>{{ compra.estado }}</small>
                                    </span>
                                </td>
                                <td>{{ compra.fecha ?? '—' }}</td>
                                <td>
                                    <span class="cc-log__when">
                                        <strong>{{ compra.proveedor }}</strong>
                                        <small>{{ compra.nombre_comercial ?? 'Sin nombre comercial' }}</small>
                                    </span>
                                </td>
                                <td>{{ cantidadFmt.format(compra.lineas) }} · {{ cantidadFmt.format(compra.unidades) }} uds.</td>
                                <td>{{ moneda.format(compra.total) }}</td>
                                <td>
                                    <button
                                        type="button"
                                        class="cc-log__eye"
                                        :aria-label="`Ver compra ${compra.numero_compra}`"
                                        @click="ficha = compra"
                                    >
                                        <Eye :size="15" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="cc-log-board">
                    <p v-if="compras.data.length === 0" class="cc-log__empty">
                        <strong>Sin compras</strong>
                        Todavía no hay compras con ese filtro.
                    </p>
                    <article v-for="compra in compras.data" :key="`pin-${compra.id}`" class="cc-log-pin">
                        <button type="button" class="cc-log-pin__abrir" @click="ficha = compra">
                            <span class="cc-log-pin__cuerpo">
                                <strong>{{ compra.numero_compra }}</strong>
                                <p>{{ compra.proveedor }}</p>
                                <small>{{ compra.fecha ?? 'Sin fecha' }} · {{ compra.lineas }} líneas</small>
                            </span>
                        </button>
                        <span class="cc-log-pin__valor">{{ moneda.format(compra.total) }}</span>
                    </article>
                </div>
                <footer class="cc-log__foot">
                    <p>{{ resumen }}</p>
                    <div class="cc-log__pager">
                        <button
                            type="button"
                            aria-label="Página anterior"
                            :disabled="compras.meta.current_page <= 1"
                            @click="ir(compras.meta.current_page - 1)"
                        >
                            <ChevronLeft :size="16" />
                        </button>
                        <span>Página {{ compras.meta.current_page }} de {{ compras.meta.last_page }}</span>
                        <button
                            type="button"
                            aria-label="Página siguiente"
                            :disabled="compras.meta.current_page >= compras.meta.last_page"
                            @click="ir(compras.meta.current_page + 1)"
                        >
                            <ChevronRight :size="16" />
                        </button>
                    </div>
                </footer>
            </motion.div>

            <div class="cc-compra__bloque">
                <div class="cc-compra__bloque-head">
                    <div>
                        <h2>Para reponer</h2>
                        <p>Marcá los agotados y compralos en un solo lote. La cantidad cubre el mínimo.</p>
                    </div>
                    <div class="cc-compra__lote">
                        <button type="button" class="cc-button cc-button--ghost" @click="marcarAgotados">
                            Sold out
                        </button>
                        <button
                            type="button"
                            class="cc-button"
                            :disabled="seleccion.length === 0"
                            @click="comprarLote"
                        >
                            <PackagePlus :size="16" />
                            Comprar lote ({{ seleccion.length }})
                        </button>
                    </div>
                </div>
                <div class="cc-log__row">
                    <label class="cc-log__control cc-compra__buscar">
                        <Search :size="16" />
                        <input
                            v-model="qRepo"
                            type="search"
                            placeholder="SKU, producto o proveedor..."
                            maxlength="100"
                        />
                    </label>
                    <CcRevealSelect
                        v-model="vistaRepo"
                        etiqueta="Agotados y bajos"
                        :opciones="opcionesVista"
                    />
                </div>
                <div class="cc-log__panel">
                    <div class="cc-log__table-wrap">
                        <table class="cc-log__table">
                            <thead>
                                <tr>
                                    <th>
                                        <input
                                            type="checkbox"
                                            :checked="todosVisibles"
                                            aria-label="Seleccionar visibles"
                                            @change="alternarVisibles"
                                        />
                                    </th>
                                    <th>Producto</th>
                                    <th>Stock</th>
                                    <th>Último proveedor</th>
                                    <th>Último costo</th>
                                    <th>Lote</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="reposicionFiltrada.length === 0">
                                    <td class="cc-log__empty" colspan="6">
                                        <strong>Nada para reponer</strong>
                                        No hay productos agotados ni bajo el mínimo.
                                    </td>
                                </tr>
                                <tr v-for="item in reposicionFiltrada" :key="item.sku">
                                    <td>
                                        <input
                                            type="checkbox"
                                            :checked="seleccion.includes(item.sku)"
                                            :aria-label="`Seleccionar ${item.sku}`"
                                            @change="marcar(item.sku)"
                                        />
                                    </td>
                                    <td>
                                        <span class="cc-log__when">
                                            <strong>{{ item.producto ?? item.sku }}</strong>
                                            <small>{{ item.sku }} · {{ item.atributos || 'Sin atributos' }}</small>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-log__badge" :class="badgeRepo(item)">
                                            {{ item.agotado ? 'Agotado' : 'Bajo' }}
                                            {{ cantidadFmt.format(item.stock_actual) }}/{{ cantidadFmt.format(item.stock_minimo) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-log__when">
                                            <strong>{{ item.proveedor ?? 'Sin compras' }}</strong>
                                            <small>{{ item.ultima_compra ? `${item.ultima_compra} · ${item.ultima_fecha ?? ''}` : 'Todavía no se compró' }}</small>
                                        </span>
                                    </td>
                                    <td>{{ item.costo_ultimo === null ? '—' : moneda.format(item.costo_ultimo) }}</td>
                                    <td>
                                        <span class="cc-log__control cc-compra__num">
                                            <input
                                                type="number"
                                                min="1"
                                                step="1"
                                                :value="cantidadDe(item)"
                                                :aria-label="`Cantidad del lote de ${item.sku}`"
                                                @change="fijarCantidad(item, $event)"
                                            />
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="cc-log-board">
                        <p v-if="reposicionFiltrada.length === 0" class="cc-log__empty">
                            <strong>Nada para reponer</strong>
                            No hay productos agotados ni bajo el mínimo.
                        </p>
                        <article v-for="item in reposicionFiltrada" :key="`repo-${item.sku}`" class="cc-log-pin">
                            <label class="cc-compra__check">
                                <input
                                    type="checkbox"
                                    :checked="seleccion.includes(item.sku)"
                                    :aria-label="`Seleccionar ${item.sku}`"
                                    @change="marcar(item.sku)"
                                />
                            </label>
                            <span class="cc-log-pin__cuerpo">
                                <strong>{{ item.producto ?? item.sku }}</strong>
                                <p>{{ item.sku }}</p>
                                <small>{{ item.proveedor ?? 'Sin compras anteriores' }}</small>
                                <span class="cc-log__badge" :class="badgeRepo(item)">
                                    {{ item.agotado ? 'Agotado' : 'Bajo' }}
                                </span>
                            </span>
                        </article>
                    </div>
                </div>
            </div>
        </section>

        <Teleport v-if="enCliente" to="#cc-portal">
            <CcRadialFab
                v-show="ancho <= 768 && !compraAbierta && !ficha"
                :acciones="fabAcciones"
                @elegir="fabElegir"
            />
        </Teleport>

        <Dialog v-model:open="compraAbierta">
            <DialogContent class="cc-theme cc-admin cc-log-dialog cc-compra-dialog">
                <DialogHeader>
                    <DialogTitle>Nueva compra</DialogTitle>
                    <DialogDescription>
                        {{ carrito.length }} {{ carrito.length === 1 ? 'línea' : 'líneas' }} · {{ moneda.format(total) }}
                    </DialogDescription>
                </DialogHeader>
                <form class="cc-compra" @submit.prevent="registrar">
                    <div v-if="avisoLote" class="cc-compra__aviso">{{ avisoLote }}</div>
                    <div v-if="errores.length" class="cc-compra__alertas">
                        <p v-for="(error, i) in errores" :key="i">{{ error }}</p>
                    </div>
                    <div class="cc-compra__datos">
                        <div class="cc-modal-field">
                            <span>Proveedor <b class="cc-req">*</b></span>
                            <CcRevealSelect
                                v-model="form.proveedor_id"
                                etiqueta="Elegí un proveedor"
                                :opciones="opcionesAlta"
                            />
                        </div>
                        <div class="cc-modal-field">
                            <span>Método de pago <b class="cc-req">*</b></span>
                            <CcRevealSelect
                                v-model="form.metodo_pago_id"
                                etiqueta="Elegí un método"
                                :opciones="opcionesMetodo"
                            />
                        </div>
                        <label class="cc-modal-field">
                            <span>Número <b class="cc-req">*</b></span>
                            <span class="cc-log__control">
                                <input v-model="form.numero_compra" type="text" maxlength="50" required />
                            </span>
                        </label>
                        <label class="cc-modal-field">
                            <span>Fecha <b class="cc-req">*</b></span>
                            <span class="cc-log__control">
                                <input v-model="form.fecha_compra" type="date" :max="fechaHoy" required />
                            </span>
                        </label>
                    </div>
                    <div class="cc-compra__sumar">
                        <label class="cc-log__control cc-compra__buscar">
                            <Search :size="16" />
                            <input
                                v-model="busqueda"
                                type="search"
                                placeholder="Sumar un SKU..."
                                maxlength="100"
                                @keydown.enter.prevent
                            />
                        </label>
                        <div v-if="catalogoFiltrado.length" class="cc-compra__lista">
                            <button
                                v-for="item in catalogoFiltrado"
                                :key="item.sku"
                                type="button"
                                class="cc-compra__prod"
                                @click="agregar(item)"
                            >
                                <span>
                                    <b>{{ item.producto }}</b>
                                    <small>
                                        {{ item.sku }} · {{ item.proveedor ?? 'Sin proveedor' }} ·
                                        {{ item.agotado ? 'Agotado' : `${item.stock_actual} uds.` }}
                                    </small>
                                </span>
                                <em><Plus :size="14" /> Agregar</em>
                            </button>
                        </div>
                    </div>
                    <div class="cc-log__table-wrap">
                        <table class="cc-log__table">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Cantidad</th>
                                    <th>Costo</th>
                                    <th>Serie</th>
                                    <th>Subtotal</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="carrito.length === 0">
                                    <td class="cc-log__empty" colspan="6">
                                        <strong>Lote vacío</strong>
                                        Elegí productos agotados o sumá un SKU.
                                    </td>
                                </tr>
                                <tr v-for="linea in carrito" :key="linea.clave">
                                    <td>
                                        <span class="cc-log__when">
                                            <strong>{{ linea.producto }}</strong>
                                            <small>{{ linea.sku }} · {{ linea.proveedor ?? 'Sin compra anterior' }}</small>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-log__control cc-compra__num">
                                            <input
                                                v-model.number="linea.cantidad"
                                                type="number"
                                                min="1"
                                                step="1"
                                                :aria-label="`Cantidad de ${linea.sku}`"
                                                @change="ajustarCantidad(linea)"
                                            />
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-log__control cc-compra__costo">
                                            <input
                                                v-model="linea.costo"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                placeholder="0,00"
                                                :aria-label="`Costo de ${linea.sku}`"
                                            />
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-log__control cc-compra__serie">
                                            <input
                                                v-model="linea.numero_serie"
                                                type="text"
                                                maxlength="100"
                                                placeholder="Opcional"
                                                :aria-label="`Serie de ${linea.sku}`"
                                            />
                                        </span>
                                    </td>
                                    <td>{{ moneda.format(subtotal(linea)) }}</td>
                                    <td>
                                        <button
                                            type="button"
                                            class="cc-log__eye cc-log__eye--danger"
                                            :aria-label="`Quitar ${linea.sku}`"
                                            @click="quitar(linea.clave)"
                                        >
                                            <Trash2 :size="15" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="cc-compra__cierre">
                        <p>
                            <ShoppingCart :size="16" />
                            <span>{{ unidades }} uds.</span>
                            <b>{{ moneda.format(total) }}</b>
                        </p>
                        <button
                            type="submit"
                            class="cc-button"
                            :class="{ 'is-load': form.processing }"
                            :disabled="!puedeRegistrar || form.processing"
                        >
                            Registrar compra
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="ficha !== null" @update:open="(abierto) => { if (!abierto) ficha = null; }">
            <DialogContent class="cc-theme cc-admin cc-log-dialog cc-compra-dialog">
                <DialogHeader>
                    <DialogTitle>{{ ficha?.numero_compra }}</DialogTitle>
                    <DialogDescription>
                        {{ ficha?.proveedor }} · {{ ficha?.fecha ?? 'Sin fecha' }}
                    </DialogDescription>
                </DialogHeader>
                <div v-if="ficha" class="cc-log__table-wrap">
                    <table class="cc-log__table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cantidad</th>
                                <th>Costo</th>
                                <th>Serie</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in ficha.items" :key="`${ficha.id}-${item.sku}-${item.numero_serie}`">
                                <td>
                                    <span class="cc-log__when">
                                        <strong>{{ item.producto ?? item.sku }}</strong>
                                        <small>{{ item.sku }}</small>
                                    </span>
                                </td>
                                <td>{{ cantidadFmt.format(item.cantidad) }}</td>
                                <td>{{ moneda.format(item.costo_unitario) }}</td>
                                <td>{{ item.numero_serie ?? '—' }}</td>
                                <td>{{ moneda.format(item.subtotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p v-if="ficha" class="cc-compra__total-ficha">
                    Total {{ moneda.format(ficha.total) }}
                </p>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.cc-compra-kpis {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.cc-compra__bloque {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.cc-compra__bloque-head,
.cc-compra__lote,
.cc-compra__cierre,
.cc-compra__cierre p {
    display: flex;
    align-items: center;
    gap: 10px;
}

.cc-compra__bloque-head {
    justify-content: space-between;
}

.cc-compra__bloque h2 {
    margin: 0;
    font-size: 18px;
    line-height: 24px;
}

.cc-compra__bloque p,
.cc-compra__aviso,
.cc-compra__total-ficha {
    margin: 0;
    color: var(--cc-muted);
    font-size: 13px;
}

.cc-compra__buscar {
    flex: 0 1 280px;
    width: min(100%, 280px);
    height: 40px;
    min-height: 40px;
}

.cc-compra {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.cc-compra__datos {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.cc-compra__sumar {
    position: relative;
    width: min(100%, 320px);
}

.cc-compra__lista {
    position: absolute;
    z-index: 4;
    top: calc(100% + 4px);
    left: 0;
    display: flex;
    width: 100%;
    max-height: 220px;
    flex-direction: column;
    gap: 6px;
    overflow: auto;
}

.cc-compra__prod {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--cc-border);
    border-radius: 8px;
    background: var(--cc-card);
    color: inherit;
    text-align: left;
    cursor: pointer;
}

.cc-compra__prod span {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 2px;
}

.cc-compra__prod small,
.cc-compra__prod em {
    color: var(--cc-muted);
    font-size: 12px;
    font-style: normal;
}

.cc-compra__num {
    width: 88px;
}

.cc-compra__costo {
    width: 110px;
}

.cc-compra__serie {
    min-width: 120px;
}

.cc-compra__alertas,
.cc-compra__aviso {
    padding: 8px 10px;
    border: 1px solid var(--cc-border);
    border-radius: 8px;
}

.cc-compra__alertas {
    border-color: var(--cc-error);
    color: var(--cc-error);
}

.cc-compra__alertas p {
    margin: 0;
}

.cc-compra__cierre {
    justify-content: space-between;
}

.cc-compra__cierre p {
    margin: 0;
    color: var(--cc-text);
}

.cc-compra__cierre b,
.cc-compra__total-ficha {
    color: var(--cc-text);
    font-size: 16px;
}

.cc-compra__check {
    display: grid;
    place-items: center;
}

.cc-req {
    color: var(--cc-error);
}

@media (max-width: 768px) {
    .cc-compra-kpis,
    .cc-compra__datos {
        grid-template-columns: 1fr 1fr;
    }

    .cc-compra__bloque-head,
    .cc-compra__cierre {
        align-items: stretch;
        flex-direction: column;
    }

    .cc-compra__buscar,
    .cc-compra__sumar {
        width: 100%;
        flex-basis: auto;
    }
}
</style>
