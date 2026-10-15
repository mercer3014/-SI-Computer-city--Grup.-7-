<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import {
    Ban,
    Calendar,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    Mail,
    Plus,
    RotateCcw,
    Search,
    ShoppingCart,
    Trash2,
    UserPlus,
    UserRound,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import CcRadialFab from '@/components/admin/CcRadialFab.vue';
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
    abrirAlta: boolean;
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

onBeforeUnmount(() => {
    window.clearTimeout(busquedaTimer);
    window.removeEventListener('resize', alRedimensionar);
    document.removeEventListener('pointerdown', cerrarMenuVenta);
});

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

const ventaAbierta = ref(props.abrirAlta);
const modoCliente = ref<'frecuente' | 'nuevo'>('frecuente');
const menuVenta = ref(false);
const menuRaiz = ref<HTMLElement | null>(null);

function abrirVenta(modo: 'frecuente' | 'nuevo'): void {
    limpiarVenta();
    modoCliente.value = modo;
    menuVenta.value = false;
    ventaAbierta.value = true;
}

const fabAcciones = [
    { id: 'frecuente', label: 'Cliente frecuente', icon: UserRound },
    { id: 'nuevo', label: 'Nuevo cliente', icon: UserPlus },
];

function elegirFab(id: string): void {
    if (id === 'frecuente' || id === 'nuevo') abrirVenta(id);
}

function cerrarMenuVenta(evento: PointerEvent): void {
    if (!menuRaiz.value?.contains(evento.target as Node)) {
        menuVenta.value = false;
    }
}

const enCliente = ref(false);
const ancho = ref(1024);

function alRedimensionar(): void {
    ancho.value = window.innerWidth;
}

onMounted(() => {
    enCliente.value = true;
    alRedimensionar();
    window.addEventListener('resize', alRedimensionar);
    document.addEventListener('pointerdown', cerrarMenuVenta);
});

watch(
    () => props.abrirAlta,
    (abrir) => {
        if (abrir) ventaAbierta.value = true;
    },
);
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
    if (q === '') return [];
    return props.catalogo
        .filter(
            (item) =>
                item.sku.toLowerCase().includes(q) ||
                (item.codigo_barras ?? '').toLowerCase().includes(q) ||
                (item.producto ?? '').toLowerCase().includes(q) ||
                item.atributos.toLowerCase().includes(q),
        )
        .slice(0, 6);
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
    if (q === '') return [];
    return props.clientes
        .filter(
            (c) =>
                !c.es_generico &&
                (c.nombre.toLowerCase().includes(q) ||
                    (c.numero_documento ?? '').toLowerCase().includes(q) ||
                    (c.razon_social ?? '').toLowerCase().includes(q)),
        )
        .slice(0, 5);
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
    cliente_id: null as number | null,
    cliente_nuevo: null as null | {
        tipo_documento: string;
        numero_documento: string;
        nombre: string;
        telefono: string;
        email: string;
    },
    descuento_global: 0,
    observacion: '',
    enviar_email: false,
    items: [] as ItemVenta[],
    pagos: [] as PagoVentaForm[],
});

function clienteEstaListo(): boolean {
    if (modoCliente.value === 'nuevo') {
        return alta.nombre.trim() !== '' && alta.numero_documento.trim() !== '';
    }

    return clienteId.value !== null && !(clienteElegido.value?.es_generico ?? false);
}

const puedeConfirmar = computed(() => {
    if (!props.puedeCrear) return false;
    if (carrito.value.length === 0) return false;
    if (!clienteEstaListo()) return false;
    if (!pagosOk.value) return false;
    if (total.value < 0) return false;
    return carrito.value.every((linea) => linea.cantidad <= linea.stock);
});

function confirmarVenta(): void {
    if (!puedeConfirmar.value) {
        if (carrito.value.length === 0 || !clienteEstaListo()) {
            toast.error(
                modoCliente.value === 'nuevo'
                    ? 'La venta necesita productos y los datos del cliente.'
                    : 'La venta necesita productos y un cliente frecuente.',
            );
        } else if (!pagosOk.value) {
            toast.error(
                `Los pagos no coinciden con el total. Saldo: ${moneda.format(saldo.value)}.`,
            );
        }
        return;
    }

    if (modoCliente.value === 'nuevo') {
        formVenta.cliente_id = null;
        formVenta.cliente_nuevo = {
            tipo_documento: alta.tipo_documento,
            numero_documento: alta.numero_documento.trim(),
            nombre: alta.nombre.trim(),
            telefono: alta.telefono.trim(),
            email: alta.email.trim(),
        };
    } else {
        formVenta.cliente_id = clienteId.value;
        formVenta.cliente_nuevo = null;
    }
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
    formVenta.post('/ventas', {
        preserveScroll: true,
        onSuccess: () => {
            ventaAbierta.value = false;
            limpiarVenta();
        },
    });
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

function limpiarVenta(): void {
    carrito.value = [];
    clienteId.value = null;
    clienteQ.value = '';
    busquedaProducto.value = '';
    descuentoGlobal.value = 0;
    observacion.value = '';
    enviarEmail.value = false;
    pagos.value = [
        {
            clave: uid(),
            metodo_pago_id: metodoInicial.value,
            monto: '',
            referencia: '',
        },
    ];
    alta.reset();
    alta.clearErrors();
    formVenta.reset();
    formVenta.clearErrors();
}
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

const chips = computed(() => {
    const lista: { clave: 'q' | 'estado' | 'desde' | 'hasta'; texto: string }[] = [];

    if (formFiltro.q) {
        lista.push({ clave: 'q', texto: `Buscar: ${formFiltro.q}` });
    }

    if (formFiltro.estado) {
        lista.push({
            clave: 'estado',
            texto: formFiltro.estado === 'ANULADA' ? 'Anuladas' : 'Completadas',
        });
    }

    if (formFiltro.desde) {
        lista.push({ clave: 'desde', texto: `Desde ${formFiltro.desde}` });
    }

    if (formFiltro.hasta) {
        lista.push({ clave: 'hasta', texto: `Hasta ${formFiltro.hasta}` });
    }

    return lista;
});

function quitarFiltro(clave: 'q' | 'estado' | 'desde' | 'hasta'): void {
    formFiltro[clave] = '';
    aplicar();
}

function limpiarConsulta(): void {
    window.clearTimeout(busquedaTimer);
    formFiltro.q = '';
    formFiltro.estado = '';
    formFiltro.desde = '';
    formFiltro.hasta = '';
    aplicar({ q: '', estado: '', desde: '', hasta: '' });
}

const erroresVenta = computed(() => Object.values(formVenta.errors));

watch(erroresVenta, (errores) => {
    if (errores.length > 0) ventaAbierta.value = true;
});
</script>

<template>
    <div>
        <Head title="Ventas" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Punto de venta</p>
                    <h1>Ventas</h1>
                    <p>
                        Consultá las notas de venta. El alta se carga en un
                        formulario, sin ocupar la pantalla.
                    </p>
                </motion.div>
                <motion.div
                    v-if="puedeCrear"
                    layout
                    class="cc-log__actions cc-pos__alta"
                    :transition="adminSpring"
                >
                    <div ref="menuRaiz" class="cc-pos__menu">
                        <button
                            type="button"
                            class="cc-button"
                            :aria-expanded="menuVenta"
                            aria-haspopup="menu"
                            @click="menuVenta = !menuVenta"
                        >
                            <Plus :size="16" />
                            Nueva venta
                            <ChevronDown :size="16" />
                        </button>
                        <div v-if="menuVenta" class="cc-pos__menu-panel" role="menu">
                            <button type="button" role="menuitem" @click="abrirVenta('frecuente')">
                                <UserRound :size="16" />
                                Cliente frecuente
                            </button>
                            <button type="button" role="menuitem" @click="abrirVenta('nuevo')">
                                <UserPlus :size="16" />
                                Nuevo cliente
                            </button>
                        </div>
                    </div>
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

            <Dialog v-if="!filtros.creada" v-model:open="ventaAbierta">
                <DialogContent class="cc-theme cc-admin cc-log-dialog cc-pos-form">
                    <DialogHeader>
                        <DialogTitle>
                            {{
                                modoCliente === 'nuevo'
                                    ? 'Venta a cliente nuevo'
                                    : 'Venta a cliente frecuente'
                            }}
                        </DialogTitle>
                        <DialogDescription>
                            {{
                                modoCliente === 'nuevo'
                                    ? 'Cargá los datos del cliente, los productos y el pago.'
                                    : 'Elegí un cliente que ya está registrado, los productos y el pago.'
                            }}
                        </DialogDescription>
                    </DialogHeader>
                    <form class="cc-pos-sheet" @submit.prevent="confirmarVenta">
                        <div class="cc-pos-sheet__cuerpo">
                            <div v-if="erroresVenta.length" class="cc-pos__alertas">
                                <p v-for="(error, i) in erroresVenta" :key="i">{{ error }}</p>
                            </div>

                            <section class="cc-pos__bloque">
                                <h2 class="cc-pos__h">Productos</h2>
                                <label class="cc-log__control cc-log__search">
                                    <Search :size="16" />
                                    <input
                                        v-model="busquedaProducto"
                                        type="search"
                                        placeholder="SKU, código de barras o nombre..."
                                    />
                                </label>
                                <div v-if="catalogoFiltrado.length" class="cc-pos__lista">
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
                                </div>
                                <p v-else class="cc-pos__vacio">
                                    {{
                                        busquedaProducto.trim()
                                            ? 'No hay variantes que coincidan.'
                                            : 'Escribí para ver productos. No se vende más de lo que hay en stock.'
                                    }}
                                </p>
                            </section>

                            <section class="cc-pos__bloque">
                                <h2 class="cc-pos__h">
                                    <ShoppingCart :size="16" />
                                    Carrito
                                    <span v-if="carrito.length" class="cc-pos__cuenta">{{ carrito.length }}</span>
                                </h2>
                                <p v-if="carrito.length === 0" class="cc-pos__vacio">
                                    Todavía no hay productos.
                                </p>
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
                                                class="cc-log__eye"
                                                aria-label="Quitar del carrito"
                                                @click="quitar(linea.clave)"
                                            >
                                                <Trash2 :size="14" />
                                            </button>
                                        </header>
                                        <div class="cc-pos__campos">
                                            <label class="cc-modal-field">
                                                <span>Cant.</span>
                                                <span class="cc-log__control">
                                                    <input
                                                        v-model.number="linea.cantidad"
                                                        type="number"
                                                        min="1"
                                                        :max="linea.stock"
                                                        @change="ajustarCantidad(linea)"
                                                    />
                                                </span>
                                            </label>
                                            <label class="cc-modal-field">
                                                <span>Desc. Bs</span>
                                                <span class="cc-log__control">
                                                    <input
                                                        v-model.number="linea.descuento"
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                    />
                                                </span>
                                            </label>
                                            <label class="cc-modal-field">
                                                <span>Serie</span>
                                                <span class="cc-log__control">
                                                    <input
                                                        v-model="linea.numero_serie"
                                                        type="text"
                                                        maxlength="100"
                                                        placeholder="Opcional"
                                                    />
                                                </span>
                                            </label>
                                            <label class="cc-modal-field">
                                                <span>Garantía</span>
                                                <span class="cc-log__control">
                                                    <input
                                                        v-model.number="linea.meses_garantia"
                                                        type="number"
                                                        min="0"
                                                        max="60"
                                                    />
                                                </span>
                                            </label>
                                        </div>
                                        <p class="cc-pos__sub">
                                            {{ moneda.format(linea.cantidad * linea.precio - linea.descuento) }}
                                            · stock {{ linea.stock }}
                                        </p>
                                    </article>
                                </div>
                            </section>

                            <section class="cc-pos__bloque">
                                <h2 class="cc-pos__h">
                                    <UserRound :size="16" />
                                    {{ modoCliente === 'nuevo' ? 'Cliente nuevo' : 'Cliente frecuente' }}
                                </h2>
                                <template v-if="modoCliente === 'frecuente'">
                                    <label class="cc-log__control cc-log__search">
                                        <Search :size="16" />
                                        <input
                                            v-model="clienteQ"
                                            type="search"
                                            placeholder="Nombre o CI/NIT..."
                                        />
                                    </label>
                                    <div v-if="clientesFiltrados.length" class="cc-pos__chips">
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
                                    <p v-else-if="clienteQ.trim()" class="cc-pos__vacio">
                                        No hay clientes con ese dato.
                                    </p>
                                    <p v-else class="cc-pos__vacio">
                                        Buscá por nombre o documento.
                                    </p>
                                    <p v-if="clienteElegido && !clienteElegido.es_generico" class="cc-pos__elegido">
                                        {{ clienteElegido.nombre }}
                                        <small>
                                            {{ clienteElegido.tipo_documento }}
                                            {{ clienteElegido.numero_documento }}
                                        </small>
                                    </p>
                                </template>
                                <template v-else>
                                    <label class="cc-modal-field">
                                        <span>Nombre</span>
                                        <span class="cc-log__control">
                                            <input v-model="alta.nombre" type="text" maxlength="200" required />
                                        </span>
                                        <small v-if="formVenta.errors['cliente_nuevo.nombre']" class="cc-modal-error">
                                            {{ formVenta.errors['cliente_nuevo.nombre'] }}
                                        </small>
                                    </label>
                                    <div class="cc-pos__pareja">
                                        <div class="cc-modal-field">
                                            <span>Tipo de documento</span>
                                            <CcRevealSelect
                                                v-model="alta.tipo_documento"
                                                etiqueta="CI o NIT"
                                                :opciones="[
                                                    { value: 'CI', label: 'CI' },
                                                    { value: 'NIT', label: 'NIT' },
                                                ]"
                                            />
                                        </div>
                                        <label class="cc-modal-field">
                                            <span>Número de documento</span>
                                            <span class="cc-log__control">
                                                <input
                                                    v-model="alta.numero_documento"
                                                    type="text"
                                                    maxlength="50"
                                                    required
                                                />
                                            </span>
                                            <small v-if="formVenta.errors['cliente_nuevo.numero_documento']" class="cc-modal-error">
                                                {{ formVenta.errors['cliente_nuevo.numero_documento'] }}
                                            </small>
                                        </label>
                                    </div>
                                    <div class="cc-pos__pareja">
                                        <label class="cc-modal-field">
                                            <span>Teléfono</span>
                                            <span class="cc-log__control">
                                                <input v-model="alta.telefono" type="tel" maxlength="15" />
                                            </span>
                                            <small v-if="formVenta.errors['cliente_nuevo.telefono']" class="cc-modal-error">
                                                {{ formVenta.errors['cliente_nuevo.telefono'] }}
                                            </small>
                                        </label>
                                        <label class="cc-modal-field">
                                            <span>Email</span>
                                            <span class="cc-log__control">
                                                <input v-model="alta.email" type="email" maxlength="150" />
                                            </span>
                                            <small v-if="formVenta.errors['cliente_nuevo.email']" class="cc-modal-error">
                                                {{ formVenta.errors['cliente_nuevo.email'] }}
                                            </small>
                                        </label>
                                    </div>
                                </template>
                            </section>

                            <section class="cc-pos__bloque">
                                <h2 class="cc-pos__h">Pago</h2>
                                <p v-if="carrito.length > 0 && !pagosOk" class="cc-pos__aviso">
                                    El pago tiene que coincidir con el total. Saldo
                                    {{ moneda.format(saldo) }}.
                                </p>
                                <div
                                    v-for="pago in pagos"
                                    :key="pago.clave"
                                    class="cc-pos__pago"
                                >
                                    <div class="cc-modal-field">
                                        <span>Método</span>
                                        <CcRevealSelect
                                            v-model="pago.metodo_pago_id"
                                            etiqueta="Método"
                                            :opciones="opcionesMetodo"
                                        />
                                    </div>
                                    <label class="cc-modal-field">
                                        <span>Monto</span>
                                        <span class="cc-log__control">
                                            <input
                                                v-model="pago.monto"
                                                type="number"
                                                min="0.01"
                                                step="0.01"
                                                placeholder="0,00"
                                            />
                                        </span>
                                    </label>
                                    <label class="cc-modal-field">
                                        <span>Referencia</span>
                                        <span class="cc-log__control">
                                            <input
                                                v-model="pago.referencia"
                                                type="text"
                                                maxlength="100"
                                                placeholder="Opcional"
                                            />
                                        </span>
                                    </label>
                                    <button
                                        type="button"
                                        class="cc-log__eye"
                                        aria-label="Quitar pago"
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
                                <div class="cc-pos__extra">
                                    <label class="cc-modal-field">
                                        <span>Descuento global</span>
                                        <span class="cc-log__control">
                                            <input
                                                v-model.number="descuentoGlobal"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                            />
                                        </span>
                                    </label>
                                    <label class="cc-pos__check">
                                        <input v-model="enviarEmail" type="checkbox" />
                                        Enviar nota por email
                                    </label>
                                </div>
                                <label class="cc-modal-field">
                                    <span>Observación</span>
                                    <span class="cc-log__control cc-log__control--area">
                                        <textarea v-model="observacion" rows="2" maxlength="1000" />
                                    </span>
                                </label>
                            </section>
                        </div>
                        <div class="cc-pos__cierre">
                            <div class="cc-pos__totales">
                                <span>Subtotal {{ moneda.format(subtotal) }}</span>
                                <b>Total {{ moneda.format(total) }}</b>
                            </div>
                            <button
                                type="submit"
                                class="cc-button cc-pos__confirmar"
                                :class="{ 'is-load': formVenta.processing }"
                                :disabled="!puedeConfirmar || formVenta.processing"
                            >
                                Confirmar venta
                            </button>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

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
                            <button
                                v-if="formFiltro.q"
                                type="button"
                                class="cc-log__clear"
                                aria-label="Limpiar búsqueda"
                                @click="quitarFiltro('q')"
                            >
                                <X :size="14" />
                            </button>
                        </label>
                        <CcRevealSelect
                            v-model="formFiltro.estado"
                            etiqueta="Todos los estados"
                            :opciones="opcionesEstado"
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
                                @click="limpiarConsulta"
                            >
                                <RotateCcw :size="16" />
                                Limpiar
                            </button>
                        </div>
                    </div>
                    <div class="cc-log__row">
                        <div class="cc-log__dates-par cc-ventas-fechas">
                            <label class="cc-modal-field">
                                <span>Desde</span>
                                <span class="cc-log__control">
                                    <Calendar :size="16" />
                                    <input v-model="formFiltro.desde" type="date" @change="aplicar()" />
                                </span>
                            </label>
                            <label class="cc-modal-field">
                                <span>Hasta</span>
                                <span class="cc-log__control">
                                    <Calendar :size="16" />
                                    <input v-model="formFiltro.hasta" type="date" @change="aplicar()" />
                                </span>
                            </label>
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
                                        <span class="cc-row-actions">
                                            <button
                                                type="button"
                                                class="cc-log__eye"
                                                :aria-label="`Ver nota ${venta.numero}`"
                                                @click="aplicar({ ficha: String(venta.id) })"
                                            >
                                                <Eye :size="15" />
                                            </button>
                                            <button
                                                v-if="puedeAnular && venta.estado !== 'ANULADA'"
                                                type="button"
                                                class="cc-log__eye cc-log__eye--danger"
                                                :aria-label="`Anular nota ${venta.numero}`"
                                                @click="pedirAnular(venta)"
                                            >
                                                <Ban :size="15" />
                                            </button>
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="ventas.data.length === 0">
                                    <td class="cc-log__empty" colspan="6">
                                        <strong>Sin ventas</strong>
                                        No hay ventas con esos filtros.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="cc-log-board">
                        <p v-if="ventas.data.length === 0" class="cc-log__empty">
                            <strong>Sin ventas</strong>
                            No hay ventas con esos filtros.
                        </p>
                        <article
                            v-for="venta in ventas.data"
                            :key="`pin-${venta.id}`"
                            class="cc-log-pin"
                            :class="{ 'is-off': venta.estado === 'ANULADA' }"
                        >
                            <button
                                type="button"
                                class="cc-log-pin__abrir"
                                @click="aplicar({ ficha: String(venta.id) })"
                            >
                                <span class="cc-log-pin__cuerpo">
                                    <strong>{{ venta.numero }}</strong>
                                    <p>
                                        {{ venta.cliente }}
                                        · {{ moneda.format(venta.total) }}
                                    </p>
                                    <small>
                                        {{ venta.fecha }}
                                        · {{ venta.documento || 'Sin documento' }}
                                    </small>
                                </span>
                            </button>
                            <span class="cc-row-actions">
                                <span
                                    class="cc-log__badge"
                                    :class="
                                        venta.estado === 'ANULADA'
                                            ? 'cc-log__badge--error'
                                            : 'cc-log__badge--crear'
                                    "
                                >
                                    {{ venta.estado }}
                                </span>
                                <button
                                    v-if="puedeAnular && venta.estado !== 'ANULADA'"
                                    type="button"
                                    class="cc-log__eye cc-log__eye--danger"
                                    :aria-label="`Anular nota ${venta.numero}`"
                                    @click="pedirAnular(venta)"
                                >
                                    <Ban :size="15" />
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
                                :disabled="ventas.meta.current_page <= 1"
                                @click="aplicar({}, ventas.meta.current_page - 1)"
                            >
                                <ChevronLeft :size="16" />
                            </button>
                            <span>
                                Página {{ ventas.meta.current_page }} de
                                {{ ventas.meta.last_page }}
                            </span>
                            <button
                                type="button"
                                aria-label="Página siguiente"
                                :disabled="ventas.meta.current_page >= ventas.meta.last_page"
                                @click="aplicar({}, ventas.meta.current_page + 1)"
                            >
                                <ChevronRight :size="16" />
                            </button>
                        </div>
                    </footer>
                </motion.div>
        </section>

        <Teleport v-if="enCliente && puedeCrear" to="#cc-portal">
            <CcRadialFab
                v-show="
                    ancho <= 768 &&
                    !ventaAbierta &&
                    !anularAbierto &&
                    !fichaAbierta &&
                    !clienteNuevo
                "
                :acciones="fabAcciones"
                @elegir="elegirFab"
            />
        </Teleport>

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
                    <label class="cc-modal-field">
                        <span>Nombre</span>
                        <span class="cc-log__control">
                            <input v-model="alta.nombre" required maxlength="200" />
                        </span>
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
                        <label class="cc-modal-field">
                            <span>Documento</span>
                            <span class="cc-log__control">
                            <input
                                v-model="alta.numero_documento"
                                required
                                maxlength="50"
                                placeholder="Número"
                            />
                            </span>
                        </label>
                    </div>
                    <label class="cc-modal-field">
                        <span>Email</span>
                        <span class="cc-log__control">
                            <input v-model="alta.email" type="email" />
                        </span>
                    </label>
                    <label class="cc-modal-field">
                        <span>Teléfono</span>
                        <span class="cc-log__control">
                            <input v-model="alta.telefono" type="tel" />
                        </span>
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
                    <label class="cc-modal-field">
                        <span>Motivo</span>
                        <span class="cc-log__control cc-log__control--area">
                            <textarea v-model="motivoAnular" rows="3" required minlength="5" />
                        </span>
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
    min-height: 44px;
    padding: 10px 14px;
    white-space: nowrap;
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
.cc-pos__menu {
    position: relative;
}
.cc-pos__menu-panel {
    position: absolute;
    top: calc(100% + 6px);
    right: 0;
    z-index: 30;
    display: flex;
    flex-direction: column;
    min-width: 220px;
    border: 1px solid var(--cc-border);
    border-radius: 8px;
    background: var(--cc-card);
    padding: 4px;
    box-shadow: 0 12px 32px color-mix(in srgb, #000 28%, transparent);
}
.cc-pos__menu-panel button {
    min-height: 40px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--cc-text);
    font: inherit;
    font-size: 14px;
    text-align: left;
    padding: 0 12px;
    cursor: pointer;
}
.cc-pos__menu-panel button:hover,
.cc-pos__menu-panel button:focus-visible {
    background: color-mix(in srgb, var(--cc-accent) 14%, transparent);
}
.cc-pos__pareja {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}
.cc-ventas-fechas {
    flex: 0 1 440px;
    max-width: 440px;
}
.cc-pos-sheet {
    display: flex;
    min-height: 0;
    flex: 1;
    flex-direction: column;
    gap: 0;
}
.cc-pos-sheet .cc-log__control,
.cc-pos-sheet .cc-reveal__btn {
    display: flex;
    flex: none;
    align-items: center;
    box-sizing: border-box;
    width: 100%;
    height: 48px;
    min-height: 48px;
    padding: 0 12px;
}
.cc-pos-sheet .cc-log__control--area {
    height: auto;
    min-height: 88px;
    align-items: flex-start;
    padding: 10px 12px;
}
.cc-pos-sheet .cc-log__search {
    flex: none;
    width: 100%;
}
.cc-pos-sheet__cuerpo {
    display: block;
    min-height: 0;
    flex: 1 1 auto;
    overflow: auto;
    padding-right: 2px;
    scrollbar-width: thin;
    scrollbar-color: var(--cc-border) transparent;
}
.cc-pos-sheet__cuerpo::-webkit-scrollbar {
    width: 6px;
}
.cc-pos-sheet__cuerpo::-webkit-scrollbar-thumb {
    border-radius: 99px;
    background: var(--cc-border);
}
.cc-pos__bloque {
    display: flex;
    flex: none;
    flex-direction: column;
    gap: 12px;
}
.cc-pos__bloque + .cc-pos__bloque {
    padding-top: 12px;
    border-top: 1px solid var(--cc-border);
}
.cc-pos__cuenta {
    display: inline-grid;
    min-width: 18px;
    height: 18px;
    place-items: center;
    border-radius: 99px;
    background: color-mix(in srgb, var(--cc-accent) 16%, transparent);
    color: var(--cc-accent);
    font-size: 11px;
    font-weight: 600;
}
.cc-pos__pago .cc-reveal {
    min-width: 0;
}
.cc-pos__extra {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    gap: 12px;
    align-items: end;
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
    gap: 8px;
    max-height: 248px;
    overflow: auto;
    padding-right: 4px;
}
.cc-pos__prod {
    display: flex;
    flex: none;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 64px;
    padding: 12px;
    border: 1px solid var(--cc-border);
    border-radius: 10px;
    background: var(--cc-surface);
    color: inherit;
    font: inherit;
    line-height: 1.35;
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
.cc-pos__prod > div:first-child {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
    gap: 4px;
}
.cc-pos__prod b,
.cc-pos__linea b,
.cc-pos__elegido {
    display: block;
    line-height: 18px;
    overflow-wrap: anywhere;
}
.cc-pos__prod small {
    display: block;
    line-height: 16px;
}
.cc-pos__confirmar {
    flex: none;
}
.cc-pos__cierre {
    display: flex;
    flex: none;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-top: 12px;
    border-top: 1px solid var(--cc-border);
    padding-top: 12px;
}
.cc-pos__prod-meta {
    display: flex;
    flex: none;
    flex-direction: column;
    align-items: flex-end;
    gap: 6px;
}
.cc-pos__prod-meta em {
    white-space: nowrap;
}
.cc-pos__h {
    display: flex;
    gap: 8px;
    align-items: center;
    margin: 0;
    font-size: 13px;
    font-weight: 600;
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
    grid-template-columns: 72px 88px minmax(0, 1fr) 72px;
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
.cc-pos__campos .cc-log__control,
.cc-pos__pago .cc-log__control {
    width: 100%;
}
.cc-pos__cliente {
    display: flex;
    gap: 8px;
}
.cc-pos__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.cc-pos__chip {
    border: 1px solid var(--cc-border);
    background: transparent;
    border-radius: 999px;
    min-height: 36px;
    padding: 8px 14px;
    color: inherit;
    cursor: pointer;
    font: inherit;
    font-size: 13px;
    line-height: 18px;
}
.cc-pos__chip.is-on {
    border-color: var(--cc-accent);
    color: var(--cc-accent);
}
.cc-pos__pago {
    display: grid;
    grid-template-columns: minmax(0, 1.15fr) minmax(96px, 0.8fr) minmax(0, 1fr) 36px;
    gap: 10px;
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
@media (max-width: 768px) {
    .cc-ventas-fechas {
        flex-basis: 100%;
        max-width: none;
    }

    .cc-pos__pareja {
        grid-template-columns: 1fr;
    }

    .cc-pos__campos,
    .cc-pos__extra {
        grid-template-columns: 1fr 1fr;
    }

    .cc-pos__pago {
        grid-template-columns: 1fr;
        gap: 10px;
    }

    .cc-pos__pago .cc-reveal,
    .cc-pos__pago .cc-log__control,
    .cc-pos__pago .cc-log__eye,
    .cc-pos__campos .cc-modal-field:nth-child(3) {
        grid-column: 1 / -1;
    }

    .cc-pos__pago .cc-log__eye {
        justify-self: start;
    }

    .cc-pos__cierre {
        align-items: stretch;
        flex-direction: column;
    }
}
</style>
