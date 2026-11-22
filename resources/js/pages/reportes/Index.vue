<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    BarChart3,
    Check,
    ChevronDown,
    Download,
    FileSpreadsheet,
    FileText,
    FileType2,
    Search,
    Sparkles,
    X,
} from '@lucide/vue';
import { motion } from 'motion-v';
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import GraficoReporte from '@/components/reportes/GraficoReporte.vue';
import TablaReporte from '@/components/reportes/TablaReporte.vue';
import BotonMicrofono from '@/components/voz/BotonMicrofono.vue';
import TranscripcionEnVivo from '@/components/voz/TranscripcionEnVivo.vue';
import { MENSAJES_DICTADO, useDictado } from '@/composables/useDictado';
import { adminSpring } from '@/lib/adminMotion';
import { enviarJson } from '@/lib/http';
import type { ParametrosReporte, ResultadoReporte } from '@/types/reportes';

type Opcion = { id: number; nombre: string };

type Reporte = {
    clave: string;
    titulo: string;
    descripcion: string;
    filtros: string[];
};

type Interpretacion = {
    tipo: string | null;
    periodo: string | null;
    desde: string | null;
    hasta: string | null;
    formato: string | null;
    filtros: Record<string, number>;
    entendido: string;
    faltantes: string[];
    es_predefinido: boolean;
};

const props = defineProps<{
    catalogo: Reporte[];
    opciones: {
        categorias: Opcion[];
        marcas: Opcion[];
        proveedores: Opcion[];
        categorias_gasto: Opcion[];
        metodos_pago: Opcion[];
    };
    parametros: ParametrosReporte | null;
    resultado: ResultadoReporte | null;
    hoy: string;
    puedeAsistente: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Reportes', href: '/reportes' }],
    },
});

const page = usePage();
const errores = computed(
    () => (page.props.errors ?? {}) as Record<string, string | undefined>,
);

const PERIODOS = [
    { id: 'diario', etiqueta: 'Diario' },
    { id: 'semanal', etiqueta: 'Semanal' },
    { id: 'mensual', etiqueta: 'Mensual' },
    { id: 'personalizado', etiqueta: 'Rango de fechas' },
] as const;

const FILTROS: Record<
    string,
    { etiqueta: string; lista: keyof typeof props.opciones }
> = {
    categoria_id: { etiqueta: 'Categoría', lista: 'categorias' },
    marca_id: { etiqueta: 'Marca', lista: 'marcas' },
    metodo_pago_id: { etiqueta: 'Método de pago', lista: 'metodos_pago' },
    proveedor_id: { etiqueta: 'Proveedor', lista: 'proveedores' },
    categoria_gasto_id: {
        etiqueta: 'Categoría de gasto',
        lista: 'categorias_gasto',
    },
};

const OPCIONES_AGRUPAR = [
    { value: '', label: 'Automático' },
    { value: 'dia', label: 'Por día' },
    { value: 'semana', label: 'Por semana' },
    { value: 'mes', label: 'Por mes' },
];

const FORMATOS = [
    { id: 'pdf', etiqueta: 'PDF', icono: FileText },
    { id: 'xlsx', etiqueta: 'Excel (.xlsx)', icono: FileSpreadsheet },
    { id: 'docx', etiqueta: 'Word (.docx)', icono: FileType2 },
] as const;

const ETIQUETA_FORMATO: Record<string, string> = {
    pdf: 'PDF',
    xlsx: 'Excel',
    docx: 'Word',
};

const inicial = props.parametros;

const form = reactive({
    tipo: inicial?.tipo ?? 'ventas_periodo',
    periodo: inicial?.periodo ?? 'mensual',
    desde: inicial?.desde ?? props.hoy,
    hasta: inicial?.hasta ?? props.hoy,
    categoria_id: String(inicial?.filtros.categoria_id ?? ''),
    marca_id: String(inicial?.filtros.marca_id ?? ''),
    metodo_pago_id: String(inicial?.filtros.metodo_pago_id ?? ''),
    proveedor_id: String(inicial?.filtros.proveedor_id ?? ''),
    categoria_gasto_id: String(inicial?.filtros.categoria_gasto_id ?? ''),
    agrupar: String(inicial?.filtros.agrupar ?? ''),
    limite: String(inicial?.filtros.limite ?? ''),
});

const reporteElegido = computed(
    () =>
        props.catalogo.find((r) => r.clave === form.tipo) ?? props.catalogo[0],
);

const filtrosVisibles = computed(() =>
    (reporteElegido.value?.filtros ?? []).filter((clave) => clave in FILTROS),
);

const permiteAgrupar = computed(() =>
    (reporteElegido.value?.filtros ?? []).includes('agrupar'),
);
const permiteLimite = computed(() =>
    (reporteElegido.value?.filtros ?? []).includes('limite'),
);

function opcionesDe(clave: string): { value: string; label: string }[] {
    const definicion = FILTROS[clave];

    if (!definicion) {
        return [];
    }

    return [
        { value: '', label: 'Todos' },
        ...props.opciones[definicion.lista].map((o) => ({
            value: String(o.id),
            label: o.nombre,
        })),
    ];
}

function valorDe(clave: string): string {
    return (form as Record<string, string>)[clave] ?? '';
}

function cambiarFiltro(clave: string, valor: string): void {
    (form as Record<string, string>)[clave] = valor;
}

function consulta(): Record<string, string> {
    const query: Record<string, string> = {
        tipo: form.tipo,
        periodo: form.periodo,
        desde: form.desde,
    };

    if (form.periodo === 'personalizado') {
        query.hasta = form.hasta;
    }

    for (const clave of reporteElegido.value?.filtros ?? []) {
        const valor = valorDe(clave);

        if (valor !== '') {
            query[clave] = valor;
        }
    }

    return query;
}

const generando = ref(false);

function generar(alTerminar?: () => void): void {
    generando.value = true;

    router.get('/reportes/generar', consulta(), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => alTerminar?.(),
        onFinish: () => {
            generando.value = false;
        },
    });
}

function elegirReporte(clave: string): void {
    form.tipo = clave;

    for (const filtro of Object.keys(FILTROS)) {
        if (!(reporteElegido.value?.filtros ?? []).includes(filtro)) {
            cambiarFiltro(filtro, '');
        }
    }
}

const exportarAbierto = ref(false);

function urlExportar(formato: string): string {
    if (!props.resultado) {
        return '#';
    }

    const parametros = props.resultado.parametros;
    const query = new URLSearchParams({
        tipo: parametros.tipo,
        periodo: 'personalizado',
        desde: parametros.desde,
        hasta: parametros.hasta,
        formato,
    });

    for (const [clave, valor] of Object.entries(parametros.filtros)) {
        query.set(clave, String(valor));
    }

    return `/reportes/exportar?${query.toString()}`;
}

function descargar(formato: string): void {
    exportarAbierto.value = false;
    window.location.assign(urlExportar(formato));
}

/* ── Voz ── */

const dictado = useDictado();
const textoVoz = ref('');
const interpretando = ref(false);
const entendido = ref<Interpretacion | null>(null);
const errorVoz = ref<string | null>(null);
const resaltados = ref<string[]>([]);
let temporizadorResalte = 0;

watch(dictado.textoFinal, (texto) => {
    if (texto !== '') {
        textoVoz.value = texto;
        void interpretar();
    }
});

watch(dictado.error, (error) => {
    errorVoz.value = error ? MENSAJES_DICTADO[error] : null;
});

const mostrarNotaSinVoz = computed(
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

    entendido.value = null;
    textoVoz.value = '';
    dictado.iniciar();
}

async function interpretar(): Promise<void> {
    const texto = textoVoz.value.trim();

    if (texto === '' || interpretando.value) {
        return;
    }

    interpretando.value = true;
    errorVoz.value = null;

    try {
        const respuesta = await enviarJson('/reportes/interpretar-voz', {
            texto,
        });

        if (!respuesta.ok) {
            errorVoz.value =
                'No se pudo interpretar lo que dijiste. Usa el formulario.';

            return;
        }

        aplicar((await respuesta.json()) as Interpretacion);
    } catch {
        errorVoz.value =
            'No se pudo interpretar lo que dijiste. Usa el formulario.';
    } finally {
        interpretando.value = false;
    }
}

/** Llena el formulario con lo entendido y resalta los campos, pero no genera nada. */
function aplicar(datos: Interpretacion): void {
    const llenados: string[] = [];

    if (datos.tipo) {
        elegirReporte(datos.tipo);
        llenados.push('tipo');
    }

    if (datos.periodo && datos.desde && datos.hasta) {
        form.periodo = datos.periodo;
        form.desde = datos.desde;
        form.hasta = datos.hasta;
        llenados.push('periodo');
    }

    for (const [clave, valor] of Object.entries(datos.filtros)) {
        cambiarFiltro(clave, String(valor));
        llenados.push(clave);
    }

    entendido.value = datos;
    resaltados.value = llenados;
    window.clearTimeout(temporizadorResalte);
    temporizadorResalte = window.setTimeout(() => {
        resaltados.value = [];
    }, 5000);
}

const faltaCompletar = computed(
    () => (entendido.value?.faltantes ?? []).length > 0,
);

const textoFaltantes = computed(() =>
    (entendido.value?.faltantes ?? [])
        .map((f) => (f === 'periodo' ? 'período' : 'tipo de reporte'))
        .join(' y '),
);

function confirmarVoz(): void {
    const formato = entendido.value?.formato ?? null;

    entendido.value = null;
    generar(() => {
        if (formato) {
            descargar(formato);
        }
    });
}

function corregirVoz(): void {
    entendido.value = null;
}

onBeforeUnmount(() => {
    window.clearTimeout(temporizadorResalte);
});

const resaltado = (clave: string): boolean => resaltados.value.includes(clave);

const monedaGrafico = computed(
    () => props.resultado?.clave !== 'productos_mas_vendidos',
);
</script>

<template>
    <div>
        <Head title="Reportes" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Finanzas</p>
                    <h1>Reportes</h1>
                    <p>
                        Elige un reporte, un período y filtros, o dicta lo que
                        necesitas. Puedes exportarlo a PDF, Excel y Word.
                    </p>
                </motion.div>
            </motion.div>

            <!-- ── Voz ── -->
            <motion.div
                layout
                class="cc-log__panel cc-rep__voz"
                :transition="adminSpring"
            >
                <div class="cc-rep__voz-fila">
                    <BotonMicrofono
                        :escuchando="dictado.escuchando.value"
                        :soportado="dictado.soportado.value"
                        @alternar="alternarDictado"
                    />
                    <form
                        class="cc-rep__voz-texto"
                        @submit.prevent="interpretar"
                    >
                        <span class="cc-log__control">
                            <input
                                v-model="textoVoz"
                                type="text"
                                maxlength="500"
                                placeholder="Ej.: productos más vendidos de octubre en Excel"
                                aria-label="Pedido del reporte"
                                data-test="texto-voz"
                            />
                        </span>
                        <button
                            type="submit"
                            class="cc-button cc-button--ghost"
                            :disabled="interpretando || textoVoz.trim() === ''"
                        >
                            <Sparkles :size="16" />
                            Interpretar
                        </button>
                    </form>
                </div>

                <TranscripcionEnVivo
                    :parcial="dictado.textoParcial.value"
                    :final="dictado.textoFinal.value"
                    :escuchando="dictado.escuchando.value"
                />

                <p
                    v-if="mostrarNotaSinVoz"
                    class="cc-rep__nota"
                    data-test="nota-sin-voz"
                >
                    Tu navegador no permite dictado por voz; usa el formulario o
                    la entrada de texto.
                </p>
                <p
                    v-else-if="errorVoz"
                    class="cc-rep__nota cc-rep__nota--error"
                    role="alert"
                >
                    {{ errorVoz }}
                </p>

                <div
                    v-if="entendido"
                    class="cc-rep__entendido"
                    data-test="voz-entendido"
                >
                    <p>
                        <b>Entendí:</b>
                        {{ entendido.entendido || 'nada concreto' }}.
                        <span v-if="faltaCompletar">
                            <b>Completa:</b> {{ textoFaltantes }}.
                        </span>
                        <span v-else>¿Generar?</span>
                    </p>
                    <p v-if="!entendido.es_predefinido && puedeAsistente">
                        Esto no es un reporte predefinido.
                        <Link href="/asistente" class="cc-rep__enlace">
                            Hazlo en el Asistente virtual
                        </Link>
                    </p>
                    <div class="cc-rep__entendido-acciones">
                        <button
                            type="button"
                            class="cc-button"
                            :disabled="faltaCompletar"
                            data-test="voz-confirmar"
                            @click="confirmarVoz"
                        >
                            <Check :size="16" />
                            Confirmar
                        </button>
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            data-test="voz-corregir"
                            @click="corregirVoz"
                        >
                            <X :size="16" />
                            Corregir
                        </button>
                    </div>
                </div>
            </motion.div>

            <!-- ── Formulario ── -->
            <motion.form
                layout
                class="cc-log__panel cc-rep__form"
                :transition="adminSpring"
                @submit.prevent="generar()"
            >
                <fieldset class="cc-rep__tipos">
                    <legend>Tipo de reporte</legend>
                    <button
                        v-for="reporte in catalogo"
                        :key="reporte.clave"
                        type="button"
                        class="cc-rep__tipo"
                        :class="{
                            'is-activo': form.tipo === reporte.clave,
                            'is-llenado':
                                resaltado('tipo') &&
                                form.tipo === reporte.clave,
                        }"
                        :aria-pressed="form.tipo === reporte.clave"
                        :data-test="`tipo-${reporte.clave}`"
                        @click="elegirReporte(reporte.clave)"
                    >
                        <BarChart3 :size="18" />
                        <strong>{{ reporte.titulo }}</strong>
                        <small>{{ reporte.descripcion }}</small>
                    </button>
                </fieldset>
                <p v-if="errores.tipo" class="cc-modal-error">
                    {{ errores.tipo }}
                </p>

                <div
                    class="cc-rep__periodo"
                    :class="{ 'is-llenado': resaltado('periodo') }"
                >
                    <div
                        class="cc-rep__segmentos"
                        role="group"
                        aria-label="Período"
                    >
                        <button
                            v-for="periodo in PERIODOS"
                            :key="periodo.id"
                            type="button"
                            :class="{
                                'is-activo': form.periodo === periodo.id,
                            }"
                            :aria-pressed="form.periodo === periodo.id"
                            @click="form.periodo = periodo.id"
                        >
                            {{ periodo.etiqueta }}
                        </button>
                    </div>

                    <div class="cc-rep__fechas">
                        <label class="cc-modal-field">
                            <span>
                                {{
                                    form.periodo === 'personalizado'
                                        ? 'Desde'
                                        : 'Fecha de referencia'
                                }}
                            </span>
                            <span class="cc-log__control">
                                <input
                                    v-model="form.desde"
                                    type="date"
                                    required
                                />
                            </span>
                            <p v-if="errores.desde" class="cc-modal-error">
                                {{ errores.desde }}
                            </p>
                        </label>
                        <label
                            v-if="form.periodo === 'personalizado'"
                            class="cc-modal-field"
                        >
                            <span>Hasta</span>
                            <span class="cc-log__control">
                                <input
                                    v-model="form.hasta"
                                    type="date"
                                    required
                                />
                            </span>
                            <p
                                v-if="errores.hasta"
                                class="cc-modal-error"
                                data-test="error-hasta"
                            >
                                {{ errores.hasta }}
                            </p>
                        </label>
                    </div>
                    <p
                        v-if="form.periodo !== 'personalizado' && errores.hasta"
                        class="cc-modal-error"
                    >
                        {{ errores.hasta }}
                    </p>
                </div>

                <div
                    v-if="
                        filtrosVisibles.length > 0 ||
                        permiteAgrupar ||
                        permiteLimite
                    "
                    class="cc-rep__filtros"
                >
                    <div
                        v-for="clave in filtrosVisibles"
                        :key="clave"
                        class="cc-modal-field"
                        :class="{ 'is-llenado': resaltado(clave) }"
                    >
                        <span>{{ FILTROS[clave]?.etiqueta }}</span>
                        <CcRevealSelect
                            :model-value="valorDe(clave)"
                            :etiqueta="FILTROS[clave]?.etiqueta ?? ''"
                            :opciones="opcionesDe(clave)"
                            @update:model-value="cambiarFiltro(clave, $event)"
                        />
                    </div>
                    <div v-if="permiteAgrupar" class="cc-modal-field">
                        <span>Desglose</span>
                        <CcRevealSelect
                            v-model="form.agrupar"
                            etiqueta="Desglose"
                            :opciones="OPCIONES_AGRUPAR"
                        />
                    </div>
                    <label
                        v-if="permiteLimite"
                        class="cc-modal-field"
                        :class="{ 'is-llenado': resaltado('limite') }"
                    >
                        <span>Cantidad de resultados</span>
                        <span class="cc-log__control">
                            <input
                                v-model="form.limite"
                                type="number"
                                min="1"
                                max="50"
                                placeholder="10"
                            />
                        </span>
                        <p v-if="errores.limite" class="cc-modal-error">
                            {{ errores.limite }}
                        </p>
                    </label>
                </div>

                <div class="cc-rep__acciones">
                    <button
                        type="submit"
                        class="cc-button"
                        :disabled="generando"
                        data-test="generar"
                    >
                        <Search :size="16" />
                        Generar
                    </button>
                </div>
            </motion.form>

            <!-- ── Resultado ── -->
            <motion.div
                v-if="resultado"
                layout
                class="cc-log__panel cc-rep__resultado"
                :transition="adminSpring"
                data-test="resultado"
            >
                <header class="cc-rep__cabecera">
                    <div>
                        <p class="cc-log__kicker">
                            {{ resultado.encabezado.tienda }}
                        </p>
                        <h2>{{ resultado.titulo }}</h2>
                        <p class="cc-rep__meta">
                            Período: {{ resultado.encabezado.periodo }} ·
                            Generado por {{ resultado.encabezado.usuario }} el
                            {{ resultado.encabezado.generado }}
                        </p>
                    </div>

                    <div class="cc-rep__exportar">
                        <button
                            type="button"
                            class="cc-button"
                            :disabled="resultado.vacio"
                            aria-haspopup="menu"
                            :aria-expanded="exportarAbierto"
                            data-test="exportar"
                            @click="exportarAbierto = !exportarAbierto"
                        >
                            <Download :size="16" />
                            Exportar
                            <ChevronDown :size="14" />
                        </button>
                        <ul
                            v-if="exportarAbierto && !resultado.vacio"
                            class="cc-rep__menu"
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
                                    @click="descargar(formato.id)"
                                >
                                    <component :is="formato.icono" :size="16" />
                                    {{ formato.etiqueta }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </header>

                <div
                    v-for="aviso in resultado.advertencias"
                    :key="aviso"
                    class="cc-rep__aviso"
                    role="alert"
                    data-test="advertencia"
                >
                    <AlertTriangle :size="16" />
                    {{ aviso }}
                </div>

                <div
                    v-if="resultado.vacio"
                    class="cc-rep__vacio"
                    data-test="sin-datos"
                >
                    <strong>No hay datos para el período seleccionado</strong>
                    <span
                        >Prueba con otro rango de fechas o con otros
                        filtros.</span
                    >
                </div>

                <template v-else>
                    <GraficoReporte
                        :grafico="resultado.grafico"
                        :moneda="monedaGrafico"
                    />

                    <TablaReporte :resultado="resultado" />
                </template>
            </motion.div>
        </section>
    </div>
</template>

<style scoped>
.cc-rep__voz,
.cc-rep__form,
.cc-rep__resultado {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding: 20px;
}

.cc-rep__voz-fila {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}

.cc-rep__voz-texto {
    display: flex;
    flex: 1;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    min-width: 260px;
}

.cc-rep__voz-texto .cc-log__control {
    flex: 1;
    min-width: 200px;
}

.cc-rep__nota {
    margin: 0;
    color: var(--cc-muted);
    font-size: 13px;
}

.cc-rep__nota--error {
    color: var(--cc-error);
}

.cc-rep__entendido {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 14px 16px;
    border: 1px solid color-mix(in srgb, var(--cc-accent) 45%, transparent);
    border-radius: 12px;
    background: color-mix(in srgb, var(--cc-accent) 8%, transparent);
}

.cc-rep__entendido p {
    margin: 0;
}

.cc-rep__entendido-acciones {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.cc-rep__enlace {
    color: var(--cc-accent);
    text-decoration: underline;
}

.cc-rep__tipos {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px;
    margin: 0;
    padding: 0;
    border: 0;
}

.cc-rep__tipos legend {
    margin-bottom: 8px;
    padding: 0;
    color: var(--cc-muted);
    font-size: 12px;
    font-weight: 500;
}

.cc-rep__tipo {
    display: flex;
    flex-direction: column;
    gap: 4px;
    align-items: flex-start;
    padding: 12px 14px;
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    background: transparent;
    color: var(--cc-text);
    font: inherit;
    text-align: left;
    cursor: pointer;
    transition:
        border-color 140ms ease,
        background 140ms ease;
}

.cc-rep__tipo small {
    color: var(--cc-muted);
    font-size: 12px;
    line-height: 1.35;
}

.cc-rep__tipo:hover,
.cc-rep__tipo:focus-visible {
    border-color: var(--cc-accent);
}

.cc-rep__tipo.is-activo {
    border-color: var(--cc-accent);
    background: color-mix(in srgb, var(--cc-accent) 10%, transparent);
}

.is-llenado {
    border-radius: 10px;
    outline: 2px solid var(--cc-accent);
    outline-offset: 3px;
    animation: cc-llenado 1.4s ease-out;
}

@keyframes cc-llenado {
    from {
        background: color-mix(in srgb, var(--cc-accent) 22%, transparent);
    }

    to {
        background: transparent;
    }
}

.cc-rep__periodo {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 4px;
}

.cc-rep__segmentos {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 4px;
    padding: 4px;
    border: 1px solid var(--cc-border);
    border-radius: 999px;
    align-self: flex-start;
}

.cc-rep__segmentos button {
    padding: 7px 14px;
    border: 0;
    border-radius: 999px;
    background: transparent;
    color: var(--cc-muted);
    font: inherit;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
}

.cc-rep__segmentos button.is-activo {
    background: var(--cc-accent);
    color: #fff;
}

.cc-rep__fechas,
.cc-rep__filtros {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 16px;
}

.cc-rep__acciones {
    display: flex;
    justify-content: flex-end;
}

.cc-rep__cabecera {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: flex-start;
    justify-content: space-between;
}

.cc-rep__cabecera h2 {
    margin: 2px 0 4px;
    font-size: 22px;
}

.cc-rep__meta {
    margin: 0;
    color: var(--cc-muted);
    font-size: 13px;
}

.cc-rep__exportar {
    position: relative;
}

.cc-rep__menu {
    position: absolute;
    right: 0;
    z-index: 20;
    min-width: 200px;
    margin: 6px 0 0;
    padding: 6px;
    list-style: none;
    border: 1px solid var(--cc-border);
    border-radius: 12px;
    background: var(--cc-surface, #fff);
    box-shadow: 0 12px 30px rgb(0 0 0 / 18%);
}

.cc-rep__menu button {
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
    text-align: left;
    cursor: pointer;
}

.cc-rep__menu button:hover,
.cc-rep__menu button:focus-visible {
    background: color-mix(in srgb, var(--cc-accent) 12%, transparent);
}

.cc-rep__aviso {
    display: flex;
    gap: 8px;
    align-items: center;
    padding: 10px 14px;
    border: 1px solid #f0c36d;
    border-radius: 10px;
    background: #fff7e0;
    color: #7a4a05;
    font-size: 13px;
}

.cc-rep__vacio {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 36px 16px;
    text-align: center;
    color: var(--cc-muted);
}

.cc-rep__vacio strong {
    color: var(--cc-text);
    font-size: 16px;
}

.cc-modal-error {
    margin: 4px 0 0;
    color: var(--cc-error);
    font-size: 12px;
}
</style>
