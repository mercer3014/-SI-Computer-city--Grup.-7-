<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { AnimatePresence, motion } from 'motion-v';
import { adminSpring } from '@/lib/adminMotion';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import {
    Box,
    ChevronLeft,
    ChevronRight,
    ImagePlus,
    Layers,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Tag,
    Trash2,
    X,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

// ── Tipos ─────────────────────────────────────────────────────────────────

type Imagen = {
    id: number;
    url_webp: string;
    es_principal: boolean;
    orden: number;
    alt: string | null;
};

type Producto = {
    id: number;
    nombre: string;
    slug: string;
    descripcion: string | null;
    estado: 'ACTIVO' | 'INACTIVO';
    categoria_id: number;
    categoria: string | null;
    marca_id: number | null;
    marca: string | null;
    variantes_count: number;
    stock_total: number;
    precio_venta: number | null;
    sku: string | null;
    imagen_url: string | null;
    imagen_alt: string | null;
    imagenes: Imagen[];
};

type Categoria = { id: number; nombre: string; slug: string };
type Marca = { id: number; nombre: string; slug: string };

type Filtros = { q: string; categoria: string; marca: string; estado: string };

// ── Props ─────────────────────────────────────────────────────────────────

const props = defineProps<{
    productos: {
        data: Producto[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    filtros: Filtros;
    opciones: {
        categorias: Categoria[];
        marcas: Marca[];
    };
    puedeGestionar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Productos', href: '/productos' }],
    },
});

// ── Acceso reactivo a permisos ────────────────────────────────────────────
const page = usePage();
const esAdmin = computed(() =>
    (page.props.auth as any)?.user?.permisos?.includes('productos.gestionar') ?? false,
);

// ── Filtros ───────────────────────────────────────────────────────────────

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

function aplicar(): void {
    router.get('/productos', consulta(form), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function buscarLuego(): void {
    window.clearTimeout(busquedaTimer);
    busquedaTimer = window.setTimeout(() => aplicar(), 320);
}

function limpiar(): void {
    window.clearTimeout(busquedaTimer);
    form.q = '';
    form.categoria = '';
    form.marca = '';
    form.estado = '';
    aplicar();
}

function quitarFiltro(clave: keyof Filtros): void {
    form[clave] = '';
    aplicar();
}

function ir(pagina: number): void {
    if (pagina < 1 || pagina > props.productos.meta.last_page) {
        return;
    }

    router.get('/productos', consulta(props.filtros, pagina), {
        preserveState: true,
        preserveScroll: true,
    });
}

// ── Formateo ──────────────────────────────────────────────────────────────

const cantidad = new Intl.NumberFormat('es-BO');
const moneda = new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 2,
});

const resumen = computed(() => {
    const { from, to, total } = props.productos.meta;

    if (!total || from === null || to === null) {
        return 'Mostrando 0 productos';
    }

    return `Mostrando ${from}–${to} de ${cantidad.format(total)} productos`;
});

const kpis = computed(() => {
    const filas = props.productos.data;
    const activos = filas.filter((p) => p.estado === 'ACTIVO').length;
    const inactivos = filas.filter((p) => p.estado === 'INACTIVO').length;
    const conStock = filas.filter((p) => p.stock_total > 0).length;

    return [
        { label: 'En esta página', valor: cantidad.format(filas.length) },
        { label: 'Activos', valor: cantidad.format(activos) },
        { label: 'Inactivos', valor: cantidad.format(inactivos) },
        { label: 'Con stock', valor: cantidad.format(conStock) },
    ];
});

// ── Opciones de filtros ───────────────────────────────────────────────────

const opcionesCategorias = computed(() => [
    { value: '', label: 'Todas las categorías' },
    ...props.opciones.categorias.map((c) => ({ value: String(c.id), label: c.nombre })),
]);

const opcionesMarcas = computed(() => [
    { value: '', label: 'Todas las marcas' },
    ...props.opciones.marcas.map((m) => ({ value: String(m.id), label: m.nombre })),
]);

const opcionesEstado = [
    { value: '', label: 'Todos los estados' },
    { value: 'ACTIVO', label: 'Activo' },
    { value: 'INACTIVO', label: 'Inactivo' },
];

// ── Chips de filtros activos ──────────────────────────────────────────────

const chips = computed(() => {
    const lista: { clave: keyof Filtros; texto: string }[] = [];

    if (form.q) lista.push({ clave: 'q', texto: `Buscar: ${form.q}` });

    if (form.categoria) {
        const cat = props.opciones.categorias.find((c) => String(c.id) === form.categoria);
        lista.push({ clave: 'categoria', texto: cat?.nombre ?? `Cat. ${form.categoria}` });
    }

    if (form.marca) {
        const mar = props.opciones.marcas.find((m) => String(m.id) === form.marca);
        lista.push({ clave: 'marca', texto: mar?.nombre ?? `Marca ${form.marca}` });
    }

    if (form.estado) {
        lista.push({ clave: 'estado', texto: form.estado === 'ACTIVO' ? 'Activos' : 'Inactivos' });
    }

    return lista;
});

// ── Responsive ────────────────────────────────────────────────────────────

const enCliente = ref(false);
const ancho = ref(1024);

onMounted(() => {
    enCliente.value = true;
    ancho.value = window.innerWidth;
    window.addEventListener('resize', () => { ancho.value = window.innerWidth; });
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', () => {});
});

// ── Modal Crear / Editar Producto ─────────────────────────────────────────

const editando = ref<Producto | null>(null);
const formProductoAbierto = ref(false);

const altaProducto = useForm({
    nombre: '',
    slug: '',
    categoria_id: '' as number | string,
    marca_id: '' as number | string | null,
    descripcion: '',
    estado: 'ACTIVO' as 'ACTIVO' | 'INACTIVO',
});

function abrirCrear(): void {
    editando.value = null;
    altaProducto.reset();
    altaProducto.estado = 'ACTIVO';
    altaProducto.clearErrors();
    formProductoAbierto.value = true;
}

function abrirEditar(producto: Producto): void {
    editando.value = producto;
    altaProducto.nombre = producto.nombre;
    altaProducto.slug = producto.slug;
    altaProducto.categoria_id = producto.categoria_id;
    altaProducto.marca_id = producto.marca_id ?? '';
    altaProducto.descripcion = producto.descripcion ?? '';
    altaProducto.estado = producto.estado;
    altaProducto.clearErrors();
    formProductoAbierto.value = true;
}

function guardarProducto(): void {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            formProductoAbierto.value = false;
        },
    };

    if (editando.value) {
        altaProducto.put(`/productos/${editando.value.id}`, opciones);
        return;
    }

    altaProducto.post('/productos', opciones);
}

// ── Cambiar estado (baja lógica) ──────────────────────────────────────────

const objetivoEstado = ref<Producto | null>(null);
const dialogEstadoAbierto = computed({
    get: () => objetivoEstado.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) objetivoEstado.value = null;
    },
});
const procesando = ref(false);

function cambiarEstado(producto: Producto, estado: 'ACTIVO' | 'INACTIVO'): void {
    procesando.value = true;
    router.patch(
        `/productos/${producto.id}/estado`,
        { estado },
        {
            preserveScroll: true,
            onFinish: () => {
                procesando.value = false;
                objetivoEstado.value = null;
            },
        },
    );
}

// ── Modal Detalle / Imágenes ──────────────────────────────────────────────

const viendoDetalle = ref<Producto | null>(null);
const detalleAbierto = computed({
    get: () => viendoDetalle.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) viendoDetalle.value = null;
    },
});

const imagenActiva = ref(0);

function verDetalle(producto: Producto): void {
    viendoDetalle.value = producto;
    imagenActiva.value = 0;
}

// ── Subir imagen ──────────────────────────────────────────────────────────

const subiendoImagen = ref(false);

function seleccionarImagen(event: Event, producto: Producto): void {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];

    if (!archivo) return;

    const formData = new FormData();
    formData.append('imagen', archivo);
    formData.append('es_principal', producto.imagenes.length === 0 ? '1' : '0');

    subiendoImagen.value = true;

    router.post(`/productos/${producto.id}/imagenes`, formData as any, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => {
            subiendoImagen.value = false;
            input.value = '';
        },
    });
}

function eliminarImagen(imagenId: number): void {
    if (!confirm('¿Eliminar esta imagen?')) return;

    router.delete(`/productos/imagenes/${imagenId}`, {
        preserveScroll: true,
    });
}

// ── Modal Categoría nueva ─────────────────────────────────────────────────

const formCategoriaAbierto = ref(false);
const altaCategoria = useForm({ nombre: '', descripcion: '' });

function guardarCategoria(): void {
    altaCategoria.post('/productos/categorias', {
        preserveScroll: true,
        onSuccess: () => {
            formCategoriaAbierto.value = false;
            altaCategoria.reset();
        },
    });
}

// ── Modal Marca nueva ─────────────────────────────────────────────────────

const formMarcaAbierto = ref(false);
const altaMarca = useForm({ nombre: '' });

function guardarMarca(): void {
    altaMarca.post('/productos/marcas', {
        preserveScroll: true,
        onSuccess: () => {
            formMarcaAbierto.value = false;
            altaMarca.reset();
        },
    });
}

// ── Helpers ───────────────────────────────────────────────────────────────

function badgeEstado(estado: string): string {
    return estado === 'ACTIVO' ? 'cc-log__badge--crear' : 'cc-log__badge--error';
}

function badgeStock(stock: number): string {
    if (stock === 0) return 'cc-log__badge--error';
    if (stock <= 5) return 'cc-log__badge--dot';
    return 'cc-log__badge--crear';
}
</script>

<template>
    <div>
        <Head title="Productos" />

        <section class="cc-admin cc-log">
            <!-- ── Cabecera ── -->
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Inventario</p>
                    <h1>Productos</h1>
                    <p>
                        Consultá el catálogo de productos, stock y precios.
                        <template v-if="esAdmin">
                            Creá, editá o dá de baja productos.
                        </template>
                    </p>
                </motion.div>

                <motion.div v-if="esAdmin" layout class="cc-log__actions" :transition="adminSpring">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost"
                        title="Nueva categoría"
                        @click="formCategoriaAbierto = true"
                    >
                        <Layers :size="16" />
                        Categoría
                    </button>
                    <button
                        type="button"
                        class="cc-button cc-button--ghost"
                        title="Nueva marca"
                        @click="formMarcaAbierto = true"
                    >
                        <Tag :size="16" />
                        Marca
                    </button>
                    <button type="button" class="cc-button" @click="abrirCrear">
                        <Plus :size="16" />
                        Nuevo producto
                    </button>
                </motion.div>
            </motion.div>

            <!-- ── KPIs ── -->
            <motion.div
                layout
                class="cc-admin__kpis"
                aria-label="Resumen de la página"
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

            <!-- ── Filtros ── -->
            <motion.div layout :transition="adminSpring">
                <form class="cc-log__filters" @submit.prevent="aplicar">
                    <div class="cc-log__row">
                        <label class="cc-log__control cc-log__search">
                            <Search :size="16" />
                            <input
                                v-model="form.q"
                                type="search"
                                placeholder="Buscar por nombre o slug..."
                                maxlength="150"
                                @input="buscarLuego"
                            />
                        </label>
                        <CcRevealSelect
                            v-model="form.categoria"
                            etiqueta="Todas las categorías"
                            :opciones="opcionesCategorias"
                            @change="aplicar"
                        />
                        <CcRevealSelect
                            v-model="form.marca"
                            etiqueta="Todas las marcas"
                            :opciones="opcionesMarcas"
                            @change="aplicar"
                        />
                        <CcRevealSelect
                            v-model="form.estado"
                            etiqueta="Todos los estados"
                            :opciones="opcionesEstado"
                            @change="aplicar"
                        />
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
            </motion.div>

            <!-- ── Tabla ── -->
            <motion.div layout class="cc-log__panel" :transition="adminSpring">
                <div class="cc-log__table-wrap">
                    <table class="cc-log__table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Categoría / Marca</th>
                                <th>SKU</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Empty state -->
                            <tr v-if="productos.data.length === 0">
                                <td class="cc-log__empty" colspan="7">
                                    <strong>Sin productos</strong>
                                    No hay productos que coincidan con los filtros.
                                    <button
                                        v-if="esAdmin && !chips.length"
                                        type="button"
                                        class="cc-button"
                                        @click="abrirCrear"
                                    >
                                        <Plus :size="16" />
                                        Crear el primero
                                    </button>
                                </td>
                            </tr>

                            <!-- Rows -->
                            <tr
                                v-for="producto in productos.data"
                                :key="producto.id"
                                :class="{ 'is-off': producto.estado === 'INACTIVO' }"
                            >
                                <!-- Producto -->
                                <td>
                                    <button
                                        type="button"
                                        class="cc-prod__quien"
                                        :aria-label="`Ver ficha de ${producto.nombre}`"
                                        @click="verDetalle(producto)"
                                    >
                                        <span class="cc-prod__thumb">
                                            <img
                                                v-if="producto.imagen_url"
                                                :src="producto.imagen_url"
                                                :alt="producto.imagen_alt ?? producto.nombre"
                                                loading="lazy"
                                            />
                                            <Box v-else :size="20" />
                                        </span>
                                        <span class="cc-prod__info">
                                            <strong>{{ producto.nombre }}</strong>
                                            <small>{{ producto.slug }}</small>
                                        </span>
                                    </button>
                                </td>

                                <!-- Categoría / Marca -->
                                <td>
                                    <span class="cc-log__when">
                                        {{ producto.categoria ?? '—' }}
                                        <small>{{ producto.marca ?? 'Sin marca' }}</small>
                                    </span>
                                </td>

                                <!-- SKU -->
                                <td>
                                    <code class="cc-prod__sku">{{ producto.sku ?? '—' }}</code>
                                </td>

                                <!-- Precio -->
                                <td>
                                    <span v-if="producto.precio_venta !== null" class="cc-prod__precio">
                                        {{ moneda.format(producto.precio_venta) }}
                                    </span>
                                    <span v-else class="cc-log__when">
                                        <small>Sin precio</small>
                                    </span>
                                </td>

                                <!-- Stock -->
                                <td>
                                    <span
                                        class="cc-log__badge"
                                        :class="badgeStock(producto.stock_total)"
                                    >
                                        {{ cantidad.format(producto.stock_total) }} uds.
                                    </span>
                                </td>

                                <!-- Estado -->
                                <td>
                                    <span
                                        class="cc-log__badge"
                                        :class="badgeEstado(producto.estado)"
                                    >
                                        {{ producto.estado }}
                                    </span>
                                </td>

                                <!-- Acciones -->
                                <td>
                                    <span class="cc-row-actions">
                                        <!-- Ver ficha (todos) -->
                                        <button
                                            type="button"
                                            class="cc-log__eye"
                                            title="Ver ficha"
                                            :aria-label="`Ver ficha de ${producto.nombre}`"
                                            @click="verDetalle(producto)"
                                        >
                                            <Box :size="15" />
                                        </button>

                                        <!-- Variantes y precios (todos; escritura solo admin) -->
                                        <Link
                                            :href="`/productos/${producto.id}/variantes`"
                                            class="cc-log__eye"
                                            title="Variantes y precios"
                                            :aria-label="`Variantes y precios de ${producto.nombre}`"
                                        >
                                            <Layers :size="15" />
                                        </Link>

                                        <!-- Editar (solo admin) -->
                                        <button
                                            v-if="esAdmin"
                                            type="button"
                                            class="cc-log__eye"
                                            title="Editar producto"
                                            :aria-label="`Editar ${producto.nombre}`"
                                            @click="abrirEditar(producto)"
                                        >
                                            <Pencil :size="15" />
                                        </button>

                                        <!-- Dar de baja (solo admin, solo si activo) -->
                                        <button
                                            v-if="esAdmin && producto.estado === 'ACTIVO'"
                                            type="button"
                                            class="cc-log__eye cc-log__eye--danger"
                                            title="Dar de baja"
                                            :aria-label="`Dar de baja a ${producto.nombre}`"
                                            @click="objetivoEstado = producto"
                                        >
                                            <Trash2 :size="15" />
                                        </button>

                                        <!-- Reactivar (solo admin, solo si inactivo) -->
                                        <button
                                            v-if="esAdmin && producto.estado === 'INACTIVO'"
                                            type="button"
                                            class="cc-log__eye"
                                            title="Reactivar"
                                            :aria-label="`Reactivar ${producto.nombre}`"
                                            @click="cambiarEstado(producto, 'ACTIVO')"
                                        >
                                            <RotateCcw :size="15" />
                                        </button>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                <footer class="cc-log__foot">
                    <p>{{ resumen }}</p>
                    <div class="cc-log__pager">
                        <button
                            type="button"
                            aria-label="Página anterior"
                            :disabled="productos.meta.current_page <= 1"
                            @click="ir(productos.meta.current_page - 1)"
                        >
                            <ChevronLeft :size="16" />
                        </button>
                        <span>
                            Página {{ productos.meta.current_page }} de
                            {{ productos.meta.last_page }}
                        </span>
                        <button
                            type="button"
                            aria-label="Página siguiente"
                            :disabled="productos.meta.current_page >= productos.meta.last_page"
                            @click="ir(productos.meta.current_page + 1)"
                        >
                            <ChevronRight :size="16" />
                        </button>
                    </div>
                </footer>
            </motion.div>
        </section>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- Modal: Crear / Editar Producto                                  -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <Dialog v-model:open="formProductoAbierto">
            <DialogContent class="cc-theme cc-admin cc-log-dialog" style="max-width: 560px;">
                <DialogHeader>
                    <DialogTitle>
                        {{ editando ? 'Editar producto' : 'Nuevo producto' }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ editando
                            ? 'Modificá los datos del producto. El slug debe ser único.'
                            : 'Completá los campos para registrar un nuevo producto.' }}
                    </DialogDescription>
                </DialogHeader>

                <form class="cc-modal-form" @submit.prevent="guardarProducto">
                    <!-- Nombre -->
                    <div class="cc-modal-field">
                        <label class="cc-log__control">
                            <span class="cc-log__label">Nombre <b class="cc-req">*</b></span>
                            <input
                                v-model="altaProducto.nombre"
                                type="text"
                                maxlength="200"
                                placeholder="Ej. Mouse Logitech G203"
                                required
                            />
                        </label>
                        <p v-if="altaProducto.errors.nombre" class="cc-modal-error">
                            {{ altaProducto.errors.nombre }}
                        </p>
                    </div>

                    <!-- Slug -->
                    <div class="cc-modal-field">
                        <label class="cc-log__control">
                            <span class="cc-log__label">Slug <small>(opcional — se genera automáticamente)</small></span>
                            <input
                                v-model="altaProducto.slug"
                                type="text"
                                maxlength="250"
                                placeholder="mouse-logitech-g203"
                            />
                        </label>
                        <p v-if="altaProducto.errors.slug" class="cc-modal-error">
                            {{ altaProducto.errors.slug }}
                        </p>
                    </div>

                    <!-- Categoría -->
                    <div class="cc-modal-field">
                        <label class="cc-log__label">Categoría <b class="cc-req">*</b></label>
                        <CcRevealSelect
                            v-model="altaProducto.categoria_id"
                            etiqueta="Seleccioná una categoría"
                            :opciones="[
                                { value: '', label: 'Seleccioná una categoría' },
                                ...opciones.categorias.map(c => ({ value: String(c.id), label: c.nombre }))
                            ]"
                        />
                        <p v-if="altaProducto.errors.categoria_id" class="cc-modal-error">
                            {{ altaProducto.errors.categoria_id }}
                        </p>
                    </div>

                    <!-- Marca -->
                    <div class="cc-modal-field">
                        <label class="cc-log__label">Marca</label>
                        <CcRevealSelect
                            v-model="altaProducto.marca_id"
                            etiqueta="Sin marca"
                            :opciones="[
                                { value: '', label: 'Sin marca' },
                                ...opciones.marcas.map(m => ({ value: String(m.id), label: m.nombre }))
                            ]"
                        />
                        <p v-if="altaProducto.errors.marca_id" class="cc-modal-error">
                            {{ altaProducto.errors.marca_id }}
                        </p>
                    </div>

                    <!-- Descripción -->
                    <div class="cc-modal-field">
                        <label class="cc-log__control cc-log__control--area">
                            <span class="cc-log__label">Descripción</span>
                            <textarea
                                v-model="altaProducto.descripcion"
                                rows="3"
                                maxlength="2000"
                                placeholder="Descripción breve del producto..."
                            />
                        </label>
                    </div>

                    <!-- Estado -->
                    <div class="cc-modal-field">
                        <label class="cc-log__label">Estado <b class="cc-req">*</b></label>
                        <div class="cc-prod__estado-toggle">
                            <button
                                type="button"
                                class="cc-button"
                                :class="altaProducto.estado === 'ACTIVO' ? '' : 'cc-button--ghost'"
                                @click="altaProducto.estado = 'ACTIVO'"
                            >
                                Activo
                            </button>
                            <button
                                type="button"
                                class="cc-button"
                                :class="altaProducto.estado === 'INACTIVO' ? 'cc-button--danger' : 'cc-button--ghost'"
                                @click="altaProducto.estado = 'INACTIVO'"
                            >
                                Inactivo
                            </button>
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="cc-modal-actions">
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            @click="formProductoAbierto = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="cc-button"
                            :class="{ 'is-load': altaProducto.processing }"
                        >
                            <span v-if="altaProducto.processing" class="cc-admin__spin" />
                            {{ editando ? 'Guardar cambios' : 'Crear producto' }}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- Modal: Confirmar cambio de estado                               -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <Dialog v-model:open="dialogEstadoAbierto">
            <DialogContent class="cc-theme cc-admin cc-log-dialog cc-admin-dialog--danger" style="max-width: 420px;">
                <DialogHeader>
                    <DialogTitle>Dar de baja al producto</DialogTitle>
                    <DialogDescription>
                        ¿Estás seguro de que querés dar de baja
                        <strong>{{ objetivoEstado?.nombre }}</strong>?
                        El producto quedará INACTIVO y no aparecerá en el catálogo ni en ventas.
                    </DialogDescription>
                </DialogHeader>
                <div class="cc-modal-actions">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost"
                        @click="objetivoEstado = null"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="cc-button cc-button--danger"
                        :disabled="procesando"
                        @click="objetivoEstado && cambiarEstado(objetivoEstado, 'INACTIVO')"
                    >
                        <span v-if="procesando" class="cc-admin__spin" />
                        Dar de baja
                    </button>
                </div>
            </DialogContent>
        </Dialog>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- Modal: Ficha del Producto (detalle + imágenes)                  -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <Dialog v-model:open="detalleAbierto">
            <DialogContent class="cc-theme cc-admin cc-log-dialog cc-prod-ficha" style="max-width: 640px;">
                <template v-if="viendoDetalle">
                    <DialogHeader>
                        <DialogTitle>{{ viendoDetalle.nombre }}</DialogTitle>
                        <DialogDescription>
                            <span
                                class="cc-log__badge"
                                :class="badgeEstado(viendoDetalle.estado)"
                            >{{ viendoDetalle.estado }}</span>
                            {{ viendoDetalle.categoria }}
                            <template v-if="viendoDetalle.marca"> · {{ viendoDetalle.marca }}</template>
                        </DialogDescription>
                    </DialogHeader>

                    <!-- Galería de imágenes -->
                    <div class="cc-prod-ficha__galeria">
                        <div class="cc-prod-ficha__img-wrap">
                            <img
                                v-if="viendoDetalle.imagenes[imagenActiva]"
                                :src="viendoDetalle.imagenes[imagenActiva].url_webp"
                                :alt="viendoDetalle.imagenes[imagenActiva].alt ?? viendoDetalle.nombre"
                                class="cc-prod-ficha__img"
                            />
                            <div v-else class="cc-prod-ficha__img-empty">
                                <Box :size="48" />
                                <p>Sin imágenes</p>
                            </div>
                        </div>

                        <!-- Miniaturas -->
                        <div v-if="viendoDetalle.imagenes.length > 1" class="cc-prod-ficha__thumbs">
                            <button
                                v-for="(img, idx) in viendoDetalle.imagenes"
                                :key="img.id"
                                type="button"
                                class="cc-prod-ficha__thumb"
                                :class="{ 'is-activa': imagenActiva === idx }"
                                @click="imagenActiva = idx"
                            >
                                <img :src="img.url_webp" :alt="img.alt ?? ''" />
                            </button>
                        </div>

                        <!-- Subir imagen (solo admin) -->
                        <div v-if="esAdmin" class="cc-prod-ficha__upload">
                            <label class="cc-button cc-button--ghost">
                                <ImagePlus :size="16" />
                                Subir imagen
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    class="sr-only"
                                    :disabled="subiendoImagen"
                                    @change="(e: Event) => seleccionarImagen(e, viendoDetalle!)"
                                />
                            </label>

                            <!-- Eliminar imagen actual (solo admin) -->
                            <button
                                v-if="viendoDetalle.imagenes[imagenActiva]"
                                type="button"
                                class="cc-button cc-button--ghost"
                                title="Eliminar imagen actual"
                                @click="eliminarImagen(viendoDetalle.imagenes[imagenActiva].id)"
                            >
                                <Trash2 :size="16" />
                                Eliminar
                            </button>
                        </div>
                    </div>

                    <!-- Datos del producto -->
                    <dl class="cc-log-detalle__datos">
                        <div>
                            <dt>SKU</dt>
                            <dd><code>{{ viendoDetalle.sku ?? '—' }}</code></dd>
                        </div>
                        <div>
                            <dt>Precio de venta</dt>
                            <dd>
                                <strong>
                                    {{ viendoDetalle.precio_venta !== null
                                        ? moneda.format(viendoDetalle.precio_venta)
                                        : '—' }}
                                </strong>
                            </dd>
                        </div>
                        <div>
                            <dt>Stock disponible</dt>
                            <dd>
                                <span
                                    class="cc-log__badge"
                                    :class="badgeStock(viendoDetalle.stock_total)"
                                >
                                    {{ cantidad.format(viendoDetalle.stock_total) }} unidades
                                </span>
                            </dd>
                        </div>
                        <div>
                            <dt>Variantes</dt>
                            <dd>{{ viendoDetalle.variantes_count }}</dd>
                        </div>
                        <div v-if="viendoDetalle.descripcion">
                            <dt>Descripción</dt>
                            <dd>{{ viendoDetalle.descripcion }}</dd>
                        </div>
                        <div>
                            <dt>Slug</dt>
                            <dd><code>{{ viendoDetalle.slug }}</code></dd>
                        </div>
                    </dl>

                    <!-- Acciones del modal detalle (solo admin) -->
                    <div v-if="esAdmin" class="cc-modal-actions">
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            @click="detalleAbierto = false"
                        >
                            Cerrar
                        </button>
                        <button
                            type="button"
                            class="cc-button"
                            @click="abrirEditar(viendoDetalle); detalleAbierto = false"
                        >
                            <Pencil :size="15" />
                            Editar producto
                        </button>
                    </div>
                </template>
            </DialogContent>
        </Dialog>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- Modal: Nueva Categoría                                          -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <Dialog v-if="esAdmin" v-model:open="formCategoriaAbierto">
            <DialogContent class="cc-theme cc-admin cc-log-dialog" style="max-width: 420px;">
                <DialogHeader>
                    <DialogTitle>Nueva categoría</DialogTitle>
                    <DialogDescription>
                        Creá una nueva categoría de productos para el catálogo.
                    </DialogDescription>
                </DialogHeader>
                <form class="cc-modal-form" @submit.prevent="guardarCategoria">
                    <div class="cc-modal-field">
                        <label class="cc-log__control">
                            <span class="cc-log__label">Nombre <b class="cc-req">*</b></span>
                            <input
                                v-model="altaCategoria.nombre"
                                type="text"
                                maxlength="150"
                                placeholder="Ej. Teclados"
                                required
                            />
                        </label>
                        <p v-if="altaCategoria.errors.nombre" class="cc-modal-error">
                            {{ altaCategoria.errors.nombre }}
                        </p>
                    </div>
                    <div class="cc-modal-field">
                        <label class="cc-log__control cc-log__control--area">
                            <span class="cc-log__label">Descripción</span>
                            <textarea v-model="altaCategoria.descripcion" rows="2" />
                        </label>
                    </div>
                    <div class="cc-modal-actions">
                        <button type="button" class="cc-button cc-button--ghost" @click="formCategoriaAbierto = false">
                            Cancelar
                        </button>
                        <button type="submit" class="cc-button" :class="{ 'is-load': altaCategoria.processing }">
                            <span v-if="altaCategoria.processing" class="cc-admin__spin" />
                            Crear categoría
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- Modal: Nueva Marca                                              -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <Dialog v-if="esAdmin" v-model:open="formMarcaAbierto">
            <DialogContent class="cc-theme cc-admin cc-log-dialog" style="max-width: 380px;">
                <DialogHeader>
                    <DialogTitle>Nueva marca</DialogTitle>
                    <DialogDescription>
                        Registrá una nueva marca de productos.
                    </DialogDescription>
                </DialogHeader>
                <form class="cc-modal-form" @submit.prevent="guardarMarca">
                    <div class="cc-modal-field">
                        <label class="cc-log__control">
                            <span class="cc-log__label">Nombre <b class="cc-req">*</b></span>
                            <input
                                v-model="altaMarca.nombre"
                                type="text"
                                maxlength="120"
                                placeholder="Ej. Logitech"
                                required
                            />
                        </label>
                        <p v-if="altaMarca.errors.nombre" class="cc-modal-error">
                            {{ altaMarca.errors.nombre }}
                        </p>
                    </div>
                    <div class="cc-modal-actions">
                        <button type="button" class="cc-button cc-button--ghost" @click="formMarcaAbierto = false">
                            Cancelar
                        </button>
                        <button type="submit" class="cc-button" :class="{ 'is-load': altaMarca.processing }">
                            <span v-if="altaMarca.processing" class="cc-admin__spin" />
                            Crear marca
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
/* ── Imagen de producto en tabla ── */
.cc-prod__quien {
    display: flex;
    gap: 10px;
    align-items: center;
    border: 0;
    background: none;
    padding: 0;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: pointer;
}

.cc-prod__thumb {
    display: grid;
    width: 40px;
    height: 40px;
    flex: none;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--cc-border);
    border-radius: 6px;
    background: var(--cc-card);
    color: var(--cc-muted);
}

.cc-prod__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cc-prod__info {
    display: flex;
    min-width: 0;
    flex-direction: column;
    gap: 1px;
}

.cc-prod__info strong {
    font-size: 14px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
}

.cc-prod__info small {
    color: var(--cc-muted);
    font-size: 11px;
}

.cc-prod__sku {
    font-family: 'IBM Plex Mono', monospace;
    font-size: 11px;
    color: var(--cc-muted);
    background: color-mix(in srgb, var(--cc-muted) 10%, transparent);
    padding: 2px 6px;
    border-radius: 4px;
}

.cc-prod__precio {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    color: var(--cc-accent);
}

.cc-prod__estado-toggle {
    display: flex;
    gap: 8px;
}

/* ── Ficha del producto (modal detalle) ── */
.cc-prod-ficha {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.cc-prod-ficha__galeria {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.cc-prod-ficha__img-wrap {
    width: 100%;
    aspect-ratio: 4/3;
    overflow: hidden;
    border: 1px solid var(--cc-border);
    border-radius: 8px;
    background: var(--cc-card);
    display: grid;
    place-items: center;
}

.cc-prod-ficha__img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.cc-prod-ficha__img-empty {
    display: flex;
    flex-direction: column;
    gap: 8px;
    align-items: center;
    color: var(--cc-muted);
    font-size: 13px;
}

.cc-prod-ficha__img-empty p {
    margin: 0;
}

.cc-prod-ficha__thumbs {
    display: flex;
    gap: 6px;
    overflow-x: auto;
}

.cc-prod-ficha__thumb {
    width: 56px;
    height: 56px;
    flex: none;
    overflow: hidden;
    border: 2px solid transparent;
    border-radius: 6px;
    background: var(--cc-card);
    cursor: pointer;
    transition: border-color 140ms ease;
}

.cc-prod-ficha__thumb.is-activa {
    border-color: var(--cc-accent);
}

.cc-prod-ficha__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cc-prod-ficha__upload {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

/* ── Formulario modal ── */
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

.cc-log__label {
    display: block;
    margin-bottom: 4px;
    color: var(--cc-muted);
    font-size: 12px;
    font-weight: 500;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.cc-req {
    color: var(--cc-error);
}

/* sr-only para input de archivo */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
</style>
