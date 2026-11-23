<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ExternalLink, ImageUp, Save } from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, onBeforeUnmount, ref } from 'vue';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import { adminSpring } from '@/lib/adminMotion';

type Horario = {
    dia_semana: number;
    dia: string;
    cerrado: boolean;
    hora_apertura: string | null;
    hora_cierre: string | null;
    horario: string | null;
};

type Configuracion = {
    nombre: string;
    centro_comercial: string | null;
    direccion: string | null;
    numero_pasillo: string | null;
    numero_local: string | null;
    telefono: string | null;
    whatsapp: string | null;
    email: string | null;
    latitud: number | null;
    longitud: number | null;
    google_maps_url: string | null;
    maps: string | null;
    logo: string | null;
    moneda: string;
    anuncio: string | null;
    horarios: Horario[];
};

const props = defineProps<{
    configuracion: Configuracion;
    monedas: string[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Configuración', href: '/configuracion/tienda' },
        ],
    },
});

const pestana = ref<'datos' | 'horarios'>('datos');

const pestanas = [
    { id: 'datos', label: 'Datos generales' },
    { id: 'horarios', label: 'Horarios' },
] as const;

const c = props.configuracion;

const datos = useForm({
    nombre_tienda: c.nombre,
    centro_comercial: c.centro_comercial ?? '',
    direccion: c.direccion ?? '',
    numero_pasillo: c.numero_pasillo ?? '',
    numero_local: c.numero_local ?? '',
    telefono: c.telefono ?? '',
    whatsapp: c.whatsapp ?? '',
    email: c.email ?? '',
    latitud: c.latitud === null ? '' : String(c.latitud),
    longitud: c.longitud === null ? '' : String(c.longitud),
    google_maps_url: c.google_maps_url ?? '',
    moneda: c.moneda,
    anuncio: c.anuncio ?? '',
});

const opcionesMoneda = props.monedas.map((moneda) => ({
    value: moneda,
    label: moneda === 'BOB' ? 'BOB — Boliviano' : 'USD — Dólar',
}));

const guardarDatos = (): void => {
    datos.put('/configuracion/tienda', { preserveScroll: true });
};

const enlaceMapa = computed(() => {
    if (datos.google_maps_url.trim() !== '') {
        return datos.google_maps_url.trim();
    }

    if (datos.latitud !== '' && datos.longitud !== '') {
        return `https://www.google.com/maps?q=${datos.latitud},${datos.longitud}`;
    }

    return null;
});

const logoForm = useForm<{ logo: File | null }>({ logo: null });
const vistaLogo = ref<string | null>(null);

const logoMostrado = computed(() => vistaLogo.value ?? c.logo);

function elegirLogo(evento: Event): void {
    const archivo = (evento.target as HTMLInputElement).files?.[0] ?? null;

    if (vistaLogo.value) {
        URL.revokeObjectURL(vistaLogo.value);
    }

    logoForm.logo = archivo;
    logoForm.clearErrors();
    vistaLogo.value = archivo ? URL.createObjectURL(archivo) : null;
}

function subirLogo(): void {
    logoForm.post('/configuracion/tienda/logo', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            logoForm.reset();
        },
    });
}

onBeforeUnmount(() => {
    if (vistaLogo.value) {
        URL.revokeObjectURL(vistaLogo.value);
    }
});

const horarios = useForm({
    horarios: c.horarios.map((dia) => ({
        dia_semana: dia.dia_semana,
        cerrado: dia.cerrado,
        hora_apertura: dia.hora_apertura ?? '',
        hora_cierre: dia.hora_cierre ?? '',
    })),
});

const erroresHorario: () => Record<string, string | undefined> = () =>
    horarios.errors;

function errorDe(indice: number, campo: 'hora_apertura' | 'hora_cierre') {
    return erroresHorario()[`horarios.${indice}.${campo}`];
}

const guardarHorarios = (): void => {
    horarios.put('/configuracion/tienda/horarios', { preserveScroll: true });
};

const nombreDia = (indice: number): string =>
    c.horarios[indice]?.dia ?? `Día ${indice + 1}`;
</script>

<template>
    <div>
        <Head title="Configuración de la tienda" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Configuración</p>
                    <h1>Tienda</h1>
                    <p>
                        Estos datos salen en el catálogo, las notas de venta,
                        las cotizaciones y los reportes.
                    </p>
                </motion.div>
            </motion.div>

            <div
                class="cc-conf__tabs"
                role="tablist"
                aria-label="Secciones de la configuración"
            >
                <button
                    v-for="item in pestanas"
                    :key="item.id"
                    type="button"
                    role="tab"
                    class="cc-conf__tab"
                    :class="{ 'is-activa': pestana === item.id }"
                    :aria-selected="pestana === item.id"
                    @click="pestana = item.id"
                >
                    {{ item.label }}
                </button>
            </div>

            <motion.div
                v-if="pestana === 'datos'"
                layout
                class="cc-log__panel cc-conf__panel"
                :transition="adminSpring"
            >
                <form class="cc-conf__form" @submit.prevent="guardarDatos">
                    <div class="cc-conf__logo">
                        <div class="cc-conf__logo-preview">
                            <img
                                v-if="logoMostrado"
                                :src="logoMostrado"
                                alt="Logo de la tienda"
                            />
                            <span v-else>Sin logo</span>
                        </div>
                        <div class="cc-conf__logo-actions">
                            <label class="cc-modal-field">
                                <span>Logo (PNG, JPG o WebP, hasta 1 MB)</span>
                                <input
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp"
                                    aria-label="Elegir logo"
                                    @change="elegirLogo"
                                />
                                <p
                                    v-if="logoForm.errors.logo"
                                    class="cc-modal-error"
                                >
                                    {{ logoForm.errors.logo }}
                                </p>
                            </label>
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                :disabled="
                                    !logoForm.logo || logoForm.processing
                                "
                                @click="subirLogo"
                            >
                                <ImageUp :size="16" />
                                Subir logo
                            </button>
                        </div>
                    </div>

                    <div class="cc-conf__grid">
                        <label class="cc-modal-field cc-conf__full">
                            <span
                                >Nombre de la tienda
                                <b class="cc-req">*</b></span
                            >
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.nombre_tienda"
                                    type="text"
                                    maxlength="150"
                                />
                            </span>
                            <p
                                v-if="datos.errors.nombre_tienda"
                                class="cc-modal-error"
                            >
                                {{ datos.errors.nombre_tienda }}
                            </p>
                        </label>

                        <label class="cc-modal-field">
                            <span>Centro comercial</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.centro_comercial"
                                    type="text"
                                    maxlength="150"
                                />
                            </span>
                            <p
                                v-if="datos.errors.centro_comercial"
                                class="cc-modal-error"
                            >
                                {{ datos.errors.centro_comercial }}
                            </p>
                        </label>

                        <label class="cc-modal-field">
                            <span>Dirección</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.direccion"
                                    type="text"
                                    maxlength="500"
                                />
                            </span>
                            <p
                                v-if="datos.errors.direccion"
                                class="cc-modal-error"
                            >
                                {{ datos.errors.direccion }}
                            </p>
                        </label>

                        <label class="cc-modal-field">
                            <span>Número de pasillo</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.numero_pasillo"
                                    type="text"
                                    maxlength="20"
                                />
                            </span>
                        </label>

                        <label class="cc-modal-field">
                            <span>Número de local</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.numero_local"
                                    type="text"
                                    maxlength="20"
                                />
                            </span>
                        </label>

                        <label class="cc-modal-field">
                            <span>Teléfono</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.telefono"
                                    type="tel"
                                    maxlength="30"
                                />
                            </span>
                            <p
                                v-if="datos.errors.telefono"
                                class="cc-modal-error"
                            >
                                {{ datos.errors.telefono }}
                            </p>
                        </label>

                        <label class="cc-modal-field">
                            <span>WhatsApp</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.whatsapp"
                                    type="tel"
                                    maxlength="30"
                                    placeholder="+59177712345"
                                />
                            </span>
                            <p
                                v-if="datos.errors.whatsapp"
                                class="cc-modal-error"
                            >
                                {{ datos.errors.whatsapp }}
                            </p>
                        </label>

                        <label class="cc-modal-field">
                            <span>Correo</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.email"
                                    type="email"
                                    maxlength="150"
                                />
                            </span>
                            <p v-if="datos.errors.email" class="cc-modal-error">
                                {{ datos.errors.email }}
                            </p>
                        </label>

                        <label class="cc-modal-field">
                            <span>Latitud</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.latitud"
                                    type="text"
                                    inputmode="decimal"
                                    placeholder="-17.7833"
                                />
                            </span>
                            <p
                                v-if="datos.errors.latitud"
                                class="cc-modal-error"
                            >
                                {{ datos.errors.latitud }}
                            </p>
                        </label>

                        <label class="cc-modal-field">
                            <span>Longitud</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.longitud"
                                    type="text"
                                    inputmode="decimal"
                                    placeholder="-63.1821"
                                />
                            </span>
                            <p
                                v-if="datos.errors.longitud"
                                class="cc-modal-error"
                            >
                                {{ datos.errors.longitud }}
                            </p>
                        </label>

                        <label class="cc-modal-field cc-conf__full">
                            <span>Enlace de Google Maps</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="datos.google_maps_url"
                                    type="url"
                                    maxlength="500"
                                    placeholder="https://maps.app.goo.gl/..."
                                />
                            </span>
                            <p
                                v-if="datos.errors.google_maps_url"
                                class="cc-modal-error"
                            >
                                {{ datos.errors.google_maps_url }}
                            </p>
                        </label>

                        <div class="cc-modal-field">
                            <span>Moneda</span>
                            <CcRevealSelect
                                v-model="datos.moneda"
                                etiqueta="Moneda"
                                :opciones="opcionesMoneda"
                            />
                            <p
                                v-if="datos.errors.moneda"
                                class="cc-modal-error"
                            >
                                {{ datos.errors.moneda }}
                            </p>
                        </div>

                        <label class="cc-modal-field cc-conf__full">
                            <span>Anuncio</span>
                            <span class="cc-log__control cc-log__control--area">
                                <textarea
                                    v-model="datos.anuncio"
                                    rows="3"
                                    maxlength="500"
                                />
                            </span>
                            <p
                                v-if="datos.errors.anuncio"
                                class="cc-modal-error"
                            >
                                {{ datos.errors.anuncio }}
                            </p>
                        </label>
                    </div>

                    <div class="cc-conf__actions">
                        <a
                            v-if="enlaceMapa"
                            class="cc-conf__maps"
                            :href="enlaceMapa"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <ExternalLink :size="15" />
                            Ver en Google Maps
                        </a>
                        <button
                            type="submit"
                            class="cc-button"
                            :disabled="datos.processing"
                        >
                            <Save :size="16" />
                            Guardar datos
                        </button>
                    </div>
                </form>
            </motion.div>

            <motion.div
                v-else
                layout
                class="cc-log__panel cc-conf__panel"
                :transition="adminSpring"
            >
                <form class="cc-conf__form" @submit.prevent="guardarHorarios">
                    <div class="cc-log__table-wrap cc-log__table-wrap--keep">
                        <table class="cc-log__table">
                            <thead>
                                <tr>
                                    <th>Día</th>
                                    <th>Cerrado</th>
                                    <th>Apertura</th>
                                    <th>Cierre</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(fila, indice) in horarios.horarios"
                                    :key="fila.dia_semana"
                                    :class="{ 'is-off': fila.cerrado }"
                                    :data-test="`horario-${fila.dia_semana}`"
                                >
                                    <td>
                                        <strong>{{ nombreDia(indice) }}</strong>
                                    </td>
                                    <td>
                                        <label class="cc-conf__switch">
                                            <input
                                                v-model="fila.cerrado"
                                                type="checkbox"
                                                role="switch"
                                                :aria-label="`${nombreDia(indice)} cerrado`"
                                            />
                                            <span aria-hidden="true" />
                                        </label>
                                    </td>
                                    <td>
                                        <span class="cc-log__control">
                                            <input
                                                v-model="fila.hora_apertura"
                                                type="time"
                                                :disabled="fila.cerrado"
                                                :aria-label="`Apertura del ${nombreDia(indice)}`"
                                            />
                                        </span>
                                        <p
                                            v-if="
                                                errorDe(indice, 'hora_apertura')
                                            "
                                            class="cc-modal-error"
                                        >
                                            {{
                                                errorDe(indice, 'hora_apertura')
                                            }}
                                        </p>
                                    </td>
                                    <td>
                                        <span class="cc-log__control">
                                            <input
                                                v-model="fila.hora_cierre"
                                                type="time"
                                                :disabled="fila.cerrado"
                                                :aria-label="`Cierre del ${nombreDia(indice)}`"
                                            />
                                        </span>
                                        <p
                                            v-if="
                                                errorDe(indice, 'hora_cierre')
                                            "
                                            class="cc-modal-error"
                                        >
                                            {{ errorDe(indice, 'hora_cierre') }}
                                        </p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p v-if="horarios.errors.horarios" class="cc-modal-error">
                        {{ horarios.errors.horarios }}
                    </p>

                    <div class="cc-conf__actions">
                        <button
                            type="submit"
                            class="cc-button"
                            :disabled="horarios.processing"
                        >
                            <Save :size="16" />
                            Guardar horarios
                        </button>
                    </div>
                </form>
            </motion.div>
        </section>
    </div>
</template>

<style scoped>
.cc-conf__tabs {
    display: flex;
    gap: 4px;
    overflow-x: auto;
    border-bottom: 1px solid var(--cc-border);
}

.cc-conf__tab {
    margin-bottom: -1px;
    padding: 10px 14px;
    border: 0;
    border-bottom: 2px solid transparent;
    background: none;
    color: var(--cc-muted);
    font: inherit;
    font-size: 14px;
    font-weight: 500;
    white-space: nowrap;
    cursor: pointer;
    transition:
        color 140ms ease,
        border-color 140ms ease;
}

.cc-conf__tab:hover,
.cc-conf__tab:focus-visible {
    color: var(--cc-text);
}

.cc-conf__tab.is-activa {
    border-bottom-color: var(--cc-accent);
    color: var(--cc-text);
}

.cc-conf__panel {
    padding: 20px;
}

.cc-conf__form {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.cc-conf__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
}

.cc-conf__full {
    grid-column: 1 / -1;
}

.cc-conf__logo {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-end;
}

.cc-conf__logo-preview {
    display: grid;
    width: 120px;
    height: 120px;
    place-items: center;
    overflow: hidden;
    border: 1px dashed var(--cc-border);
    border-radius: 12px;
    color: var(--cc-muted);
    font-size: 12px;
}

.cc-conf__logo-preview img {
    max-width: 100%;
    max-height: 100%;
    object-fit: contain;
}

.cc-conf__logo-actions {
    display: flex;
    flex: 1 1 200px;
    flex-direction: column;
    gap: 10px;
    min-width: 0;
}

.cc-conf__logo-actions input[type='file'] {
    max-width: 100%;
}

.cc-conf__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
    justify-content: flex-end;
}

.cc-conf__maps {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    margin-right: auto;
    color: var(--cc-accent);
    font-size: 14px;
}

.cc-conf__switch {
    position: relative;
    display: inline-flex;
    cursor: pointer;
}

.cc-conf__switch input {
    position: absolute;
    opacity: 0;
    inset: 0;
    cursor: pointer;
}

.cc-conf__switch span {
    width: 38px;
    height: 22px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--cc-muted) 35%, transparent);
    transition: background 140ms ease;
}

.cc-conf__switch span::after {
    content: '';
    display: block;
    width: 16px;
    height: 16px;
    margin: 3px;
    border-radius: 50%;
    background: var(--cc-text);
    transition: transform 140ms ease;
}

.cc-conf__switch input:checked + span {
    background: var(--cc-accent);
}

.cc-conf__switch input:checked + span::after {
    transform: translateX(16px);
}

.cc-conf__switch input:focus-visible + span {
    outline: 2px solid var(--cc-accent);
    outline-offset: 2px;
}

.cc-modal-error {
    margin: 4px 0 0;
    color: var(--cc-error);
    font-size: 12px;
}

.cc-req {
    color: var(--cc-error);
}

@media (max-width: 720px) {
    .cc-conf__grid {
        grid-template-columns: minmax(0, 1fr);
    }
}
</style>
