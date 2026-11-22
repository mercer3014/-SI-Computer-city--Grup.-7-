<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Bookmark,
    ChevronDown,
    Download,
    ExternalLink,
    FileSpreadsheet,
    FileText,
    FileType2,
    MessageSquarePlus,
    Play,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import GraficoReporte from '@/components/reportes/GraficoReporte.vue';
import TablaReporte from '@/components/reportes/TablaReporte.vue';
import BotonMicrofono from '@/components/voz/BotonMicrofono.vue';
import TranscripcionEnVivo from '@/components/voz/TranscripcionEnVivo.vue';
import { useDictado } from '@/composables/useDictado';
import { enviarJson } from '@/lib/http';
import type { ResultadoReporte } from '@/types/reportes';

type Mensaje = {
    id: number;
    rol: 'USUARIO' | 'ASISTENTE';
    tipo: 'TEXTO' | 'CONSULTA' | 'REPORTE' | 'ERROR' | 'NO_SOPORTADO';
    origen: 'TEXTO' | 'VOZ';
    contenido: string;
    interpretacion: string | null;
    enlace: string | null;
    resultado: ResultadoReporte | null;
    fecha: string | null;
    /** Solo en el navegador: el servidor no está disponible (EX-02). */
    ofrecerReportes?: boolean;
};

const props = defineProps<{
    conversacion: { id: number; titulo: string | null } | null;
    mensajes: Mensaje[];
    conversaciones: { id: number; titulo: string; fecha: string | null }[];
    plantillas: { id: number; nombre: string }[];
    ejemplos: string[];
    limitePorMinuto: number;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Asistente virtual', href: '/asistente' }],
    },
});

const FORMATOS = [
    { id: 'pdf', etiqueta: 'PDF', icono: FileText },
    { id: 'xlsx', etiqueta: 'Excel (.xlsx)', icono: FileSpreadsheet },
    { id: 'docx', etiqueta: 'Word (.docx)', icono: FileType2 },
] as const;

const mensajes = ref<Mensaje[]>([...props.mensajes]);
const conversacionId = ref<number | null>(props.conversacion?.id ?? null);
const plantillas = ref([...props.plantillas]);
const texto = ref('');
const origen = ref<'TEXTO' | 'VOZ'>('TEXTO');
const enviando = ref(false);
const avisoError = ref<string | null>(null);
const espera = ref(0);
const contenedor = ref<HTMLElement | null>(null);
let relojEspera = 0;
let contadorLocal = 0;

watch(
    () => props.mensajes,
    (nuevos) => {
        mensajes.value = [...nuevos];
    },
);

watch(
    () => props.conversacion?.id,
    (id) => {
        conversacionId.value = id ?? null;
    },
);

function bajar(): void {
    void nextTick(() => {
        contenedor.value?.scrollTo({
            top: contenedor.value.scrollHeight,
            behavior: 'smooth',
        });
    });
}

watch(mensajes, bajar, { deep: true });

/* ── Voz ── */

const dictado = useDictado();

watch(dictado.textoFinal, (dictado_) => {
    if (dictado_ !== '') {
        texto.value = dictado_;
        origen.value = 'VOZ';
    }
});

const mensajeDeVoz = computed(() => {
    switch (dictado.error.value) {
        case 'sin-audio':
        case 'no-reconocido':
            return 'No se pudo reconocer el audio, intenta de nuevo o escribe tu consulta.';
        case 'red':
            return 'No se pudo conectar con el servicio de voz. Escribe tu consulta.';
        default:
            return null;
    }
});

const sinVoz = computed(
    () =>
        !dictado.soportado.value &&
        (dictado.error.value === 'no-soportado' ||
            dictado.error.value === 'permiso-denegado'),
);

function alternarDictado(): void {
    if (dictado.escuchando.value) {
        dictado.detener();

        return;
    }

    dictado.iniciar();
}

/* ── Envío ── */

function iniciarEspera(segundos: number): void {
    window.clearInterval(relojEspera);
    espera.value = segundos;
    relojEspera = window.setInterval(() => {
        espera.value -= 1;

        if (espera.value <= 0) {
            window.clearInterval(relojEspera);
            espera.value = 0;
            avisoError.value = null;
        }
    }, 1000);
}

onBeforeUnmount(() => window.clearInterval(relojEspera));

function mensajeLocal(
    rol: Mensaje['rol'],
    contenido: string,
    tipo: Mensaje['tipo'] = 'TEXTO',
): Mensaje {
    contadorLocal -= 1;

    return {
        id: contadorLocal,
        rol,
        tipo,
        origen: rol === 'USUARIO' ? origen.value : 'TEXTO',
        contenido,
        interpretacion: null,
        enlace: null,
        resultado: null,
        fecha: null,
    };
}

function fijarConversacion(id: number): void {
    if (conversacionId.value === id) {
        return;
    }

    conversacionId.value = id;
    window.history.replaceState(
        window.history.state,
        '',
        `/asistente/conversaciones/${id}`,
    );
}

async function enviar(contenido: string = texto.value): Promise<void> {
    const limpio = contenido.trim();

    if (limpio === '' || enviando.value || espera.value > 0) {
        return;
    }

    enviando.value = true;
    avisoError.value = null;

    const provisional = mensajeLocal('USUARIO', limpio);
    mensajes.value.push(provisional);
    texto.value = '';
    const origenUsado = origen.value;
    origen.value = 'TEXTO';
    dictado.limpiar();

    try {
        const respuesta = await enviarJson('/asistente/mensajes', {
            conversacion_id: conversacionId.value,
            contenido: limpio,
            origen: origenUsado,
        });
        const datos = (await respuesta.json().catch(() => ({}))) as Record<
            string,
            unknown
        >;

        if (respuesta.status === 429) {
            const segundos = Number(datos.retry_after ?? 60);
            mensajes.value = mensajes.value.filter(
                (m) => m.id !== provisional.id,
            );
            texto.value = limpio;
            avisoError.value = String(
                datos.message ?? 'Superaste el límite de consultas por minuto.',
            );
            iniciarEspera(segundos);

            return;
        }

        if (respuesta.status === 403) {
            mensajes.value = mensajes.value.filter(
                (m) => m.id !== provisional.id,
            );
            avisoError.value =
                'No tienes permiso para usar el asistente virtual.';

            return;
        }

        if (respuesta.status === 422) {
            mensajes.value = mensajes.value.filter(
                (m) => m.id !== provisional.id,
            );
            texto.value = limpio;
            const errores = (datos.errors ?? {}) as Record<string, string[]>;
            avisoError.value =
                Object.values(errores)[0]?.[0] ?? 'La consulta no es válida.';

            return;
        }

        const usuario = datos.mensaje_usuario as Mensaje | undefined;
        const asistente = datos.mensaje_asistente as Mensaje | undefined;

        if (usuario) {
            mensajes.value = mensajes.value.map((m) =>
                m.id === provisional.id ? usuario : m,
            );
        }

        if (typeof datos.conversacion_id === 'number') {
            fijarConversacion(datos.conversacion_id);
        }

        if (asistente) {
            mensajes.value.push({
                ...asistente,
                ofrecerReportes: respuesta.status === 503,
            });
        } else {
            mensajes.value.push(
                mensajeLocal(
                    'ASISTENTE',
                    'No se pudo completar la consulta. Inténtalo de nuevo.',
                    'ERROR',
                ),
            );
        }
    } catch {
        mensajes.value.push(
            mensajeLocal(
                'ASISTENTE',
                'No se pudo conectar con el servidor. Revisa tu conexión e inténtalo de nuevo.',
                'ERROR',
            ),
        );
    } finally {
        enviando.value = false;
    }
}

function alTeclear(evento: KeyboardEvent): void {
    if (evento.key === 'Enter' && !evento.shiftKey) {
        evento.preventDefault();
        void enviar();
    }
}

/* ── Exportar y plantillas ── */

const menuAbierto = ref<number | null>(null);
const guardando = ref<number | null>(null);
const nombrePlantilla = ref('');
const errorPlantilla = ref<string | null>(null);

function exportar(mensaje: Mensaje, formato: string): void {
    menuAbierto.value = null;
    window.location.assign(
        `/asistente/mensajes/${mensaje.id}/exportar?formato=${formato}`,
    );
}

function abrirPlantilla(mensaje: Mensaje): void {
    guardando.value = guardando.value === mensaje.id ? null : mensaje.id;
    nombrePlantilla.value = '';
    errorPlantilla.value = null;
}

async function guardarPlantilla(mensaje: Mensaje): Promise<void> {
    errorPlantilla.value = null;

    const respuesta = await enviarJson('/asistente/plantillas', {
        mensaje_id: mensaje.id,
        nombre: nombrePlantilla.value,
    });
    const datos = (await respuesta.json().catch(() => ({}))) as Record<
        string,
        unknown
    >;

    if (respuesta.status === 201) {
        plantillas.value.push({
            id: Number(datos.id),
            nombre: String(datos.nombre),
        });
        plantillas.value.sort((a, b) => a.nombre.localeCompare(b.nombre));
        guardando.value = null;

        return;
    }

    const errores = (datos.errors ?? {}) as Record<string, string[]>;
    errorPlantilla.value =
        Object.values(errores)[0]?.[0] ?? 'No se pudo guardar la plantilla.';
}

async function ejecutarPlantilla(id: number): Promise<void> {
    if (enviando.value) {
        return;
    }

    enviando.value = true;

    try {
        const respuesta = await enviarJson(
            `/asistente/plantillas/${id}/ejecutar`,
            {
                conversacion_id: conversacionId.value,
            },
        );
        const datos = (await respuesta.json().catch(() => ({}))) as Record<
            string,
            unknown
        >;

        if (respuesta.status === 429) {
            avisoError.value = String(
                datos.message ?? 'Superaste el límite de consultas por minuto.',
            );
            iniciarEspera(Number(datos.retry_after ?? 60));

            return;
        }

        const usuario = datos.mensaje_usuario as Mensaje | undefined;
        const asistente = datos.mensaje_asistente as Mensaje | undefined;

        if (usuario && asistente && typeof datos.conversacion_id === 'number') {
            mensajes.value.push(usuario, asistente);
            fijarConversacion(datos.conversacion_id);
        }
    } finally {
        enviando.value = false;
    }
}

async function eliminarPlantilla(id: number): Promise<void> {
    const respuesta = await enviarJson(
        `/asistente/plantillas/${id}`,
        {},
        'DELETE',
    );

    if (respuesta.ok) {
        plantillas.value = plantillas.value.filter((p) => p.id !== id);
    }
}

function nuevaConversacion(): void {
    router.get('/asistente');
}

const monedaDe = (resultado: ResultadoReporte): boolean =>
    resultado.columnas.some((c) => c.tipo === 'moneda');

const dosPartes = (contenido: string): string[] => contenido.split('\n');
</script>

<template>
    <div>
        <Head title="Asistente virtual" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log cc-asi">
            <aside class="cc-asi__lateral">
                <button
                    type="button"
                    class="cc-button cc-button--ghost cc-asi__nueva"
                    data-test="nueva-conversacion"
                    @click="nuevaConversacion"
                >
                    <MessageSquarePlus :size="16" />
                    Nueva conversación
                </button>

                <h2>Conversaciones</h2>
                <p v-if="conversaciones.length === 0" class="cc-asi__vacio">
                    Aún no hay conversaciones.
                </p>
                <ul v-else class="cc-asi__lista">
                    <li v-for="c in conversaciones" :key="c.id">
                        <Link
                            :href="`/asistente/conversaciones/${c.id}`"
                            :class="{ 'is-activa': c.id === conversacionId }"
                        >
                            <strong>{{ c.titulo }}</strong>
                            <small>{{ c.fecha }}</small>
                        </Link>
                    </li>
                </ul>

                <h2>Plantillas</h2>
                <p v-if="plantillas.length === 0" class="cc-asi__vacio">
                    Guarda un reporte como plantilla para repetirlo.
                </p>
                <ul v-else class="cc-asi__lista" data-test="plantillas">
                    <li
                        v-for="p in plantillas"
                        :key="p.id"
                        class="cc-asi__plantilla"
                    >
                        <span>{{ p.nombre }}</span>
                        <span class="cc-asi__acciones">
                            <button
                                type="button"
                                :aria-label="`Ejecutar la plantilla ${p.nombre}`"
                                :disabled="enviando"
                                @click="ejecutarPlantilla(p.id)"
                            >
                                <Play :size="14" />
                            </button>
                            <button
                                type="button"
                                :aria-label="`Eliminar la plantilla ${p.nombre}`"
                                @click="eliminarPlantilla(p.id)"
                            >
                                <Trash2 :size="14" />
                            </button>
                        </span>
                    </li>
                </ul>
            </aside>

            <div class="cc-asi__chat">
                <div
                    ref="contenedor"
                    class="cc-asi__mensajes"
                    aria-live="polite"
                    data-test="mensajes"
                >
                    <div
                        v-if="mensajes.length === 0"
                        class="cc-asi__bienvenida"
                        data-test="bienvenida"
                    >
                        <h1>Asistente virtual</h1>
                        <p>
                            Hola, soy tu analista de datos. Pregúntame por las
                            ventas, la utilidad, los gastos, las compras, la
                            caja, el stock, los pedidos, las cotizaciones o las
                            calificaciones. Puedes escribir o dictar.
                        </p>
                        <p class="cc-asi__ejemplos-titulo">Prueba con:</p>
                        <div class="cc-asi__ejemplos">
                            <button
                                v-for="(ejemplo, indice) in ejemplos"
                                :key="ejemplo"
                                type="button"
                                :data-test="`ejemplo-${indice}`"
                                :disabled="enviando"
                                @click="enviar(ejemplo)"
                            >
                                {{ ejemplo }}
                            </button>
                        </div>
                    </div>

                    <article
                        v-for="mensaje in mensajes"
                        :key="mensaje.id"
                        class="cc-asi__burbuja"
                        :class="[
                            mensaje.rol === 'USUARIO'
                                ? 'es-usuario'
                                : 'es-asistente',
                            `tipo-${mensaje.tipo.toLowerCase()}`,
                        ]"
                        :data-test="
                            mensaje.rol === 'USUARIO'
                                ? 'mensaje-usuario'
                                : 'mensaje-asistente'
                        "
                    >
                        <p
                            v-if="
                                mensaje.rol === 'USUARIO' &&
                                mensaje.origen === 'VOZ'
                            "
                            class="cc-asi__origen"
                        >
                            Dictado por voz
                        </p>

                        <p
                            v-if="
                                mensaje.tipo === 'ERROR' ||
                                mensaje.tipo === 'NO_SOPORTADO'
                            "
                            class="cc-asi__aviso"
                            role="status"
                        >
                            <AlertTriangle :size="16" />
                            <span>{{ mensaje.contenido }}</span>
                        </p>
                        <template v-else>
                            <p
                                v-for="(linea, indice) in dosPartes(
                                    mensaje.contenido,
                                )"
                                :key="indice"
                                class="cc-asi__texto"
                            >
                                {{ linea }}
                            </p>
                        </template>

                        <Link
                            v-if="mensaje.ofrecerReportes"
                            href="/reportes"
                            class="cc-button cc-button--ghost cc-asi__ir"
                            data-test="ir-a-reportes"
                        >
                            Ir a Reportes
                        </Link>

                        <a
                            v-if="mensaje.enlace && mensaje.tipo !== 'ERROR'"
                            :href="mensaje.enlace"
                            class="cc-asi__enlace"
                            data-test="enlace-modulo"
                        >
                            <ExternalLink :size="14" />
                            Ver en el módulo
                        </a>

                        <div
                            v-if="
                                mensaje.tipo === 'REPORTE' && mensaje.resultado
                            "
                            class="cc-asi__reporte"
                            data-test="reporte"
                        >
                            <p
                                v-if="mensaje.interpretacion"
                                class="cc-asi__chip"
                                data-test="interpretacion"
                            >
                                Se muestra: {{ mensaje.interpretacion }}
                            </p>

                            <div
                                v-for="aviso in mensaje.resultado.advertencias"
                                :key="aviso"
                                class="cc-asi__alerta"
                                role="alert"
                            >
                                <AlertTriangle :size="14" />
                                {{ aviso }}
                            </div>

                            <template v-if="!mensaje.resultado.vacio">
                                <GraficoReporte
                                    :grafico="mensaje.resultado.grafico"
                                    :moneda="monedaDe(mensaje.resultado)"
                                />
                                <TablaReporte :resultado="mensaje.resultado" />
                            </template>

                            <div class="cc-asi__herramientas">
                                <div class="cc-asi__exportar">
                                    <button
                                        type="button"
                                        class="cc-button cc-button--ghost"
                                        :disabled="mensaje.resultado.vacio"
                                        aria-haspopup="menu"
                                        :aria-expanded="
                                            menuAbierto === mensaje.id
                                        "
                                        data-test="exportar"
                                        @click="
                                            menuAbierto =
                                                menuAbierto === mensaje.id
                                                    ? null
                                                    : mensaje.id
                                        "
                                    >
                                        <Download :size="16" />
                                        Exportar
                                        <ChevronDown :size="14" />
                                    </button>
                                    <ul
                                        v-if="
                                            menuAbierto === mensaje.id &&
                                            !mensaje.resultado.vacio
                                        "
                                        class="cc-asi__menu"
                                        role="menu"
                                        data-test="menu-exportar"
                                    >
                                        <li
                                            v-for="formato in FORMATOS"
                                            :key="formato.id"
                                            role="none"
                                        >
                                            <button
                                                type="button"
                                                role="menuitem"
                                                :data-test="`exportar-${formato.id}`"
                                                @click="
                                                    exportar(
                                                        mensaje,
                                                        formato.id,
                                                    )
                                                "
                                            >
                                                <component
                                                    :is="formato.icono"
                                                    :size="16"
                                                />
                                                {{ formato.etiqueta }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>

                                <button
                                    type="button"
                                    class="cc-button cc-button--ghost"
                                    :disabled="mensaje.resultado.vacio"
                                    data-test="guardar-plantilla"
                                    @click="abrirPlantilla(mensaje)"
                                >
                                    <Bookmark :size="16" />
                                    Guardar como plantilla
                                </button>
                            </div>

                            <form
                                v-if="guardando === mensaje.id"
                                class="cc-asi__plantilla-form"
                                @submit.prevent="guardarPlantilla(mensaje)"
                            >
                                <span class="cc-log__control">
                                    <input
                                        v-model="nombrePlantilla"
                                        type="text"
                                        maxlength="100"
                                        placeholder="Nombre de la plantilla"
                                        aria-label="Nombre de la plantilla"
                                        data-test="nombre-plantilla"
                                    />
                                </span>
                                <button
                                    type="submit"
                                    class="cc-button"
                                    data-test="confirmar-plantilla"
                                >
                                    Guardar
                                </button>
                                <p v-if="errorPlantilla" class="cc-asi__error">
                                    {{ errorPlantilla }}
                                </p>
                            </form>
                        </div>

                        <small v-if="mensaje.fecha" class="cc-asi__fecha">{{
                            mensaje.fecha
                        }}</small>
                    </article>

                    <p
                        v-if="enviando"
                        class="cc-asi__pensando"
                        data-test="pensando"
                    >
                        <span class="cc-asi__puntos"><i /><i /><i /></span>
                        Pensando…
                    </p>
                </div>

                <form class="cc-asi__entrada" @submit.prevent="enviar()">
                    <TranscripcionEnVivo
                        :parcial="dictado.textoParcial.value"
                        :final="dictado.textoFinal.value"
                        :escuchando="dictado.escuchando.value"
                    />

                    <p
                        v-if="avisoError"
                        class="cc-asi__error"
                        role="alert"
                        data-test="aviso-error"
                    >
                        {{ avisoError }}
                        <template v-if="espera > 0">
                            <strong data-test="cuenta-regresiva">{{
                                espera
                            }}</strong>
                        </template>
                    </p>
                    <p
                        v-else-if="mensajeDeVoz"
                        class="cc-asi__error"
                        role="alert"
                        data-test="error-voz"
                    >
                        {{ mensajeDeVoz }}
                    </p>
                    <p
                        v-if="sinVoz"
                        class="cc-asi__nota"
                        data-test="nota-sin-voz"
                    >
                        Tu navegador no permite dictado por voz; usa la entrada
                        de texto.
                    </p>

                    <div class="cc-asi__fila">
                        <BotonMicrofono
                            :escuchando="dictado.escuchando.value"
                            :soportado="dictado.soportado.value"
                            @alternar="alternarDictado"
                        />
                        <textarea
                            v-model="texto"
                            rows="2"
                            maxlength="1000"
                            placeholder="Escribe tu consulta o usa el micrófono…"
                            aria-label="Consulta para el asistente"
                            data-test="asistente-entrada"
                            @keydown="alTeclear"
                        />
                        <button
                            type="submit"
                            class="cc-button"
                            :disabled="
                                enviando || espera > 0 || texto.trim() === ''
                            "
                            data-test="asistente-enviar"
                        >
                            <Send :size="16" />
                            Enviar
                        </button>
                    </div>
                    <small class="cc-asi__pie">
                        Enter envía, Mayús + Enter agrega una línea. Hasta
                        {{ limitePorMinuto }} consultas por minuto.
                    </small>
                </form>
            </div>
        </section>
    </div>
</template>

<style scoped>
.cc-asi {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    gap: 18px;
    align-items: start;
}

.cc-asi__lateral {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 16px;
    border: 1px solid var(--cc-border);
    border-radius: 14px;
}

.cc-asi__lateral h2 {
    margin: 10px 0 0;
    color: var(--cc-muted);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.cc-asi__nueva {
    width: 100%;
    justify-content: center;
}

.cc-asi__vacio {
    margin: 0;
    color: var(--cc-muted);
    font-size: 13px;
}

.cc-asi__lista {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.cc-asi__lista a {
    display: flex;
    flex-direction: column;
    padding: 8px 10px;
    border-radius: 8px;
    color: var(--cc-text);
    font-size: 13px;
    text-decoration: none;
}

.cc-asi__lista a small {
    color: var(--cc-muted);
    font-size: 11px;
}

.cc-asi__lista a:hover,
.cc-asi__lista a.is-activa {
    background: color-mix(in srgb, var(--cc-accent) 12%, transparent);
}

.cc-asi__plantilla {
    display: flex;
    gap: 6px;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    font-size: 13px;
}

.cc-asi__acciones {
    display: inline-flex;
    gap: 2px;
}

.cc-asi__acciones button {
    padding: 4px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: var(--cc-muted);
    cursor: pointer;
}

.cc-asi__acciones button:hover,
.cc-asi__acciones button:focus-visible {
    background: color-mix(in srgb, var(--cc-accent) 14%, transparent);
    color: var(--cc-text);
}

.cc-asi__chat {
    display: flex;
    flex-direction: column;
    min-height: 70vh;
    border: 1px solid var(--cc-border);
    border-radius: 14px;
}

.cc-asi__mensajes {
    display: flex;
    flex: 1;
    flex-direction: column;
    gap: 14px;
    max-height: calc(100vh - 290px);
    min-height: 360px;
    padding: 20px;
    overflow-y: auto;
}

.cc-asi__bienvenida {
    display: flex;
    flex-direction: column;
    gap: 10px;
    max-width: 640px;
    margin: auto;
    text-align: center;
}

.cc-asi__bienvenida h1 {
    margin: 0;
    font-size: 26px;
}

.cc-asi__bienvenida p {
    margin: 0;
    color: var(--cc-muted);
}

.cc-asi__ejemplos-titulo {
    margin-top: 8px !important;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.cc-asi__ejemplos {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.cc-asi__ejemplos button {
    padding: 10px 14px;
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    background: transparent;
    color: var(--cc-text);
    font: inherit;
    font-size: 14px;
    cursor: pointer;
    transition: border-color 140ms ease;
}

.cc-asi__ejemplos button:hover,
.cc-asi__ejemplos button:focus-visible {
    border-color: var(--cc-accent);
}

.cc-asi__burbuja {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-width: min(900px, 94%);
    padding: 12px 16px;
    border-radius: 14px;
}

.cc-asi__burbuja.es-usuario {
    align-self: flex-end;
    background: color-mix(in srgb, var(--cc-accent) 16%, transparent);
}

.cc-asi__burbuja.es-asistente {
    align-self: flex-start;
    width: 100%;
    border: 1px solid var(--cc-border);
}

.cc-asi__texto {
    margin: 0;
    line-height: 1.5;
}

.cc-asi__origen {
    margin: 0;
    color: var(--cc-muted);
    font-size: 11px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.cc-asi__aviso {
    display: flex;
    gap: 8px;
    align-items: flex-start;
    margin: 0;
    color: var(--cc-text);
}

.cc-asi__burbuja.tipo-error .cc-asi__aviso svg {
    color: var(--cc-error);
}

.cc-asi__burbuja.tipo-no_soportado .cc-asi__aviso svg {
    color: #c27803;
}

.cc-asi__ir {
    align-self: flex-start;
}

.cc-asi__enlace {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    align-self: flex-start;
    color: var(--cc-accent);
    font-size: 13px;
    text-decoration: underline;
}

.cc-asi__reporte {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.cc-asi__chip {
    align-self: flex-start;
    margin: 0;
    padding: 5px 12px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--cc-accent) 14%, transparent);
    color: var(--cc-text);
    font-size: 13px;
}

.cc-asi__alerta {
    display: flex;
    gap: 6px;
    align-items: center;
    padding: 8px 12px;
    border: 1px solid #f0c36d;
    border-radius: 8px;
    background: #fff7e0;
    color: #7a4a05;
    font-size: 12px;
}

.cc-asi__herramientas {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.cc-asi__exportar {
    position: relative;
}

.cc-asi__menu {
    position: absolute;
    bottom: calc(100% + 6px);
    left: 0;
    z-index: 20;
    min-width: 190px;
    margin: 0;
    padding: 6px;
    list-style: none;
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    background: var(--cc-surface, #fff);
    box-shadow: 0 12px 30px rgb(0 0 0 / 18%);
}

.cc-asi__menu button {
    display: flex;
    gap: 10px;
    align-items: center;
    width: 100%;
    padding: 9px 10px;
    border: 0;
    border-radius: 8px;
    background: transparent;
    color: var(--cc-text);
    font: inherit;
    font-size: 14px;
    cursor: pointer;
}

.cc-asi__menu button:hover,
.cc-asi__menu button:focus-visible {
    background: color-mix(in srgb, var(--cc-accent) 12%, transparent);
}

.cc-asi__plantilla-form {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}

.cc-asi__plantilla-form .cc-log__control {
    flex: 1;
    min-width: 200px;
}

.cc-asi__fecha {
    align-self: flex-end;
    color: var(--cc-muted);
    font-size: 11px;
}

.cc-asi__pensando {
    display: flex;
    gap: 10px;
    align-items: center;
    margin: 0;
    color: var(--cc-muted);
    font-size: 14px;
}

.cc-asi__puntos {
    display: inline-flex;
    gap: 4px;
}

.cc-asi__puntos i {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: var(--cc-accent);
    animation: cc-asi-punto 1s ease-in-out infinite;
}

.cc-asi__puntos i:nth-child(2) {
    animation-delay: 0.15s;
}

.cc-asi__puntos i:nth-child(3) {
    animation-delay: 0.3s;
}

@keyframes cc-asi-punto {
    0%,
    80%,
    100% {
        opacity: 0.25;
    }

    40% {
        opacity: 1;
    }
}

.cc-asi__entrada {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 14px 16px 12px;
    border-top: 1px solid var(--cc-border);
}

.cc-asi__fila {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.cc-asi__fila textarea {
    flex: 1;
    min-height: 52px;
    padding: 10px 14px;
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    background: transparent;
    color: var(--cc-text);
    font: inherit;
    resize: vertical;
}

.cc-asi__fila textarea:focus-visible {
    border-color: var(--cc-accent);
    outline: 0;
}

.cc-asi__error {
    margin: 0;
    color: var(--cc-error);
    font-size: 13px;
}

.cc-asi__nota,
.cc-asi__pie {
    margin: 0;
    color: var(--cc-muted);
    font-size: 12px;
}

@media (max-width: 960px) {
    .cc-asi {
        grid-template-columns: minmax(0, 1fr);
    }

    .cc-asi__mensajes {
        max-height: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .cc-asi__puntos i {
        animation: none;
    }
}
</style>
