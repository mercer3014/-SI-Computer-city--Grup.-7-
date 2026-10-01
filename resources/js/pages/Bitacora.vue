<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Calendar,
    ChevronLeft,
    ChevronRight,
    Download,
    Eye,
    RefreshCw,
    RotateCcw,
    Search,
    SlidersHorizontal,
    User,
    X,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import bitacora from '@/routes/bitacora';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Evento = {
    id: number;
    fecha: string;
    hora: string;
    usuario: string;
    modulo: string;
    accion: string;
    direccion_ip: string | null;
    descripcion: string;
    tipo_entidad: string | null;
    entidad_id: number | null;
    valor_anterior: unknown;
    valor_nuevo: unknown;
};

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
        data: Evento[];
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
const detalle = ref<Evento | null>(null);
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

function limpiar(): void {
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
        preserveScroll: true,
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

function etiqueta(accion: string): string {
    const mapa: Record<string, string> = {
        LOGIN: 'INGRESAR',
        LOGOUT: 'SALIR',
        LOGIN_FALLIDO: 'FALLIDO',
        BLOQUEAR_USUARIO: 'BLOQUEAR',
        DESBLOQUEAR_USUARIO: 'HABILITAR',
        CAMBIAR_ESTADO_USUARIO: 'ESTADO',
        CAMBIAR_ROL: 'ROL',
        CAMBIAR_CLAVE: 'CLAVE',
        ACTUALIZAR_PRECIO: 'PRECIO',
        ELIMINAR_USUARIO: 'ELIMINAR',
    };

    return mapa[accion] ?? (accion.split('_')[0] || accion);
}

function tono(accion: string): string {
    if (
        accion.includes('FALLID') ||
        accion.startsWith('ELIMIN') ||
        accion.startsWith('BLOQUEAR')
    ) {
        return 'error';
    }

    if (
        accion.startsWith('DESHABIL') ||
        accion.startsWith('ANUL') ||
        accion === 'LOGOUT'
    ) {
        return 'alerta';
    }

    if (accion.startsWith('CREAR') || accion.startsWith('APROB') || accion === 'LOGIN') {
        return 'crear';
    }

    if (accion.startsWith('REGIST')) {
        return 'registrar';
    }

    if (accion.includes('AJUST')) {
        return 'ajustar';
    }

    if (accion.startsWith('ACTUAL') || accion.startsWith('CAMBI')) {
        return 'actualizar';
    }

    return 'neutro';
}

function textoJson(valor: unknown): string {
    if (valor === null || valor === undefined || valor === '') {
        return '—';
    }

    if (typeof valor === 'string') {
        return valor;
    }

    return JSON.stringify(valor, null, 2);
}

function entidad(evento: Evento): string {
    if (!evento.tipo_entidad) {
        return '—';
    }

    return evento.entidad_id
        ? `${evento.tipo_entidad} #${evento.entidad_id}`
        : evento.tipo_entidad;
}
</script>

<template>
    <Head title="Consultar bitácora" />

    <section class="cc-log">
        <header class="cc-log__head">
            <div>
                
                <h1>Consultar bitácora</h1>
                <p>
                    Revisá las acciones realizadas en el sistema y rastreá cada
                    evento administrativo.
                </p>
            </div>
            <div class="cc-log__actions">
                <button
                    type="button"
                    class="cc-button cc-button--ghost cc-log__btn"
                    @click="exportarCsv"
                >
                    <Download />
                    Exportar CSV
                </button>
                <button
                    type="button"
                    class="cc-button cc-log__btn cc-log__btn--solid"
                    :disabled="cargando"
                    @click="actualizar"
                >
                    <RefreshCw :class="{ 'animate-spin': cargando }" />
                    Actualizar
                </button>
            </div>
        </header>

        <form class="cc-log__filters" @submit.prevent="aplicar">
            <div class="cc-log__row">
                <label class="cc-log__control cc-log__search">
                    <Search :size="16" />
                    <input
                        v-model="form.q"
                        type="text"
                        placeholder="Buscar en la descripción..."
                        maxlength="200"
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

                <label class="cc-log__control cc-log__select">
                    <span class="sr-only">Usuario</span>
                    <select v-model="form.usuario">
                        <option value="">Todos los usuarios</option>
                        <option
                            v-for="usuario in opciones.usuarios"
                            :key="usuario.id"
                            :value="String(usuario.id)"
                        >
                            {{ usuario.nombre }}
                        </option>
                    </select>
                </label>

                <label class="cc-log__control cc-log__select">
                    <span class="sr-only">Módulo</span>
                    <select v-model="form.modulo">
                        <option value="">Todos los módulos</option>
                        <option
                            v-for="modulo in opciones.modulos"
                            :key="modulo"
                            :value="modulo"
                        >
                            {{ modulo }}
                        </option>
                    </select>
                </label>

                <label class="cc-log__control cc-log__select">
                    <span class="sr-only">Acción</span>
                    <select v-model="form.accion">
                        <option value="">Todas las acciones</option>
                        <option
                            v-for="accion in opciones.acciones"
                            :key="accion"
                            :value="accion"
                        >
                            {{ accion }}
                        </option>
                    </select>
                </label>
            </div>

            <div class="cc-log__row">
                <label class="cc-log__control cc-log__dates">
                    <Calendar :size="16" />
                    <span>Desde</span>
                    <input v-model="form.desde" type="date" />
                    <span>Hasta</span>
                    <input v-model="form.hasta" type="date" />
                </label>

                <div class="cc-log__row-actions">
                    <button type="submit" class="cc-button cc-log__btn">
                        <SlidersHorizontal />
                        Aplicar filtros
                    </button>
                    <button
                        type="button"
                        class="cc-button cc-button--ghost cc-log__btn"
                        @click="limpiar"
                    >
                        <RotateCcw />
                        Limpiar
                    </button>
                </div>
            </div>
        </form>

        <div class="cc-log__panel">
            <div class="cc-log__table-wrap">
                <table class="cc-log__table">
                    <thead>
                        <tr>
                            <th>Fecha / hora</th>
                            <th>Usuario</th>
                            <th>Módulo</th>
                            <th>Acción</th>
                            <th>Dirección IP</th>
                            <th>Descripción</th>
                            <th>Detalle</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="eventos.data.length === 0">
                            <td class="cc-log__empty" colspan="7">
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
                                    <span class="cc-log__avatar" aria-hidden="true">
                                        <User :size="12" />
                                    </span>
                                    {{ evento.usuario }}
                                </span>
                            </td>
                            <td>{{ evento.modulo }}</td>
                            <td>
                                <span
                                    class="cc-log__badge"
                                    :class="`cc-log__badge--${tono(evento.accion)}`"
                                    :title="evento.accion"
                                >
                                    {{ etiqueta(evento.accion) }}
                                </span>
                            </td>
                            <td class="cc-log__ip">
                                {{ evento.direccion_ip ?? '—' }}
                            </td>
                            <td>{{ evento.descripcion }}</td>
                            <td>
                                <button
                                    type="button"
                                    class="cc-log__eye"
                                    :aria-label="`Ver detalle de ${evento.descripcion}`"
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
        </div>
    </section>

    <Dialog v-model:open="detalleAbierto">
        <DialogContent
            v-if="detalle"
            class="cc-theme cc-log-dialog"
        >
            <DialogHeader>
                <DialogTitle>Detalle del evento</DialogTitle>
                <DialogDescription>
                    {{ detalle.fecha }} {{ detalle.hora }} · {{ detalle.modulo }}
                </DialogDescription>
            </DialogHeader>

            <dl class="cc-log-dialog__grid">
                <dt>Usuario</dt>
                <dd>{{ detalle.usuario }}</dd>
                <dt>Acción</dt>
                <dd>{{ detalle.accion }}</dd>
                <dt>Dirección IP</dt>
                <dd>{{ detalle.direccion_ip ?? '—' }}</dd>
                <dt>Descripción</dt>
                <dd>{{ detalle.descripcion }}</dd>
                <dt>Entidad</dt>
                <dd>{{ entidad(detalle) }}</dd>
                <dt>Valor anterior</dt>
                <dd>
                    <pre>{{ textoJson(detalle.valor_anterior) }}</pre>
                </dd>
                <dt>Valor nuevo</dt>
                <dd>
                    <pre>{{ textoJson(detalle.valor_nuevo) }}</pre>
                </dd>
            </dl>
        </DialogContent>
    </Dialog>
</template>
