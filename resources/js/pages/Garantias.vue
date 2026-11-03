<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import { RotateCcw, Search, X } from '@lucide/vue';
import { ref, watch } from 'vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { adminSpring } from '@/lib/adminMotion';

type Resultado = {
    numero_venta: string;
    producto: string;
    numero_serie: string | null;
    fecha_venta: string | null;
    fecha_fin_garantia: string | null;
    vigente: boolean;
    estado_item: string;
    cliente: string | null;
};

type Filtro = 'todas' | 'vigentes' | 'por_vencer' | 'vencidas' | 'devueltas';

const props = defineProps<{
    busqueda: string;
    filtro: Filtro;
    resultados: Resultado[];
    kpis: Record<Filtro, number>;
    puedeProcesar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Garantías y devoluciones', href: '/garantias' }],
    },
});

const numeroSerie = ref(props.busqueda);
const filtroActivo = ref<Filtro>(props.filtro);
const resultadoSeleccionado = ref<Resultado | null>(null);
const modalAbierto = ref(false);
const form = useForm({
    numero_serie: '',
    numero_venta: '',
    motivo: '',
});

const filtros: { id: Filtro; etiqueta: string }[] = [
    { id: 'todas', etiqueta: 'Todas' },
    { id: 'vigentes', etiqueta: 'Vigentes' },
    { id: 'por_vencer', etiqueta: 'Por vencer' },
    { id: 'vencidas', etiqueta: 'Vencidas' },
    { id: 'devueltas', etiqueta: 'Devueltas' },
];

watch(
    () => props.busqueda,
    (valor) => {
        numeroSerie.value = valor;
    },
);

watch(
    () => props.filtro,
    (valor) => {
        filtroActivo.value = valor;
    },
);

function consulta(serie: string, filtro: Filtro): Record<string, string> {
    const query: Record<string, string> = { filtro };

    if (serie.trim() !== '') {
        query.numero_serie = serie.trim();
    }

    return query;
}

function aplicar(serie = numeroSerie.value, filtro = filtroActivo.value): void {
    router.get('/garantias', consulta(serie, filtro), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function buscar(): void {
    aplicar();
}

function aplicarFiltro(filtro: Filtro): void {
    filtroActivo.value = filtro;
    aplicar(numeroSerie.value, filtro);
}

function limpiar(): void {
    numeroSerie.value = '';
    filtroActivo.value = 'todas';
    aplicar('', 'todas');
}

function quitarSerie(): void {
    numeroSerie.value = '';
    aplicar('', filtroActivo.value);
}

function puedeDevolver(resultado: Resultado): boolean {
    return (
        props.puedeProcesar &&
        resultado.vigente &&
        resultado.estado_item === 'VIGENTE'
    );
}

function abrirDevolucion(resultado: Resultado): void {
    if (!puedeDevolver(resultado)) {
        return;
    }

    resultadoSeleccionado.value = resultado;
    form.reset();
    form.clearErrors();
    form.numero_serie = resultado.numero_serie ?? props.busqueda;
    form.numero_venta = resultado.numero_venta;
    modalAbierto.value = true;
}

function cerrarDevolucion(): void {
    if (!form.processing) {
        modalAbierto.value = false;
    }
}

function procesarDevolucion(): void {
    form.post('/garantias/devolver', {
        preserveScroll: true,
        onSuccess: () => {
            modalAbierto.value = false;
            resultadoSeleccionado.value = null;
        },
    });
}

function parseFecha(valor: string): Date {
    const [anio, mes, dia] = valor.slice(0, 10).split('-').map(Number);

    return new Date(anio, (mes ?? 1) - 1, dia ?? 1);
}

function fecha(valor: string | null): string {
    if (!valor) {
        return 'Sin fecha';
    }

    return new Intl.DateTimeFormat('es-BO', {
        dateStyle: 'medium',
    }).format(parseFecha(valor));
}

function porVencer(resultado: Resultado): boolean {
    if (!resultado.vigente || !resultado.fecha_fin_garantia) {
        return false;
    }

    const fin = parseFecha(resultado.fecha_fin_garantia);
    const limite = new Date();
    limite.setHours(0, 0, 0, 0);
    limite.setDate(limite.getDate() + 30);

    return fin <= limite;
}

function estado(resultado: Resultado): string {
    if (resultado.estado_item === 'DEVUELTO') {
        return 'Devuelta';
    }

    if (resultado.estado_item === 'VIGENTE' && resultado.vigente) {
        return porVencer(resultado) ? 'Por vencer' : 'Vigente';
    }

    if (!resultado.vigente) {
        return 'Vencida';
    }

    return resultado.estado_item;
}

function badge(resultado: Resultado): string {
    if (resultado.estado_item === 'DEVUELTO' || !resultado.vigente) {
        return 'cc-log__badge--error';
    }

    if (resultado.estado_item === 'VIGENTE' && porVencer(resultado)) {
        return 'cc-log__badge--alerta';
    }

    if (resultado.estado_item === 'VIGENTE' && resultado.vigente) {
        return 'cc-log__badge--crear';
    }

    return 'cc-log__badge--neutro';
}

function inactiva(resultado: Resultado): boolean {
    return resultado.estado_item === 'DEVUELTO' || !resultado.vigente;
}
</script>

<template>
    <div>
        <Head title="Garantías y devoluciones" />

        <section class="cc-admin cc-log cc-admin--pins cc-admin--log">
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div layout :transition="adminSpring">
                    <p class="cc-log__kicker">Ventas</p>
                    <h1>Garantías y devoluciones</h1>
                    <p>
                        Consultá la serie, filtrá por vencimiento y registrá la
                        devolución cuando la garantía siga vigente.
                    </p>
                </motion.div>
            </motion.div>

            <motion.div
                layout
                class="cc-admin__kpis cc-admin__kpis--five"
                aria-label="Filtros de garantías"
                :transition="adminSpring"
            >
                <button
                    v-for="filtro in filtros"
                    :key="filtro.id"
                    type="button"
                    class="cc-admin__kpi"
                    :class="{ 'is-active': filtroActivo === filtro.id }"
                    :aria-pressed="filtroActivo === filtro.id"
                    @click="aplicarFiltro(filtro.id)"
                >
                    <b>{{ props.kpis[filtro.id] }}</b>
                    <span>{{ filtro.etiqueta }}</span>
                </button>
            </motion.div>

            <form class="cc-log__filters" @submit.prevent="buscar">
                <div class="cc-log__row">
                    <label class="cc-log__control cc-log__search">
                        <Search :size="16" />
                        <input
                            v-model="numeroSerie"
                            type="search"
                            maxlength="100"
                            autocomplete="off"
                            placeholder="Buscar por número de serie..."
                        />
                        <button
                            v-if="numeroSerie"
                            type="button"
                            class="cc-log__clear"
                            aria-label="Limpiar búsqueda"
                            @click="quitarSerie"
                        >
                            <X :size="14" />
                        </button>
                    </label>
                    <div class="cc-gt-actions">
                        <button type="submit" class="cc-button">
                            <Search :size="16" />
                            Buscar
                        </button>
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            @click="limpiar"
                        >
                            <RotateCcw :size="16" />
                            Limpiar
                        </button>
                    </div>
                </div>
                <div v-if="props.busqueda" class="cc-admin__chips">
                    <button
                        type="button"
                        class="cc-admin__chip"
                        :aria-label="`Quitar filtro Serie ${props.busqueda}`"
                        @click="quitarSerie"
                    >
                        Serie: {{ props.busqueda }}
                        <X :size="12" />
                    </button>
                </div>
            </form>

            <motion.div layout class="cc-log__panel" :transition="adminSpring">
                <div class="cc-log__table-wrap">
                    <table class="cc-log__table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Venta</th>
                                <th>Garantía</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="props.resultados.length === 0">
                                <td class="cc-log__empty" colspan="5">
                                    <strong>{{
                                        props.busqueda
                                            ? 'Sin coincidencias'
                                            : 'Sin garantías'
                                    }}</strong>
                                    {{
                                        props.busqueda
                                            ? 'No se encontró una garantía para ese número de serie.'
                                            : 'No hay garantías que coincidan con este filtro.'
                                    }}
                                </td>
                            </tr>
                            <tr
                                v-for="resultado in props.resultados"
                                :key="`${resultado.numero_venta}-${resultado.numero_serie}`"
                                :class="{ 'is-off': inactiva(resultado) }"
                            >
                                <td>
                                    <span class="cc-log__when">
                                        {{ resultado.producto }}
                                        <small>{{
                                            resultado.numero_serie ||
                                            'Sin serie'
                                        }}</small>
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        {{ resultado.numero_venta }}
                                        <small>{{
                                            resultado.cliente || 'Cliente Final'
                                        }}</small>
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        Vence
                                        {{ fecha(resultado.fecha_fin_garantia) }}
                                        <small
                                            >Compra
                                            {{
                                                fecha(resultado.fecha_venta)
                                            }}</small
                                        >
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="cc-log__badge"
                                        :class="badge(resultado)"
                                    >
                                        {{ estado(resultado) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-row-actions">
                                        <button
                                            v-if="puedeDevolver(resultado)"
                                            class="cc-log__eye cc-log__eye--danger"
                                            type="button"
                                            title="Procesar devolución"
                                            :aria-label="`Procesar devolución de ${resultado.numero_venta}`"
                                            @click="abrirDevolucion(resultado)"
                                        >
                                            <RotateCcw
                                                :size="15"
                                                :stroke-width="1.5"
                                            />
                                        </button>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="cc-log-board">
                    <p v-if="props.resultados.length === 0" class="cc-log__empty">
                        <strong>{{
                            props.busqueda ? 'Sin coincidencias' : 'Sin garantías'
                        }}</strong>
                        {{
                            props.busqueda
                                ? 'No se encontró una garantía para ese número de serie.'
                                : 'No hay garantías que coincidan con este filtro.'
                        }}
                    </p>
                    <article
                        v-for="resultado in props.resultados"
                        :key="`pin-${resultado.numero_venta}-${resultado.numero_serie}`"
                        class="cc-log-pin"
                        :class="{ 'is-off': inactiva(resultado) }"
                    >
                        <span class="cc-log-pin__cuerpo">
                            <strong>{{ resultado.producto }}</strong>
                            <p>
                                {{ resultado.numero_serie || 'Sin serie' }}
                            </p>
                            <small>
                                {{ resultado.numero_venta }}
                                ·
                                {{ resultado.cliente || 'Cliente Final' }}
                                · vence
                                {{ fecha(resultado.fecha_fin_garantia) }}
                            </small>
                        </span>
                        <span class="cc-row-actions">
                            <span
                                class="cc-log__badge"
                                :class="badge(resultado)"
                            >
                                {{ estado(resultado) }}
                            </span>
                            <button
                                v-if="puedeDevolver(resultado)"
                                class="cc-log__eye cc-log__eye--danger"
                                type="button"
                                title="Procesar devolución"
                                :aria-label="`Procesar devolución de ${resultado.numero_venta}`"
                                @click="abrirDevolucion(resultado)"
                            >
                                <RotateCcw :size="15" :stroke-width="1.5" />
                            </button>
                        </span>
                    </article>
                </div>

                <footer class="cc-log__foot">
                    <p>
                        {{ props.resultados.length }}
                        {{
                            props.resultados.length === 1
                                ? 'garantía'
                                : 'garantías'
                        }}
                    </p>
                </footer>
            </motion.div>
        </section>

        <Dialog :open="modalAbierto" @update:open="(abierto) => !abierto && cerrarDevolucion()">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog"
                style="max-width: 520px"
            >
                <DialogHeader>
                    <DialogTitle>Procesar devolución</DialogTitle>
                    <DialogDescription>
                        Se anula la venta
                        {{ resultadoSeleccionado?.numero_venta }} y la base de
                        datos repone el stock.
                    </DialogDescription>
                </DialogHeader>

                <form class="cc-modal-form" @submit.prevent="procesarDevolucion">
                    <dl v-if="resultadoSeleccionado" class="cc-log-detalle__datos">
                        <div>
                            <dt>Producto</dt>
                            <dd>{{ resultadoSeleccionado.producto }}</dd>
                        </div>
                        <div>
                            <dt>Serie</dt>
                            <dd>
                                {{
                                    resultadoSeleccionado.numero_serie ||
                                    'Sin serie'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Cliente</dt>
                            <dd>
                                {{
                                    resultadoSeleccionado.cliente ||
                                    'Cliente Final'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt>Vence</dt>
                            <dd>
                                {{
                                    fecha(
                                        resultadoSeleccionado.fecha_fin_garantia,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>

                    <label class="cc-modal-field">
                        <span>Motivo <b class="cc-req">*</b></span>
                        <span class="cc-log__control cc-log__control--area">
                            <textarea
                                v-model="form.motivo"
                                rows="4"
                                maxlength="500"
                                required
                                placeholder="Ej. Falla cubierta por garantía"
                            />
                        </span>
                        <p v-if="form.errors.motivo" class="cc-modal-error">
                            {{ form.errors.motivo }}
                        </p>
                    </label>

                    <p
                        v-if="form.errors.numero_venta || form.errors.numero_serie"
                        class="cc-modal-error"
                    >
                        {{ form.errors.numero_venta || form.errors.numero_serie }}
                    </p>

                    <div class="cc-modal-actions">
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            :disabled="form.processing"
                            @click="cerrarDevolucion"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="cc-button cc-button--danger"
                            :class="{ 'is-load': form.processing }"
                            :disabled="
                                form.processing || form.motivo.trim().length < 5
                            "
                        >
                            <span
                                v-if="form.processing"
                                class="cc-admin__spin"
                            />
                            Procesar devolución
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.cc-gt-actions {
    display: flex;
    flex: none;
    gap: 8px;
    margin-left: auto;
}

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

@media (max-width: 768px) {
    .cc-gt-actions {
        width: 100%;
        margin-left: 0;
    }

    .cc-gt-actions .cc-button {
        flex: 1;
    }
}
</style>
