<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    ChevronRight,
    Lock,
    Pencil,
    Plus,
    Power,
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
const seleccionId = ref<number | null>(
    props.seleccionado ?? props.roles[0]?.id ?? null,
);
const marcados = ref<Set<number>>(new Set());
const errorPermisos = ref<string | null>(null);
const guardando = ref(false);

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

    return [...mapa.entries()].map(([nombre, permisos]) => ({ nombre, permisos }));
});

const idsEsenciales = computed(
    () => new Set(props.permisos.filter((p) => props.esenciales.includes(p.clave)).map((p) => p.id)),
);

function bloqueado(permiso: Permiso): boolean {
    return !!rol.value?.protegido && props.esenciales.includes(permiso.clave);
}

function sincronizar(): void {
    marcados.value = new Set(rol.value?.permisos ?? []);
    errorPermisos.value = null;
}

watch(() => [props.roles, seleccionId.value], sincronizar, { immediate: true });

watch(
    () => props.seleccionado,
    (id) => {
        if (id) {
            seleccionId.value = id;
        }
    },
);

const cambios = computed(() => {
    const original = new Set(rol.value?.permisos ?? []);
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

    return total;
});

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
            ? props.permisos.filter((p) => props.esenciales.includes(p.clave)).map((p) => p.id)
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
            nombre: rol.value.nombre,
            descripcion: rol.value.descripcion ?? '',
            permisos: [...marcados.value],
        },
        {
            preserveScroll: true,
            onError: (errores) => {
                errorPermisos.value =
                    errores.permisos ?? errores.nombre ?? errores.descripcion ?? 'No se pudo guardar.';
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

// ---- Crear ----
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

// ---- Editar ----
const editarAbierto = ref(false);
const editar = useForm({ nombre: '', descripcion: '' });

function abrirEditar(): void {
    if (!rol.value) {
        return;
    }

    editar.nombre = rol.value.nombre;
    editar.descripcion = rol.value.descripcion ?? '';
    editar.clearErrors();
    editarAbierto.value = true;
}

function enviarEditar(): void {
    if (!rol.value) {
        return;
    }

    editar
        .transform((datos) => ({ ...datos, permisos: rol.value?.permisos ?? [] }))
        .put(`/roles/${rol.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                editarAbierto.value = false;
            },
        });
}
</script>

<template>
    <Head title="Roles y permisos" />

    <section class="cc-log">
        <header class="cc-log__head">
            <div>
                <h1>Roles y permisos</h1>
                <p>
                    Definí responsabilidades y controlá el acceso a cada módulo
                    del sistema.
                </p>
            </div>
            <div class="cc-log__actions">
                <button
                    type="button"
                    class="cc-button cc-log__btn cc-log__btn--solid"
                    @click="abrirCrear"
                >
                    <Plus />
                    Crear rol
                </button>
            </div>
        </header>

        <div class="cc-roles">
            <aside class="cc-log__panel cc-roles__list">
                <div class="cc-roles__list-head">
                    <strong>Roles</strong>
                    <span class="cc-log__badge cc-log__badge--ajustar">
                        {{ roles.length }} REGISTRADOS
                    </span>
                </div>
                <label class="cc-log__control">
                    <Search :size="16" />
                    <input v-model="busqueda" type="text" placeholder="Buscar rol..." />
                </label>
                <button
                    v-for="item in rolesFiltrados"
                    :key="item.id"
                    type="button"
                    class="cc-roles__item"
                    :class="{ active: item.id === seleccionId }"
                    @click="seleccionId = item.id"
                >
                    <span class="cc-roles__icon"><ShieldCheck :size="16" /></span>
                    <span class="cc-roles__text">
                        <strong>{{ item.nombre }}</strong>
                        <small>{{ item.descripcion || 'Sin descripción' }}</small>
                        <small>{{ item.usuarios }} usuario(s)</small>
                    </span>
                    <ChevronRight :size="16" />
                </button>
                <p v-if="rolesFiltrados.length === 0" class="cc-log__empty">
                    No hay roles que coincidan.
                </p>
            </aside>

            <div v-if="rol" class="cc-log__panel cc-roles__detail">
                <div class="cc-roles__detail-head">
                    <div>
                        <h2>
                            {{ rol.nombre }}
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
                        </h2>
                        <p>{{ rol.descripcion || 'Sin descripción' }}</p>
                    </div>
                    <div class="cc-log__actions">
                        <button
                            type="button"
                            class="cc-button cc-button--ghost cc-log__btn"
                            @click="abrirEditar"
                        >
                            <Pencil />
                            Editar rol
                        </button>
                        <button
                            type="button"
                            class="cc-button cc-button--ghost cc-log__btn"
                            :disabled="rol.protegido"
                            :title="rol.protegido ? 'El rol Administrador no se puede desactivar' : ''"
                            @click="cambiarEstado"
                        >
                            <Power />
                            {{ rol.estado === 'ACTIVO' ? 'Desactivar' : 'Activar' }}
                        </button>
                    </div>
                </div>

                <div class="cc-roles__bulk">
                    <button type="button" @click="seleccionarTodo">Seleccionar todo</button>
                    <button type="button" @click="revocarTodo">Revocar todo</button>
                </div>

                <p v-if="errorPermisos" class="cc-roles__alert" role="alert">
                    {{ errorPermisos }}
                </p>

                <div class="cc-roles__matrix">
                    <div v-for="modulo in modulos" :key="modulo.nombre" class="cc-roles__module">
                        <div class="cc-roles__module-name">
                            <strong>{{ modulo.nombre }}</strong>
                            <small>
                                {{ moduloMarcados(modulo.permisos) }} de
                                {{ modulo.permisos.length }}
                            </small>
                        </div>
                        <div class="cc-roles__perms">
                            <label
                                v-for="permiso in modulo.permisos"
                                :key="permiso.id"
                                class="cc-roles__perm"
                                :class="{
                                    on: marcados.has(permiso.id),
                                    locked: bloqueado(permiso),
                                }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="marcados.has(permiso.id)"
                                    :disabled="bloqueado(permiso)"
                                    @change="alternar(permiso)"
                                />
                                <span>{{ permiso.nombre }}</span>
                                <Lock v-if="bloqueado(permiso)" :size="12" />
                            </label>
                        </div>
                    </div>
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
                        class="cc-button cc-log__btn cc-log__btn--solid"
                        :disabled="cambios === 0 || guardando"
                        @click="guardar"
                    >
                        <Save />
                        Guardar cambios
                    </button>
                </footer>
            </div>

            <div v-else class="cc-log__panel cc-log__empty">
                <strong>Sin roles</strong>
                Creá el primer rol para empezar.
            </div>
        </div>
    </section>

    <!-- Crear rol -->
    <Dialog v-model:open="crearAbierto">
        <DialogContent class="cc-theme cc-log-dialog cc-roles__dialog">
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
                <label class="cc-roles__switch">
                    <input v-model="crear.activo" type="checkbox" />
                    <span>Rol activo (disponible para asignar a usuarios)</span>
                </label>

                <div class="cc-roles__create-perms">
                    <div class="cc-roles__list-head">
                        <strong>Permisos del rol</strong>
                        <span class="cc-log__badge cc-log__badge--registrar">
                            {{ crear.permisos.length }} PERMISOS
                        </span>
                    </div>
                    <div v-for="modulo in modulos" :key="modulo.nombre" class="cc-roles__module">
                        <div class="cc-roles__module-name">
                            <strong>{{ modulo.nombre }}</strong>
                        </div>
                        <div class="cc-roles__perms">
                            <label
                                v-for="permiso in modulo.permisos"
                                :key="permiso.id"
                                class="cc-roles__perm"
                                :class="{ on: crear.permisos.includes(permiso.id) }"
                            >
                                <input
                                    type="checkbox"
                                    :checked="crear.permisos.includes(permiso.id)"
                                    @change="alternarNuevo(permiso.id)"
                                />
                                <span>{{ permiso.nombre }}</span>
                            </label>
                        </div>
                    </div>
                    <small v-if="crear.errors.permisos" class="cc-modal-error">
                        {{ crear.errors.permisos }}
                    </small>
                </div>

                <div class="cc-modal-actions">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost cc-log__btn"
                        @click="crearAbierto = false"
                    >
                        <X />
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="cc-button cc-log__btn cc-log__btn--solid"
                        :disabled="crear.processing"
                    >
                        <Save />
                        Crear rol
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Editar rol -->
    <Dialog v-model:open="editarAbierto">
        <DialogContent class="cc-theme cc-log-dialog">
            <DialogHeader>
                <DialogTitle>Editar rol</DialogTitle>
                <DialogDescription>
                    Actualizá el nombre y la descripción. Los permisos se guardan desde la matriz.
                </DialogDescription>
            </DialogHeader>

            <form class="cc-modal-form" @submit.prevent="enviarEditar">
                <label class="cc-modal-field">
                    <span>Nombre del rol</span>
                    <span class="cc-log__control">
                        <input
                            v-model="editar.nombre"
                            type="text"
                            maxlength="100"
                            :disabled="rol?.protegido"
                        />
                    </span>
                    <small v-if="rol?.protegido">El rol Administrador no se puede renombrar.</small>
                    <small v-if="editar.errors.nombre" class="cc-modal-error">
                        {{ editar.errors.nombre }}
                    </small>
                </label>
                <label class="cc-modal-field">
                    <span>Descripción</span>
                    <span class="cc-log__control cc-log__control--area">
                        <textarea v-model="editar.descripcion" maxlength="500" />
                    </span>
                    <small v-if="editar.errors.descripcion" class="cc-modal-error">
                        {{ editar.errors.descripcion }}
                    </small>
                </label>
                <div class="cc-modal-actions">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost cc-log__btn"
                        @click="editarAbierto = false"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="cc-button cc-log__btn cc-log__btn--solid"
                        :disabled="editar.processing"
                    >
                        Guardar
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
