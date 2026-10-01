<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { AnimatePresence, motion } from 'motion-v';
import { adminSpring } from '@/lib/adminMotion';
import {
    cambiosDe,
    etiquetaAccion,
    fraseEvento,
    iniciales,
    tonoAccion,
    type EventoBitacora,
} from '@/lib/bitacoraLectura';
import CcBitacoraDetalle from '@/components/admin/CcBitacoraDetalle.vue';
import CcRadialFab from '@/components/admin/CcRadialFab.vue';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import {
    Calendar,
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    RefreshCw,
    RotateCcw,
    Search,
    X,
} from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import bitacora from '@/routes/bitacora';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Filtros = {
    q: string;
    usuario: string;
    modulo: string;
    accion: string;
    desde: string;
    hasta: string;
};

const props = defineProps<{
    eventos: {
        data: EventoBitacora[];
        meta: {
            current_page: number;
            last_page: number;
            per_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    filtros: Filtros;
    opciones: {
        usuarios: { id: number; nombre: string }[];
        modulos: string[];
        acciones: string[];
    };
    error: string | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Bitácora',
                href: bitacora.index(),
            },
        ],
    },
});

const form = reactive<Filtros>({ ...props.filtros });
const cargando = ref(false);
const detalle = ref<EventoBitacora | null>(null);
let busquedaTimer = 0;
const esqueletos = Array.from({ length: 6 }, (_, i) => i);
const enCliente = ref(false);
const ancho = ref(1024);

const detalleAbierto = computed({
    get: () => detalle.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) {
            detalle.value = null;
        }
    },
});

watch(
    () => props.filtros,
    (filtros) => {
        Object.assign(form, filtros);
    },
);

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

const cantidad = new Intl.NumberFormat('es-BO');

const resumen = computed(() => {
    const { from, to, total } = props.eventos.meta;

    if (!total || from === null || to === null) {
        return 'Mostrando 0 eventos';
    }

    return `Mostrando ${cantidad.format(from)}–${cantidad.format(to)} de ${cantidad.format(total)} eventos`;
});

const mensajeVacio = computed(() => {
    if (props.error) {
        return props.error;
    }

    const hayFiltros = Object.values(props.filtros).some((valor) => valor !== '');

    return hayFiltros
        ? 'No hay eventos que coincidan con los filtros. Probá con otro criterio o limpiá la búsqueda.'
        : 'No hay eventos registrados en la bitácora.';
});

const chips = computed(() => {
    const lista: { clave: keyof Filtros; texto: string }[] = [];

    if (form.q) {
        lista.push({ clave: 'q', texto: `Buscar: ${form.q}` });
    }

    if (form.usuario) {
        const quien = props.opciones.usuarios.find(
            (u) => String(u.id) === form.usuario,
        );
        lista.push({ clave: 'usuario', texto: quien?.nombre ?? 'Usuario' });
    }

    if (form.modulo) {
        lista.push({ clave: 'modulo', texto: form.modulo });
    }

    if (form.accion) {
        lista.push({ clave: 'accion', texto: etiquetaAccion(form.accion) });
    }

    if (form.desde) {
        lista.push({ clave: 'desde', texto: `Desde ${form.desde}` });
    }

    if (form.hasta) {
        lista.push({ clave: 'hasta', texto: `Hasta ${form.hasta}` });
    }

    return lista;
});

const opcionesUsuario = computed(() => [
    { value: '', label: 'Todos los usuarios' },
    ...props.opciones.usuarios.map((usuario) => ({
        value: String(usuario.id),
        label: usuario.nombre,
    })),
]);

const opcionesModulo = computed(() => [
    { value: '', label: 'Todos los módulos' },
    ...props.opciones.modulos.map((modulo) => ({
        value: modulo,
        label: modulo,
    })),
]);

const opcionesAccion = computed(() => [
    { value: '', label: 'Todas las acciones' },
    ...props.opciones.acciones.map((accion) => ({
        value: accion,
        label: etiquetaAccion(accion),
    })),
]);

const fabAcciones = [
    { id: 'exportar', label: 'Exportar CSV', icon: Download },
    { id: 'actualizar', label: 'Actualizar', icon: RefreshCw },
];

function parametros(pagina?: number): Record<string, string | number> {
    const consulta: Record<string, string | number> = {};

    (Object.keys(form) as (keyof Filtros)[]).forEach((clave) => {
        if (form[clave] !== '') {
            consulta[clave] = form[clave];
        }
    });

    if (pagina && pagina > 1) {
        consulta.page = pagina;
    }

    return consulta;
}

function aplicar(): void {
    cargando.value = true;
    router.get(bitacora.index().url, parametros(), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onFinish: () => {
            cargando.value = false;
        },
    });
}

function buscarLuego(): void {
    window.clearTimeout(busquedaTimer);
    busquedaTimer = window.setTimeout(() => aplicar(), 320);
}

function quitarFiltro(clave: keyof Filtros): void {
    form[clave] = '';
    aplicar();
}

function limpiar(): void {
    window.clearTimeout(busquedaTimer);
    form.q = '';
    form.usuario = '';
    form.modulo = '';
    form.accion = '';
    form.desde = '';
    form.hasta = '';
    aplicar();
}

function limpiarBusqueda(): void {
    form.q = '';

    if (props.filtros.q !== '') {
        aplicar();
    }
}

function actualizar(): void {
    cargando.value = true;
    router.reload({
        onFinish: () => {
            cargando.value = false;
        },
    });
}

function ir(pagina: number): void {
    if (pagina < 1 || pagina > props.eventos.meta.last_page) {
        return;
    }

    const consulta: Record<string, string | number> = {};

    (Object.keys(props.filtros) as (keyof Filtros)[]).forEach((clave) => {
        if (props.filtros[clave] !== '') {
            consulta[clave] = props.filtros[clave];
        }
    });

    if (pagina > 1) {
        consulta.page = pagina;
    }

    cargando.value = true;
    router.get(bitacora.index().url, consulta, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            cargando.value = false;
        },
    });
}

function exportarCsv(): void {
    const consulta = new URLSearchParams();

    (Object.keys(props.filtros) as (keyof Filtros)[]).forEach((clave) => {
        if (props.filtros[clave] !== '') {
            consulta.set(clave, props.filtros[clave]);
        }
    });

    const query: Record<string, string> = {};
    consulta.forEach((valor, clave) => {
        query[clave] = valor;
    });

    window.location.href = bitacora.exportar.url({ query });
}

function fabElegir(id: string): void {
    if (id === 'exportar') {
        exportarCsv();
        return;
    }

    actualizar();
}

function resumenCambio(evento: EventoBitacora): string {
    const cambios = cambiosDe(evento.valor_anterior, evento.valor_nuevo);

    if (cambios.length === 0) {
        return evento.descripcion;
    }

    return cambios
        .slice(0, 2)
        .map((cambio) =>
            cambio.ahora
                ? `${cambio.etiqueta}: ${cambio.ahora}`
                : `${cambio.etiqueta}: ${cambio.antes ?? '—'}`,
        )
        .join(' · ');
}
</script>

<template>
    <div>
    <Head title="Consultar bitácora" />

    <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
        <motion.div layout class="cc-log__head" :transition="adminSpring">
            <motion.div layout :transition="adminSpring">
                <p class="cc-log__kicker">Sistema</p>
                <h1>Consultar bitácora</h1>
                <p>
                    Quién hizo qué, en qué módulo y qué valor quedó.
                </p>
            </motion.div>
            <motion.div layout class="cc-log__actions" :transition="adminSpring">
                <button
                    type="button"
                    class="cc-button cc-button--ghost"
                    @click="exportarCsv"
                >
                    <Download />
                    Exportar CSV
                </button>
                <button
                    type="button"
                    class="cc-button"
                    :class="{ 'is-load': cargando }"
                    :disabled="cargando"
                    @click="actualizar"
                >
                    <RefreshCw :class="{ 'animate-spin': cargando }" />
                    Actualizar
                </button>
            </motion.div>
        </motion.div>

        <motion.div
            layout
            class="cc-admin__kpis"
            aria-label="Resumen"
            :transition="adminSpring"
        >
            <motion.div layout class="cc-admin__kpi" :transition="adminSpring">
                <b>{{ cantidad.format(eventos.meta.total) }}</b>
                <span>Eventos</span>
            </motion.div>
            <motion.div layout class="cc-admin__kpi" :transition="adminSpring">
                <b>{{ eventos.meta.current_page }}</b>
                <span>Página actual</span>
            </motion.div>
            <motion.div layout class="cc-admin__kpi" :transition="adminSpring">
                <b>{{ chips.length }}</b>
                <span>Filtros activos</span>
            </motion.div>
        </motion.div>

        <motion.div layout :transition="adminSpring">
        <form
            class="cc-log__filters"
            @submit.prevent="aplicar"
        >
            <div class="cc-log__row">
                <label class="cc-log__control cc-log__search">
                    <Search :size="16" />
                    <input
                        v-model="form.q"
                        type="search"
                        placeholder="Buscar quién o qué cambió..."
                        maxlength="200"
                        @input="buscarLuego"
                    />
                    <button
                        v-if="form.q"
                        type="button"
                        class="cc-log__clear"
                        aria-label="Limpiar búsqueda"
                        @click="limpiarBusqueda"
                    >
                        <X :size="14" />
                    </button>
                </label>

                <CcRevealSelect
                    v-model="form.usuario"
                    etiqueta="Todos los usuarios"
                    :opciones="opcionesUsuario"
                    @change="aplicar"
                />
                <CcRevealSelect
                    v-model="form.modulo"
                    etiqueta="Todos los módulos"
                    :opciones="opcionesModulo"
                    @change="aplicar"
                />
                <CcRevealSelect
                    v-model="form.accion"
                    etiqueta="Todas las acciones"
                    :opciones="opcionesAccion"
                    @change="aplicar"
                />
            </div>

            <div class="cc-log__row">
                <div class="cc-log__dates-par">
                    <label class="cc-modal-field">
                        <span>Desde</span>
                        <span class="cc-log__control">
                            <Calendar :size="16" />
                            <input
                                v-model="form.desde"
                                type="date"
                                @change="aplicar"
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
                                @change="aplicar"
                            />
                        </span>
                    </label>
                </div>

                <div class="cc-log__row-actions">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost"
                        @click="limpiar"
                    >
                        <RotateCcw />
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
            :class="{ 'is-loading': cargando }"
            :aria-busy="cargando"
            :transition="adminSpring"
        >
            <div class="cc-log-board">
                <p v-if="eventos.data.length === 0" class="cc-log__empty">
                    <strong>
                        {{
                            error
                                ? 'No se pudo leer la bitácora'
                                : 'Sin registros'
                        }}
                    </strong>
                    {{ mensajeVacio }}
                </p>
                <button
                    v-for="evento in eventos.data"
                    :key="`pin-${evento.id}`"
                    type="button"
                    class="cc-log-pin"
                    @click="detalle = evento"
                >
                    <span class="cc-user-initials">
                        {{ iniciales(evento.usuario) }}
                    </span>
                    <span class="cc-log-pin__cuerpo">
                        <strong>{{ evento.usuario }}</strong>
                        <p>{{ fraseEvento(evento) }}</p>
                        <span
                            v-if="resumenCambio(evento)"
                            class="cc-log-pin__valor"
                        >
                            {{ resumenCambio(evento) }}
                        </span>
                        <small>
                            {{ evento.fecha }} · {{ evento.hora }}
                            · {{ evento.modulo }}
                        </small>
                    </span>
                </button>
            </div>

            <div class="cc-log__table-wrap">
                <table class="cc-log__table">
                    <thead>
                        <tr>
                            <th>Cuándo</th>
                            <th>Quién</th>
                            <th>Qué pasó</th>
                            <th>Módulo</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="cargando && eventos.data.length === 0">
                            <tr
                                v-for="fila in esqueletos"
                                :key="`sk-${fila}`"
                                class="cc-admin__skel"
                            >
                                <td><span class="cc-admin__bone cc-admin__bone--sm" /></td>
                                <td><span class="cc-admin__bone" /></td>
                                <td><span class="cc-admin__bone" /></td>
                                <td><span class="cc-admin__bone cc-admin__bone--sm" /></td>
                                <td><span class="cc-admin__bone cc-admin__bone--sm" /></td>
                            </tr>
                        </template>
                        <tr v-else-if="eventos.data.length === 0">
                            <td class="cc-log__empty" colspan="5">
                                <strong>
                                    {{
                                        error
                                            ? 'No se pudo leer la bitácora'
                                            : 'Sin registros'
                                    }}
                                </strong>
                                {{ mensajeVacio }}
                            </td>
                        </tr>
                        <tr v-for="evento in eventos.data" :key="evento.id">
                            <td>
                                <span class="cc-log__when">
                                    {{ evento.fecha }}
                                    <small>{{ evento.hora }}</small>
                                </span>
                            </td>
                            <td>
                                <span class="cc-log__who">
                                    <span class="cc-user-initials">
                                        {{ iniciales(evento.usuario) }}
                                    </span>
                                    {{ evento.usuario }}
                                </span>
                            </td>
                            <td>
                                <span class="cc-log__hecho">
                                    {{ fraseEvento(evento) }}
                                    <small v-if="resumenCambio(evento)">
                                        {{ resumenCambio(evento) }}
                                    </small>
                                </span>
                            </td>
                            <td>
                                <span
                                    class="cc-log__badge"
                                    :class="`cc-log__badge--${tonoAccion(evento.accion)}`"
                                    :title="evento.accion"
                                >
                                    {{ evento.modulo }}
                                </span>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="cc-log__eye"
                                    :aria-label="`Ver qué cambió: ${fraseEvento(evento)}`"
                                    @click="detalle = evento"
                                >
                                    <Eye :size="16" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <footer class="cc-log__foot">
                <p>{{ resumen }}</p>
                <div class="cc-log__pager">
                    <button
                        type="button"
                        aria-label="Página anterior"
                        :disabled="eventos.meta.current_page <= 1"
                        @click="ir(eventos.meta.current_page - 1)"
                    >
                        <ChevronLeft :size="16" />
                    </button>
                    <span>
                        Página {{ eventos.meta.current_page }} de
                        {{ eventos.meta.last_page }}
                    </span>
                    <button
                        type="button"
                        aria-label="Página siguiente"
                        :disabled="
                            eventos.meta.current_page >= eventos.meta.last_page
                        "
                        @click="ir(eventos.meta.current_page + 1)"
                    >
                        <ChevronRight :size="16" />
                    </button>
                </div>
            </footer>
        </motion.div>
    </section>

    <Teleport v-if="enCliente" to="#cc-portal">
        <CcRadialFab
            v-show="ancho <= 768 && !detalle"
            :acciones="fabAcciones"
            @elegir="fabElegir"
        />
    </Teleport>

    <Teleport v-if="enCliente" to="#cc-portal">
        <AnimatePresence>
            <motion.div
                v-if="detalle && ancho <= 768"
                key="ficha-log"
                class="cc-user-sheet"
            >
                <motion.button
                    type="button"
                    class="cc-user-sheet__velo"
                    aria-label="Cerrar"
                    :initial="{ opacity: 0 }"
                    :animate="{ opacity: 1 }"
                    :exit="{ opacity: 0 }"
                    @click="detalle = null"
                />
                <motion.section
                    class="cc-user-sheet__panel cc-theme cc-admin"
                    :initial="{ y: '100%' }"
                    :animate="{ y: 0 }"
                    :exit="{ y: '100%' }"
                    :transition="adminSpring"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="cc-log-ficha"
                >
                    <div class="cc-user-sheet__asa" />
                    <CcBitacoraDetalle :evento="detalle" />
                    <div class="cc-modal-actions">
                        <button
                            type="button"
                            class="cc-button"
                            @click="detalle = null"
                        >
                            Cerrar
                        </button>
                    </div>
                </motion.section>
            </motion.div>
        </AnimatePresence>
    </Teleport>

    <Dialog v-if="enCliente && ancho > 768" v-model:open="detalleAbierto">
        <DialogContent
            v-if="detalle"
            class="cc-theme cc-admin cc-log-dialog"
        >
            <DialogHeader>
                <DialogTitle>Qué pasó</DialogTitle>
                <DialogDescription class="sr-only">
                    {{ fraseEvento(detalle) }}
                </DialogDescription>
            </DialogHeader>
            <CcBitacoraDetalle :evento="detalle" />
        </DialogContent>
    </Dialog>
    </div>
</template>
