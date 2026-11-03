<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import {
    ArrowDownRight,
    ArrowUpRight,
    Ban,
    ChevronLeft,
    ChevronRight,
    Plus,
    Search,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { adminSpring } from '@/lib/adminMotion';

type Movimiento = {
    clave: string;
    origen: 'VENTA' | 'COMPRA' | 'GASTO' | 'MOVIMIENTO';
    origen_id: number;
    tipo: 'INGRESO' | 'EGRESO';
    categoria: string;
    descripcion: string;
    metodo: string | null;
    referencia: string | null;
    monto: number;
    fecha: string | null;
    estado: string;
    anulable: boolean;
};

type Filtros = { desde: string; hasta: string; tipo: string; origen: string };

const props = defineProps<{
    movimientos: {
        data: Movimiento[];
        meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null };
    };
    resumen: {
        ingresos: number;
        egresos: number;
        neto: number;
        saldo: number;
        por_categoria: { tipo: 'INGRESO' | 'EGRESO'; categoria: string; total: number; cantidad: number }[];
    };
    filtros: Filtros;
    opciones: {
        categorias_gasto: { id: number; nombre: string }[];
        categorias_ingreso: string[];
        categorias_egreso: string[];
        metodos: { id: number; nombre: string }[];
    };
    puedeRegistrar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Flujo de caja', href: '/caja' }],
    },
});

const moneda = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const bs = (monto: number) => `Bs ${moneda.format(monto)}`;

const form = reactive<Filtros>({ ...props.filtros });

watch(
    () => props.filtros,
    (filtros) => Object.assign(form, filtros),
);

function aplicar(pagina?: number): void {
    const query: Record<string, string | number> = { desde: form.desde, hasta: form.hasta };

    if (form.tipo) query.tipo = form.tipo;
    if (form.origen) query.origen = form.origen;
    if (pagina && pagina > 1) query.page = pagina;

    router.get('/caja', query, { preserveState: true, preserveScroll: true, replace: true });
}

function iso(fecha: Date): string {
    const local = new Date(fecha.getTime() - fecha.getTimezoneOffset() * 60000);

    return local.toISOString().slice(0, 10);
}

function preset(nombre: 'hoy' | 'semana' | 'mes' | 'anterior' | 'anio'): void {
    const hoy = new Date();
    let desde = new Date(hoy);
    let hasta = new Date(hoy);

    if (nombre === 'semana') {
        desde.setDate(hoy.getDate() - ((hoy.getDay() + 6) % 7));
    } else if (nombre === 'mes') {
        desde = new Date(hoy.getFullYear(), hoy.getMonth(), 1);
    } else if (nombre === 'anterior') {
        desde = new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1);
        hasta = new Date(hoy.getFullYear(), hoy.getMonth(), 0);
    } else if (nombre === 'anio') {
        desde = new Date(hoy.getFullYear(), 0, 1);
    }

    form.desde = iso(desde);
    form.hasta = iso(hasta);
    aplicar();
}

const kpis = computed(() => [
    { label: 'Ingresos del período', valor: bs(props.resumen.ingresos), clase: 'is-ingreso' },
    { label: 'Egresos del período', valor: bs(props.resumen.egresos), clase: 'is-egreso' },
    { label: 'Resultado neto', valor: bs(props.resumen.neto), clase: props.resumen.neto >= 0 ? 'is-ingreso' : 'is-egreso' },
    { label: 'Saldo de caja acumulado', valor: bs(props.resumen.saldo), clase: '' },
]);

const opcionesTipo = [
    { value: '', label: 'Ingresos y egresos' },
    { value: 'INGRESO', label: 'Solo ingresos' },
    { value: 'EGRESO', label: 'Solo egresos' },
];

const opcionesOrigen = [
    { value: '', label: 'Todos los orígenes' },
    { value: 'VENTA', label: 'Ventas' },
    { value: 'COMPRA', label: 'Compras' },
    { value: 'GASTO', label: 'Gastos' },
    { value: 'MOVIMIENTO', label: 'Movimientos manuales' },
];

const etiquetaOrigen: Record<Movimiento['origen'], string> = {
    VENTA: 'Venta',
    COMPRA: 'Compra',
    GASTO: 'Gasto',
    MOVIMIENTO: 'Manual',
};

const desglose = computed(() => {
    const maximo = Math.max(1, ...props.resumen.por_categoria.map((c) => c.total));

    return (['INGRESO', 'EGRESO'] as const).map((tipo) => ({
        tipo,
        titulo: tipo === 'INGRESO' ? 'Ingresos' : 'Egresos',
        filas: props.resumen.por_categoria
            .filter((c) => c.tipo === tipo)
            .map((c) => ({ ...c, ancho: `${Math.max(4, (c.total / maximo) * 100)}%` })),
    }));
});

const resumenPagina = computed(() => {
    const { from, to, total } = props.movimientos.meta;

    if (!total || from === null || to === null) return 'Sin movimientos en el período';

    return `Mostrando ${from}–${to} de ${total} movimientos`;
});

// ── Registro ──────────────────────────────────────────────────────────
type Modo = 'gasto' | 'egreso' | 'ingreso';

const modalAbierto = ref(false);
const modo = ref<Modo>('gasto');
const alta = useForm({
    tipo: 'EGRESO',
    es_gasto: true,
    monto: '',
    fecha: iso(new Date()),
    descripcion: '',
    categoria_gasto_id: '' as string | number,
    categoria: '',
    metodo_pago_id: '' as string | number,
    referencia: '',
    observacion: '',
});

const nuevaCategoria = useForm({ nombre: '' });
const creandoCategoria = ref(false);

function abrir(m: Modo = 'gasto'): void {
    alta.reset();
    alta.clearErrors();
    alta.fecha = iso(new Date());
    cambiarModo(m);
    modalAbierto.value = true;
}

function cambiarModo(m: Modo): void {
    modo.value = m;
    alta.tipo = m === 'ingreso' ? 'INGRESO' : 'EGRESO';
    alta.es_gasto = m === 'gasto';
    alta.categoria = '';
    alta.categoria_gasto_id = '';
    alta.clearErrors('categoria', 'categoria_gasto_id');
}

function registrar(): void {
    alta.transform((datos) => ({
        ...datos,
        categoria_gasto_id: datos.categoria_gasto_id === '' ? null : datos.categoria_gasto_id,
        metodo_pago_id: datos.metodo_pago_id === '' ? null : datos.metodo_pago_id,
    })).post('/caja/movimientos', {
        preserveScroll: true,
        onSuccess: () => {
            modalAbierto.value = false;
        },
    });
}

function crearCategoria(): void {
    nuevaCategoria.post('/caja/categorias', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const creada = props.opciones.categorias_gasto.find(
                (c) => c.nombre.toLowerCase() === nuevaCategoria.nombre.trim().toLowerCase(),
            );

            if (creada) alta.categoria_gasto_id = creada.id;
            nuevaCategoria.reset();
            creandoCategoria.value = false;
        },
    });
}

// ── Anulación ─────────────────────────────────────────────────────────
const objetivo = ref<Movimiento | null>(null);
const anulacion = useForm({ motivo: '' });
const anularAbierto = computed({
    get: () => objetivo.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) objetivo.value = null;
    },
});

function pedirAnulacion(movimiento: Movimiento): void {
    anulacion.reset();
    anulacion.clearErrors();
    objetivo.value = movimiento;
}

function anular(): void {
    if (!objetivo.value) return;

    anulacion.patch(`/caja/${objetivo.value.origen}/${objetivo.value.origen_id}/anular`, {
        preserveScroll: true,
        onSuccess: () => {
            objetivo.value = null;
        },
    });
}
</script>

<template>
    <div>
        <Head title="Gastos y flujo de caja" />

        <section class="cc-admin cc-log cc-admin--log cc-caja">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Finanzas</p>
                    <h1>Gastos y flujo de caja</h1>
                    <p>
                        Registrá gastos e ingresos y consultá el consolidado de
                        ventas, compras y gastos en el período que elijas.
                    </p>
                </motion.div>
                <motion.div v-if="puedeRegistrar" layout class="cc-log__actions" :transition="adminSpring">
                    <button type="button" class="cc-button cc-button--ghost" @click="abrir('ingreso')">
                        <ArrowUpRight :size="16" />
                        Registrar ingreso
                    </button>
                    <button type="button" class="cc-button" @click="abrir('gasto')">
                        <Plus :size="16" />
                        Registrar gasto
                    </button>
                </motion.div>
            </motion.div>

            <div class="cc-admin__kpis cc-caja__kpis">
                <div v-for="kpi in kpis" :key="kpi.label" class="cc-admin__kpi" :class="kpi.clase">
                    <b>{{ kpi.valor }}</b>
                    <span>{{ kpi.label }}</span>
                </div>
            </div>

            <form class="cc-log__filters" @submit.prevent="aplicar()">
                <div class="cc-log__row">
                    <div class="cc-log__dates-par">
                        <label class="cc-modal-field">
                            <span>Desde</span>
                            <span class="cc-log__control">
                                <input v-model="form.desde" type="date" :max="form.hasta" required />
                            </span>
                        </label>
                        <label class="cc-modal-field">
                            <span>Hasta</span>
                            <span class="cc-log__control">
                                <input v-model="form.hasta" type="date" :min="form.desde" required />
                            </span>
                        </label>
                    </div>
                    <CcRevealSelect v-model="form.tipo" etiqueta="Ingresos y egresos" :opciones="opcionesTipo" @change="aplicar()" />
                    <CcRevealSelect v-model="form.origen" etiqueta="Todos los orígenes" :opciones="opcionesOrigen" @change="aplicar()" />
                    <div class="cc-log__row-actions">
                        <button type="submit" class="cc-button">
                            <Search :size="16" />
                            Consultar
                        </button>
                    </div>
                </div>
                <div class="cc-admin__chips">
                    <button type="button" class="cc-admin__chip" @click="preset('hoy')">Hoy</button>
                    <button type="button" class="cc-admin__chip" @click="preset('semana')">Esta semana</button>
                    <button type="button" class="cc-admin__chip" @click="preset('mes')">Este mes</button>
                    <button type="button" class="cc-admin__chip" @click="preset('anterior')">Mes anterior</button>
                    <button type="button" class="cc-admin__chip" @click="preset('anio')">Este año</button>
                </div>
            </form>

            <div class="cc-caja__grid">
                <section class="cc-log__panel cc-caja__desglose" aria-label="Consolidado por categoría">
                    <h2>Consolidado del período</h2>
                    <div v-for="bloque in desglose" :key="bloque.tipo" class="cc-caja__bloque">
                        <h3 :class="bloque.tipo === 'INGRESO' ? 'is-ingreso' : 'is-egreso'">
                            {{ bloque.titulo }}
                            <span>{{ bs(bloque.tipo === 'INGRESO' ? resumen.ingresos : resumen.egresos) }}</span>
                        </h3>
                        <p v-if="bloque.filas.length === 0" class="cc-caja__vacio">Sin registros.</p>
                        <ul v-else>
                            <li v-for="fila in bloque.filas" :key="fila.categoria">
                                <div class="cc-caja__fila">
                                    <span>{{ fila.categoria }} <small>· {{ fila.cantidad }}</small></span>
                                    <b>{{ bs(fila.total) }}</b>
                                </div>
                                <span class="cc-caja__barra" :data-tipo="bloque.tipo">
                                    <span :style="{ width: fila.ancho }" />
                                </span>
                            </li>
                        </ul>
                    </div>
                </section>

                <motion.div layout class="cc-log__panel" :transition="adminSpring">
                    <div class="cc-log__table-wrap">
                        <table class="cc-log__table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Detalle</th>
                                    <th>Origen</th>
                                    <th class="cc-caja__num">Monto</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="movimientos.data.length === 0">
                                    <td class="cc-log__empty" colspan="5">
                                        <strong>Sin movimientos</strong>
                                        No hay ingresos ni egresos en el período elegido.
                                    </td>
                                </tr>
                                <tr
                                    v-for="m in movimientos.data"
                                    :key="m.clave"
                                    :class="{ 'is-off': m.estado !== 'ACTIVO' }"
                                >
                                    <td>{{ m.fecha }}</td>
                                    <td>
                                        <span class="cc-log__when">
                                            {{ m.descripcion }}
                                            <small>
                                                {{ m.categoria }}
                                                <template v-if="m.metodo"> · {{ m.metodo }}</template>
                                                <template v-if="m.estado !== 'ACTIVO'"> · ANULADO</template>
                                            </small>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="cc-log__badge" :class="m.tipo === 'INGRESO' ? 'cc-log__badge--crear' : 'cc-log__badge--alerta'">
                                            {{ etiquetaOrigen[m.origen] }}
                                        </span>
                                    </td>
                                    <td class="cc-caja__num" :class="m.tipo === 'INGRESO' ? 'is-ingreso' : 'is-egreso'">
                                        <ArrowUpRight v-if="m.tipo === 'INGRESO'" :size="14" />
                                        <ArrowDownRight v-else :size="14" />
                                        {{ bs(m.monto) }}
                                    </td>
                                    <td>
                                        <span class="cc-row-actions">
                                            <button
                                                v-if="m.anulable && puedeRegistrar"
                                                type="button"
                                                class="cc-log__eye cc-log__eye--danger"
                                                title="Anular"
                                                :aria-label="`Anular ${m.descripcion}`"
                                                @click="pedirAnulacion(m)"
                                            >
                                                <Ban :size="15" />
                                            </button>
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <footer class="cc-log__foot">
                        <p>{{ resumenPagina }}</p>
                        <div class="cc-log__pager">
                            <button
                                type="button"
                                aria-label="Página anterior"
                                :disabled="movimientos.meta.current_page <= 1"
                                @click="aplicar(movimientos.meta.current_page - 1)"
                            >
                                <ChevronLeft :size="16" />
                            </button>
                            <span>Página {{ movimientos.meta.current_page }} de {{ movimientos.meta.last_page }}</span>
                            <button
                                type="button"
                                aria-label="Página siguiente"
                                :disabled="movimientos.meta.current_page >= movimientos.meta.last_page"
                                @click="aplicar(movimientos.meta.current_page + 1)"
                            >
                                <ChevronRight :size="16" />
                            </button>
                        </div>
                    </footer>
                </motion.div>
            </div>
        </section>

        <Dialog v-model:open="modalAbierto">
            <DialogContent class="cc-theme cc-admin cc-log-dialog" style="max-width: 560px">
                <DialogHeader>
                    <DialogTitle>Registrar movimiento de caja</DialogTitle>
                    <DialogDescription>
                        Los gastos se clasifican por categoría. El monto debe ser mayor a cero.
                    </DialogDescription>
                </DialogHeader>

                <form class="cc-modal-form" @submit.prevent="registrar">
                    <div class="cc-caja__modos" role="radiogroup" aria-label="Tipo de movimiento">
                        <button
                            v-for="opcion in ([
                                { id: 'gasto', label: 'Gasto operativo' },
                                { id: 'egreso', label: 'Otro egreso' },
                                { id: 'ingreso', label: 'Ingreso' },
                            ] as const)"
                            :key="opcion.id"
                            type="button"
                            role="radio"
                            class="cc-admin__chip"
                            :class="{ 'is-active': modo === opcion.id }"
                            :aria-checked="modo === opcion.id"
                            @click="cambiarModo(opcion.id)"
                        >
                            {{ opcion.label }}
                        </button>
                    </div>

                    <div class="cc-caja__par">
                        <label class="cc-modal-field">
                            <span>Monto (Bs) <b class="cc-req">*</b></span>
                            <span class="cc-log__control">
                                <input v-model="alta.monto" type="number" min="0.01" step="0.01" inputmode="decimal" required />
                            </span>
                            <p v-if="alta.errors.monto" class="cc-modal-error">{{ alta.errors.monto }}</p>
                        </label>
                        <label class="cc-modal-field">
                            <span>Fecha <b class="cc-req">*</b></span>
                            <span class="cc-log__control">
                                <input v-model="alta.fecha" type="date" :max="iso(new Date())" required />
                            </span>
                            <p v-if="alta.errors.fecha" class="cc-modal-error">{{ alta.errors.fecha }}</p>
                        </label>
                    </div>

                    <label v-if="modo === 'gasto'" class="cc-modal-field">
                        <span>Categoría del gasto <b class="cc-req">*</b></span>
                        <span class="cc-log__control">
                            <select v-model="alta.categoria_gasto_id" required>
                                <option value="" disabled>Elegí una categoría</option>
                                <option v-for="c in opciones.categorias_gasto" :key="c.id" :value="c.id">{{ c.nombre }}</option>
                            </select>
                        </span>
                        <p v-if="alta.errors.categoria_gasto_id" class="cc-modal-error">{{ alta.errors.categoria_gasto_id }}</p>
                        <button
                            v-if="!creandoCategoria"
                            type="button"
                            class="cc-caja__link"
                            @click="creandoCategoria = true"
                        >
                            + Nueva categoría
                        </button>
                        <span v-else class="cc-caja__nueva">
                            <span class="cc-log__control">
                                <input v-model="nuevaCategoria.nombre" type="text" maxlength="150" placeholder="Ej. Mantenimiento" />
                            </span>
                            <button type="button" class="cc-button" :disabled="nuevaCategoria.processing || !nuevaCategoria.nombre.trim()" @click="crearCategoria">
                                Crear
                            </button>
                        </span>
                        <p v-if="nuevaCategoria.errors.nombre" class="cc-modal-error">{{ nuevaCategoria.errors.nombre }}</p>
                    </label>

                    <label v-else class="cc-modal-field">
                        <span>Categoría <b class="cc-req">*</b></span>
                        <span class="cc-log__control">
                            <select v-model="alta.categoria" required>
                                <option value="" disabled>Elegí una categoría</option>
                                <option
                                    v-for="c in modo === 'ingreso' ? opciones.categorias_ingreso : opciones.categorias_egreso"
                                    :key="c"
                                    :value="c"
                                >
                                    {{ c }}
                                </option>
                            </select>
                        </span>
                        <p v-if="alta.errors.categoria" class="cc-modal-error">{{ alta.errors.categoria }}</p>
                    </label>

                    <label class="cc-modal-field">
                        <span>Descripción <b class="cc-req">*</b></span>
                        <span class="cc-log__control">
                            <input
                                v-model="alta.descripcion"
                                type="text"
                                maxlength="255"
                                :placeholder="modo === 'ingreso' ? 'Ej. Reparación de teclado' : 'Ej. Alquiler de noviembre'"
                                required
                            />
                        </span>
                        <p v-if="alta.errors.descripcion" class="cc-modal-error">{{ alta.errors.descripcion }}</p>
                    </label>

                    <div class="cc-caja__par">
                        <label class="cc-modal-field">
                            <span>Método de pago</span>
                            <span class="cc-log__control">
                                <select v-model="alta.metodo_pago_id">
                                    <option value="">Sin especificar</option>
                                    <option v-for="m in opciones.metodos" :key="m.id" :value="m.id">{{ m.nombre }}</option>
                                </select>
                            </span>
                        </label>
                        <label class="cc-modal-field">
                            <span>Referencia</span>
                            <span class="cc-log__control">
                                <input v-model="alta.referencia" type="text" maxlength="100" placeholder="N.º de factura o recibo" />
                            </span>
                        </label>
                    </div>

                    <div class="cc-modal-actions">
                        <button type="button" class="cc-button cc-button--ghost" @click="modalAbierto = false">Cancelar</button>
                        <button type="submit" class="cc-button" :class="{ 'is-load': alta.processing }" :disabled="alta.processing">
                            <span v-if="alta.processing" class="cc-admin__spin" />
                            Registrar
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="anularAbierto">
            <DialogContent class="cc-theme cc-admin cc-log-dialog cc-admin-dialog--danger" style="max-width: 460px">
                <DialogHeader>
                    <DialogTitle>Anular registro</DialogTitle>
                    <DialogDescription>
                        <strong>{{ objetivo?.descripcion }}</strong> ({{ bs(objetivo?.monto ?? 0) }}) quedará
                        ANULADO y dejará de sumar al flujo de caja.
                    </DialogDescription>
                </DialogHeader>
                <form class="cc-modal-form" @submit.prevent="anular">
                    <label class="cc-modal-field">
                        <span>Motivo <b class="cc-req">*</b></span>
                        <span class="cc-log__control cc-log__control--area">
                            <textarea v-model="anulacion.motivo" rows="3" maxlength="500" required />
                        </span>
                        <p v-if="anulacion.errors.motivo" class="cc-modal-error">{{ anulacion.errors.motivo }}</p>
                    </label>
                    <div class="cc-modal-actions">
                        <button type="button" class="cc-button cc-button--ghost" @click="objetivo = null">Cancelar</button>
                        <button
                            type="submit"
                            class="cc-button cc-button--danger"
                            :disabled="anulacion.processing || anulacion.motivo.trim().length < 5"
                        >
                            Anular
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

.cc-caja__kpis {
    grid-template-columns: repeat(4, minmax(0, 1fr));
}

.cc-caja__kpis .is-ingreso b {
    color: var(--cc-success);
}

.cc-caja__kpis .is-egreso b {
    color: var(--cc-error);
}

.cc-caja__grid {
    display: grid;
    grid-template-columns: minmax(260px, 340px) minmax(0, 1fr);
    gap: 16px;
    align-items: start;
}

.cc-caja__desglose {
    padding: 16px;
}

.cc-caja__desglose h2 {
    margin: 0 0 12px;
    font-size: 14px;
    font-weight: 600;
}

.cc-caja__bloque + .cc-caja__bloque {
    margin-top: 20px;
}

.cc-caja__bloque h3 {
    display: flex;
    justify-content: space-between;
    margin: 0 0 8px;
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.cc-caja__bloque h3.is-ingreso span {
    color: var(--cc-success);
}

.cc-caja__bloque h3.is-egreso span {
    color: var(--cc-error);
}

.cc-caja__bloque ul {
    display: grid;
    gap: 10px;
    margin: 0;
    padding: 0;
    list-style: none;
}

.cc-caja__fila {
    display: flex;
    justify-content: space-between;
    gap: 8px;
    font-size: 12px;
}

.cc-caja__fila small {
    color: var(--cc-muted);
}

.cc-caja__barra {
    display: block;
    height: 6px;
    margin-top: 4px;
    overflow: hidden;
    border-radius: 999px;
    background: var(--cc-border);
}

.cc-caja__barra span {
    display: block;
    height: 100%;
    border-radius: inherit;
    background: var(--cc-success);
}

.cc-caja__barra[data-tipo='EGRESO'] span {
    background: var(--cc-error);
}

.cc-caja__vacio {
    margin: 0;
    color: var(--cc-muted);
    font-size: 12px;
}

.cc-caja__num {
    text-align: right;
    white-space: nowrap;
}

td.cc-caja__num {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

td.cc-caja__num svg {
    display: inline;
    vertical-align: -2px;
}

td.is-ingreso {
    color: var(--cc-success);
}

td.is-egreso {
    color: var(--cc-error);
}

.cc-caja__modos {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.cc-caja__modos .cc-admin__chip {
    padding: 6px 12px;
    background: transparent;
    border: 1px solid var(--cc-border);
    color: var(--cc-muted);
}

.cc-caja__modos .cc-admin__chip.is-active {
    border-color: var(--cc-accent);
    background: var(--cc-accent);
    color: var(--cc-on-accent);
}

.cc-caja__par {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.cc-caja__link {
    align-self: flex-start;
    border: 0;
    background: none;
    padding: 2px 0;
    color: var(--cc-accent);
    font-size: 12px;
    cursor: pointer;
}

.cc-caja__nueva {
    display: flex;
    gap: 8px;
}

.cc-caja__nueva .cc-log__control {
    flex: 1;
}

@media (max-width: 1024px) {
    .cc-caja__grid {
        grid-template-columns: 1fr;
    }

    .cc-caja__kpis {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .cc-caja__par {
        grid-template-columns: 1fr;
    }
}
</style>
