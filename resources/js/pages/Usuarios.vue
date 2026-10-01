<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
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
import { computed, reactive, ref, watch } from 'vue';
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

watch(
    () => props.filtros,
    (filtros) => Object.assign(form, filtros),
);

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

function limpiar(): void {
    form.q = '';
    form.rol = '';
    form.estado = '';
    form.personal = '';
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
    alta.clearErrors();
    alta.personal_modo = 'existente';
    alta.nombre_completo = usuario.nombre;
    alta.telefono = usuario.telefono ?? '';
    alta.cargo = usuario.cargo ?? '';
    alta.email = usuario.email;
    alta.rol_id = usuario.rol_id;
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
    router.post(`/usuarios/${usuario.id}/reenviar-clave`, {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Gestionar usuarios" />

    <section class="cc-log">
        <header class="cc-log__head">
            <div>
                <h1>Gestionar usuarios</h1>
                <p>
                    Registrá, modificá, habilitá o deshabilitá las cuentas del
                    sistema.
                </p>
            </div>
            <div class="cc-log__actions">
                <button
                    type="button"
                    class="cc-button cc-button--ghost cc-log__btn"
                    @click="exportar"
                >
                    <Download />
                    Exportar
                </button>
                <button
                    type="button"
                    class="cc-button cc-log__btn cc-log__btn--solid"
                    @click="abrirCrear"
                >
                    <Plus />
                    Crear usuario
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
                        placeholder="Buscar por nombre o correo..."
                        maxlength="150"
                    />
                </label>
                <label class="cc-log__control cc-log__select">
                    <span class="sr-only">Rol</span>
                    <select v-model="form.rol" @change="aplicar">
                        <option value="">Todos los roles</option>
                        <option
                            v-for="rol in opciones.roles"
                            :key="rol.id"
                            :value="String(rol.id)"
                        >
                            {{ rol.nombre }}
                        </option>
                    </select>
                </label>
                <label class="cc-log__control cc-log__select">
                    <span class="sr-only">Estado</span>
                    <select v-model="form.estado" @change="aplicar">
                        <option value="">Todos los estados</option>
                        <option value="ACTIVO">Activo</option>
                        <option value="INACTIVO">Inactivo</option>
                    </select>
                </label>
                <label class="cc-log__control cc-log__select">
                    <span class="sr-only">Personal</span>
                    <select v-model="form.personal" @change="aplicar">
                        <option value="">Todo el personal</option>
                        <option
                            v-for="p in opciones.personal"
                            :key="p.id"
                            :value="String(p.id)"
                        >
                            {{ p.nombre_completo }}
                        </option>
                    </select>
                </label>
                <div class="cc-log__row-actions">
                    <button type="submit" class="cc-button cc-log__btn">
                        <Search />
                        Buscar
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
                            </td>
                        </tr>
                        <tr v-for="usuario in usuarios.data" :key="usuario.id">
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
                            <td>{{ usuario.email }}</td>
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
                                <small
                                    v-if="usuario.primer_login"
                                    class="cc-user-note"
                                >
                                    Pendiente de primer ingreso
                                </small>
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
                                        :aria-label="`Reenviar clave a ${usuario.nombre}`"
                                        @click="reenviar(usuario)"
                                    >
                                        <Mail :size="15" />
                                    </button>
                                    <button
                                        v-if="usuario.estado === 'ACTIVO'"
                                        type="button"
                                        class="cc-log__eye"
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
        </div>
    </section>

    <!-- Crear / editar -->
    <Dialog v-model:open="formAbierto">
        <DialogContent class="cc-theme cc-log-dialog">
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
                <div v-if="!editando" class="cc-segment" role="group" aria-label="Personal">
                    <button
                        type="button"
                        :class="{ active: alta.personal_modo === 'nuevo' }"
                        @click="alta.personal_modo = 'nuevo'"
                    >
                        Personal nuevo
                    </button>
                    <button
                        type="button"
                        :class="{ active: alta.personal_modo === 'existente' }"
                        @click="alta.personal_modo = 'existente'"
                    >
                        Personal existente
                    </button>
                </div>

                <template v-if="!editando && alta.personal_modo === 'existente'">
                    <label class="cc-modal-field">
                        <span>Personal a vincular</span>
                        <span class="cc-log__control">
                            <select v-model="alta.personal_id">
                                <option value="">Elegí una persona</option>
                                <option
                                    v-for="p in opciones.personal_disponible"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.nombre_completo }}
                                    {{ p.cargo ? `· ${p.cargo}` : '' }}
                                </option>
                            </select>
                        </span>
                        <small v-if="opciones.personal_disponible.length === 0">
                            No hay personal sin usuario. Creá uno nuevo.
                        </small>
                        <small v-if="alta.errors.personal_id" class="cc-modal-error">
                            {{ alta.errors.personal_id }}
                        </small>
                    </label>
                </template>

                <template v-else>
                    <label class="cc-modal-field">
                        <span>Nombre completo</span>
                        <span class="cc-log__control">
                            <input v-model="alta.nombre_completo" type="text" maxlength="200" />
                        </span>
                        <small v-if="alta.errors.nombre_completo" class="cc-modal-error">
                            {{ alta.errors.nombre_completo }}
                        </small>
                    </label>
                    <div class="cc-modal-grid">
                        <label v-if="!editando" class="cc-modal-field">
                            <span>CI</span>
                            <span class="cc-log__control">
                                <input v-model="alta.ci" type="text" maxlength="30" />
                            </span>
                            <small v-if="alta.errors.ci" class="cc-modal-error">
                                {{ alta.errors.ci }}
                            </small>
                        </label>
                        <label class="cc-modal-field">
                            <span>Teléfono</span>
                            <span class="cc-log__control">
                                <input v-model="alta.telefono" type="tel" inputmode="numeric" maxlength="15" />
                            </span>
                            <small v-if="alta.errors.telefono" class="cc-modal-error">
                                {{ alta.errors.telefono }}
                            </small>
                        </label>
                    </div>
                    <label class="cc-modal-field">
                        <span>Cargo</span>
                        <span class="cc-log__control">
                            <input v-model="alta.cargo" type="text" maxlength="100" />
                        </span>
                        <small v-if="alta.errors.cargo" class="cc-modal-error">
                            {{ alta.errors.cargo }}
                        </small>
                    </label>
                </template>

                <label class="cc-modal-field">
                    <span>Correo electrónico</span>
                    <span class="cc-log__control">
                        <input v-model="alta.email" type="email" maxlength="150" autocomplete="off" />
                    </span>
                    <small v-if="alta.errors.email" class="cc-modal-error">
                        {{ alta.errors.email }}
                    </small>
                </label>

                <label class="cc-modal-field">
                    <span>Rol</span>
                    <span class="cc-log__control">
                        <select v-model="alta.rol_id">
                            <option value="">Elegí un rol</option>
                            <option v-for="rol in opciones.roles" :key="rol.id" :value="rol.id">
                                {{ rol.nombre }}
                            </option>
                        </select>
                    </span>
                    <small v-if="alta.errors.rol_id" class="cc-modal-error">
                        {{ alta.errors.rol_id }}
                    </small>
                </label>

                <div class="cc-modal-actions">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost cc-log__btn"
                        @click="formAbierto = false"
                    >
                        <X />
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="cc-button cc-log__btn cc-log__btn--solid"
                        :disabled="alta.processing"
                    >
                        {{ editando ? 'Guardar cambios' : 'Crear y enviar clave' }}
                    </button>
                </div>
            </form>
        </DialogContent>
    </Dialog>

    <!-- Deshabilitar -->
    <Dialog v-model:open="objetivoAbierto">
        <DialogContent v-if="objetivo" class="cc-theme cc-log-dialog">
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
                    class="cc-button cc-button--ghost cc-log__btn"
                    @click="objetivo = null"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    class="cc-button cc-log__btn cc-log__btn--solid"
                    :disabled="procesando"
                    @click="cambiarEstado(objetivo, 'INACTIVO')"
                >
                    Sí, deshabilitar
                </button>
            </div>
        </DialogContent>
    </Dialog>
</template>
