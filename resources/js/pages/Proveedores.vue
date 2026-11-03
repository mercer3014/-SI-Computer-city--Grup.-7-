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

type Proveedor = {
    id: number;
    nit: string | null;
    razon_social: string;
    nombre_comercial: string | null;
    contacto: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    observacion: string | null;
    estado: string;
    fecha_registro: string | null;
};

type Filtros = {
    q: string;
    estado: string;
};

const props = defineProps<{
    proveedores: {
        data: Proveedor[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            from: number | null;
            to: number | null;
        };
    };
    kpis: { activos: number; inactivos: number; total: number };
    filtros: Filtros;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Proveedores', href: '/proveedores' }],
    },
});

const cantidad = new Intl.NumberFormat('es-BO');
const form = reactive<Filtros>({ ...props.filtros });
let busquedaTimer = 0;
const enCliente = ref(false);
const ancho = ref(1024);

watch(
    () => props.filtros,
    (filtros) => Object.assign(form, filtros),
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

function consulta(origen: Filtros, pagina?: number): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    if (origen.q !== '') query.q = origen.q;
    query.estado = origen.estado;
    if (pagina && pagina > 1) query.page = pagina;

    return query;
}

function aplicar(extra: Partial<Filtros> = {}, pagina?: number): void {
    router.get('/proveedores', consulta({ ...form, ...extra }, pagina), {
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
    if (pagina < 1 || pagina > props.proveedores.meta.last_page) return;
    aplicar({}, pagina);
}

function quitarFiltro(clave: keyof Filtros): void {
    form[clave] = clave === 'estado' ? 'ACTIVO' : '';
    aplicar();
}

function limpiar(): void {
    window.clearTimeout(busquedaTimer);
    form.q = '';
    form.estado = 'ACTIVO';
    aplicar({ q: '', estado: 'ACTIVO' });
}

const kpis = computed(() => [
    { label: 'Activos', valor: cantidad.format(props.kpis.activos) },
    { label: 'Inactivos', valor: cantidad.format(props.kpis.inactivos) },
    { label: 'Total', valor: cantidad.format(props.kpis.total) },
]);

const opcionesEstado = [
    { value: '', label: 'Todos los estados' },
    { value: 'ACTIVO', label: 'Activos' },
    { value: 'INACTIVO', label: 'Inactivos' },
];

const chips = computed(() => {
    const lista: { clave: keyof Filtros; texto: string }[] = [];

    if (form.q) lista.push({ clave: 'q', texto: `Búsqueda: ${form.q}` });
    if (form.estado === 'INACTIVO') lista.push({ clave: 'estado', texto: 'Inactivos' });
    if (form.estado === '') lista.push({ clave: 'estado', texto: 'Todos los estados' });

    return lista;
});

const resumen = computed(() => {
    const { from, to, total } = props.proveedores.meta;
    if (!total || from === null || to === null) return 'Mostrando 0 proveedores';

    return `Mostrando ${from}–${to} de ${cantidad.format(total)} proveedores`;
});

function badgeEstado(estado: string): string {
    return estado === 'ACTIVO' ? 'cc-log__badge--crear' : 'cc-log__badge--error';
}

const editando = ref<Proveedor | null>(null);
const formAbierto = ref(false);
const alta = useForm({
    nit: '',
    razon_social: '',
    nombre_comercial: '',
    contacto: '',
    telefono: '',
    email: '',
    direccion: '',
    observacion: '',
});

function proporcionarDatos(proveedor?: Proveedor): void {
    editando.value = proveedor ?? null;
    alta.reset();
    alta.clearErrors();

    if (proveedor) {
        alta.nit = proveedor.nit ?? '';
        alta.razon_social = proveedor.razon_social;
        alta.nombre_comercial = proveedor.nombre_comercial ?? '';
        alta.contacto = proveedor.contacto ?? '';
        alta.telefono = proveedor.telefono ?? '';
        alta.email = proveedor.email ?? '';
        alta.direccion = proveedor.direccion ?? '';
        alta.observacion = proveedor.observacion ?? '';
    }

    formAbierto.value = true;
}

function confirmar(): void {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            formAbierto.value = false;
        },
    };

    if (editando.value) {
        alta.put(`/proveedores/${editando.value.id}`, opciones);

        return;
    }

    alta.post('/proveedores', opciones);
}

const objetivoBaja = ref<Proveedor | null>(null);
const bajaAbierta = computed({
    get: () => objetivoBaja.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) objetivoBaja.value = null;
    },
});

function cambiarEstado(proveedor: Proveedor, estado: 'ACTIVO' | 'INACTIVO'): void {
    router.patch(
        `/proveedores/${proveedor.id}/estado`,
        { estado },
        {
            preserveScroll: true,
            onFinish: () => {
                objetivoBaja.value = null;
            },
        },
    );
}

const fabAcciones = [{ id: 'crear', label: 'Nuevo proveedor', icon: Plus }];

function fabElegir(id: string): void {
    if (id === 'crear') proporcionarDatos();
}
</script>

<template>
    <div>
        <Head title="Proveedores" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Compras</p>
                    <h1>Proveedores</h1>
                    <p>
                        Registrá el NIT y la razón social de quien abastece la
                        tienda. Un NIT no puede repetirse.
                    </p>
                </motion.div>
                <motion.div layout class="cc-log__actions" :transition="adminSpring">
                    <button type="button" class="cc-button" @click="proporcionarDatos()">
                        <Plus :size="16" />
                        Nuevo proveedor
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

            <form class="cc-log__filters" @submit.prevent="aplicar()">
                <div class="cc-log__row">
                    <label class="cc-log__control cc-log__search">
                        <Search :size="16" />
                        <input
                            v-model="form.q"
                            type="search"
                            placeholder="Buscar por razón social, NIT o contacto..."
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
                        etiqueta="Activos"
                        :opciones="opcionesEstado"
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
                                <th>Proveedor</th>
                                <th>NIT</th>
                                <th>Contacto</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="proveedores.data.length === 0">
                                <td class="cc-log__empty" colspan="5">
                                    <strong>Sin proveedores</strong>
                                    No hay registros que coincidan con la búsqueda.
                                </td>
                            </tr>
                            <tr
                                v-for="proveedor in proveedores.data"
                                :key="proveedor.id"
                                :class="{ 'is-off': proveedor.estado === 'INACTIVO' }"
                            >
                                <td>
                                    <span class="cc-log__when">
                                        <strong>{{ proveedor.razon_social }}</strong>
                                        <small>{{
                                            proveedor.nombre_comercial ??
                                            proveedor.fecha_registro ??
                                            'Sin nombre comercial'
                                        }}</small>
                                    </span>
                                </td>
                                <td>{{ proveedor.nit ?? '—' }}</td>
                                <td>
                                    <span class="cc-log__when">
                                        {{ proveedor.contacto ?? proveedor.telefono ?? 'Sin contacto' }}
                                        <small>{{ proveedor.email ?? proveedor.telefono ?? '—' }}</small>
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-log__badge" :class="badgeEstado(proveedor.estado)">
                                        {{ proveedor.estado }}
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-row-actions">
                                        <button
                                            type="button"
                                            class="cc-log__eye"
                                            title="Editar"
                                            :aria-label="`Editar ${proveedor.razon_social}`"
                                            @click="proporcionarDatos(proveedor)"
                                        >
                                            <Pencil :size="15" />
                                        </button>
                                        <button
                                            v-if="proveedor.estado === 'ACTIVO'"
                                            type="button"
                                            class="cc-log__eye cc-log__eye--danger"
                                            title="Dar de baja"
                                            :aria-label="`Dar de baja a ${proveedor.razon_social}`"
                                            @click="objetivoBaja = proveedor"
                                        >
                                            <Trash2 :size="15" />
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            class="cc-log__eye"
                                            title="Reactivar"
                                            :aria-label="`Reactivar ${proveedor.razon_social}`"
                                            @click="cambiarEstado(proveedor, 'ACTIVO')"
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
                    <p v-if="proveedores.data.length === 0" class="cc-log__empty">
                        <strong>Sin proveedores</strong>
                        No hay registros que coincidan con la búsqueda.
                    </p>
                    <article
                        v-for="proveedor in proveedores.data"
                        :key="`pin-${proveedor.id}`"
                        class="cc-log-pin"
                        :class="{ 'is-off': proveedor.estado === 'INACTIVO' }"
                    >
                        <span class="cc-log-pin__cuerpo">
                            <strong>{{ proveedor.razon_social }}</strong>
                            <p>{{ proveedor.nit ?? 'Sin NIT' }}</p>
                            <small>
                                {{ proveedor.contacto ?? proveedor.telefono ?? 'Sin contacto' }}
                                ·
                                {{ proveedor.email ?? 'sin email' }}
                            </small>
                        </span>
                        <span class="cc-row-actions">
                            <span class="cc-log__badge" :class="badgeEstado(proveedor.estado)">
                                {{ proveedor.estado }}
                            </span>
                            <button
                                type="button"
                                class="cc-log__eye"
                                :aria-label="`Editar ${proveedor.razon_social}`"
                                @click="proporcionarDatos(proveedor)"
                            >
                                <Pencil :size="15" />
                            </button>
                            <button
                                v-if="proveedor.estado === 'ACTIVO'"
                                type="button"
                                class="cc-log__eye cc-log__eye--danger"
                                :aria-label="`Dar de baja a ${proveedor.razon_social}`"
                                @click="objetivoBaja = proveedor"
                            >
                                <Trash2 :size="15" />
                            </button>
                            <button
                                v-else
                                type="button"
                                class="cc-log__eye"
                                :aria-label="`Reactivar ${proveedor.razon_social}`"
                                @click="cambiarEstado(proveedor, 'ACTIVO')"
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
                            :disabled="proveedores.meta.current_page <= 1"
                            @click="ir(proveedores.meta.current_page - 1)"
                        >
                            <ChevronLeft :size="16" />
                        </button>
                        <span>
                            Página {{ proveedores.meta.current_page }} de
                            {{ proveedores.meta.last_page }}
                        </span>
                        <button
                            type="button"
                            aria-label="Página siguiente"
                            :disabled="proveedores.meta.current_page >= proveedores.meta.last_page"
                            @click="ir(proveedores.meta.current_page + 1)"
                        >
                            <ChevronRight :size="16" />
                        </button>
                    </div>
                </footer>
            </motion.div>
        </section>

        <Teleport v-if="enCliente" to="#cc-portal">
            <CcRadialFab
                v-show="ancho <= 768 && !formAbierto && !objetivoBaja"
                :acciones="fabAcciones"
                @elegir="fabElegir"
            />
        </Teleport>

        <Dialog v-model:open="formAbierto">
            <DialogContent class="cc-theme cc-admin cc-log-dialog" style="max-width: 560px">
                <DialogHeader>
                    <DialogTitle>{{
                        editando ? 'Editar proveedor' : 'Nuevo proveedor'
                    }}</DialogTitle>
                    <DialogDescription>
                        El NIT y la razón social son obligatorios. El NIT no
                        puede repetirse.
                    </DialogDescription>
                </DialogHeader>
                <form class="cc-modal-form" @submit.prevent="confirmar">
                    <label class="cc-modal-field">
                        <span>NIT <b class="cc-req">*</b></span>
                        <span class="cc-log__control">
                            <input v-model="alta.nit" type="text" maxlength="50" required />
                        </span>
                        <p v-if="alta.errors.nit" class="cc-modal-error">{{ alta.errors.nit }}</p>
                    </label>
                    <label class="cc-modal-field">
                        <span>Razón social <b class="cc-req">*</b></span>
                        <span class="cc-log__control">
                            <input v-model="alta.razon_social" type="text" maxlength="200" required />
                        </span>
                        <p v-if="alta.errors.razon_social" class="cc-modal-error">
                            {{ alta.errors.razon_social }}
                        </p>
                    </label>
                    <label class="cc-modal-field">
                        <span>Nombre comercial</span>
                        <span class="cc-log__control">
                            <input v-model="alta.nombre_comercial" type="text" maxlength="200" />
                        </span>
                    </label>
                    <label class="cc-modal-field">
                        <span>Contacto</span>
                        <span class="cc-log__control">
                            <input v-model="alta.contacto" type="text" maxlength="150" />
                        </span>
                    </label>
                    <label class="cc-modal-field">
                        <span>Teléfono</span>
                        <span class="cc-log__control">
                            <input v-model="alta.telefono" type="tel" maxlength="15" inputmode="tel" />
                        </span>
                        <p v-if="alta.errors.telefono" class="cc-modal-error">
                            {{ alta.errors.telefono }}
                        </p>
                    </label>
                    <label class="cc-modal-field">
                        <span>Email</span>
                        <span class="cc-log__control">
                            <input v-model="alta.email" type="email" maxlength="150" />
                        </span>
                        <p v-if="alta.errors.email" class="cc-modal-error">{{ alta.errors.email }}</p>
                    </label>
                    <label class="cc-modal-field">
                        <span>Dirección</span>
                        <span class="cc-log__control">
                            <input v-model="alta.direccion" type="text" maxlength="1000" />
                        </span>
                    </label>
                    <label class="cc-modal-field">
                        <span>Observación</span>
                        <span class="cc-log__control cc-log__control--area">
                            <textarea v-model="alta.observacion" rows="3" maxlength="1000" />
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
                            <span v-if="alta.processing" class="cc-admin__spin" />
                            {{ editando ? 'Guardar cambios' : 'Registrar proveedor' }}
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="bajaAbierta">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog cc-admin-dialog--danger"
                style="max-width: 460px"
            >
                <DialogHeader>
                    <DialogTitle>Dar de baja al proveedor</DialogTitle>
                    <DialogDescription>
                        <strong>{{ objetivoBaja?.razon_social }}</strong> queda
                        INACTIVO y no se ofrece en compras nuevas. El historial
                        se conserva.
                    </DialogDescription>
                </DialogHeader>
                <div class="cc-modal-actions">
                    <button type="button" class="cc-button cc-button--ghost" @click="objetivoBaja = null">
                        Cancelar
                    </button>
                    <button
                        type="button"
                        class="cc-button cc-button--danger"
                        @click="objetivoBaja && cambiarEstado(objetivoBaja, 'INACTIVO')"
                    >
                        Dejar INACTIVO
                    </button>
                </div>
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
