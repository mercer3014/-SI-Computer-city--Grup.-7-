<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import {
    Ban,
    ChevronLeft,
    ChevronRight,
    Download,
    Mail,
    Plus,
    RotateCcw,
    Search,
    ShoppingCart,
    Trash2,
    UserRound,
} from '@lucide/vue';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { adminSpring } from '@/lib/adminMotion';

type VariantePos = {
    id: number;
    sku: string;
    codigo_barras: string | null;
    producto: string | null;
    atributos: string;
    precio_venta: number;
    stock_actual: number;
    stock_minimo: number;
};

type ClientePos = {
    id: number;
    nombre: string;
    razon_social: string | null;
    tipo_documento: string | null;
    numero_documento: string | null;
    telefono: string | null;
    email: string | null;
    es_generico: boolean;
};

type Metodo = { id: number; codigo: string; nombre: string };

type VentaFila = {
    id: number;
    numero: string;
    fecha: string | null;
    estado: string | null;
    cliente: string | null;
    documento: string | null;
    vendedor: string | null;
    total: number;
};

type LineaFicha = {
    id: number;
    sku: string | null;
    producto: string | null;
    variante: string | null;
    cantidad: number;
    precio_unitario: number;
    descuento: number;
    subtotal: number;
    numero_serie: string | null;
    meses_garantia: number;
    fecha_fin_garantia: string | null;
    estado: string;
};

type Ficha = {
    id: number;
    numero: string;
    fecha: string | null;
    estado: string | null;
    observacion: string | null;
    descuento_global: number;
    total: number;
    motivo_anulacion: string | null;
    fecha_anulacion: string | null;
    pdf: string;
    cliente: {
        id: number | null;
        nombre: string | null;
        documento: string;
        email: string | null;
        telefono: string | null;
    };
    vendedor: string | null;
    items: LineaFicha[];
    pagos: { id: number; metodo: string | null; monto: number; referencia: string | null }[];
};

type LineaCarrito = {
    clave: string;
    variante_id: number;
    sku: string;
    producto: string;
    atributos: string;
    precio: number;
    stock: number;
    cantidad: number;
    descuento: number;
    numero_serie: string;
    meses_garantia: number;
};

type PagoFila = {
    clave: string;
    metodo_pago_id: string;
    monto: string;
    referencia: string;
};

type Filtros = {
    q: string;
    estado: string;
    tab: string;
    desde: string;
    hasta: string;
    cliente: string;
    creada: string;
    ficha: string;
};

const props = defineProps<{
    ventas: {
        data: VentaFila[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    catalogo: VariantePos[];
    clientes: ClientePos[];
    metodos: Metodo[];
    ficha: Ficha | null;
    kpis: { hoy: number; completadas: number; anuladas: number };
    filtros: Filtros;
    puedeCrear: boolean;
    puedeAnular: boolean;
    puedeClientes: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ventas', href: '/ventas' }],
    },
});

const cantidadFmt = new Intl.NumberFormat('es-BO');
const moneda = new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 2,
});

const formFiltro = reactive<Filtros>({ ...props.filtros });
let busquedaTimer = 0;

watch(
    () => props.filtros,
    (filtros) => Object.assign(formFiltro, filtros),
);

onBeforeUnmount(() => window.clearTimeout(busquedaTimer));

function consulta(origen: Filtros, pagina?: number): Record<string, string | number> {
    const query: Record<string, string | number> = {};
    (Object.keys(origen) as (keyof Filtros)[]).forEach((clave) => {
        if (origen[clave] !== '') query[clave] = origen[clave];
    });
    if (pagina && pagina > 1) query.page = pagina;
    return query;
}

function aplicar(extra: Partial<Filtros> = {}, pagina?: number): void {
    router.get('/ventas', consulta({ ...formFiltro, ...extra }, pagina), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function buscarLuego(): void {
    window.clearTimeout(busquedaTimer);
    busquedaTimer = window.setTimeout(() => aplicar(), 320);
}

const pestana = computed(() => props.filtros.tab || 'nueva');
const kpis = computed(() => [
    { label: 'Ventas de hoy', valor: cantidadFmt.format(props.kpis.hoy) },
    { label: 'Completadas', valor: cantidadFmt.format(props.kpis.completadas) },
    { label: 'Anuladas', valor: cantidadFmt.format(props.kpis.anuladas) },
]);

function uid(): string {
    return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

const busquedaProducto = ref('');
const carrito = ref<LineaCarrito[]>([]);
const clienteId = ref<number | null>(
    props.filtros.cliente ? Number(props.filtros.cliente) : null,
);
const clienteQ = ref('');
const descuentoGlobal = ref(0);
const observacion = ref('');
const enviarEmail = ref(false);
const metodoInicial = computed(() =>
    props.metodos[0] ? String(props.metodos[0].id) : '',
);
const pagos = ref<PagoFila[]>([
    { clave: uid(), metodo_pago_id: metodoInicial.value, monto: '', referencia: '' },
]);

watch(
    () => props.filtros.cliente,
    (valor) => {
        if (valor) clienteId.value = Number(valor);
    },
);

const reservado = computed(() => {
    const mapa: Record<number, number> = {};
    carrito.value.forEach((linea) => {
        mapa[linea.variante_id] = (mapa[linea.variante_id] ?? 0) + linea.cantidad;
    });
    return mapa;
});

const catalogoFiltrado = computed(() => {
    const q = busquedaProducto.value.trim().toLowerCase();
    const lista = props.catalogo.filter((item) => {
        if (q === '') return true;
        return (
            item.sku.toLowerCase().includes(q) ||
            (item.codigo_barras ?? '').toLowerCase().includes(q) ||
            (item.producto ?? '').toLowerCase().includes(q) ||
            item.atributos.toLowerCase().includes(q)
        );
    });
    return lista.slice(0, 40);
});

function disponible(item: VariantePos): number {
    return Math.max(0, item.stock_actual - (reservado.value[item.id] ?? 0));
}

function tonoStock(item: VariantePos): string {
    const stock = disponible(item);
    if (stock <= 0) return 'cc-log__badge--error';
    if (stock <= item.stock_minimo) return 'cc-log__badge--alerta';
    return 'cc-log__badge--crear';
}

function agregar(item: VariantePos): void {
    if (!props.puedeCrear) return;
    const stock = disponible(item);
    if (stock <= 0) {
        toast.error(`Sin stock para ${item.sku}. Disponible: ${item.stock_actual}.`);
        return;
    }
    const existente = carrito.value.find(
        (linea) => linea.variante_id === item.id && linea.numero_serie === '',
    );
    if (existente && existente.cantidad < item.stock_actual) {
        existente.cantidad += 1;
        return;
    }
    carrito.value.push({
        clave: uid(),
        variante_id: item.id,
        sku: item.sku,
        producto: item.producto ?? item.sku,
        atributos: item.atributos,
        precio: item.precio_venta,
        stock: item.stock_actual,
        cantidad: 1,
        descuento: 0,
        numero_serie: '',
        meses_garantia: 3,
    });
}

function quitar(clave: string): void {
    carrito.value = carrito.value.filter((linea) => linea.clave !== clave);
}

function ajustarCantidad(linea: LineaCarrito): void {
    const otras = carrito.value
        .filter((otra) => otra.variante_id === linea.variante_id && otra.clave !== linea.clave)
        .reduce((acc, otra) => acc + otra.cantidad, 0);
    const max = Math.max(1, linea.stock - otras);
    if (linea.cantidad > max) {
        linea.cantidad = max;
        toast.error(`Stock insuficiente. Disponible: ${max}.`);
    }
    if (linea.cantidad < 1) linea.cantidad = 1;
}

const subtotal = computed(() =>
    carrito.value.reduce(
        (acc, linea) => acc + linea.cantidad * linea.precio - Number(linea.descuento || 0),
        0,
    ),
);
const total = computed(() =>
    Math.round((subtotal.value - Number(descuentoGlobal.value || 0)) * 100) / 100,
);
const pagado = computed(() =>
    Math.round(
        pagos.value.reduce((acc, pago) => acc + Number(pago.monto || 0), 0) * 100,
    ) / 100,
);
const saldo = computed(() => Math.round((total.value - pagado.value) * 100) / 100);
const pagosOk = computed(
    () =>
        pagos.value.length > 0 &&
        pagos.value.every((pago) => Number(pago.monto) > 0 && pago.metodo_pago_id !== '') &&
        Math.abs(saldo.value) < 0.01,
);

const clienteElegido = computed(
    () => props.clientes.find((c) => c.id === clienteId.value) ?? null,
);

const clientesFiltrados = computed(() => {
    const q = clienteQ.value.trim().toLowerCase();
    if (q === '') return props.clientes.slice(0, 8);
    return props.clientes
        .filter(
            (c) =>
                c.nombre.toLowerCase().includes(q) ||
                (c.numero_documento ?? '').toLowerCase().includes(q) ||
                (c.razon_social ?? '').toLowerCase().includes(q),
        )
        .slice(0, 8);
});

function agregarPago(): void {
    pagos.value.push({
        clave: uid(),
        metodo_pago_id: metodoInicial.value,
        monto: saldo.value > 0 ? String(saldo.value) : '',
        referencia: '',
    });
}

function quitarPago(clave: string): void {
    if (pagos.value.length === 1) return;
    pagos.value = pagos.value.filter((pago) => pago.clave !== clave);
}

const opcionesMetodo = computed(() =>
    props.metodos.map((m) => ({ value: String(m.id), label: m.nombre })),
);

type ItemVenta = {
    variante_id: number;
    cantidad: number;
    descuento: number;
    numero_serie: string;
    meses_garantia: number;
};

type PagoVentaForm = {
    metodo_pago_id: number;
    monto: number;
    referencia: string;
};

const formVenta = useForm({
    cliente_id: 0,
    descuento_global: 0,
    observacion: '',
    enviar_email: false,
    items: [] as ItemVenta[],
    pagos: [] as PagoVentaForm[],
});

const puedeConfirmar = computed(() => {
    if (!props.puedeCrear) return false;
    if (carrito.value.length === 0) return false;
    if (clienteId.value === null) return false;
    if (!pagosOk.value) return false;
    if (total.value < 0) return false;
    return carrito.value.every((linea) => linea.cantidad <= linea.stock);
});

function confirmarVenta(): void {
    if (!puedeConfirmar.value) {
        if (carrito.value.length === 0 || clienteId.value === null) {
            toast.error('La venta necesita productos y un cliente.');
        } else if (!pagosOk.value) {
            toast.error(
                `Los pagos no coinciden con el total. Saldo: ${moneda.format(saldo.value)}.`,
            );
        }
        return;
    }

    formVenta.cliente_id = clienteId.value as number;
    formVenta.descuento_global = Number(descuentoGlobal.value || 0);
    formVenta.observacion = observacion.value;
    formVenta.enviar_email = enviarEmail.value;
    formVenta.items = carrito.value.map((linea) => ({
        variante_id: linea.variante_id,
        cantidad: linea.cantidad,
        descuento: Number(linea.descuento || 0),
        numero_serie: linea.numero_serie,
        meses_garantia: linea.meses_garantia,
    }));
    formVenta.pagos = pagos.value.map((pago) => ({
        metodo_pago_id: Number(pago.metodo_pago_id),
        monto: Number(pago.monto),
        referencia: pago.referencia,
    }));
    formVenta.post('/ventas', { preserveScroll: true });
}

const alta = useForm({
    tipo_documento: 'CI',
    numero_documento: '',
    nombre: '',
    razon_social: '',
    telefono: '',
    email: '',
    direccion: '',
    observacion: '',
});
const clienteNuevo = ref(false);

function registrarCliente(): void {
    alta.post('/ventas/cliente', {
        preserveScroll: true,
        onSuccess: () => {
            clienteNuevo.value = false;
            alta.reset();
        },
    });
}

const fichaAbierta = computed({
    get: () => props.ficha !== null,
    set: (abierto: boolean) => {
        if (!abierto) aplicar({ ficha: '', creada: '' });
    },
});

const objetivoAnular = ref<VentaFila | Ficha | null>(null);
const motivoAnular = ref('');
const anularAbierto = computed({
    get: () => objetivoAnular.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) {
            objetivoAnular.value = null;
            motivoAnular.value = '';
        }
    },
});

function pedirAnular(venta: VentaFila | Ficha): void {
    if (venta.estado === 'ANULADA') {
        toast.error(`La venta ${venta.numero} ya está ANULADA.`);
        return;
    }
    objetivoAnular.value = venta;
    motivoAnular.value = '';
}

function confirmarAnular(): void {
    if (!objetivoAnular.value) return;
    router.patch(
        `/ventas/${objetivoAnular.value.id}/anular`,
        { motivo: motivoAnular.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                objetivoAnular.value = null;
                motivoAnular.value = '';
            },
        },
    );
}

function reenviar(venta: Ficha): void {
    router.post(`/ventas/${venta.id}/reenviar`, {}, { preserveScroll: true });
}

const opcionesEstado = [
    { value: '', label: 'Todos los estados' },
    { value: 'COMPLETADA', label: 'Completadas' },
    { value: 'ANULADA', label: 'Anuladas' },
];

const resumen = computed(() => {
    const { from, to, total } = props.ventas.meta;
    if (!total || from === null || to === null) return 'Mostrando 0 ventas';
    return `Mostrando ${from}–${to} de ${cantidadFmt.format(total)}`;
});

const erroresVenta = computed(() => Object.values(formVenta.errors));
</script>

<template>
    <div>
        <Head title="Ventas" />

        <section class="cc-admin cc-log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Punto de venta</p>
                    <h1>Ventas</h1>
                    <p>
                        Registrá la venta en un solo paso: productos con stock,
                        serie, garantía, cliente y pagos. El inventario se
                        actualiza al confirmar.
                    </p>
                </motion.div>
            </motion.div>

            <motion.div layout class="cc-admin__kpis" :transition="adminSpring">
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

            <div class="cc-pos__tabs" role="tablist">
                <button
                    type="button"
                    class="cc-pos__tab"
                    :class="{ 'is-activa': pestana === 'nueva' }"
                    @click="aplicar({ tab: 'nueva' })"
                >
                    Nueva venta
                </button>
                <button
                    type="button"
                    class="cc-pos__tab"
                    :class="{ 'is-activa': pestana === 'consultar' }"
                    @click="aplicar({ tab: 'consultar' })"
                >
                    Consultar
                    <span>{{ cantidadFmt.format(props.ventas.meta.total) }}</span>
                </button>
            </div>

            <template v-if="pestana === 'nueva'">
                <div v-if="erroresVenta.length" class="cc-pos__alertas">
                    <p v-for="(error, i) in erroresVenta" :key="i">{{ error }}</p>
                </div>

                <div class="cc-pos__grid">
                    <div class="cc-log__panel cc-pos__col">
                        <label class="cc-log__control cc-log__search">
                            <Search :size="16" />
                            <input
                                v-model="busquedaProducto"
                                type="search"
                                placeholder="SKU, código de barras o nombre..."
                            />
                        </label>
                        <div class="cc-pos__lista">
                            <button
                                v-for="item in catalogoFiltrado"
                                :key="item.id"
                                type="button"
                                class="cc-pos__prod"
                                :disabled="disponible(item) <= 0 || !puedeCrear"
                                @click="agregar(item)"
                            >
                                <div>
                                    <b>{{ item.producto }}</b>
                                    <small>{{ item.sku }} · {{ item.atributos || '—' }}</small>
                                </div>
                                <div class="cc-pos__prod-meta">
                                    <span>{{ moneda.format(item.precio_venta) }}</span>
                                    <em :class="['cc-log__badge', tonoStock(item)]">
                                        {{ disponible(item) }} uds.
                                    </em>
                                </div>
                            </button>
                            <p v-if="catalogoFiltrado.length === 0" class="cc-pos__vacio">
                                No hay variantes que coincidan.
                            </p>
                        </div>
                    </div>

                    <div class="cc-log__panel cc-pos__col">
                        <h2 class="cc-pos__h">
                            <ShoppingCart :size="16" />
                            Carrito
                        </h2>
                        <div v-if="carrito.length === 0" class="cc-pos__vacio">
                            Agregá productos desde el buscador. No se puede
                            vender más de lo que hay en stock.
                        </div>
                        <div v-else class="cc-pos__carrito">
                            <article
                                v-for="linea in carrito"
                                :key="linea.clave"
                                class="cc-pos__linea"
                            >
                                <header>
                                    <div>
                                        <b>{{ linea.producto }}</b>
                                        <small>{{ linea.sku }}</small>
                                    </div>
                                    <button
                                        type="button"
                                        class="cc-button cc-button--ghost"
                                        @click="quitar(linea.clave)"
                                    >
                                        <Trash2 :size="14" />
                                    </button>
                                </header>
                                <div class="cc-pos__campos">
                                    <label>
                                        Cant.
                                        <input
                                            v-model.number="linea.cantidad"
                                            type="number"
                                            min="1"
                                            :max="linea.stock"
                                            @change="ajustarCantidad(linea)"
                                        />
                                    </label>
                                    <label>
                                        Desc. Bs
                                        <input
                                            v-model.number="linea.descuento"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                        />
                                    </label>
                                    <label>
                                        Serie
                                        <input
                                            v-model="linea.numero_serie"
                                            type="text"
                                            maxlength="100"
                                            placeholder="N° de serie"
                                        />
                                    </label>
                                    <label>
                                        Garantía (meses)
                                        <input
                                            v-model.number="linea.meses_garantia"
                                            type="number"
                                            min="0"
                                            max="60"
                                        />
                                    </label>
                                </div>
                                <p class="cc-pos__sub">
                                    {{ moneda.format(linea.cantidad * linea.precio - linea.descuento) }}
                                    · stock {{ linea.stock }}
                                </p>
                            </article>
                        </div>

                        <h2 class="cc-pos__h">
                            <UserRound :size="16" />
                            Cliente
                        </h2>
                        <div class="cc-pos__cliente">
                            <label class="cc-log__control cc-log__search">
                                <Search :size="16" />
                                <input
                                    v-model="clienteQ"
                                    type="search"
                                    placeholder="Buscar por nombre o CI/NIT..."
                                />
                            </label>
                            <button
                                v-if="puedeClientes"
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="clienteNuevo = true"
                            >
                                <Plus :size="14" />
                                Nuevo
                            </button>
                        </div>
                        <p v-if="clienteElegido" class="cc-pos__elegido">
                            {{ clienteElegido.nombre }}
                            <small>
                                {{ clienteElegido.tipo_documento }}
                                {{ clienteElegido.numero_documento }}
                            </small>
                        </p>
                        <div class="cc-pos__chips">
                            <button
                                v-for="c in clientesFiltrados"
                                :key="c.id"
                                type="button"
                                class="cc-pos__chip"
                                :class="{ 'is-on': clienteId === c.id }"
                                @click="clienteId = c.id"
                            >
                                {{ c.nombre }}
                            </button>
                        </div>

                        <h2 class="cc-pos__h">Pagos</h2>
                        <p v-if="!pagosOk" class="cc-pos__aviso">
                            EX-02 · El total pagado debe coincidir con el total
                            de la venta. Saldo: {{ moneda.format(saldo) }}.
                        </p>
                        <div
                            v-for="pago in pagos"
                            :key="pago.clave"
                            class="cc-pos__pago"
                        >
                            <CcRevealSelect
                                v-model="pago.metodo_pago_id"
                                etiqueta="Método"
                                :opciones="opcionesMetodo"
                            />
                            <label class="cc-log__control">
                                <input
                                    v-model="pago.monto"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    placeholder="Monto"
                                />
                            </label>
                            <label class="cc-log__control">
                                <input
                                    v-model="pago.referencia"
                                    type="text"
                                    maxlength="100"
                                    placeholder="Referencia / voucher"
                                />
                            </label>
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                :disabled="pagos.length === 1"
                                @click="quitarPago(pago.clave)"
                            >
                                <Trash2 :size="14" />
                            </button>
                        </div>
                        <button type="button" class="cc-button cc-button--ghost" @click="agregarPago">
                            <Plus :size="14" />
                            Otro pago
                        </button>

                        <label class="cc-log__control">
                            <span class="cc-log__label">Descuento global</span>
                            <input v-model.number="descuentoGlobal" type="number" min="0" step="0.01" />
                        </label>
                        <label class="cc-log__control cc-log__control--area">
                            <span class="cc-log__label">Observación</span>
                            <textarea v-model="observacion" rows="2" maxlength="1000" />
                        </label>
                        <label class="cc-pos__check">
                            <input v-model="enviarEmail" type="checkbox" />
                            Enviar nota de venta por email
                        </label>

                        <div class="cc-pos__totales">
                            <span>Subtotal {{ moneda.format(subtotal) }}</span>
                            <b>Total {{ moneda.format(total) }}</b>
                            <span>Pagado {{ moneda.format(pagado) }}</span>
                        </div>
                        <button
                            type="button"
                            class="cc-button"
                            :class="{ 'is-load': formVenta.processing }"
                            :disabled="!puedeConfirmar || formVenta.processing"
                            @click="confirmarVenta"
                        >
                            Confirmar venta
                        </button>
                    </div>
                </div>
            </template>

            <template v-else>
                <form class="cc-log__filters" @submit.prevent="aplicar()">
                    <div class="cc-log__row">
                        <label class="cc-log__control cc-log__search">
                            <Search :size="16" />
                            <input
                                v-model="formFiltro.q"
                                type="search"
                                placeholder="N° de venta, cliente o serie..."
                                @input="buscarLuego"
                            />
                        </label>
                        <CcRevealSelect
                            v-model="formFiltro.estado"
                            etiqueta="Estado"
                            :opciones="opcionesEstado"
                            @change="aplicar()"
                        />
                        <label class="cc-log__control">
                            <input v-model="formFiltro.desde" type="date" @change="aplicar()" />
                        </label>
                        <label class="cc-log__control">
                            <input v-model="formFiltro.hasta" type="date" @change="aplicar()" />
                        </label>
                        <div class="cc-log__row-actions">
                            <button type="submit" class="cc-button">
                                <Search :size="16" />
                                Buscar
                            </button>
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="
                                    formFiltro.q = '';
                                    formFiltro.estado = '';
                                    formFiltro.desde = '';
                                    formFiltro.hasta = '';
                                    aplicar({ q: '', estado: '', desde: '', hasta: '' });
                                "
                            >
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
                                    <th>N°</th>
                                    <th>Fecha</th>
                                    <th>Cliente</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="venta in ventas.data" :key="venta.id">
                                    <td>
                                        <button
                                            type="button"
                                            class="cc-pos__link"
                                            @click="aplicar({ ficha: String(venta.id) })"
                                        >
                                            {{ venta.numero }}
                                        </button>
                                    </td>
                                    <td>{{ venta.fecha }}</td>
                                    <td>
                                        {{ venta.cliente }}
                                        <small class="cc-pos__muted">{{ venta.documento }}</small>
                                    </td>
                                    <td>{{ moneda.format(venta.total) }}</td>
                                    <td>
                                        <em
                                            class="cc-log__badge"
                                            :class="
                                                venta.estado === 'ANULADA'
                                                    ? 'cc-log__badge--error'
                                                    : 'cc-log__badge--crear'
                                            "
                                        >
                                            {{ venta.estado }}
                                        </em>
                                    </td>
                                    <td>
                                        <button
                                            type="button"
                                            class="cc-button cc-button--ghost"
                                            @click="aplicar({ ficha: String(venta.id) })"
                                        >
                                            Ver
                                        </button>
                                        <button
                                            v-if="puedeAnular && venta.estado !== 'ANULADA'"
                                            type="button"
                                            class="cc-button cc-button--ghost"
                                            @click="pedirAnular(venta)"
                                        >
                                            <Ban :size="14" />
                                            Anular
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="ventas.data.length === 0">
                                    <td colspan="6">No hay ventas con esos filtros.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="cc-log__pager">
                        <span>{{ resumen }}</span>
                        <div>
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                :disabled="ventas.meta.current_page <= 1"
                                @click="aplicar({}, ventas.meta.current_page - 1)"
                            >
                                <ChevronLeft :size="16" />
                            </button>
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                :disabled="ventas.meta.current_page >= ventas.meta.last_page"
                                @click="aplicar({}, ventas.meta.current_page + 1)"
                            >
                                <ChevronRight :size="16" />
                            </button>
                        </div>
                    </div>
                </motion.div>
            </template>
        </section>

        <Dialog v-model:open="fichaAbierta">
            <DialogContent class="cc-theme cc-admin cc-log-dialog" style="max-width: 640px">
                <DialogHeader>
                    <DialogTitle>Nota {{ ficha?.numero }}</DialogTitle>
                    <DialogDescription>
                        {{ ficha?.fecha }} · {{ ficha?.cliente.nombre }} ·
                        {{ ficha?.estado }}
                    </DialogDescription>
                </DialogHeader>
                <div v-if="ficha" class="cc-pos__ficha">
                    <p>
                        Total <b>{{ moneda.format(ficha.total) }}</b>
                    </p>
                    <ul>
                        <li v-for="item in ficha.items" :key="item.id">
                            {{ item.cantidad }} × {{ item.producto }}
                            <small v-if="item.numero_serie"> · serie {{ item.numero_serie }}</small>
                            <small> · {{ item.meses_garantia }} meses</small>
                        </li>
                    </ul>
                    <div class="cc-modal-actions">
                        <a :href="ficha.pdf" class="cc-button" target="_blank" rel="noopener">
                            <Download :size="14" />
                            Descargar PDF
                        </a>
                        <button
                            v-if="puedeCrear && ficha.cliente.email"
                            type="button"
                            class="cc-button cc-button--ghost"
                            @click="reenviar(ficha)"
                        >
                            <Mail :size="14" />
                            Reenviar
                        </button>
                        <button
                            v-if="puedeAnular && ficha.estado !== 'ANULADA'"
                            type="button"
                            class="cc-button cc-button--danger"
                            @click="pedirAnular(ficha)"
                        >
                            Anular
                        </button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="clienteNuevo">
            <DialogContent class="cc-theme cc-admin cc-log-dialog" style="max-width: 520px">
                <DialogHeader>
                    <DialogTitle>Nuevo cliente</DialogTitle>
                    <DialogDescription>
                        Se registra ahora y queda seleccionado en la venta.
                    </DialogDescription>
                </DialogHeader>
                <form class="cc-modal-form" @submit.prevent="registrarCliente">
                    <label class="cc-log__control">
                        <span class="cc-log__label">Nombre</span>
                        <input v-model="alta.nombre" required maxlength="200" />
                    </label>
                    <div class="cc-pos__campos">
                        <CcRevealSelect
                            v-model="alta.tipo_documento"
                            etiqueta="CI o NIT"
                            :opciones="[
                                { value: 'CI', label: 'CI' },
                                { value: 'NIT', label: 'NIT' },
                            ]"
                        />
                        <label class="cc-log__control">
                            <input
                                v-model="alta.numero_documento"
                                required
                                maxlength="50"
                                placeholder="Documento"
                            />
                        </label>
                    </div>
                    <label class="cc-log__control">
                        <span class="cc-log__label">Email</span>
                        <input v-model="alta.email" type="email" />
                    </label>
                    <label class="cc-log__control">
                        <span class="cc-log__label">Teléfono</span>
                        <input v-model="alta.telefono" />
                    </label>
                    <p v-if="alta.errors.numero_documento" class="cc-modal-error">
                        {{ alta.errors.numero_documento }}
                    </p>
                    <div class="cc-modal-actions">
                        <button type="submit" class="cc-button" :disabled="alta.processing">
                            Guardar cliente
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="anularAbierto">
            <DialogContent class="cc-theme cc-admin cc-log-dialog" style="max-width: 480px">
                <DialogHeader>
                    <DialogTitle>Anular {{ objetivoAnular?.numero }}</DialogTitle>
                    <DialogDescription>
                        El stock vuelve al inventario. El motivo es obligatorio.
                    </DialogDescription>
                </DialogHeader>
                <form class="cc-modal-form" @submit.prevent="confirmarAnular">
                    <label class="cc-log__control cc-log__control--area">
                        <span class="cc-log__label">Motivo</span>
                        <textarea v-model="motivoAnular" rows="3" required minlength="5" />
                    </label>
                    <div class="cc-modal-actions">
                        <button type="submit" class="cc-button cc-button--danger">
                            Anular venta
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.cc-pos__tabs {
    display: flex;
    gap: 4px;
    overflow-x: auto;
    border-bottom: 1px solid var(--cc-border);
}
.cc-pos__tab {
    display: inline-flex;
    gap: 8px;
    align-items: center;
    margin-bottom: -1px;
    padding: 10px 14px;
    border: 0;
    border-bottom: 2px solid transparent;
    background: none;
    color: var(--cc-muted);
    font: inherit;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
}
.cc-pos__tab span {
    padding: 1px 7px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--cc-muted) 14%, transparent);
    font-size: 11px;
}
.cc-pos__tab.is-activa {
    border-bottom-color: var(--cc-accent);
    color: var(--cc-text);
}
.cc-pos__grid {
    display: grid;
    grid-template-columns: 1.15fr 0.85fr;
    gap: 16px;
    align-items: start;
}
.cc-pos__col {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.cc-pos__lista {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-height: 520px;
    overflow: auto;
}
.cc-pos__prod {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 12px;
    border: 1px solid var(--cc-border);
    border-radius: 10px;
    background: var(--cc-surface);
    color: inherit;
    text-align: left;
    cursor: pointer;
}
.cc-pos__prod:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
.cc-pos__prod small,
.cc-pos__muted,
.cc-pos__sub,
.cc-pos__vacio,
.cc-pos__aviso {
    color: var(--cc-muted);
    font-size: 12px;
}
.cc-pos__prod b,
.cc-pos__linea b,
.cc-pos__elegido {
    display: block;
}
.cc-pos__prod-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}
.cc-pos__h {
    display: flex;
    gap: 8px;
    align-items: center;
    margin: 8px 0 0;
    font-size: 14px;
}
.cc-pos__linea {
    border: 1px solid var(--cc-border);
    border-radius: 10px;
    padding: 10px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.cc-pos__linea header {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}
.cc-pos__campos {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
}
.cc-pos__campos label,
.cc-pos__check {
    display: flex;
    flex-direction: column;
    gap: 4px;
    font-size: 12px;
    color: var(--cc-muted);
}
.cc-pos__campos input,
.cc-pos__pago input {
    width: 100%;
}
.cc-pos__cliente {
    display: flex;
    gap: 8px;
}
.cc-pos__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.cc-pos__chip {
    border: 1px solid var(--cc-border);
    background: transparent;
    border-radius: 999px;
    padding: 4px 10px;
    color: inherit;
    cursor: pointer;
    font: inherit;
    font-size: 12px;
}
.cc-pos__chip.is-on {
    border-color: var(--cc-accent);
    color: var(--cc-accent);
}
.cc-pos__pago {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr 1fr auto;
    gap: 8px;
    align-items: center;
}
.cc-pos__totales {
    display: flex;
    flex-direction: column;
    gap: 2px;
    font-size: 14px;
}
.cc-pos__alertas {
    padding: 10px 12px;
    border: 1px solid color-mix(in srgb, var(--cc-error) 40%, var(--cc-border));
    border-radius: 10px;
    color: var(--cc-error);
    font-size: 13px;
}
.cc-pos__link {
    border: 0;
    background: none;
    color: var(--cc-accent);
    font: inherit;
    cursor: pointer;
    padding: 0;
}
.cc-pos__ficha ul {
    padding-left: 18px;
    margin: 8px 0;
}
.cc-modal-form,
.cc-pos__carrito {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.cc-modal-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}
.cc-modal-error {
    color: var(--cc-error);
    font-size: 12px;
}
.cc-log__label {
    display: block;
    margin-bottom: 4px;
    color: var(--cc-muted);
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}
@media (max-width: 960px) {
    .cc-pos__grid,
    .cc-pos__pago,
    .cc-pos__campos {
        grid-template-columns: 1fr;
    }
}
</style>
