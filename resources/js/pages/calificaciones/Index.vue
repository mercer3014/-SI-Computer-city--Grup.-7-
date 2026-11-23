<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Calendar,
    Check,
    ChevronLeft,
    ChevronRight,
    EyeOff,
    RotateCcw,
    Search,
    X,
} from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import Estrellas from '@/components/tienda/Estrellas.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { adminSpring } from '@/lib/adminMotion';

type Estado = 'PENDIENTE' | 'PUBLICADA' | 'OCULTA';

type Calificacion = {
    id: number;
    producto: string;
    producto_id: number;
    cliente: string;
    pedido: string | null;
    puntuacion: number;
    comentario: string | null;
    estado: Estado;
    motivo_ocultamiento: string | null;
    fecha: string | null;
    fecha_moderacion: string | null;
    moderador: string | null;
};

type Filtros = {
    estado: string;
    producto: string;
    puntuacion: string;
    desde: string;
    hasta: string;
};

const props = defineProps<{
    calificaciones: {
        data: Calificacion[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    kpis: {
        total: number;
        pendientes: number;
        publicadas: number;
        ocultas: number;
    };
    filtros: Filtros;
    promedio: { promedio: number | null; total: number };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Calificaciones', href: '/calificaciones' }],
    },
});

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
    router.get('/calificaciones', consulta({ ...form, ...extra }, pagina), {
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
    if (pagina < 1 || pagina > props.calificaciones.meta.last_page) {
        return;
    }

    aplicar({}, pagina);
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
    Object.assign(form, {
        estado: '',
        producto: '',
        puntuacion: '',
        desde: '',
        hasta: '',
    });
    aplicar();
}

const opcionesPuntuacion = [
    { value: '', label: 'Toda puntuación' },
    { value: '5', label: '5 estrellas' },
    { value: '4', label: '4 estrellas' },
    { value: '3', label: '3 estrellas' },
    { value: '2', label: '2 estrellas' },
    { value: '1', label: '1 estrella' },
];

const kpis = computed(() => [
    {
        estado: 'PENDIENTE',
        label: 'Pendientes',
        valor: cantidad.format(props.kpis.pendientes),
    },
    {
        estado: 'PUBLICADA',
        label: 'Publicadas',
        valor: cantidad.format(props.kpis.publicadas),
    },
    {
        estado: 'OCULTA',
        label: 'Ocultas',
        valor: cantidad.format(props.kpis.ocultas),
    },
    {
        estado: '',
        label: 'Total',
        valor: cantidad.format(props.kpis.total),
    },
]);

const chips = computed(() => {
    const lista: { clave: keyof Filtros; texto: string }[] = [];

    if (form.estado) {
        lista.push({
            clave: 'estado',
            texto: `Estado: ${etiqueta(form.estado)}`,
        });
    }

    if (form.producto) {
        lista.push({ clave: 'producto', texto: `Producto: ${form.producto}` });
    }

    if (form.puntuacion) {
        lista.push({ clave: 'puntuacion', texto: `${form.puntuacion} ★` });
    }

    if (form.desde) {
        lista.push({ clave: 'desde', texto: `Desde ${form.desde}` });
    }

    if (form.hasta) {
        lista.push({ clave: 'hasta', texto: `Hasta ${form.hasta}` });
    }

    return lista;
});

const resumen = computed(() => {
    const { from, to, total } = props.calificaciones.meta;

    if (!total || from === null || to === null) {
        return 'Mostrando 0 calificaciones';
    }

    return `Mostrando ${from}–${to} de ${cantidad.format(total)} calificaciones`;
});

const promedioGeneral = computed(() =>
    props.promedio.promedio === null
        ? 'Sin calificaciones publicadas'
        : `Promedio publicado: ${props.promedio.promedio.toLocaleString('es-BO', { minimumFractionDigits: 1, maximumFractionDigits: 2 })} de 5 (${cantidad.format(props.promedio.total)})`,
);

function etiqueta(estado: string): string {
    switch (estado) {
        case 'PENDIENTE':
            return 'Pendiente';
        case 'PUBLICADA':
            return 'Publicada';
        case 'OCULTA':
            return 'Oculta';
        default:
            return estado;
    }
}

function badge(estado: Estado): string {
    switch (estado) {
        case 'PUBLICADA':
            return 'cc-log__badge--crear';
        case 'OCULTA':
            return 'cc-log__badge--neutro';
        default:
            return 'cc-log__badge--alerta';
    }
}

/* ── Moderar ── */

function publicar(calificacion: Calificacion): void {
    router.patch(
        `/calificaciones/${calificacion.id}/publicar`,
        {},
        { preserveScroll: true },
    );
}

const ocultando = ref<Calificacion | null>(null);
const formOcultar = useForm({ motivo: '' });
const errorEstado = computed(
    () => (formOcultar.errors as Record<string, string | undefined>).estado,
);

const dialogoAbierto = computed({
    get: () => ocultando.value !== null,
    set: (abierto: boolean) => {
        if (!abierto && !formOcultar.processing) {
            ocultando.value = null;
        }
    },
});

function abrirOcultar(calificacion: Calificacion): void {
    formOcultar.reset();
    formOcultar.clearErrors();
    ocultando.value = calificacion;
}

function confirmarOcultar(): void {
    const calificacion = ocultando.value;

    if (!calificacion) {
        return;
    }

    formOcultar.patch(`/calificaciones/${calificacion.id}/ocultar`, {
        preserveScroll: true,
        onSuccess: () => {
            ocultando.value = null;
        },
    });
}
</script>

<template>
    <div>
        <Head title="Calificaciones" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Clientes</p>
                    <h1>Calificaciones</h1>
                    <p>
                        Revisa lo que opinan los clientes de sus compras. Solo
                        las publicadas se ven en el catálogo y cuentan en el
                        promedio. {{ promedioGeneral }}.
                    </p>
                </motion.div>
            </motion.div>

            <motion.div
                layout
                class="cc-admin__kpis cc-admin__kpis--four"
                aria-label="Filtrar por estado"
                :transition="adminSpring"
            >
                <button
                    v-for="kpi in kpis"
                    :key="kpi.label"
                    type="button"
                    class="cc-admin__kpi"
                    :class="{ 'is-active': form.estado === kpi.estado }"
                    :aria-pressed="form.estado === kpi.estado"
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
                            v-model="form.producto"
                            type="search"
                            placeholder="Buscar por producto..."
                            maxlength="100"
                            data-test="filtro-producto"
                            @input="buscarLuego"
                        />
                        <button
                            v-if="form.producto"
                            type="button"
                            class="cc-log__clear"
                            aria-label="Limpiar búsqueda"
                            @click="quitarFiltro('producto')"
                        >
                            <X :size="14" />
                        </button>
                    </label>
                    <CcRevealSelect
                        v-model="form.puntuacion"
                        etiqueta="Toda puntuación"
                        :opciones="opcionesPuntuacion"
                        @change="aplicar()"
                    />
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

            <motion.div layout class="cc-log__panel" :transition="adminSpring">
                <div class="cc-log__table-wrap">
                    <table
                        class="cc-log__table"
                        data-test="tabla-calificaciones"
                    >
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Cliente</th>
                                <th>Calificación</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="calificaciones.data.length === 0">
                                <td class="cc-log__empty" colspan="5">
                                    <strong>Sin calificaciones</strong>
                                    No hay calificaciones que coincidan con los
                                    filtros.
                                </td>
                            </tr>
                            <tr
                                v-for="calificacion in calificaciones.data"
                                :key="calificacion.id"
                                :class="{
                                    'is-off': calificacion.estado === 'OCULTA',
                                }"
                            >
                                <td>
                                    <span class="cc-log__when">
                                        <strong>{{
                                            calificacion.producto
                                        }}</strong>
                                        <small>
                                            {{ calificacion.pedido ?? '—' }} ·
                                            {{ calificacion.fecha }}
                                        </small>
                                    </span>
                                </td>
                                <td>{{ calificacion.cliente }}</td>
                                <td>
                                    <span class="cc-log__when">
                                        <Estrellas
                                            :valor="calificacion.puntuacion"
                                            :tamano="15"
                                        />
                                        <small v-if="calificacion.comentario">{{
                                            calificacion.comentario
                                        }}</small>
                                        <small v-else>Sin comentario</small>
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        <span
                                            class="cc-log__badge"
                                            :class="badge(calificacion.estado)"
                                        >
                                            {{ etiqueta(calificacion.estado) }}
                                        </span>
                                        <small
                                            v-if="
                                                calificacion.motivo_ocultamiento
                                            "
                                            >{{
                                                calificacion.motivo_ocultamiento
                                            }}</small
                                        >
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-row-actions">
                                        <button
                                            v-if="
                                                calificacion.estado !==
                                                'PUBLICADA'
                                            "
                                            type="button"
                                            class="cc-log__eye"
                                            title="Publicar"
                                            :aria-label="`Publicar la calificación de ${calificacion.cliente} sobre ${calificacion.producto}`"
                                            data-test="publicar"
                                            @click="publicar(calificacion)"
                                        >
                                            <Check :size="15" />
                                        </button>
                                        <button
                                            v-if="
                                                calificacion.estado !== 'OCULTA'
                                            "
                                            type="button"
                                            class="cc-log__eye cc-log__eye--danger"
                                            title="Ocultar"
                                            :aria-label="`Ocultar la calificación de ${calificacion.cliente} sobre ${calificacion.producto}`"
                                            data-test="ocultar"
                                            @click="abrirOcultar(calificacion)"
                                        >
                                            <EyeOff :size="15" />
                                        </button>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cc-log-board">
                    <p
                        v-if="calificaciones.data.length === 0"
                        class="cc-log__empty"
                    >
                        <strong>Sin calificaciones</strong>
                        No hay calificaciones que coincidan con los filtros.
                    </p>
                    <article
                        v-for="calificacion in calificaciones.data"
                        :key="`pin-${calificacion.id}`"
                        class="cc-log-pin"
                        :class="{ 'is-off': calificacion.estado === 'OCULTA' }"
                    >
                        <span class="cc-log-pin__cuerpo">
                            <strong>{{ calificacion.producto }}</strong>
                            <Estrellas
                                :valor="calificacion.puntuacion"
                                :tamano="14"
                            />
                            <p v-if="calificacion.comentario">
                                {{ calificacion.comentario }}
                            </p>
                            <small>
                                {{ calificacion.cliente }} ·
                                {{ calificacion.fecha }}
                            </small>
                        </span>
                        <span class="cc-row-actions">
                            <span
                                class="cc-log__badge"
                                :class="badge(calificacion.estado)"
                            >
                                {{ etiqueta(calificacion.estado) }}
                            </span>
                            <button
                                v-if="calificacion.estado !== 'PUBLICADA'"
                                type="button"
                                class="cc-log__eye"
                                :aria-label="`Publicar la calificación de ${calificacion.cliente}`"
                                @click="publicar(calificacion)"
                            >
                                <Check :size="15" />
                            </button>
                            <button
                                v-if="calificacion.estado !== 'OCULTA'"
                                type="button"
                                class="cc-log__eye cc-log__eye--danger"
                                :aria-label="`Ocultar la calificación de ${calificacion.cliente}`"
                                @click="abrirOcultar(calificacion)"
                            >
                                <EyeOff :size="15" />
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
                            :disabled="calificaciones.meta.current_page <= 1"
                            @click="ir(calificaciones.meta.current_page - 1)"
                        >
                            <ChevronLeft :size="16" />
                        </button>
                        <span>
                            Página {{ calificaciones.meta.current_page }} de
                            {{ calificaciones.meta.last_page }}
                        </span>
                        <button
                            type="button"
                            aria-label="Página siguiente"
                            :disabled="
                                calificaciones.meta.current_page >=
                                calificaciones.meta.last_page
                            "
                            @click="ir(calificaciones.meta.current_page + 1)"
                        >
                            <ChevronRight :size="16" />
                        </button>
                    </div>
                </footer>
            </motion.div>
        </section>

        <Dialog v-model:open="dialogoAbierto">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog"
                style="max-width: 520px"
            >
                <DialogHeader>
                    <DialogTitle>Ocultar calificación</DialogTitle>
                    <DialogDescription>
                        Deja de verse en el catálogo y no cuenta en el promedio
                        de
                        {{ ocultando?.producto }}. El motivo queda en la
                        bitácora.
                    </DialogDescription>
                </DialogHeader>

                <form
                    class="cc-modal-form"
                    novalidate
                    @submit.prevent="confirmarOcultar"
                >
                    <label class="cc-modal-field">
                        <span>Motivo <b class="cc-req">*</b></span>
                        <span class="cc-log__control cc-log__control--area">
                            <textarea
                                v-model="formOcultar.motivo"
                                rows="4"
                                maxlength="500"
                                placeholder="Ej. Lenguaje ofensivo o no corresponde al producto"
                                data-test="motivo"
                            />
                        </span>
                        <p
                            v-if="formOcultar.errors.motivo"
                            class="cc-modal-error"
                            role="alert"
                            data-test="error-motivo"
                        >
                            {{ formOcultar.errors.motivo }}
                        </p>
                    </label>

                    <p v-if="errorEstado" class="cc-modal-error" role="alert">
                        {{ errorEstado }}
                    </p>

                    <div class="cc-modal-actions">
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            :disabled="formOcultar.processing"
                            @click="dialogoAbierto = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="cc-button cc-button--danger"
                            :class="{ 'is-load': formOcultar.processing }"
                            :disabled="formOcultar.processing"
                            data-test="confirmar-ocultar"
                        >
                            Ocultar calificación
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
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
