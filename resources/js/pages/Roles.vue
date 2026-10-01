<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { AnimatePresence, motion } from 'motion-v';
import { adminSpring } from '@/lib/adminMotion';
import {
    ChevronLeft,
    Lock,
    Plus,
    Save,
    Search,
    ShieldCheck,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Rol = {
    id: number;
    nombre: string;
    descripcion: string | null;
    estado: 'ACTIVO' | 'INACTIVO';
    es_sistema: boolean;
    protegido: boolean;
    usuarios: number;
    usuarios_activos: number;
    permisos: number[];
};

type Permiso = { id: number; clave: string; nombre: string; modulo: string | null };

const props = defineProps<{
    roles: Rol[];
    permisos: Permiso[];
    esenciales: string[];
    seleccionado: number | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Roles y permisos', href: '/roles' }],
    },
});

const busqueda = ref('');
const seleccionId = ref<number | null>(props.seleccionado);
const editandoVista = ref(props.seleccionado !== null);
const marcados = ref<Set<number>>(new Set());
const errorPermisos = ref<string | null>(null);
const guardando = ref(false);
const editar = useForm({ nombre: '', descripcion: '' });

const rol = computed(
    () => props.roles.find((r) => r.id === seleccionId.value) ?? null,
);

const rolesFiltrados = computed(() => {
    const texto = busqueda.value.trim().toLowerCase();

    return props.roles.filter(
        (r) =>
            texto === '' ||
            r.nombre.toLowerCase().includes(texto) ||
            (r.descripcion ?? '').toLowerCase().includes(texto),
    );
});

const modulos = computed(() => {
    const mapa = new Map<string, Permiso[]>();

    props.permisos.forEach((permiso) => {
        const clave = permiso.modulo ?? 'General';
        mapa.set(clave, [...(mapa.get(clave) ?? []), permiso]);
    });

    return [...mapa.entries()].map(([nombre, permisos]) => ({
        nombre,
        permisos,
    }));
});

function bloqueado(permiso: Permiso): boolean {
    return !!rol.value?.protegido && props.esenciales.includes(permiso.clave);
}

function sincronizar(): void {
    marcados.value = new Set(rol.value?.permisos ?? []);
    errorPermisos.value = null;

    if (rol.value) {
        editar.nombre = rol.value.nombre;
        editar.descripcion = rol.value.descripcion ?? '';
    }
}

watch(() => [props.roles, seleccionId.value], sincronizar, { immediate: true });

watch(
    () => props.seleccionado,
    (id) => {
        if (id) {
            seleccionId.value = id;
            editandoVista.value = true;
        }
    },
);

const cambios = computed(() => {
    if (!rol.value) {
        return 0;
    }

    const original = new Set(rol.value.permisos);
    let total = 0;

    marcados.value.forEach((id) => {
        if (!original.has(id)) {
            total++;
        }
    });
    original.forEach((id) => {
        if (!marcados.value.has(id)) {
            total++;
        }
    });

    if (editar.nombre !== rol.value.nombre) {
        total++;
    }

    if ((editar.descripcion ?? '') !== (rol.value.descripcion ?? '')) {
        total++;
    }

    return total;
});

function abrirFicha(item: Rol): void {
    seleccionId.value = item.id;
    editandoVista.value = true;
}

function volver(): void {
    editandoVista.value = false;
    seleccionId.value = null;
}

function alternar(permiso: Permiso): void {
    if (bloqueado(permiso)) {
        errorPermisos.value =
            'Protección activa: el rol Administrador debe conservar los permisos esenciales de seguridad.';

        return;
    }

    const siguiente = new Set(marcados.value);

    if (siguiente.has(permiso.id)) {
        siguiente.delete(permiso.id);
    } else {
        siguiente.add(permiso.id);
    }

    marcados.value = siguiente;
    errorPermisos.value = null;
}

function seleccionarTodo(): void {
    marcados.value = new Set(props.permisos.map((p) => p.id));
}

function revocarTodo(): void {
    marcados.value = new Set(
        rol.value?.protegido
            ? props.permisos
                  .filter((p) => props.esenciales.includes(p.clave))
                  .map((p) => p.id)
            : [],
    );
}

function moduloMarcados(permisos: Permiso[]): number {
    return permisos.filter((p) => marcados.value.has(p.id)).length;
}

function guardar(): void {
    if (!rol.value) {
        return;
    }

    guardando.value = true;
    router.put(
        `/roles/${rol.value.id}`,
        {
            nombre: editar.nombre,
            descripcion: editar.descripcion,
            permisos: [...marcados.value],
        },
        {
            preserveScroll: true,
            onError: (errores) => {
                errorPermisos.value =
                    errores.permisos ??
                    errores.nombre ??
                    errores.descripcion ??
                    'No se pudo guardar.';
            },
            onFinish: () => {
                guardando.value = false;
            },
        },
    );
}

function cambiarEstado(): void {
    if (!rol.value) {
        return;
    }

    router.patch(
        `/roles/${rol.value.id}/estado`,
        { estado: rol.value.estado === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO' },
        { preserveScroll: true },
    );
}

const crearAbierto = ref(false);
const crear = useForm({
    nombre: '',
    descripcion: '',
    activo: true,
    permisos: [] as number[],
});

function abrirCrear(): void {
    crear.reset();
    crear.clearErrors();
    crearAbierto.value = true;
}

function alternarNuevo(id: number): void {
    crear.permisos = crear.permisos.includes(id)
        ? crear.permisos.filter((p) => p !== id)
        : [...crear.permisos, id];
}

function enviarCrear(): void {
    crear.post('/roles', {
        preserveScroll: true,
        onSuccess: () => {
            crearAbierto.value = false;
        },
    });
}
</script>

<template>
    <div>
    <Head title="Roles y permisos" />

    <section class="cc-admin cc-log">
        <motion.div layout class="cc-log__head" :transition="adminSpring">
            <div>
                <p class="cc-log__kicker">Sistema</p>
                <h1>Roles y permisos</h1>
                <p>
                    Definí responsabilidades y controlá el acceso a cada módulo
                    del sistema.
                </p>
            </div>
            <div v-if="!editandoVista" class="cc-log__actions">
                <button type="button" class="cc-button" @click="abrirCrear">
                    <Plus />
                    Crear rol
                </button>
            </div>
        </motion.div>

        <AnimatePresence mode="wait">
            <motion.div
                v-if="!editandoVista"
                key="tablero"
                :initial="{ opacity: 0, y: 12 }"
                :animate="{ opacity: 1, y: 0 }"
                :exit="{ opacity: 0, y: -12 }"
                :transition="adminSpring"
            >
                <label class="cc-log__control cc-log__search cc-role-search">
                    <Search :size="16" />
                    <input
                        v-model="busqueda"
                        type="search"
                        placeholder="Buscar rol..."
                    />
                </label>

                <p v-if="rolesFiltrados.length === 0" class="cc-log__empty">
                    No hay roles que coincidan.
                </p>

                <div v-else class="cc-role-board">
                    <article
                        v-for="item in rolesFiltrados"
                        :key="item.id"
                        class="cc-role-pin"
                        :class="{ 'is-off': item.estado === 'INACTIVO' }"
                    >
                        <div class="cc-role-pin__foto" aria-hidden="true">
                            <ShieldCheck />
                        </div>
                        <div class="cc-role-pin__cuerpo">
                            <h2>{{ item.nombre }}</h2>
                            <p>
                                {{
                                    item.descripcion ||
                                    'Sin descripción. Definí el alcance de este rol.'
                                }}
                            </p>
                            <div class="cc-role-pin__meta">
                                <span
                                    class="cc-log__badge"
                                    :class="
                                        item.estado === 'ACTIVO'
                                            ? 'cc-log__badge--crear'
                                            : 'cc-log__badge--error'
                                    "
                                >
                                    {{ item.estado }}
                                </span>
                                <span class="cc-log__badge cc-log__badge--neutro">
                                    {{ item.usuarios }} usuario(s)
                                </span>
                                <span
                                    v-if="item.protegido"
                                    class="cc-log__badge cc-log__badge--dot"
                                >
                                    Sistema
                                </span>
                            </div>
                            <button
                                type="button"
                                class="cc-button"
                                @click="abrirFicha(item)"
                            >
                                Editar
                            </button>
                        </div>
                    </article>
                </div>
            </motion.div>

            <motion.div
                v-else-if="rol"
                key="ficha"
                class="cc-role-edit"
                :initial="{ opacity: 0, y: 16 }"
                :animate="{ opacity: 1, y: 0 }"
                :exit="{ opacity: 0, y: 16 }"
                :transition="adminSpring"
            >
                <header class="cc-role-edit__head">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost"
                        @click="volver"
                    >
                        <ChevronLeft />
                        Roles
                    </button>
                    <span
                        class="cc-log__badge"
                        :class="
                            rol.estado === 'ACTIVO'
                                ? 'cc-log__badge--crear'
                                : 'cc-log__badge--error'
                        "
                    >
                        {{ rol.estado }}
                    </span>
                </header>

                <div class="cc-role-edit__hero">
                    <span class="cc-role-pin__foto" aria-hidden="true">
                        <ShieldCheck />
                    </span>
                    <div>
                        <h2>{{ rol.nombre }}</h2>
                        <p>{{ rol.usuarios }} usuario(s) con este rol</p>
                    </div>
                </div>

                <div class="cc-role-edit__campos">
                    <label class="cc-modal-field">
                        <span>Nombre del rol</span>
                        <span class="cc-log__control">
                            <input
                                v-model="editar.nombre"
                                type="text"
                                maxlength="100"
                                :disabled="rol.protegido"
                            />
                        </span>
                        <small v-if="rol.protegido">
                            El rol Administrador no se puede renombrar.
                        </small>
                    </label>
                    <label class="cc-modal-field">
                        <span>Descripción</span>
                        <span class="cc-log__control cc-log__control--area">
                            <textarea
                                v-model="editar.descripcion"
                                maxlength="500"
                            />
                        </span>
                    </label>
                    <button
                        type="button"
                        class="cc-sw"
                        :class="{ 'is-on': rol.estado === 'ACTIVO' }"
                        :disabled="rol.protegido"
                        :title="
                            rol.protegido
                                ? 'El rol Administrador no se puede desactivar'
                                : ''
                        "
                        @click="cambiarEstado"
                    >
                        <span class="cc-sw__pista">
                            <i />
                        </span>
                        <span>
                            Rol activo
                            <small>Disponible para asignar a usuarios</small>
                        </span>
                    </button>
                </div>

                <div class="cc-role-edit__bulk">
                    <button type="button" class="cc-button" @click="seleccionarTodo">
                        Seleccionar todo
                    </button>
                    <button
                        type="button"
                        class="cc-button cc-button--ghost"
                        @click="revocarTodo"
                    >
                        Revocar todo
                    </button>
                </div>

                <p v-if="errorPermisos" class="cc-roles__alert" role="alert">
                    {{ errorPermisos }}
                </p>

                <div class="cc-role-edit__mods">
                    <section
                        v-for="modulo in modulos"
                        :key="modulo.nombre"
                        class="cc-role-mod"
                    >
                        <header>
                            <strong>{{ modulo.nombre }}</strong>
                            <small>
                                {{ moduloMarcados(modulo.permisos) }} de
                                {{ modulo.permisos.length }}
                            </small>
                        </header>
                        <div class="cc-role-mod__list">
                            <button
                                v-for="permiso in modulo.permisos"
                                :key="permiso.id"
                                type="button"
                                class="cc-ck"
                                :class="{
                                    'is-on': marcados.has(permiso.id),
                                    'is-off': bloqueado(permiso),
                                }"
                                :disabled="bloqueado(permiso)"
                                @click="alternar(permiso)"
                            >
                                <span class="cc-ck__box">
                                    <svg
                                        class="cc-ck__tick"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M5 12l5 5L20 7"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                                <span class="cc-ck__txt">
                                    {{ permiso.nombre }}
                                    <small>{{ permiso.clave }}</small>
                                </span>
                                <Lock v-if="bloqueado(permiso)" :size="12" />
                            </button>
                        </div>
                    </section>
                </div>

                <footer class="cc-log__foot">
                    <p :class="{ 'cc-roles__dirty': cambios > 0 }">
                        <template v-if="cambios > 0">
                            ● {{ cambios }} cambio(s) sin guardar
                        </template>
                        <template v-else>Sin cambios pendientes</template>
                    </p>
                    <button
                        type="button"
                        class="cc-button"
                        :class="{ 'is-load': guardando }"
                        :disabled="cambios === 0 || guardando"
                        @click="guardar"
                    >
                        <Save />
                        Guardar cambios
                    </button>
                </footer>
            </motion.div>
        </AnimatePresence>
    </section>

    <Dialog v-model:open="crearAbierto">
        <DialogContent class="cc-theme cc-admin cc-log-dialog cc-roles__dialog">
            <DialogHeader>
                <DialogTitle>Crear rol</DialogTitle>
                <DialogDescription>
                    Configurá el alcance del rol antes de asignarlo al personal.
                </DialogDescription>
            </DialogHeader>

            <form class="cc-modal-form" @submit.prevent="enviarCrear">
                <label class="cc-modal-field">
                    <span>Nombre del rol</span>
                    <span class="cc-log__control">
                        <input v-model="crear.nombre" type="text" maxlength="100" />
                    </span>
                    <small v-if="crear.errors.nombre" class="cc-modal-error">
                        {{ crear.errors.nombre }}
                    </small>
                </label>
                <label class="cc-modal-field">
                    <span>Descripción</span>
                    <span class="cc-log__control cc-log__control--area">
                        <textarea v-model="crear.descripcion" maxlength="500" />
                    </span>
                    <small v-if="crear.errors.descripcion" class="cc-modal-error">
                        {{ crear.errors.descripcion }}
                    </small>
                </label>
                <button
                    type="button"
                    class="cc-sw"
                    :class="{ 'is-on': crear.activo }"
                    @click="crear.activo = !crear.activo"
                >
                    <span class="cc-sw__pista"><i /></span>
                    <span>
                        Rol activo
                        <small>Disponible para asignar a usuarios</small>
                    </span>
                </button>

                <div class="cc-roles__create-perms">
                    <div class="cc-roles__list-head">
                        <strong>Permisos del rol</strong>
                        <span class="cc-log__badge cc-log__badge--registrar">
                            {{ crear.permisos.length }} PERMISOS
                        </span>
                    </div>
                    <section
                        v-for="modulo in modulos"
                        :key="modulo.nombre"
                        class="cc-role-mod"
                    >
                        <header>
                            <strong>{{ modulo.nombre }}</strong>
                        </header>
                        <div class="cc-role-mod__list">
                            <button
                                v-for="permiso in modulo.permisos"
                                :key="permiso.id"
                                type="button"
                                class="cc-ck"
                                :class="{ 'is-on': crear.permisos.includes(permiso.id) }"
                                @click="alternarNuevo(permiso.id)"
                            >
                                <span class="cc-ck__box">
                                    <svg
                                        class="cc-ck__tick"
                                        viewBox="0 0 24 24"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M5 12l5 5L20 7"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        />
                                    </svg>
                                </span>
                                <span class="cc-ck__txt">{{ permiso.nombre }}</span>
                            </button>
                        </div>
                    </section>
                    <small v-if="crear.errors.permisos" class="cc-modal-error">
                        {{ crear.errors.permisos }}
                    </small>
                </div>

                <div class="cc-modal-actions">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost"
                        @click="crearAbierto = false"
                    >
                        <X />
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="cc-button"
                        :disabled="crear.processing"
                    >
                        <Save />
                        Crear rol
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
    </div>
</template>
