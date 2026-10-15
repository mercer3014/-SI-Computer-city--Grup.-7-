<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import {
    ChevronLeft,
    ChevronRight,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    Trash2,
    UserRound,
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

type Cliente = {
    id: number;
    tipo_documento: string | null;
    numero_documento: string | null;
    nombre: string;
    razon_social: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    observacion: string | null;
    estado: 'ACTIVO' | 'INACTIVO';
    fecha_registro: string | null;
    tiene_ventas: boolean;
    es_generico: boolean;
};

type Compra = {
    id: number;
    numero: string;
    fecha: string | null;
    estado: string | null;
    total: number;
    garantias_vigentes: number;
};

type Frecuente = {
    id: number;
    nombre: string;
    razon_social: string | null;
    numero_documento: string | null;
    telefono: string | null;
    compras: number;
    monto: number;
};

type Filtros = {
    q: string;
    estado: string;
    tab: string;
    ficha: string;
    desde: string;
    hasta: string;
};

const props = defineProps<{
    clientes: {
        data: Cliente[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    ficha: (Cliente & { compras: Compra[]; compras_total: number }) | null;
    frecuentes: Frecuente[];
    kpis: { total: number; activos: number; con_ventas: number };
    filtros: Filtros;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clientes', href: '/clientes' }],
    },
});

const cantidad = new Intl.NumberFormat('es-BO');
const moneda = new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 2,
});

const form = reactive<Filtros>({ ...props.filtros });
let busquedaTimer = 0;

watch(
    () => props.filtros,
    (filtros) => Object.assign(form, filtros),
);

const enCliente = ref(false);
const ancho = ref(1024);

function alRedimensionar(): void {
    ancho.value = window.innerWidth;
}

onMounted(() => {
    enCliente.value = true;
    alRedimensionar();
    window.addEventListener('resize', alRedimensionar);
});

onBeforeUnmount(() => {
    window.clearTimeout(busquedaTimer);
    window.removeEventListener('resize', alRedimensionar);
});

function consulta(
    origen: Filtros,
    pagina?: number,
): Record<string, string | number> {
    const query: Record<string, string | number> = {};
    (Object.keys(origen) as (keyof Filtros)[]).forEach((clave) => {
        if (origen[clave] !== '') query[clave] = origen[clave];
    });
    if (pagina && pagina > 1) query.page = pagina;
    return query;
}

function aplicar(extra: Partial<Filtros> = {}, pagina?: number): void {
    router.get('/clientes', consulta({ ...form, ...extra }, pagina), {
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
    if (pagina < 1 || pagina > props.clientes.meta.last_page) return;
    aplicar({}, pagina);
}

const pestana = computed(() => props.filtros.tab || 'clientes');

const kpis = computed(() => [
    { label: 'Clientes', valor: cantidad.format(props.kpis.total) },
    { label: 'Activos', valor: cantidad.format(props.kpis.activos) },
    { label: 'Con compras', valor: cantidad.format(props.kpis.con_ventas) },
]);

const opcionesEstado = [
    { value: '', label: 'Todos los estados' },
    { value: 'ACTIVO', label: 'Activos' },
    { value: 'INACTIVO', label: 'Inactivos' },
];

const chips = computed(() => {
    const lista: { clave: 'q' | 'estado'; texto: string }[] = [];

    if (form.q) {
        lista.push({ clave: 'q', texto: `Buscar: ${form.q}` });
    }

    if (form.estado) {
        lista.push({
            clave: 'estado',
            texto: form.estado === 'ACTIVO' ? 'Activos' : 'Inactivos',
        });
    }

    return lista;
});

function quitarFiltro(clave: 'q' | 'estado'): void {
    form[clave] = '';
    aplicar();
}

function limpiar(): void {
    window.clearTimeout(busquedaTimer);
    form.q = '';
    form.estado = '';
    aplicar({ q: '', estado: '' });
}

function iniciales(nombre: string): string {
    return nombre
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');
}

const fabAcciones = [{ id: 'crear', label: 'Nuevo cliente', icon: Plus }];

function fabElegir(id: string): void {
    if (id === 'crear') {
        proporcionarDatos();
    }
}

const opcionesTipo = [
    { value: 'CI', label: 'CI' },
    { value: 'NIT', label: 'NIT' },
];

const resumen = computed(() => {
    const { from, to, total } = props.clientes.meta;
    if (!total || from === null || to === null) return 'Mostrando 0 clientes';
    return `Mostrando ${from}–${to} de ${cantidad.format(total)} clientes`;
});

const fichaAbierta = computed({
    get: () => props.ficha !== null,
    set: (abierto: boolean) => {
        if (!abierto) aplicar({ ficha: '' });
    },
});

function abrirFicha(cliente: Cliente): void {
    aplicar({ ficha: String(cliente.id) });
}

function badgeEstado(estado: string): string {
    return estado === 'ACTIVO'
        ? 'cc-log__badge--crear'
        : 'cc-log__badge--error';
}

// ── Alta / edición ────────────────────────────────────────────────────────

const editando = ref<Cliente | null>(null);
const formAbierto = ref(false);

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

function proporcionarDatos(cliente?: Cliente): void {
    editando.value = cliente ?? null;
    alta.reset();
    alta.clearErrors();
    if (cliente) {
        alta.tipo_documento = cliente.tipo_documento === 'NIT' ? 'NIT' : 'CI';
        alta.numero_documento = cliente.numero_documento ?? '';
        alta.nombre = cliente.nombre;
        alta.razon_social = cliente.razon_social ?? '';
        alta.telefono = cliente.telefono ?? '';
        alta.email = cliente.email ?? '';
        alta.direccion = cliente.direccion ?? '';
        alta.observacion = cliente.observacion ?? '';
    }
    formAbierto.value = true;
}

function registrarCliente(): void {
    confirmar();
}

function confirmar(): void {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            formAbierto.value = false;
        },
    };

    if (editando.value) {
        alta.put(`/clientes/${editando.value.id}`, opciones);
        return;
    }

    alta.post('/clientes', opciones);
}

const duplicadoId = computed(() => {
    const crudo = (alta.errors as Record<string, string>).duplicado_id;
    const id = Number(crudo);
    return Number.isFinite(id) && id > 0 ? id : null;
});

function abrirDuplicado(): void {
    if (!duplicadoId.value) return;
    formAbierto.value = false;
    aplicar({ ficha: String(duplicadoId.value) });
}

function cambiarEstado(cliente: Cliente, estado: 'ACTIVO' | 'INACTIVO'): void {
    router.patch(
        `/clientes/${cliente.id}/estado`,
        { estado },
        {
            preserveScroll: true,
            onFinish: () => {
                objetivoBaja.value = null;
            },
        },
    );
}

const objetivoBaja = ref<Cliente | null>(null);
const bajaAbierta = computed({
    get: () => objetivoBaja.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) objetivoBaja.value = null;
    },
});

function eliminar(cliente: Cliente): void {
    router.delete(`/clientes/${cliente.id}`, {
        preserveScroll: true,
        onError: () => {
            objetivoBaja.value = cliente;
        },
        onSuccess: () => {
            objetivoBaja.value = null;
        },
    });
}
</script>

<template>
    <div>
        <Head title="Clientes" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Ventas</p>
                    <h1>Clientes</h1>
                    <p>
                        Registrá particulares y tiendas con su documento. Dejá
                        de usar “Cliente Final” cuando tengas CI o NIT, para el
                        historial y las garantías.
                    </p>
                </motion.div>
                <motion.div
                    layout
                    class="cc-log__actions"
                    :transition="adminSpring"
                >
                    <button
                        type="button"
                        class="cc-button"
                        @click="proporcionarDatos()"
                    >
                        <Plus :size="16" />
                        Nuevo cliente
                    </button>
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

            <div class="cc-cli__tabs" role="tablist">
                <button
                    type="button"
                    class="cc-cli__tab"
                    :class="{ 'is-activa': pestana === 'clientes' }"
                    @click="aplicar({ tab: 'clientes' })"
                >
                    Listado
                </button>
                <button
                    type="button"
                    class="cc-cli__tab"
                    :class="{ 'is-activa': pestana === 'frecuentes' }"
                    @click="aplicar({ tab: 'frecuentes' })"
                >
                    Clientes frecuentes
                    <span>{{ cantidad.format(frecuentes.length) }}</span>
                </button>
            </div>

            <template v-if="pestana === 'clientes'">
                <form class="cc-log__filters" @submit.prevent="aplicar()">
                    <div class="cc-log__row">
                        <label class="cc-log__control cc-log__search">
                            <Search :size="16" />
                            <input
                                v-model="form.q"
                                type="search"
                                placeholder="Buscar por nombre o CI/NIT..."
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
                            v-model="form.estado"
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
                        <table class="cc-log__table">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Documento</th>
                                    <th>Contacto</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="clientes.data.length === 0">
                                    <td class="cc-log__empty" colspan="5">
                                        <strong>Sin clientes</strong>
                                        No hay registros que coincidan con la
                                        búsqueda.
                                    </td>
                                </tr>
                                <tr
                                    v-for="cliente in clientes.data"
                                    :key="cliente.id"
                                    :class="{
                                        'is-off': cliente.estado === 'INACTIVO',
                                    }"
                                >
                                    <td>
                                        <button
                                            type="button"
                                            class="cc-cli__quien"
                                            @click="abrirFicha(cliente)"
                                        >
                                            <strong>{{
                                                cliente.nombre
                                            }}</strong>
                                            <small>{{
                                                cliente.razon_social ??
                                                cliente.fecha_registro
                                            }}</small>
                                        </button>
                                    </td>
                                    <td>
                                        <span class="cc-log__when">
                                            {{ cliente.tipo_documento ?? '—' }}
                                            <small>{{
                                                cliente.numero_documento ??
                                                'Sin documento'
                                            }}</small>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-log__when">
                                            {{
                                                cliente.telefono ??
                                                'Sin teléfono'
                                            }}
                                            <small>{{
                                                cliente.email ?? '—'
                                            }}</small>
                                        </span>
                                    </td>
                                    <td>
                                        <span
                                            class="cc-log__badge"
                                            :class="badgeEstado(cliente.estado)"
                                        >
                                            {{ cliente.estado }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-row-actions">
                                            <button
                                                type="button"
                                                class="cc-log__eye"
                                                title="Ficha e historial"
                                                @click="abrirFicha(cliente)"
                                            >
                                                <UserRound :size="15" />
                                            </button>
                                            <button
                                                type="button"
                                                class="cc-log__eye"
                                                title="Editar"
                                                @click="
                                                    proporcionarDatos(cliente)
                                                "
                                            >
                                                <Pencil :size="15" />
                                            </button>
                                            <button
                                                v-if="
                                                    cliente.estado ===
                                                        'ACTIVO' &&
                                                    !cliente.es_generico
                                                "
                                                type="button"
                                                class="cc-log__eye cc-log__eye--danger"
                                                title="Dar de baja"
                                                @click="objetivoBaja = cliente"
                                            >
                                                <Trash2 :size="15" />
                                            </button>
                                            <button
                                                v-else-if="
                                                    cliente.estado ===
                                                    'INACTIVO'
                                                "
                                                type="button"
                                                class="cc-log__eye"
                                                title="Reactivar"
                                                @click="
                                                    cambiarEstado(
                                                        cliente,
                                                        'ACTIVO',
                                                    )
                                                "
                                            >
                                                <RotateCcw :size="15" />
                                            </button>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="cc-log-board">
                        <p v-if="clientes.data.length === 0" class="cc-log__empty">
                            <strong>Sin clientes</strong>
                            No hay registros que coincidan con la búsqueda.
                        </p>
                        <article
                            v-for="cliente in clientes.data"
                            :key="`pin-${cliente.id}`"
                            class="cc-log-pin"
                            :class="{ 'is-off': cliente.estado === 'INACTIVO' }"
                        >
                            <button
                                type="button"
                                class="cc-log-pin__abrir"
                                @click="abrirFicha(cliente)"
                            >
                                <span class="cc-user-initials">
                                    {{ iniciales(cliente.nombre) }}
                                </span>
                                <span class="cc-log-pin__cuerpo">
                                    <strong>{{ cliente.nombre }}</strong>
                                    <p>
                                        {{ cliente.tipo_documento ?? 'Documento' }}
                                        {{ cliente.numero_documento ?? 'sin número' }}
                                    </p>
                                    <small>
                                        {{ cliente.telefono ?? 'Sin teléfono' }}
                                        · {{ cliente.email ?? 'sin correo' }}
                                    </small>
                                </span>
                            </button>
                            <span class="cc-row-actions">
                                <span
                                    class="cc-log__badge"
                                    :class="badgeEstado(cliente.estado)"
                                >
                                    {{ cliente.estado }}
                                </span>
                                <button
                                    type="button"
                                    class="cc-log__eye"
                                    :aria-label="`Editar a ${cliente.nombre}`"
                                    @click="proporcionarDatos(cliente)"
                                >
                                    <Pencil :size="15" />
                                </button>
                                <button
                                    v-if="
                                        cliente.estado === 'ACTIVO' &&
                                        !cliente.es_generico
                                    "
                                    type="button"
                                    class="cc-log__eye cc-log__eye--danger"
                                    :aria-label="`Dar de baja a ${cliente.nombre}`"
                                    @click="objetivoBaja = cliente"
                                >
                                    <Trash2 :size="15" />
                                </button>
                                <button
                                    v-else-if="cliente.estado === 'INACTIVO'"
                                    type="button"
                                    class="cc-log__eye"
                                    :aria-label="`Reactivar a ${cliente.nombre}`"
                                    @click="cambiarEstado(cliente, 'ACTIVO')"
                                >
                                    <RotateCcw :size="15" />
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
                                :disabled="clientes.meta.current_page <= 1"
                                @click="ir(clientes.meta.current_page - 1)"
                            >
                                <ChevronLeft :size="16" />
                            </button>
                            <span>
                                Página {{ clientes.meta.current_page }} de
                                {{ clientes.meta.last_page }}
                            </span>
                            <button
                                type="button"
                                aria-label="Página siguiente"
                                :disabled="
                                    clientes.meta.current_page >=
                                    clientes.meta.last_page
                                "
                                @click="ir(clientes.meta.current_page + 1)"
                            >
                                <ChevronRight :size="16" />
                            </button>
                        </div>
                    </footer>
                </motion.div>
            </template>

            <motion.div
                v-else
                layout
                class="cc-log__panel"
                :transition="adminSpring"
            >
                <form
                    class="cc-log__filters"
                    @submit.prevent="aplicar({ tab: 'frecuentes' })"
                >
                    <div class="cc-log__row">
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
                                Consultar período
                            </button>
                        </div>
                    </div>
                </form>
                <div class="cc-log__table-wrap">
                    <table class="cc-log__table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente</th>
                                <th>Compras</th>
                                <th>Monto</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="frecuentes.length === 0">
                                <td class="cc-log__empty" colspan="5">
                                    <strong>Sin compras en el período</strong>
                                    Todavía no hay ventas asociadas a clientes
                                    nominados.
                                </td>
                            </tr>
                            <tr
                                v-for="(item, indice) in frecuentes"
                                :key="item.id"
                            >
                                <td>{{ indice + 1 }}</td>
                                <td>
                                    <span class="cc-log__when">
                                        {{ item.nombre }}
                                        <small>{{
                                            item.numero_documento ??
                                            item.razon_social
                                        }}</small>
                                    </span>
                                </td>
                                <td>{{ cantidad.format(item.compras) }}</td>
                                <td>
                                    <b>{{ moneda.format(item.monto) }}</b>
                                </td>
                                <td>
                                    <button
                                        type="button"
                                        class="cc-button cc-button--ghost"
                                        @click="
                                            aplicar({ ficha: String(item.id) })
                                        "
                                    >
                                        Ver ficha
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="cc-log-board">
                    <p v-if="frecuentes.length === 0" class="cc-log__empty">
                        <strong>Sin compras en el período</strong>
                        Todavía no hay ventas asociadas a clientes nominados.
                    </p>
                    <article
                        v-for="(item, indice) in frecuentes"
                        :key="`freq-${item.id}`"
                        class="cc-log-pin"
                    >
                        <button
                            type="button"
                            class="cc-log-pin__abrir"
                            @click="aplicar({ ficha: String(item.id) })"
                        >
                            <span class="cc-user-initials">{{ indice + 1 }}</span>
                            <span class="cc-log-pin__cuerpo">
                                <strong>{{ item.nombre }}</strong>
                                <p>
                                    {{ cantidad.format(item.compras) }} compras ·
                                    {{ moneda.format(item.monto) }}
                                </p>
                                <small>
                                    {{
                                        item.numero_documento ??
                                        item.razon_social ??
                                        'Sin documento'
                                    }}
                                </small>
                            </span>
                        </button>
                    </article>
                </div>
            </motion.div>
        </section>

        <Teleport v-if="enCliente" to="#cc-portal">
            <CcRadialFab
                v-show="ancho <= 768 && !formAbierto && !objetivoBaja && !ficha"
                :acciones="fabAcciones"
                @elegir="fabElegir"
            />
        </Teleport>

        <Dialog v-model:open="formAbierto">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog"
                style="max-width: 560px"
            >
                <DialogHeader>
                    <DialogTitle>{{
                        editando ? 'Editar cliente' : 'Nuevo cliente'
                    }}</DialogTitle>
                    <DialogDescription>
                        El nombre y el documento son obligatorios. El documento
                        no puede repetirse.
                    </DialogDescription>
                </DialogHeader>
                <form class="cc-modal-form" @submit.prevent="registrarCliente">
                    <div class="cc-cli__pareja">
                        <div class="cc-modal-field">
                            <span>Tipo de documento <b class="cc-req">*</b></span>
                            <CcRevealSelect
                                v-model="alta.tipo_documento"
                                etiqueta="CI o NIT"
                                :opciones="opcionesTipo"
                            />
                        </div>
                        <div class="cc-modal-field">
                            <span>Número de documento <b class="cc-req">*</b></span>
                            <span class="cc-log__control">
                                <input
                                    v-model="alta.numero_documento"
                                    type="text"
                                    maxlength="50"
                                    required
                                />
                            </span>
                            <p
                                v-if="alta.errors.numero_documento"
                                class="cc-modal-error"
                            >
                                {{ alta.errors.numero_documento }}
                            </p>
                            <button
                                v-if="duplicadoId"
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="abrirDuplicado"
                            >
                                Abrir ficha del cliente existente
                            </button>
                        </div>
                    </div>

                    <label class="cc-modal-field">
                        <span>Nombre <b class="cc-req">*</b></span>
                        <span class="cc-log__control">
                            <input
                                v-model="alta.nombre"
                                type="text"
                                maxlength="200"
                                required
                            />
                        </span>
                        <p v-if="alta.errors.nombre" class="cc-modal-error">
                            {{ alta.errors.nombre }}
                        </p>
                    </label>

                    <label class="cc-modal-field">
                        <span>Razón social</span>
                        <span class="cc-log__control">
                            <input
                                v-model="alta.razon_social"
                                type="text"
                                maxlength="200"
                            />
                        </span>
                    </label>

                    <div class="cc-cli__pareja">
                        <label class="cc-modal-field">
                            <span>Teléfono</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="alta.telefono"
                                    type="tel"
                                    maxlength="15"
                                    inputmode="tel"
                                />
                            </span>
                            <p
                                v-if="alta.errors.telefono"
                                class="cc-modal-error"
                            >
                                {{ alta.errors.telefono }}
                            </p>
                        </label>
                        <label class="cc-modal-field">
                            <span>Email</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="alta.email"
                                    type="email"
                                    maxlength="150"
                                />
                            </span>
                            <p v-if="alta.errors.email" class="cc-modal-error">
                                {{ alta.errors.email }}
                            </p>
                        </label>
                    </div>

                    <label class="cc-modal-field">
                        <span>Dirección</span>
                        <span class="cc-log__control cc-log__control--area">
                            <textarea
                                v-model="alta.direccion"
                                rows="2"
                                maxlength="500"
                            />
                        </span>
                    </label>
                    <label class="cc-modal-field">
                        <span>Observación</span>
                        <span class="cc-log__control cc-log__control--area">
                            <textarea
                                v-model="alta.observacion"
                                rows="2"
                                maxlength="1000"
                            />
                        </span>
                    </label>

                    <div class="cc-modal-actions">
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            @click="formAbierto = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="cc-button"
                            :class="{ 'is-load': alta.processing }"
                            :disabled="alta.processing"
                        >
                            {{
                                editando
                                    ? 'Guardar cambios'
                                    : 'Registrar cliente'
                            }}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="fichaAbierta">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog"
                style="max-width: 640px"
            >
                <template v-if="ficha">
                    <DialogHeader>
                        <DialogTitle>{{ ficha.nombre }}</DialogTitle>
                        <DialogDescription>
                            <span
                                class="cc-log__badge"
                                :class="badgeEstado(ficha.estado)"
                                >{{ ficha.estado }}</span
                            >
                            {{ ficha.tipo_documento }}
                            {{ ficha.numero_documento }}
                        </DialogDescription>
                    </DialogHeader>
                    <dl class="cc-log-detalle__datos">
                        <div v-if="ficha.razon_social">
                            <dt>Razón social</dt>
                            <dd>{{ ficha.razon_social }}</dd>
                        </div>
                        <div>
                            <dt>Teléfono</dt>
                            <dd>{{ ficha.telefono ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd>{{ ficha.email ?? '—' }}</dd>
                        </div>
                        <div v-if="ficha.direccion">
                            <dt>Dirección</dt>
                            <dd>{{ ficha.direccion }}</dd>
                        </div>
                    </dl>
                    <h3 class="cc-cli__subtitulo">Historial de compras</h3>
                    <div class="cc-log__table-wrap">
                        <table class="cc-log__table">
                            <thead>
                                <tr>
                                    <th>N° venta</th>
                                    <th>Fecha</th>
                                    <th>Total</th>
                                    <th>Garantías</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="ficha.compras.length === 0">
                                    <td class="cc-log__empty" colspan="4">
                                        Aún no hay ventas nominadas a este
                                        cliente.
                                    </td>
                                </tr>
                                <tr
                                    v-for="compra in ficha.compras"
                                    :key="compra.id"
                                >
                                    <td>
                                        <code>{{ compra.numero }}</code>
                                        <small class="cc-cli__estado">{{
                                            compra.estado
                                        }}</small>
                                    </td>
                                    <td>{{ compra.fecha }}</td>
                                    <td>{{ moneda.format(compra.total) }}</td>
                                    <td>
                                        {{ compra.garantias_vigentes }} vigentes
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="cc-modal-actions">
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            @click="fichaAbierta = false"
                        >
                            Cerrar
                        </button>
                        <button
                            type="button"
                            class="cc-button"
                            @click="
                                proporcionarDatos(ficha);
                                fichaAbierta = false;
                            "
                        >
                            <Pencil :size="15" />
                            Editar
                        </button>
                    </div>
                </template>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="bajaAbierta">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog cc-admin-dialog--danger"
                style="max-width: 460px"
            >
                <DialogHeader>
                    <DialogTitle>Dar de baja al cliente</DialogTitle>
                    <DialogDescription>
                        <template v-if="objetivoBaja?.tiene_ventas">
                            <strong>{{ objetivoBaja.nombre }}</strong> tiene
                            ventas registradas, así que no se puede eliminar.
                            Dejalo INACTIVO para que no se use en ventas nuevas.
                        </template>
                        <template v-else>
                            ¿Eliminar a
                            <strong>{{ objetivoBaja?.nombre }}</strong
                            >? Si preferís conservarlo, dalo de baja.
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <div class="cc-modal-actions">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost"
                        @click="objetivoBaja = null"
                    >
                        Cancelar
                    </button>
                    <button
                        v-if="objetivoBaja && !objetivoBaja.tiene_ventas"
                        type="button"
                        class="cc-button cc-button--ghost"
                        @click="objetivoBaja && eliminar(objetivoBaja)"
                    >
                        Eliminar
                    </button>
                    <button
                        type="button"
                        class="cc-button cc-button--danger"
                        @click="
                            objetivoBaja &&
                            cambiarEstado(objetivoBaja, 'INACTIVO')
                        "
                    >
                        Dejar INACTIVO
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.cc-cli__tabs {
    display: flex;
    gap: 4px;
    overflow-x: auto;
    border-bottom: 1px solid var(--cc-border);
}

.cc-cli__tab {
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

.cc-cli__tab span {
    padding: 1px 7px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--cc-muted) 14%, transparent);
    font-size: 11px;
}

.cc-cli__tab.is-activa {
    border-bottom-color: var(--cc-accent);
    color: var(--cc-text);
}

.cc-cli__quien {
    display: flex;
    flex-direction: column;
    gap: 2px;
    border: 0;
    background: none;
    padding: 0;
    color: inherit;
    font: inherit;
    text-align: left;
    cursor: pointer;
}

.cc-cli__quien small,
.cc-cli__estado {
    color: var(--cc-muted);
    font-size: 11px;
}

.cc-cli__pareja {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.cc-cli__subtitulo {
    margin: 12px 0 8px;
    font-size: 13px;
    font-weight: 600;
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

@media (max-width: 520px) {
    .cc-cli__pareja {
        grid-template-columns: 1fr;
    }
}
</style>
