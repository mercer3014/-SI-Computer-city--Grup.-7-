<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { motion } from 'motion-v';
import {
    ArrowLeft,
    Box,
    History,
    PackageX,
    Pencil,
    Plus,
    RotateCcw,
    Sparkles,
    Trash2,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import CcRevealSelect from '@/components/admin/CcRevealSelect.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { adminSpring } from '@/lib/adminMotion';

// ── Tipos ─────────────────────────────────────────────────────────────────

type AtributoVariante = { atributo: string; valor: string };

type Variante = {
    id: number;
    sku: string;
    codigo_barras: string | null;
    precio_venta: number;
    precio_anterior: number | null;
    stock_actual: number;
    stock_minimo: number;
    estado: 'ACTIVO' | 'INACTIVO';
    tiene_movimientos: boolean;
    atributos: AtributoVariante[];
    ultima_venta: string | null;
    fecha_creacion: string | null;
    sin_rotacion?: boolean;
};

type VarianteSinRotacion = Variante & {
    producto_id: number;
    producto: string | null;
};

type CambioPrecio = {
    id: number;
    variante_id: number;
    sku: string | null;
    fecha: string | null;
    hora: string | null;
    usuario: string;
    precio_anterior: number | null;
    precio_nuevo: number | null;
    motivo: string | null;
    direccion_ip: string | null;
};

type Atributo = {
    id: number;
    nombre: string;
    valores: { id: number; valor: string }[];
};

type Pestana = 'variantes' | 'historial' | 'rotacion';

// ── Props ─────────────────────────────────────────────────────────────────

const props = defineProps<{
    producto: {
        id: number;
        nombre: string;
        slug: string;
        estado: 'ACTIVO' | 'INACTIVO';
        categoria: string | null;
        marca: string | null;
        imagen_url: string | null;
    };
    variantes: Variante[];
    historial: CambioPrecio[];
    sinRotacion: {
        meses: number;
        periodos: number[];
        total: number;
        data: VarianteSinRotacion[];
    };
    atributos: Atributo[];
    puedeGestionar: boolean;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Productos', href: '/productos' },
            { title: 'Variantes y precios', href: '#' },
        ],
    },
});

const page = usePage();
const esAdmin = computed(
    () =>
        props.puedeGestionar &&
        ((page.props.auth as any)?.user?.permisos?.includes(
            'productos.gestionar',
        ) ??
            false),
);

// ── Formateo ──────────────────────────────────────────────────────────────

const cantidad = new Intl.NumberFormat('es-BO');
const moneda = new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 2,
});

function textoAtributos(atributos: AtributoVariante[]): string {
    return atributos.length
        ? atributos.map((a) => `${a.atributo}: ${a.valor}`).join(' · ')
        : 'Sin atributos';
}

function badgeStock(v: Variante): string {
    if (v.stock_actual === 0) return 'cc-log__badge--error';
    if (v.stock_actual <= v.stock_minimo) return 'cc-log__badge--alerta';
    return 'cc-log__badge--crear';
}

// ── KPIs y pestañas ───────────────────────────────────────────────────────

const pestana = ref<Pestana>('variantes');

const kpis = computed(() => {
    const filas = props.variantes;
    const stock = filas.reduce((total, v) => total + v.stock_actual, 0);
    const bajoMinimo = filas.filter(
        (v) => v.stock_actual <= v.stock_minimo,
    ).length;
    const sinRotar = filas.filter((v) => v.sin_rotacion).length;

    return [
        { label: 'Variantes', valor: cantidad.format(filas.length) },
        { label: 'Stock total', valor: `${cantidad.format(stock)} uds.` },
        { label: 'En o bajo mínimo', valor: cantidad.format(bajoMinimo) },
        {
            label: `Sin ventas (${props.sinRotacion.meses} meses)`,
            valor: cantidad.format(sinRotar),
        },
    ];
});

const pestanas = computed(() => [
    {
        id: 'variantes' as const,
        label: 'Variantes',
        total: props.variantes.length,
    },
    {
        id: 'historial' as const,
        label: 'Historial de precios',
        total: props.historial.length,
    },
    {
        id: 'rotacion' as const,
        label: 'Sin rotación',
        total: props.sinRotacion.total,
    },
]);

// ── Modal: Nueva variante ─────────────────────────────────────────────────

const NUEVO = '__nuevo';
const altaAbierta = ref(false);

const altaVariante = useForm({
    sku: '',
    codigo_barras: '',
    precio_venta: '' as number | string,
    stock_minimo: 5 as number | string,
    atributos: [] as {
        atributo_id: number;
        valor_atributo_id: number | null;
        valor: string | null;
    }[],
});

const seleccion = reactive<Record<number, { opcion: string; texto: string }>>(
    {},
);

function reiniciarSeleccion(): void {
    props.atributos.forEach((a) => {
        seleccion[a.id] = { opcion: '', texto: '' };
    });
}

function opcionesDe(atributo: Atributo) {
    return [
        { value: '', label: `Sin ${atributo.nombre.toLowerCase()}` },
        ...atributo.valores.map((v) => ({
            value: String(v.id),
            label: v.valor,
        })),
        { value: NUEVO, label: 'Otro valor…' },
    ];
}

function valorElegido(atributo: Atributo): string | null {
    const actual = seleccion[atributo.id];

    if (!actual || actual.opcion === '') return null;
    if (actual.opcion === NUEVO) return actual.texto.trim() || null;

    return (
        atributo.valores.find((v) => String(v.id) === actual.opcion)?.valor ??
        null
    );
}

function abrirVariantes(): void {
    if (props.producto.estado === 'INACTIVO') {
        return;
    }

    altaVariante.reset();
    altaVariante.clearErrors();
    reiniciarSeleccion();
    altaAbierta.value = true;
}

function abreviar(texto: string): string {
    return texto
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^A-Za-z0-9 ]/g, '')
        .toUpperCase()
        .split(/\s+/)
        .filter(Boolean)
        .map((parte) => (/\d/.test(parte) ? parte : parte.slice(0, 3)))
        .join('-');
}

function sugerirSku(): void {
    const base =
        props.variantes[0]?.sku.split('-').slice(0, -1).join('-') ||
        abreviar(props.producto.nombre);
    const sufijo = props.atributos
        .map(valorElegido)
        .filter((v): v is string => v !== null)
        .map(abreviar)
        .join('-');

    altaVariante.sku = [base, sufijo || 'STD'].filter(Boolean).join('-');
}

const erroresAtributos = computed(() =>
    Object.entries(altaVariante.errors as Record<string, string>)
        .filter(([clave]) => clave.startsWith('atributos'))
        .map(([, mensaje]) => mensaje),
);

function confirmar(): void {
    altaVariante
        .transform((datos) => ({
            ...datos,
            atributos: props.atributos
                .map((a) => {
                    const actual = seleccion[a.id];

                    if (!actual || actual.opcion === '') return null;

                    return actual.opcion === NUEVO
                        ? {
                              atributo_id: a.id,
                              valor_atributo_id: null,
                              valor: actual.texto,
                          }
                        : {
                              atributo_id: a.id,
                              valor_atributo_id: Number(actual.opcion),
                              valor: null,
                          };
                })
                .filter((item) => item !== null),
        }))
        .post(`/productos/${props.producto.id}/variantes`, {
            preserveScroll: true,
            onSuccess: () => {
                altaAbierta.value = false;
                pestana.value = 'variantes';
            },
        });
}

// ── Modal: Actualizar precio ──────────────────────────────────────────────

const objetivoPrecio = ref<Variante | null>(null);
const precioAbierto = computed({
    get: () => objetivoPrecio.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) objetivoPrecio.value = null;
    },
});

const formPrecio = useForm({
    precio_venta: '' as number | string,
    motivo: '',
});

function abrirPrecio(variante: Variante): void {
    formPrecio.reset();
    formPrecio.clearErrors();
    formPrecio.precio_venta = variante.precio_venta;
    objetivoPrecio.value = variante;
}

const variacion = computed(() => {
    const actual = objetivoPrecio.value?.precio_venta ?? 0;
    const nuevo = Number(formPrecio.precio_venta);

    if (!actual || !Number.isFinite(nuevo) || nuevo <= 0 || nuevo === actual) {
        return null;
    }

    const porcentaje = ((nuevo - actual) / actual) * 100;

    return {
        sube: nuevo > actual,
        texto: `${porcentaje > 0 ? '+' : ''}${porcentaje.toFixed(1)} %`,
        diferencia: moneda.format(nuevo - actual),
    };
});

function guardarPrecio(): void {
    if (!objetivoPrecio.value) return;

    formPrecio.patch(`/productos/variantes/${objetivoPrecio.value.id}/precio`, {
        preserveScroll: true,
        onSuccess: () => {
            objetivoPrecio.value = null;
        },
    });
}

function consultarRotacion(meses: number): void {
    pestana.value = 'rotacion';
    router.get(
        `/productos/${props.producto.id}/variantes`,
        { meses },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}

const objetivoEstado = ref<Variante | null>(null);
const dialogBajaAbierto = computed({
    get: () => objetivoEstado.value !== null,
    set: (abierto: boolean) => {
        if (!abierto) objetivoEstado.value = null;
    },
});
const procesandoEstado = ref(false);

function cambiarEstado(
    variante: Variante,
    estado: 'ACTIVO' | 'INACTIVO',
): void {
    procesandoEstado.value = true;
    router.patch(
        `/productos/variantes/${variante.id}/estado`,
        { estado },
        {
            preserveScroll: true,
            onFinish: () => {
                procesandoEstado.value = false;
                objetivoEstado.value = null;
            },
        },
    );
}

function eliminarVariante(variante: Variante): void {
    if (variante.tiene_movimientos) {
        objetivoEstado.value = variante;
        return;
    }

    router.delete(`/productos/variantes/${variante.id}`, {
        preserveScroll: true,
        onFinish: () => {
            objetivoEstado.value = null;
        },
    });
}
</script>

<template>
    <div>
        <Head :title="`Variantes · ${producto.nombre}`" />

        <section class="cc-admin cc-log">
            <!-- ── Cabecera ── -->
            <motion.div layout class="cc-log__head" :transition="adminSpring">
                <motion.div
                    layout
                    class="cc-var__titulo"
                    :transition="adminSpring"
                >
                    <span class="cc-var__thumb">
                        <img
                            v-if="producto.imagen_url"
                            :src="producto.imagen_url"
                            :alt="producto.nombre"
                        />
                        <Box v-else :size="22" />
                    </span>
                    <div>
                        <p class="cc-log__kicker">Variantes y precios</p>
                        <h1>{{ producto.nombre }}</h1>
                        <p>
                            {{ producto.categoria ?? 'Sin categoría' }}
                            · {{ producto.marca ?? 'Sin marca' }}
                            <span
                                v-if="producto.estado === 'INACTIVO'"
                                class="cc-log__badge cc-log__badge--error"
                                >INACTIVO</span
                            >
                        </p>
                    </div>
                </motion.div>

                <motion.div
                    layout
                    class="cc-log__actions"
                    :transition="adminSpring"
                >
                    <Link href="/productos" class="cc-button cc-button--ghost">
                        <ArrowLeft :size="16" />
                        Productos
                    </Link>
                    <button
                        v-if="esAdmin && producto.estado === 'ACTIVO'"
                        type="button"
                        class="cc-button"
                        @click="abrirVariantes"
                    >
                        <Plus :size="16" />
                        Nueva variante
                    </button>
                </motion.div>
            </motion.div>

            <!-- ── KPIs ── -->
            <motion.div
                layout
                class="cc-admin__kpis"
                aria-label="Resumen de variantes"
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

            <!-- ── Pestañas ── -->
            <div
                class="cc-var__tabs"
                role="tablist"
                aria-label="Secciones de variantes"
            >
                <button
                    v-for="item in pestanas"
                    :key="item.id"
                    type="button"
                    role="tab"
                    class="cc-var__tab"
                    :class="{ 'is-activa': pestana === item.id }"
                    :aria-selected="pestana === item.id"
                    @click="pestana = item.id"
                >
                    {{ item.label }}
                    <span>{{ cantidad.format(item.total) }}</span>
                </button>
            </div>

            <!-- ── Tabla: variantes del producto ── -->
            <motion.div
                v-if="pestana === 'variantes'"
                layout
                class="cc-log__panel"
                :transition="adminSpring"
            >
                <div class="cc-log__table-wrap">
                    <table class="cc-log__table">
                        <thead>
                            <tr>
                                <th>SKU</th>
                                <th>Atributos</th>
                                <th>Precio de venta</th>
                                <th>Stock</th>
                                <th>Última venta</th>
                                <th>Estado</th>
                                <th v-if="esAdmin">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="variantes.length === 0">
                                <td
                                    class="cc-log__empty"
                                    :colspan="esAdmin ? 7 : 6"
                                >
                                    <strong>Sin variantes</strong>
                                    Este producto todavía no tiene variantes con
                                    SKU y precio.
                                    <button
                                        v-if="esAdmin"
                                        type="button"
                                        class="cc-button"
                                        @click="abrirVariantes"
                                    >
                                        <Plus :size="16" />
                                        Agregar la primera
                                    </button>
                                </td>
                            </tr>

                            <tr
                                v-for="variante in variantes"
                                :key="variante.id"
                                :class="{
                                    'is-off': variante.estado === 'INACTIVO',
                                }"
                            >
                                <td>
                                    <span class="cc-log__when">
                                        <code class="cc-var__sku">{{
                                            variante.sku
                                        }}</code>
                                        <small>{{
                                            variante.codigo_barras ??
                                            'Sin código de barras'
                                        }}</small>
                                    </span>
                                </td>
                                <td>
                                    <span
                                        v-if="variante.atributos.length"
                                        class="cc-var__chips"
                                    >
                                        <span
                                            v-for="a in variante.atributos"
                                            :key="a.atributo"
                                            class="cc-var__chip"
                                        >
                                            <small>{{ a.atributo }}</small>
                                            {{ a.valor }}
                                        </span>
                                    </span>
                                    <span v-else class="cc-log__when"
                                        ><small>Sin atributos</small></span
                                    >
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        <b class="cc-var__precio">{{
                                            moneda.format(variante.precio_venta)
                                        }}</b>
                                        <small
                                            v-if="
                                                variante.precio_anterior !==
                                                null
                                            "
                                        >
                                            Antes
                                            {{
                                                moneda.format(
                                                    variante.precio_anterior,
                                                )
                                            }}
                                        </small>
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        <span
                                            class="cc-log__badge"
                                            :class="badgeStock(variante)"
                                        >
                                            {{
                                                cantidad.format(
                                                    variante.stock_actual,
                                                )
                                            }}
                                            uds.
                                        </span>
                                        <small
                                            >Mínimo
                                            {{
                                                cantidad.format(
                                                    variante.stock_minimo,
                                                )
                                            }}</small
                                        >
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        {{ variante.ultima_venta ?? 'Nunca' }}
                                        <small
                                            v-if="variante.sin_rotacion"
                                            class="cc-var__alerta"
                                        >
                                            Sin ventas en
                                            {{ sinRotacion.meses }} meses
                                        </small>
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="cc-log__badge"
                                        :class="
                                            variante.estado === 'ACTIVO'
                                                ? 'cc-log__badge--crear'
                                                : 'cc-log__badge--error'
                                        "
                                    >
                                        {{ variante.estado }}
                                    </span>
                                </td>
                                <td v-if="esAdmin">
                                    <span class="cc-row-actions">
                                        <button
                                            type="button"
                                            class="cc-log__eye"
                                            title="Actualizar precio"
                                            :aria-label="`Actualizar precio de ${variante.sku}`"
                                            @click="abrirPrecio(variante)"
                                        >
                                            <Pencil :size="15" />
                                        </button>
                                        <button
                                            v-if="variante.estado === 'ACTIVO'"
                                            type="button"
                                            class="cc-log__eye cc-log__eye--danger"
                                            title="Dar de baja"
                                            :aria-label="`Dar de baja ${variante.sku}`"
                                            @click="objetivoEstado = variante"
                                        >
                                            <Trash2 :size="15" />
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            class="cc-log__eye"
                                            title="Reactivar"
                                            :aria-label="`Reactivar ${variante.sku}`"
                                            @click="
                                                cambiarEstado(
                                                    variante,
                                                    'ACTIVO',
                                                )
                                            "
                                        >
                                            <RotateCcw :size="15" />
                                        </button>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </motion.div>

            <!-- ── Tabla: historial de precios (bitacora_auditoria) ── -->
            <motion.div
                v-else-if="pestana === 'historial'"
                layout
                class="cc-log__panel"
                :transition="adminSpring"
            >
                <div class="cc-log__table-wrap">
                    <table class="cc-log__table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>SKU</th>
                                <th>Cambio</th>
                                <th>Motivo</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="historial.length === 0">
                                <td class="cc-log__empty" colspan="5">
                                    <History :size="20" />
                                    <strong>Sin cambios de precio</strong>
                                    Cada actualización de precio queda
                                    registrada aquí y en la bitácora.
                                </td>
                            </tr>
                            <tr v-for="cambio in historial" :key="cambio.id">
                                <td>
                                    <span class="cc-log__when">
                                        {{ cambio.fecha }}
                                        <small>{{ cambio.hora }}</small>
                                    </span>
                                </td>
                                <td>
                                    <code class="cc-var__sku">{{
                                        cambio.sku ?? `#${cambio.variante_id}`
                                    }}</code>
                                </td>
                                <td>
                                    <span class="cc-var__cambio">
                                        <s>{{
                                            cambio.precio_anterior !== null
                                                ? moneda.format(
                                                      cambio.precio_anterior,
                                                  )
                                                : '—'
                                        }}</s>
                                        <component
                                            :is="
                                                (cambio.precio_nuevo ?? 0) >=
                                                (cambio.precio_anterior ?? 0)
                                                    ? TrendingUp
                                                    : TrendingDown
                                            "
                                            :size="14"
                                        />
                                        <b>{{
                                            cambio.precio_nuevo !== null
                                                ? moneda.format(
                                                      cambio.precio_nuevo,
                                                  )
                                                : '—'
                                        }}</b>
                                    </span>
                                </td>
                                <td>{{ cambio.motivo ?? '—' }}</td>
                                <td>
                                    <span class="cc-log__when">
                                        {{ cambio.usuario }}
                                        <small>{{
                                            cambio.direccion_ip ?? ''
                                        }}</small>
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </motion.div>

            <!-- ── Tabla: variantes sin rotación (todo el catálogo) ── -->
            <motion.div
                v-else
                layout
                class="cc-log__panel"
                :transition="adminSpring"
            >
                <div class="cc-var__rotacion-head">
                    <p class="cc-var__nota">
                        Variantes activas con stock y sin ventas en el período
                        elegido, ordenadas por capital inmovilizado.
                        <template
                            v-if="sinRotacion.total > sinRotacion.data.length"
                        >
                            Se muestran {{ sinRotacion.data.length }} de
                            {{ cantidad.format(sinRotacion.total) }}.
                        </template>
                    </p>
                    <div
                        class="cc-var__periodos"
                        role="group"
                        aria-label="Período sin rotación"
                    >
                        <button
                            v-for="meses in sinRotacion.periodos"
                            :key="meses"
                            type="button"
                            class="cc-button"
                            :class="
                                sinRotacion.meses === meses
                                    ? ''
                                    : 'cc-button--ghost'
                            "
                            @click="consultarRotacion(meses)"
                        >
                            {{ meses }} meses
                        </button>
                    </div>
                </div>
                <div class="cc-log__table-wrap">
                    <table class="cc-log__table">
                        <thead>
                            <tr>
                                <th>Producto / SKU</th>
                                <th>Atributos</th>
                                <th>Stock</th>
                                <th>Valor en stock</th>
                                <th>Última venta</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="sinRotacion.data.length === 0">
                                <td class="cc-log__empty" colspan="5">
                                    <PackageX :size="20" />
                                    <strong
                                        >No se encontraron productos sin
                                        movimiento</strong
                                    >
                                    No hay mercadería con stock parada en los
                                    últimos
                                    {{ sinRotacion.meses }} meses.
                                </td>
                            </tr>
                            <tr v-for="item in sinRotacion.data" :key="item.id">
                                <td>
                                    <Link
                                        :href="`/productos/${item.producto_id}/variantes`"
                                        class="cc-log__when cc-var__link"
                                    >
                                        {{ item.producto ?? 'Producto' }}
                                        <small
                                            ><code class="cc-var__sku">{{
                                                item.sku
                                            }}</code></small
                                        >
                                    </Link>
                                </td>
                                <td>{{ textoAtributos(item.atributos) }}</td>
                                <td>
                                    <span
                                        class="cc-log__badge"
                                        :class="badgeStock(item)"
                                    >
                                        {{ cantidad.format(item.stock_actual) }}
                                        uds.
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        <b class="cc-var__precio">{{
                                            moneda.format(
                                                item.stock_actual *
                                                    item.precio_venta,
                                            )
                                        }}</b>
                                        <small
                                            >{{
                                                moneda.format(item.precio_venta)
                                            }}
                                            c/u</small
                                        >
                                    </span>
                                </td>
                                <td>
                                    <span class="cc-log__when">
                                        {{ item.ultima_venta ?? 'Nunca' }}
                                        <small
                                            >Alta
                                            {{
                                                item.fecha_creacion ?? '—'
                                            }}</small
                                        >
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </motion.div>
        </section>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- Modal: Nueva variante                                           -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <Dialog v-if="esAdmin" v-model:open="altaAbierta">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog"
                style="max-width: 580px"
            >
                <DialogHeader>
                    <DialogTitle>Nueva variante</DialogTitle>
                    <DialogDescription>
                        Definí los atributos, el SKU y el precio de venta para
                        {{ producto.nombre }}. El stock inicia en 0 y se carga
                        con las compras.
                    </DialogDescription>
                </DialogHeader>

                <form class="cc-modal-form" @submit.prevent="confirmar">
                    <!-- Atributos -->
                    <fieldset class="cc-modal-field cc-var__atributos">
                        <legend>Atributos</legend>
                        <div
                            v-for="atributo in atributos"
                            :key="atributo.id"
                            class="cc-var__atributo"
                        >
                            <span>{{ atributo.nombre }}</span>
                            <CcRevealSelect
                                v-if="seleccion[atributo.id]"
                                v-model="seleccion[atributo.id].opcion"
                                :etiqueta="`Sin ${atributo.nombre.toLowerCase()}`"
                                :opciones="opcionesDe(atributo)"
                            />
                            <label
                                v-if="seleccion[atributo.id]?.opcion === NUEVO"
                                class="cc-log__control"
                            >
                                <input
                                    v-model="seleccion[atributo.id].texto"
                                    type="text"
                                    maxlength="150"
                                    :placeholder="`Nuevo valor de ${atributo.nombre.toLowerCase()}`"
                                    required
                                />
                            </label>
                        </div>
                        <p v-if="atributos.length === 0" class="cc-var__nota">
                            No hay atributos configurados; la variante se creará
                            sin atributos.
                        </p>
                        <p
                            v-for="mensaje in erroresAtributos"
                            :key="mensaje"
                            class="cc-modal-error"
                        >
                            {{ mensaje }}
                        </p>
                    </fieldset>

                    <!-- SKU -->
                    <div class="cc-modal-field">
                        <span>SKU <b class="cc-req">*</b></span>
                        <div class="cc-var__sku-row">
                            <label class="cc-log__control">
                                <input
                                    v-model="altaVariante.sku"
                                    type="text"
                                    maxlength="100"
                                    placeholder="MOU-LOG-G203-BLA"
                                    autocomplete="off"
                                    required
                                    @input="
                                        altaVariante.sku =
                                            altaVariante.sku.toUpperCase()
                                    "
                                />
                            </label>
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                title="Sugerir SKU"
                                @click="sugerirSku"
                            >
                                <Sparkles :size="15" />
                                Sugerir
                            </button>
                        </div>
                        <p
                            v-if="altaVariante.errors.sku"
                            class="cc-modal-error"
                        >
                            {{ altaVariante.errors.sku }}
                        </p>
                    </div>

                    <!-- Código de barras -->
                    <label class="cc-modal-field">
                        <span>Código de barras <small>(opcional)</small></span>
                        <span class="cc-log__control">
                            <input
                                v-model="altaVariante.codigo_barras"
                                type="text"
                                maxlength="100"
                                inputmode="numeric"
                            />
                        </span>
                        <p
                            v-if="altaVariante.errors.codigo_barras"
                            class="cc-modal-error"
                        >
                            {{ altaVariante.errors.codigo_barras }}
                        </p>
                    </label>

                    <div class="cc-var__pareja">
                        <!-- Precio -->
                        <label class="cc-modal-field">
                            <span>Precio de venta (Bs) <b class="cc-req">*</b></span>
                            <span class="cc-log__control">
                                <input
                                    v-model="altaVariante.precio_venta"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    inputmode="decimal"
                                    placeholder="0.00"
                                    required
                                />
                            </span>
                            <p
                                v-if="altaVariante.errors.precio_venta"
                                class="cc-modal-error"
                            >
                                {{ altaVariante.errors.precio_venta }}
                            </p>
                        </label>

                        <!-- Stock mínimo -->
                        <label class="cc-modal-field">
                            <span>Stock mínimo <b class="cc-req">*</b></span>
                            <span class="cc-log__control">
                                <input
                                    v-model="altaVariante.stock_minimo"
                                    type="number"
                                    min="0"
                                    step="1"
                                    required
                                />
                            </span>
                            <p
                                v-if="altaVariante.errors.stock_minimo"
                                class="cc-modal-error"
                            >
                                {{ altaVariante.errors.stock_minimo }}
                            </p>
                        </label>
                    </div>

                    <div class="cc-modal-actions">
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            @click="altaAbierta = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="cc-button"
                            :class="{ 'is-load': altaVariante.processing }"
                            :disabled="altaVariante.processing"
                        >
                            <span
                                v-if="altaVariante.processing"
                                class="cc-admin__spin"
                            />
                            Registrar variante
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ════════════════════════════════════════════════════════════════ -->
        <!-- Modal: Actualizar precio                                        -->
        <!-- ════════════════════════════════════════════════════════════════ -->
        <Dialog v-if="esAdmin" v-model:open="precioAbierto">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog"
                style="max-width: 440px"
            >
                <template v-if="objetivoPrecio">
                    <DialogHeader>
                        <DialogTitle>Actualizar precio</DialogTitle>
                        <DialogDescription>
                            <code class="cc-var__sku">{{
                                objetivoPrecio.sku
                            }}</code>
                            · {{ textoAtributos(objetivoPrecio.atributos) }}. El
                            cambio queda registrado en la bitácora con tu
                            usuario.
                        </DialogDescription>
                    </DialogHeader>

                    <form class="cc-modal-form" @submit.prevent="guardarPrecio">
                        <dl class="cc-log-detalle__datos">
                            <div>
                                <dt>Precio actual</dt>
                                <dd>
                                    <strong>{{
                                        moneda.format(
                                            objetivoPrecio.precio_venta,
                                        )
                                    }}</strong>
                                </dd>
                            </div>
                            <div v-if="variacion">
                                <dt>Variación</dt>
                                <dd>
                                    <span
                                        class="cc-log__badge"
                                        :class="
                                            variacion.sube
                                                ? 'cc-log__badge--crear'
                                                : 'cc-log__badge--alerta'
                                        "
                                    >
                                        {{ variacion.texto }} ({{
                                            variacion.diferencia
                                        }})
                                    </span>
                                </dd>
                            </div>
                        </dl>

                        <label class="cc-modal-field">
                            <span>Nuevo precio (Bs) <b class="cc-req">*</b></span>
                            <span class="cc-log__control">
                                <input
                                    v-model="formPrecio.precio_venta"
                                    type="number"
                                    min="0.01"
                                    step="0.01"
                                    inputmode="decimal"
                                    required
                                />
                            </span>
                            <p
                                v-if="formPrecio.errors.precio_venta"
                                class="cc-modal-error"
                            >
                                {{ formPrecio.errors.precio_venta }}
                            </p>
                        </label>

                        <label class="cc-modal-field">
                            <span>Motivo <small>(opcional)</small></span>
                            <span class="cc-log__control cc-log__control--area">
                                <textarea
                                    v-model="formPrecio.motivo"
                                    rows="2"
                                    maxlength="255"
                                    placeholder="Ej. Subió la cotización del dólar"
                                />
                            </span>
                            <p
                                v-if="formPrecio.errors.motivo"
                                class="cc-modal-error"
                            >
                                {{ formPrecio.errors.motivo }}
                            </p>
                        </label>

                        <div class="cc-modal-actions">
                            <button
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="objetivoPrecio = null"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                class="cc-button"
                                :class="{ 'is-load': formPrecio.processing }"
                                :disabled="formPrecio.processing"
                            >
                                <span
                                    v-if="formPrecio.processing"
                                    class="cc-admin__spin"
                                />
                                Guardar precio
                            </button>
                        </div>
                    </form>
                </template>
            </DialogContent>
        </Dialog>

        <Dialog v-if="esAdmin" v-model:open="dialogBajaAbierto">
            <DialogContent
                class="cc-theme cc-admin cc-log-dialog cc-admin-dialog--danger"
                style="max-width: 460px"
            >
                <DialogHeader>
                    <DialogTitle>Dar de baja la variante</DialogTitle>
                    <DialogDescription>
                        <template v-if="objetivoEstado?.tiene_movimientos">
                            <code class="cc-var__sku">{{
                                objetivoEstado.sku
                            }}</code>
                            tiene ventas, compras u otros movimientos, así que
                            no se puede eliminar. Dejala INACTIVA para que deje
                            de usarse en ventas nuevas.
                        </template>
                        <template v-else>
                            ¿Dar de baja
                            <strong>{{ objetivoEstado?.sku }}</strong
                            >? Quedará INACTIVA. Si no tiene movimientos,
                            también podés eliminarla.
                        </template>
                    </DialogDescription>
                </DialogHeader>
                <div class="cc-modal-actions">
                    <button
                        type="button"
                        class="cc-button cc-button--ghost"
                        @click="objetivoEstado = null"
                    >
                        Cancelar
                    </button>
                    <button
                        v-if="
                            objetivoEstado && !objetivoEstado.tiene_movimientos
                        "
                        type="button"
                        class="cc-button cc-button--ghost"
                        :disabled="procesandoEstado"
                        @click="
                            objetivoEstado && eliminarVariante(objetivoEstado)
                        "
                    >
                        Eliminar
                    </button>
                    <button
                        type="button"
                        class="cc-button cc-button--danger"
                        :disabled="procesandoEstado"
                        @click="
                            objetivoEstado &&
                            cambiarEstado(objetivoEstado, 'INACTIVO')
                        "
                    >
                        <span v-if="procesandoEstado" class="cc-admin__spin" />
                        Dar de baja
                    </button>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>

<style scoped>
.cc-var__titulo {
    display: flex;
    gap: 14px;
    align-items: center;
}

.cc-var__thumb {
    display: grid;
    width: 56px;
    height: 56px;
    flex: none;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--cc-border);
    border-radius: 8px;
    background: var(--cc-card);
    color: var(--cc-muted);
}

.cc-var__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* ── Pestañas ── */
.cc-var__tabs {
    display: flex;
    gap: 4px;
    overflow-x: auto;
    border-bottom: 1px solid var(--cc-border);
}

.cc-var__tab {
    display: inline-flex;
    gap: 8px;
    align-items: center;
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

.cc-var__tab span {
    padding: 1px 7px;
    border-radius: 999px;
    background: color-mix(in srgb, var(--cc-muted) 14%, transparent);
    font-size: 11px;
    font-variant-numeric: tabular-nums;
}

.cc-var__tab:hover,
.cc-var__tab:focus-visible {
    color: var(--cc-text);
}

.cc-var__tab.is-activa {
    border-bottom-color: var(--cc-accent);
    color: var(--cc-text);
}

.cc-var__tab.is-activa span {
    background: color-mix(in srgb, var(--cc-accent) 18%, transparent);
    color: var(--cc-accent);
}

/* ── Celdas ── */
.cc-var__sku {
    padding: 2px 6px;
    border-radius: 4px;
    background: color-mix(in srgb, var(--cc-muted) 10%, transparent);
    color: var(--cc-text);
    font-family: 'IBM Plex Mono', monospace;
    font-size: 12px;
}

.cc-var__precio {
    color: var(--cc-accent);
    font-variant-numeric: tabular-nums;
    font-weight: 600;
}

.cc-var__chips {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.cc-var__chip {
    display: inline-flex;
    gap: 5px;
    align-items: baseline;
    padding: 2px 8px;
    border: 1px solid var(--cc-border);
    border-radius: 999px;
    font-size: 12px;
}

.cc-var__chip small {
    color: var(--cc-muted);
    font-size: 10px;
    text-transform: uppercase;
}

.cc-var__alerta {
    color: var(--cc-error);
}

.cc-var__cambio {
    display: inline-flex;
    gap: 6px;
    align-items: center;
    font-variant-numeric: tabular-nums;
}

.cc-var__cambio s {
    color: var(--cc-muted);
}

.cc-var__link {
    color: inherit;
    text-decoration: none;
}

.cc-var__link:hover {
    color: var(--cc-accent);
}

.cc-var__nota {
    margin: 0;
    padding: 12px 16px 0;
    color: var(--cc-muted);
    font-size: 13px;
}

.cc-var__rotacion-head {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: flex-end;
    justify-content: space-between;
}

.cc-var__rotacion-head .cc-var__nota {
    flex: 1;
    min-width: 220px;
}

.cc-var__periodos {
    display: flex;
    gap: 6px;
    padding: 12px 16px 0;
}

/* ── Formulario modal ── */
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

.cc-var__atributos {
    gap: 8px;
    margin: 0;
    padding: 12px;
    border: 1px solid var(--cc-border);
    border-radius: 8px;
}

.cc-var__atributos > legend {
    padding: 0 4px;
    color: var(--cc-muted);
    font-size: 12px;
    font-weight: 400;
}

.cc-var__atributos .cc-var__nota {
    padding: 0;
}

.cc-var__atributo {
    display: grid;
    grid-template-columns: 110px 1fr;
    gap: 8px;
    align-items: center;
}

.cc-var__atributo > span {
    color: var(--cc-text);
    font-size: 13px;
    font-weight: 500;
}

.cc-var__atributo > label {
    grid-column: 2;
}

.cc-var__sku-row {
    display: flex;
    gap: 8px;
}

.cc-var__sku-row > label {
    flex: 1;
}

.cc-var__pareja {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

@media (max-width: 520px) {
    .cc-var__pareja,
    .cc-var__atributo {
        grid-template-columns: 1fr;
    }

    .cc-var__atributo > label {
        grid-column: 1;
    }
}
</style>
