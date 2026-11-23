<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { CheckCircle2, Clock, Download, Star, XCircle } from '@lucide/vue';
import { computed, ref } from 'vue';
import Estrellas from '@/components/tienda/Estrellas.vue';
import SelectorEstrellas from '@/components/tienda/SelectorEstrellas.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { bolivianos } from '@/composables/useCarrito';

type Calificacion = {
    puntuacion: number;
    comentario: string | null;
    estado: 'PENDIENTE' | 'PUBLICADA' | 'OCULTA';
    fecha: string | null;
};

type Item = {
    producto_id: number | null;
    producto: string | null;
    variante: string | null;
    cantidad: number;
    precio: number;
    subtotal: number;
    puede_calificar: boolean;
    calificacion: Calificacion | null;
};

type Pedido = {
    numero: string;
    estado: string;
    fecha: string | null;
    fecha_pago: string | null;
    total: number;
    contacto: string | null;
    telefono: string | null;
    entrega: string;
    observacion: string | null;
    error_pago: string | null;
    venta: string | null;
    comprobante: string | null;
    items: Item[];
};

const props = defineProps<{ pedido: Pedido; nuevo: boolean }>();

const MAXIMO_COMENTARIO = 500;

const calificando = ref<Item | null>(null);
const formulario = useForm({
    producto_id: 0,
    puntuacion: 0,
    comentario: '',
});

const errorGeneral = computed(() => {
    const errores = formulario.errors as Record<string, string | undefined>;

    return errores.producto_id ?? errores.pedido;
});

const dialogoAbierto = computed({
    get: () => calificando.value !== null,
    set: (abierto: boolean) => {
        if (!abierto && !formulario.processing) {
            calificando.value = null;
        }
    },
});

const ESTADOS_CALIFICACION: Record<Calificacion['estado'], string> = {
    PENDIENTE: 'En revisión',
    PUBLICADA: 'Publicada',
    OCULTA: 'No publicada',
};

function abrirCalificacion(item: Item): void {
    if (item.producto_id === null) {
        return;
    }

    formulario.reset();
    formulario.clearErrors();
    formulario.producto_id = item.producto_id;
    calificando.value = item;
}

function enviarCalificacion(): void {
    formulario.clearErrors();

    if (formulario.puntuacion < 1 || formulario.puntuacion > 5) {
        formulario.setError('puntuacion', 'Elige de 1 a 5 estrellas.');

        return;
    }

    if (formulario.comentario.length > MAXIMO_COMENTARIO) {
        formulario.setError(
            'comentario',
            `El comentario admite hasta ${MAXIMO_COMENTARIO} caracteres.`,
        );

        return;
    }

    formulario.post(`/tienda/pedidos/${props.pedido.numero}/calificaciones`, {
        preserveScroll: true,
        onSuccess: () => {
            calificando.value = null;
        },
    });
}

const estado = computed(() => {
    switch (props.pedido.estado) {
        case 'PAGADO':
            return {
                icono: CheckCircle2,
                titulo: props.nuevo
                    ? '¡Gracias por tu compra!'
                    : 'Pedido pagado',
                clase: 'ok',
            };
        case 'PENDIENTE_PAGO':
            return {
                icono: Clock,
                titulo: 'Pago pendiente',
                clase: 'pendiente',
            };
        default:
            return {
                icono: XCircle,
                titulo:
                    props.pedido.estado === 'REEMBOLSADO'
                        ? 'Pago reembolsado'
                        : 'Pedido cancelado',
                clase: 'error',
            };
    }
});
</script>

<template>
    <div class="cc-shop__section cc-comprobante-wrap">
        <Head :title="`Pedido ${pedido.numero}`" />

        <article class="cc-comprobante">
            <header class="cc-comprobante__head" :data-estado="estado.clase">
                <component :is="estado.icono" :size="40" aria-hidden="true" />
                <div>
                    <h1>{{ estado.titulo }}</h1>
                    <p v-if="pedido.estado === 'PAGADO'">
                        Tu pago fue aprobado. Te avisaremos al
                        {{ pedido.telefono }} cuando el pedido esté listo para
                        retirar.
                    </p>
                    <p v-else-if="pedido.estado === 'PENDIENTE_PAGO'">
                        Todavía no recibimos el pago de este pedido.
                    </p>
                    <p v-else-if="pedido.error_pago">{{ pedido.error_pago }}</p>
                </div>
            </header>

            <dl class="cc-comprobante__datos">
                <div>
                    <dt>Pedido</dt>
                    <dd>{{ pedido.numero }}</dd>
                </div>
                <div v-if="pedido.venta">
                    <dt>Comprobante</dt>
                    <dd>{{ pedido.venta }}</dd>
                </div>
                <div>
                    <dt>Fecha</dt>
                    <dd>{{ pedido.fecha_pago ?? pedido.fecha }}</dd>
                </div>
                <div>
                    <dt>Retira</dt>
                    <dd>{{ pedido.contacto }}</dd>
                </div>
                <div>
                    <dt>Entrega</dt>
                    <dd>{{ pedido.entrega }}</dd>
                </div>
                <div v-if="pedido.estado === 'PAGADO'">
                    <dt>Pago</dt>
                    <dd>Tarjeta · Stripe</dd>
                </div>
            </dl>

            <div class="cc-comprobante__tabla-wrap">
                <table class="cc-comprobante__tabla">
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th>Cant.</th>
                            <th>Precio</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(item, i) in pedido.items" :key="i">
                            <td>
                                {{ item.producto
                                }}<small v-if="item.variante">{{
                                    item.variante
                                }}</small>
                                <div
                                    v-if="
                                        pedido.estado === 'PAGADO' &&
                                        (item.puede_calificar ||
                                            item.calificacion)
                                    "
                                    class="cc-calif-item"
                                >
                                    <button
                                        v-if="item.puede_calificar"
                                        type="button"
                                        class="cc-button cc-button--ghost"
                                        :data-test="`calificar-${item.producto_id}`"
                                        @click="abrirCalificacion(item)"
                                    >
                                        <Star :size="14" /> Calificar
                                    </button>
                                    <div
                                        v-else-if="item.calificacion"
                                        class="cc-calif-item__hecho"
                                        data-test="calificacion-existente"
                                    >
                                        <strong
                                            >Ya calificaste este
                                            producto</strong
                                        >
                                        <Estrellas
                                            :valor="
                                                item.calificacion.puntuacion
                                            "
                                            :tamano="14"
                                        />
                                        <span
                                            class="cc-calif-estado"
                                            :data-estado="
                                                item.calificacion.estado
                                            "
                                        >
                                            {{
                                                ESTADOS_CALIFICACION[
                                                    item.calificacion.estado
                                                ]
                                            }}
                                        </span>
                                        <p
                                            v-if="item.calificacion.comentario"
                                            class="cc-calif-comentario"
                                        >
                                            “{{
                                                item.calificacion.comentario
                                            }}”
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td>{{ item.cantidad }}</td>
                            <td>{{ bolivianos(item.precio) }}</td>
                            <td>{{ bolivianos(item.subtotal) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3">Total</td>
                            <td>{{ bolivianos(pedido.total) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <footer class="cc-comprobante__acciones">
                <a
                    v-if="pedido.comprobante"
                    :href="pedido.comprobante"
                    class="cc-button cc-button--solid"
                >
                    <Download :size="16" /> Descargar comprobante (PDF)
                </a>
                <Link
                    v-if="pedido.estado === 'PENDIENTE_PAGO'"
                    :href="`/tienda/pedidos/${pedido.numero}/pago`"
                    class="cc-button cc-button--solid"
                >
                    Completar pago
                </Link>
                <Link href="/tienda/pedidos" class="cc-button cc-button--ghost"
                    >Mis pedidos</Link
                >
                <Link href="/tienda" class="cc-button cc-button--ghost"
                    >Seguir comprando</Link
                >
            </footer>
        </article>

        <Dialog v-model:open="dialogoAbierto">
            <DialogContent
                class="cc-theme cc-calif-dialog"
                style="max-width: 480px"
            >
                <DialogHeader>
                    <DialogTitle>Calificar producto</DialogTitle>
                    <DialogDescription>
                        {{ calificando?.producto }}. Tu opinión se publica en el
                        catálogo después de una revisión.
                    </DialogDescription>
                </DialogHeader>

                <form
                    class="cc-calif-form"
                    novalidate
                    @submit.prevent="enviarCalificacion"
                >
                    <div>
                        <SelectorEstrellas
                            v-model="formulario.puntuacion"
                            :error="!!formulario.errors.puntuacion"
                        />
                        <p
                            v-if="formulario.errors.puntuacion"
                            class="cc-calif-error"
                            role="alert"
                            data-test="error-puntuacion"
                        >
                            {{ formulario.errors.puntuacion }}
                        </p>
                    </div>

                    <label>
                        Comentario (opcional)
                        <textarea
                            v-model="formulario.comentario"
                            rows="4"
                            placeholder="¿Qué tal te pareció el producto?"
                            :aria-invalid="!!formulario.errors.comentario"
                            data-test="comentario"
                        />
                        <span class="cc-calif-form__pie">
                            <span
                                v-if="formulario.errors.comentario"
                                class="cc-calif-error"
                                role="alert"
                            >
                                {{ formulario.errors.comentario }}
                            </span>
                            <span v-else />
                            <span
                                :class="{
                                    'is-excedido':
                                        formulario.comentario.length >
                                        MAXIMO_COMENTARIO,
                                }"
                                data-test="contador"
                            >
                                {{ formulario.comentario.length }}/{{
                                    MAXIMO_COMENTARIO
                                }}
                            </span>
                        </span>
                    </label>

                    <p v-if="errorGeneral" class="cc-calif-error" role="alert">
                        {{ errorGeneral }}
                    </p>

                    <div class="cc-calif-acciones">
                        <button
                            type="button"
                            class="cc-button cc-button--ghost"
                            :disabled="formulario.processing"
                            @click="dialogoAbierto = false"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            class="cc-button cc-button--solid"
                            :disabled="formulario.processing"
                            data-test="enviar-calificacion"
                        >
                            Enviar calificación
                        </button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
