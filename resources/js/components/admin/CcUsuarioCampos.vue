<script setup lang="ts">
import { computed } from 'vue';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';

const props = defineProps<{
    alta: {
        personal_modo: 'nuevo' | 'existente';
        personal_id: number | string;
        nombre_completo: string;
        ci: string;
        telefono: string;
        cargo: string;
        email: string;
        rol_id: number | string;
        errors: Partial<Record<string, string>>;
    };
    editando: boolean;
    roles: { id: number; nombre: string }[];
    personalDisponible: {
        id: number;
        nombre_completo: string;
        cargo: string | null;
    }[];
}>();

const opcionesRol = computed(() => [
    { value: '', label: 'Elegí un rol' },
    ...props.roles.map((rol) => ({
        value: String(rol.id),
        label: rol.nombre,
    })),
]);

const opcionesPersonal = computed(() => [
    { value: '', label: 'Elegí una persona' },
    ...props.personalDisponible.map((persona) => ({
        value: String(persona.id),
        label: persona.cargo
            ? `${persona.nombre_completo} · ${persona.cargo}`
            : persona.nombre_completo,
    })),
]);
</script>

<template>
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
        <div class="cc-modal-field">
            <span>Personal a vincular</span>
            <CcRevealSelect
                :model-value="String(alta.personal_id ?? '')"
                etiqueta="Elegí una persona"
                :opciones="opcionesPersonal"
                @update:model-value="alta.personal_id = $event"
            />
            <small v-if="personalDisponible.length === 0">
                No hay personal sin usuario. Creá uno nuevo.
            </small>
            <small v-if="alta.errors.personal_id" class="cc-modal-error">
                {{ alta.errors.personal_id }}
            </small>
        </div>
    </template>

    <template v-else>
        <label class="cc-modal-field">
            <span>Nombre completo</span>
            <span class="cc-log__control">
                <input
                    v-model="alta.nombre_completo"
                    type="text"
                    maxlength="200"
                />
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
                    <input
                        v-model="alta.telefono"
                        type="tel"
                        inputmode="numeric"
                        maxlength="15"
                    />
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
            <input
                v-model="alta.email"
                type="email"
                maxlength="150"
                autocomplete="off"
            />
        </span>
        <small v-if="alta.errors.email" class="cc-modal-error">
            {{ alta.errors.email }}
        </small>
    </label>

    <div class="cc-modal-field">
        <span>Rol</span>
        <CcRevealSelect
            :model-value="String(alta.rol_id ?? '')"
            etiqueta="Elegí un rol"
            :opciones="opcionesRol"
            @update:model-value="alta.rol_id = $event"
        />
        <small v-if="alta.errors.rol_id" class="cc-modal-error">
            {{ alta.errors.rol_id }}
        </small>
    </div>
</template>
