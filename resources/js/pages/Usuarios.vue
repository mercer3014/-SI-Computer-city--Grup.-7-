<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AnimatePresence, motion } from 'motion-v';
import { adminSpring } from '@/lib/adminMotion';
import CcRadialFab from '@/components/admin/CcRadialFab.vue';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import CcUsuarioCampos from '@/components/admin/CcUsuarioCampos.vue';
import {
    ChevronLeft,
    ChevronRight,
    Download,
    Mail,
    Pencil,
    Plus,
    RotateCcw,
    Search,
    UserCheck,
    UserX,
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

type Usuario = {
    id: number;
    nombre: string;
    email: string;
    rol_id: number;
    rol: string | null;
    personal_id: number | null;
    cargo: string | null;
    ci: string | null;
    telefono: string | null;
    estado: 'ACTIVO' | 'INACTIVO';
    primer_login: boolean;
    es_actual: boolean;
    protegido: boolean;
};

type Filtros = { q: string; rol: string; estado: string; personal: string };

const props = defineProps<{
    usuarios: {
        data: Usuario[];
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
        roles: { id: number; nombre: string }[];
        personal: { id: number; nombre_completo: string }[];
        personal_disponible: {
            id: number;
            nombre_completo: string;
            cargo: string | null;
            ci: string | null;
        }[];
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Usuarios', href: '/usuarios' }],
    },
});

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
    router.get('/usuarios', consulta(form), {
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
    form.rol = '';
    form.estado = '';
    form.personal = '';
    aplicar();
}

function quitarFiltro(clave: keyof Filtros): void {
    form[clave] = '';
    aplicar();
}

function ir(pagina: number): void {
    if (pagina < 1 || pagina > props.usuarios.meta.last_page) {
        return;
    }

    router.get('/usuarios', consulta(props.filtros, pagina), {
        preserveState: true,
        preserveScroll: true,
    });
}

function exportar(): void {
    const query = new URLSearchParams(
        Object.entries(consulta(props.filtros)).map(([k, v]) => [k, String(v)]),
    ).toString();

    window.location.href = query ? `/usuarios/exportar?${query}` : '/usuarios/exportar';
}

const cantidad = new Intl.NumberFormat('es-BO');

const resumen = computed(() => {
    const { from, to, total } = props.usuarios.meta;

    if (!total || from === null || to === null) {
        return 'Mostrando 0 usuarios';
    }

    return `Mostrando ${from}–${to} de ${cantidad.format(total)} usuarios`;
});

const kpis = computed(() => {
    const filas = props.usuarios.data;
    const activos = filas.filter((u) => u.estado === 'ACTIVO').length;
    const pendientes = filas.filter((u) => u.primer_login).length;

    return [
        { label: 'En esta página', valor: cantidad.format(filas.length) },
        { label: 'Activos', valor: cantidad.format(activos) },
        { label: 'Pendiente 1er ingreso', valor: cantidad.format(pendientes) },
    ];
});

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
    window.removeEventListener('resize', alRedimensionar);
});

const opcionesRol = computed(() => [
    { value: '', label: 'Todos los roles' },
    ...props.opciones.roles.map((rol) => ({
        value: String(rol.id),
        label: rol.nombre,
    })),
]);

const opcionesEstado = [
    { value: '', label: 'Todos los estados' },
    { value: 'ACTIVO', label: 'Activo' },
    { value: 'INACTIVO', label: 'Inactivo' },
];

const opcionesPersonal = computed(() => [
    { value: '', label: 'Todo el personal' },
    ...props.opciones.personal.map((persona) => ({
        value: String(persona.id),
        label: persona.nombre_completo,
    })),
]);

const fabAcciones = [
    { id: 'crear', label: 'Crear usuario', icon: Plus },
    { id: 'exportar', label: 'Exportar', icon: Download },
];

function fabElegir(id: string): void {
    if (id === 'crear') {
        abrirCrear();
        return;
    }

    exportar();
}

const chips = computed(() => {
    const lista: { clave: keyof Filtros; texto: string }[] = [];

    if (form.q) {
        lista.push({ clave: 'q', texto: `Buscar: ${form.q}` });
    }

    if (form.rol) {
        const rol = props.opciones.roles.find((r) => String(r.id) === form.rol);
        lista.push({ clave: 'rol', texto: rol?.nombre ?? `Rol ${form.rol}` });
    }

    if (form.estado) {
        lista.push({ clave: 'estado', texto: form.estado === 'ACTIVO' ? 'Activos' : 'Inactivos' });
    }

    if (form.personal) {
        const persona = props.opciones.personal.find((p) => String(p.id) === form.personal);
        lista.push({ clave: 'personal', texto: persona?.nombre_completo ?? 'Personal' });
    }

    return lista;
});

function iniciales(nombre: string): string {
    return nombre
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((parte) => parte[0]?.toUpperCase() ?? '')
        .join('');
}

// ---- Crear / editar ----
const editando = ref<Usuario | null>(null);
const formAbierto = ref(false);

const alta = useForm({
    personal_modo: 'nuevo' as 'nuevo' | 'existente',
    personal_id: '' as number | string,
    nombre_completo: '',
    ci: '',
    telefono: '',
    cargo: '',
    email: '',
    rol_id: '' as number | string,
});

function abrirCrear(): void {
    editando.value = null;
    alta.reset();
    alta.clearErrors();
    formAbierto.value = true;
}

function abrirEditar(usuario: Usuario): void {
    editando.value = usuario;
    alta.personal_modo = 'existente';
    alta.nombre_completo = usuario.nombre;
    alta.telefono = usuario.telefono ?? '';
    alta.cargo = usuario.cargo ?? '';
    alta.email = usuario.email;
    alta.rol_id = usuario.rol_id;
    alta.clearErrors();
    formAbierto.value = true;
}

function guardar(): void {
    const opciones = {
        preserveScroll: true,
        onSuccess: () => {
            formAbierto.value = false;
        },
    };

    if (editando.value) {
        alta
            .transform((datos) => ({
                email: datos.email,
                rol_id: datos.rol_id,
                nombre_completo: datos.nombre_completo,
                telefono: datos.telefono,
                cargo: datos.cargo,
            }))
            .put(`/usuarios/${editando.value.id}`, opciones);

        return;
    }

    alta
        .transform((datos) =>
            datos.personal_modo === 'existente'
                ? {
                      personal_modo: datos.personal_modo,
                      personal_id: datos.personal_id,
                      email: datos.email,
                      rol_id: datos.rol_id,
                  }
                : datos,
        )
        .post('/usuarios', opciones);
}

// ---- Deshabilitar / habilitar ----
const objetivo = ref<Usuario | null>(null);
const objetivoAbierto = computed({
    get: () => objetivo.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) {
            objetivo.value = null;
        }
    },
});
const procesando = ref(false);
const reenviando = ref<number | null>(null);

async function copiarCorreo(email: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(email);
        toast.success('Correo copiado');
    } catch {
        toast.error('No se pudo copiar el correo');
    }
}

function cambiarEstado(usuario: Usuario, estado: 'ACTIVO' | 'INACTIVO'): void {
    procesando.value = true;
    router.patch(
        `/usuarios/${usuario.id}/estado`,
        { estado },
        {
            preserveScroll: true,
            onFinish: () => {
                procesando.value = false;
                objetivo.value = null;
            },
        },
    );
}

function reenviar(usuario: Usuario): void {
    reenviando.value = usuario.id;
    router.post(
        `/usuarios/${usuario.id}/reenviar-clave`,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                reenviando.value = null;
            },
        },
    );
}
</script>

<template>
    <div>
    <Head title="Gestionar usuarios" />

    <section class="cc-admin cc-log cc-admin--pins">
        <motion.div layout class="cc-log__head" :transition="adminSpring">
            <motion.div layout :transition="adminSpring">
                <p class="cc-log__kicker">Personal</p>
                <h1>Gestionar usuarios</h1>
                <p>
                    Registrá, modificá, habilitá o deshabilitá las cuentas del
                    sistema.
                </p>
            </motion.div>
            <motion.div layout class="cc-log__actions" :transition="adminSpring">
                <button
                    type="button"
                    class="cc-button cc-button--ghost"
                    @click="exportar"
                >
                    <Download />
                    Exportar
                </button>
                <button type="button" class="cc-button" @click="abrirCrear">
                    <Plus />
                    Crear usuario
                </button>
            </motion.div>
        </motion.div>

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
                        placeholder="Buscar por nombre o correo..."
                        maxlength="150"
                        @input="buscarLuego"
                    />
                </label>
                <CcRevealSelect
                    v-model="form.rol"
                    etiqueta="Todos los roles"
                    :opciones="opcionesRol"
                    @change="aplicar"
                />
                <CcRevealSelect
                    v-model="form.estado"
                    etiqueta="Todos los estados"
                    :opciones="opcionesEstado"
                    @change="aplicar"
                />
                <CcRevealSelect
                    v-model="form.personal"
                    etiqueta="Todo el personal"
                    :opciones="opcionesPersonal"
                    @change="aplicar"
                />
                <div class="cc-log__row-actions">
                    <button type="submit" class="cc-button">
                        <Search />
                        Buscar
                    </button>
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

        <motion.div layout class="cc-log__panel" :transition="adminSpring">
            <div class="cc-log__table-wrap">
                <table class="cc-log__table">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Correo electrónico</th>
                            <th>Rol</th>
                            <th>Personal asociado</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="usuarios.data.length === 0">
                            <td class="cc-log__empty" colspan="6">
                                <strong>Sin usuarios</strong>
                                No hay usuarios que coincidan con los filtros.
                                <button
                                    v-if="!chips.length"
                                    type="button"
                                    class="cc-button"
                                    @click="abrirCrear"
                                >
                                    <Plus />
                                    Crear el primero
                                </button>
                            </td>
                        </tr>
                        <tr
                            v-for="usuario in usuarios.data"
                            :key="usuario.id"
                            :class="{ 'is-off': usuario.estado === 'INACTIVO' }"
                        >
                            <td>
                                <span class="cc-log__who">
                                    <span class="cc-user-initials">
                                        {{ iniciales(usuario.nombre) }}
                                    </span>
                                    {{ usuario.nombre }}
                                    <span
                                        v-if="usuario.es_actual"
                                        class="cc-log__badge cc-log__badge--neutro"
                                    >
                                        TÚ
                                    </span>
                                </span>
                            </td>
                            <td>
                                <button
                                    type="button"
                                    class="cc-admin__mail"
                                    :title="`Copiar ${usuario.email}`"
                                    @click="copiarCorreo(usuario.email)"
                                >
                                    {{ usuario.email }}
                                </button>
                            </td>
                            <td>{{ usuario.rol ?? '—' }}</td>
                            <td>
                                <span class="cc-log__when">
                                    {{ usuario.nombre }}
                                    <small>{{ usuario.cargo ?? 'Sin cargo' }}</small>
                                </span>
                            </td>
                            <td>
                                <span
                                    class="cc-log__badge"
                                    :class="
                                        usuario.estado === 'ACTIVO'
                                            ? 'cc-log__badge--crear'
                                            : 'cc-log__badge--error'
                                    "
                                >
                                    {{ usuario.estado }}
                                </span>
                                <span
                                    v-if="usuario.primer_login"
                                    class="cc-log__badge cc-log__badge--dot"
                                >
                                    1er ingreso
                                </span>
                            </td>
                            <td>
                                <span class="cc-row-actions">
                                    <button
                                        type="button"
                                        class="cc-log__eye"
                                        title="Editar"
                                        :aria-label="`Editar a ${usuario.nombre}`"
                                        @click="abrirEditar(usuario)"
                                    >
                                        <Pencil :size="15" />
                                    </button>
                                    <button
                                        v-if="usuario.estado === 'ACTIVO'"
                                        type="button"
                                        class="cc-log__eye"
                                        title="Reenviar clave temporal"
                                        :disabled="reenviando === usuario.id"
                                        :aria-label="`Reenviar clave a ${usuario.nombre}`"
                                        @click="reenviar(usuario)"
                                    >
                                        <Mail :size="15" />
                                    </button>
                                    <button
                                        v-if="usuario.estado === 'ACTIVO'"
                                        type="button"
                                        class="cc-log__eye cc-log__eye--danger"
                                        :title="
                                            usuario.es_actual || usuario.protegido
                                                ? 'No se puede deshabilitar'
                                                : 'Deshabilitar'
                                        "
                                        :disabled="usuario.es_actual || usuario.protegido"
                                        :aria-label="`Deshabilitar a ${usuario.nombre}`"
                                        @click="objetivo = usuario"
                                    >
                                        <UserX :size="15" />
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="cc-log__eye"
                                        title="Habilitar"
                                        :aria-label="`Habilitar a ${usuario.nombre}`"
                                        @click="cambiarEstado(usuario, 'ACTIVO')"
                                    >
                                        <UserCheck :size="15" />
                                    </button>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="cc-users-board">
                <p v-if="usuarios.data.length === 0" class="cc-log__empty">
                    <strong>Sin usuarios</strong>
                    No hay usuarios que coincidan con los filtros.
                </p>
                <button
                    v-for="usuario in usuarios.data"
                    :key="usuario.id"
                    type="button"
                    class="cc-users-pin"
                    :class="{ 'is-off': usuario.estado === 'INACTIVO' }"
                    :aria-label="`Editar a ${usuario.nombre}`"
                    @click.stop="abrirEditar(usuario)"
                >
                    <span class="cc-users-pin__who">
                        <span class="cc-user-initials">
                            {{ iniciales(usuario.nombre) }}
                        </span>
                        <strong>{{ usuario.nombre }}</strong>
                        <small>{{ usuario.email }}</small>
                    </span>
                    <span class="cc-users-pin__tags">
                        <span
                            class="cc-log__badge"
                            :class="
                                usuario.estado === 'ACTIVO'
                                    ? 'cc-log__badge--crear'
                                    : 'cc-log__badge--error'
                            "
                        >
                            {{ usuario.estado }}
                        </span>
                        <span
                            v-if="usuario.rol"
                            class="cc-log__badge cc-log__badge--neutro"
                        >
                            {{ usuario.rol }}
                        </span>
                        <span
                            v-if="usuario.primer_login"
                            class="cc-log__badge cc-log__badge--dot"
                        >
                            1er ingreso
                        </span>
                    </span>
                    <small class="cc-users-pin__cargo">{{
                        usuario.cargo ?? 'Sin cargo'
                    }}</small>
                </button>
            </div>

            <footer class="cc-log__foot">
                <p>{{ resumen }}</p>
                <div class="cc-log__pager">
                    <button
                        type="button"
                        aria-label="Página anterior"
                        :disabled="usuarios.meta.current_page <= 1"
                        @click="ir(usuarios.meta.current_page - 1)"
                    >
                        <ChevronLeft :size="16" />
                    </button>
                    <span>
                        Página {{ usuarios.meta.current_page }} de
                        {{ usuarios.meta.last_page }}
                    </span>
                    <button
                        type="button"
                        aria-label="Página siguiente"
                        :disabled="
                            usuarios.meta.current_page >= usuarios.meta.last_page
                        "
                        @click="ir(usuarios.meta.current_page + 1)"
                    >
                        <ChevronRight :size="16" />
                    </button>
                </div>
            </footer>
        </motion.div>
    </section>

    <Teleport v-if="enCliente" to="#cc-portal">
        <CcRadialFab
            v-show="ancho <= 768 && !formAbierto && !objetivo"
            :acciones="fabAcciones"
            @elegir="fabElegir"
        />
    </Teleport>

    <Teleport v-if="enCliente" to="#cc-portal">
        <AnimatePresence>
            <motion.div
                v-if="formAbierto && ancho <= 768"
                key="ficha"
                class="cc-user-sheet"
            >
                <motion.button
                    type="button"
                    class="cc-user-sheet__velo"
                    aria-label="Cerrar"
                    :initial="{ opacity: 0 }"
                    :animate="{ opacity: 1 }"
                    :exit="{ opacity: 0 }"
                    @click="formAbierto = false"
                />
                <motion.section
                    class="cc-user-sheet__panel cc-theme cc-admin"
                    :initial="{ y: '100%' }"
                    :animate="{ y: 0 }"
                    :exit="{ y: '100%' }"
                    :transition="adminSpring"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="editando ? 'cc-ficha-nombre' : 'cc-ficha-alta'"
                >
                    <div class="cc-user-sheet__asa" />
                    <header v-if="editando" class="cc-user-sheet__hero">
                        <span class="cc-user-initials">
                            {{ iniciales(editando.nombre) }}
                        </span>
                        <h2 id="cc-ficha-nombre">{{ editando.nombre }}</h2>
                        <p>{{ editando.email }}</p>
                    </header>
                    <header v-else class="cc-user-sheet__hero">
                        <span class="cc-user-initials">+</span>
                        <h2 id="cc-ficha-alta">Crear usuario</h2>
                        <p>Se enviará una clave temporal al correo.</p>
                    </header>
                    <form class="cc-modal-form" @submit.prevent="guardar">
                        <CcUsuarioCampos
                            :alta="alta"
                            :editando="Boolean(editando)"
                            :roles="opciones.roles"
                            :personal-disponible="opciones.personal_disponible"
                        />
                        <div
                            v-if="editando"
                            class="cc-user-sheet__extra"
                        >
                            <button
                                v-if="editando.estado === 'ACTIVO'"
                                type="button"
                                class="cc-button cc-button--ghost"
                                :disabled="reenviando === editando.id"
                                @click="reenviar(editando)"
                            >
                                <Mail />
                                Reenviar clave
                            </button>
                            <button
                                v-if="editando.estado === 'ACTIVO'"
                                type="button"
                                class="cc-button cc-button--danger"
                                :disabled="editando.es_actual || editando.protegido"
                                @click="objetivo = editando"
                            >
                                <UserX />
                                Dar de baja
                            </button>
                            <button
                                v-else
                                type="button"
                                class="cc-button"
                                @click="cambiarEstado(editando, 'ACTIVO')"
                            >
                                <UserCheck />
                                Habilitar
                            </button>
                        </div>
                        <div class="cc-modal-actions">
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="formAbierto = false"
                            >
                                <X />
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                class="cc-button"
                                :class="{ 'is-load': alta.processing }"
                                :disabled="alta.processing"
                            >
                                <span v-if="alta.processing" class="cc-admin__spin" />
                                {{ editando ? 'Guardar' : 'Crear y enviar clave' }}
                            </button>
                        </div>
                    </form>
                </motion.section>
            </motion.div>
        </AnimatePresence>
    </Teleport>

    <!-- Crear / editar escritorio (oculto en cel por CSS) -->
    <Dialog v-if="enCliente && ancho > 768" v-model:open="formAbierto">
        <DialogContent class="cc-theme cc-admin cc-log-dialog">
            <DialogHeader>
                <DialogTitle>
                    {{ editando ? 'Editar usuario' : 'Crear usuario' }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        editando
                            ? 'Modificá los datos permitidos de la cuenta.'
                            : 'Se enviará una clave temporal al correo indicado. En el primer ingreso deberá cambiarla.'
                    }}
                </DialogDescription>
            </DialogHeader>

            <form class="cc-modal-form" @submit.prevent="guardar">
                <CcUsuarioCampos
                    :alta="alta"
                    :editando="Boolean(editando)"
                    :roles="opciones.roles"
                    :personal-disponible="opciones.personal_disponible"
                />

                <div class="cc-modal-actions">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost"
                        @click="formAbierto = false"
                    >
                        <X />
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="cc-button"
                        :class="{ 'is-load': alta.processing }"
                        :disabled="alta.processing"
                    >
                        <span v-if="alta.processing" class="cc-admin__spin" />
                        {{ editando ? 'Guardar cambios' : 'Crear y enviar clave' }}
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Deshabilitar -->
    <Dialog v-model:open="objetivoAbierto">
        <DialogContent v-if="objetivo" class="cc-theme cc-admin cc-log-dialog cc-admin-dialog--danger">
            <DialogHeader>
                <DialogTitle>Deshabilitar usuario</DialogTitle>
                <DialogDescription>
                    {{ objetivo.nombre }} ya no podrá iniciar sesión ni operar en
                    el sistema. Su historial se conservará en la bitácora.
                </DialogDescription>
            </DialogHeader>

            <div class="cc-user-card">
                <span class="cc-user-initials">{{ iniciales(objetivo.nombre) }}</span>
                <span>
                    <strong>{{ objetivo.nombre }}</strong>
                    <small>{{ objetivo.email }}</small>
                </span>
            </div>

            <div class="cc-modal-actions">
                <button
                    type="button"
                    class="cc-button cc-button--ghost"
                    @click="objetivo = null"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="cc-button cc-button--danger"
                    :class="{ 'is-load': procesando }"
                    :disabled="procesando"
                    @click="cambiarEstado(objetivo, 'INACTIVO')"
                >
                    <span v-if="procesando" class="cc-admin__spin" />
                    Sí, deshabilitar
                </button>
            </div>
        </DialogContent>
    </Dialog>
    </div>
</template>
