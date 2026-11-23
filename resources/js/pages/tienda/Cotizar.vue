<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { AlertTriangle, ChevronLeft, FileText } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import {
    bolivianos,
    useCarrito,
    type VarianteVigente,
} from '@/composables/useCarrito';

type CambioPrecio = {
    variante_id: number;
    producto: string;
    anterior: number;
    nuevo: number;
};

const props = defineProps<{
    cliente: {
        nombre: string | null;
        telefono: string | null;
        email: string;
        tipo_documento: string | null;
        numero_documento: string | null;
    };
    validezDias: number;
}>();

const { items, total, sincronizar } = useCarrito();

const montado = ref(false);
const sincronizando = ref(false);
const avisos = ref<string[]>([]);
const cambiosPrecio = ref<CambioPrecio[]>([]);

const form = useForm({
    items: [] as {
        variante_id: number;
        cantidad: number;
        precio_visto: number;
    }[],
    nombre: props.cliente.nombre ?? '',
    telefono: props.cliente.telefono ?? '',
    email: props.cliente.email,
    tipo_documento: props.cliente.tipo_documento ?? '',
    numero_documento: props.cliente.numero_documento ?? '',
});

const errores = computed(
    () => form.errors as Record<string, string | undefined>,
);

const errorItems = computed(
    () =>
        errores.value.items ??
        Object.entries(errores.value).find(([clave]) =>
            clave.startsWith('items.'),
        )?.[1] ??
        null,
);

/** EX-02, EX-03 y EX-06: pone el carrito al día con los precios y el stock de la base. */
async function refrescar(): Promise<void> {
    if (items.value.length === 0) {
        return;
    }

    sincronizando.value = true;

    try {
        const ids = items.value.map((item) => item.variante_id).join(',');
        const respuesta = await fetch(`/tienda/carrito/variantes?ids=${ids}`, {
            headers: { Accept: 'application/json' },
        });

        if (respuesta.ok) {
            const datos = (await respuesta.json()) as {
                variantes: VarianteVigente[];
            };

            avisos.value = sincronizar(datos.variantes);
        }
    } finally {
        sincronizando.value = false;
    }
}

onMounted(async () => {
    montado.value = true;
    await refrescar();
});

function confirmar(): void {
    form.items = items.value.map((item) => ({
        variante_id: item.variante_id,
        cantidad: item.cantidad,
        precio_visto: item.precio,
    }));
    cambiosPrecio.value = [];

    form.post('/tienda/cotizaciones', {
        preserveScroll: true,
        onFlash: (flash) => {
            const nuevos = (flash as Record<string, unknown> | undefined)
                ?.precios_nuevos;

            if (Array.isArray(nuevos)) {
                cambiosPrecio.value = nuevos as CambioPrecio[];
                void refrescar();
            }
        },
    });
}

const vacio = computed(() => montado.value && items.value.length === 0);
</script>

<template>
    <div class="cc-shop__section">
        <Head title="Confirmar cotización" />

        <nav class="cc-migas" aria-label="Ruta">
            <Link href="/tienda/carrito"
                ><ChevronLeft :size="14" /> Volver al carrito</Link
            >
        </nav>

        <ol class="cc-etapas" aria-label="Etapas de la cotización">
            <li class="is-done">Carrito</li>
            <li class="is-current" aria-current="step">Datos de contacto</li>
            <li>Cotización</li>
        </ol>

        <div v-if="vacio" class="cc-vacio" data-test="carrito-vacio">
            <strong>Tu carrito está vacío</strong>
            <p>Agrega productos para generar una cotización.</p>
            <div class="cc-vacio__acciones">
                <Link href="/tienda" class="cc-button cc-button--solid"
                    >Ir al catálogo</Link
                >
                <button type="button" class="cc-button" disabled>
                    <FileText :size="16" />
                    Generar cotización
                </button>
            </div>
        </div>

        <form v-else class="cc-checkout" novalidate @submit.prevent="confirmar">
            <section class="cc-panel">
                <h1>Confirma tus datos de contacto</h1>
                <p class="cc-panel__nota">
                    La tienda usará estos datos para responder tu cotización.
                    Los guardamos en tu cuenta.
                </p>

                <ul
                    v-if="avisos.length"
                    class="cc-avisos"
                    role="status"
                    data-test="avisos"
                >
                    <li v-for="aviso in avisos" :key="aviso">{{ aviso }}</li>
                </ul>

                <div
                    v-if="cambiosPrecio.length"
                    class="cc-error--bloque"
                    role="alert"
                    data-test="precios-actualizados"
                >
                    <strong
                        ><AlertTriangle :size="14" /> Los precios
                        cambiaron</strong
                    >
                    <ul class="cc-cambios">
                        <li
                            v-for="cambio in cambiosPrecio"
                            :key="cambio.variante_id"
                        >
                            {{ cambio.producto }}:
                            <s>{{ bolivianos(cambio.anterior) }}</s>
                            →
                            <b>{{ bolivianos(cambio.nuevo) }}</b>
                        </li>
                    </ul>
                    <span
                        >Revisa el total actualizado y confirma otra vez.</span
                    >
                </div>

                <label class="cc-campo">
                    <span>Nombre completo <b class="cc-req">*</b></span>
                    <input
                        v-model="form.nombre"
                        type="text"
                        maxlength="200"
                        autocomplete="name"
                        :aria-invalid="!!form.errors.nombre"
                        data-test="nombre"
                    />
                    <small v-if="form.errors.nombre" class="cc-error">{{
                        form.errors.nombre
                    }}</small>
                </label>

                <label class="cc-campo">
                    <span>Teléfono / WhatsApp <b class="cc-req">*</b></span>
                    <input
                        v-model="form.telefono"
                        type="tel"
                        maxlength="15"
                        inputmode="tel"
                        autocomplete="tel"
                        :aria-invalid="!!form.errors.telefono"
                        data-test="telefono"
                    />
                    <small v-if="form.errors.telefono" class="cc-error">{{
                        form.errors.telefono
                    }}</small>
                </label>

                <label class="cc-campo">
                    <span>Correo electrónico <b class="cc-req">*</b></span>
                    <input
                        v-model="form.email"
                        type="email"
                        maxlength="150"
                        autocomplete="email"
                        :aria-invalid="!!form.errors.email"
                        data-test="email"
                    />
                    <small v-if="form.errors.email" class="cc-error">{{
                        form.errors.email
                    }}</small>
                </label>

                <div class="cc-campo-par">
                    <label class="cc-campo">
                        <span>Documento (opcional)</span>
                        <select
                            v-model="form.tipo_documento"
                            :aria-invalid="!!form.errors.tipo_documento"
                        >
                            <option value="">Sin documento</option>
                            <option value="CI">CI</option>
                            <option value="NIT">NIT</option>
                            <option value="PASAPORTE">Pasaporte</option>
                        </select>
                        <small
                            v-if="form.errors.tipo_documento"
                            class="cc-error"
                            >{{ form.errors.tipo_documento }}</small
                        >
                    </label>
                    <label class="cc-campo">
                        <span>Número</span>
                        <input
                            v-model="form.numero_documento"
                            type="text"
                            maxlength="50"
                            :aria-invalid="!!form.errors.numero_documento"
                        />
                        <small
                            v-if="form.errors.numero_documento"
                            class="cc-error"
                            >{{ form.errors.numero_documento }}</small
                        >
                    </label>
                </div>
            </section>

            <aside class="cc-resumen" aria-label="Resumen de la cotización">
                <h2>Tu cotización</h2>
                <ul
                    v-if="montado"
                    class="cc-resumen__items"
                    :aria-busy="sincronizando"
                >
                    <li v-for="item in items" :key="item.variante_id">
                        <span>{{ item.cantidad }} × {{ item.producto }}</span>
                        <span>{{
                            bolivianos(item.precio * item.cantidad)
                        }}</span>
                    </li>
                </ul>
                <dl>
                    <div class="cc-resumen__total">
                        <dt>Total</dt>
                        <dd data-test="total">
                            {{ bolivianos(montado ? total : 0) }}
                        </dd>
                    </div>
                </dl>

                <p
                    v-if="errorItems"
                    class="cc-error cc-error--bloque"
                    role="alert"
                >
                    {{ errorItems }}
                </p>
                <p
                    v-if="errores.precios && !cambiosPrecio.length"
                    class="cc-error cc-error--bloque"
                    role="alert"
                >
                    {{ errores.precios }}
                </p>

                <button
                    type="submit"
                    class="cc-button cc-button--solid cc-button--block"
                    :disabled="
                        form.processing ||
                        sincronizando ||
                        !montado ||
                        items.length === 0
                    "
                    data-test="confirmar-cotizacion"
                >
                    <FileText :size="16" />
                    {{
                        form.processing
                            ? 'Generando…'
                            : 'Confirmar y generar cotización'
                    }}
                </button>
                <p class="cc-resumen__nota">
                    Precios válidos por {{ validezDias }}
                    {{ validezDias === 1 ? 'día' : 'días' }}. No reserva stock.
                </p>
            </aside>
        </form>
    </div>
</template>

<style scoped>
.cc-campo-par {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 2fr);
    gap: 12px;
}

@media (max-width: 520px) {
    .cc-campo-par {
        grid-template-columns: 1fr;
    }
}

.cc-cambios {
    margin: 6px 0;
    padding-left: 18px;
}

.cc-campo select {
    border: 1px solid var(--cc-border);
    border-radius: 10px;
    background: var(--cc-surface);
    padding: 10px 12px;
    color: var(--cc-text);
    font: inherit;
}
</style>
