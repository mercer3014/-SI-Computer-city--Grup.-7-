<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    Eye,
    ImageOff,
    Minus,
    Plus,
    ShieldCheck,
    ShoppingCart,
    Store,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import TarjetaProducto, {
    type ProductoTarjeta,
} from '@/components/tienda/TarjetaProducto.vue';
import { bolivianos, MAXIMO_POR_PRODUCTO, useCarrito } from '@/composables/useCarrito';

type Variante = {
    id: number;
    sku: string;
    etiqueta: string;
    precio: number;
    precio_anterior: number | null;
    stock: number;
    disponibilidad: 'DISPONIBLE' | 'ULTIMAS' | 'AGOTADO';
};

const props = defineProps<{
    producto: {
        id: number;
        nombre: string;
        slug: string;
        descripcion: string | null;
        categoria: { nombre: string; slug: string } | null;
        marca: { nombre: string; slug: string } | null;
        visitas: number;
        imagenes: { url: string; alt: string }[];
        variantes: Variante[];
    };
    relacionados: ProductoTarjeta[];
}>();

const { agregar } = useCarrito();

const imagenActiva = ref(0);
const varianteId = ref(
    props.producto.variantes.find((v) => v.stock > 0)?.id ??
        props.producto.variantes[0]?.id ??
        0,
);
const cantidad = ref(1);

const variante = computed(
    () => props.producto.variantes.find((v) => v.id === varianteId.value) ?? null,
);
const maximo = computed(() => Math.min(variante.value?.stock ?? 0, MAXIMO_POR_PRODUCTO));
const agotado = computed(() => (variante.value?.stock ?? 0) <= 0);

const textoStock = computed(() => {
    const v = variante.value;

    if (!v || v.stock <= 0) return 'Sin stock por ahora';
    if (v.disponibilidad === 'ULTIMAS') return `¡Últimas ${v.stock} unidades!`;

    return `${v.stock} unidades disponibles`;
});

function elegirVariante(id: number): void {
    varianteId.value = id;
    cantidad.value = 1;
}

function cambiar(delta: number): void {
    cantidad.value = Math.max(1, Math.min(cantidad.value + delta, maximo.value || 1));
}

function añadir(irAlCarrito = false): void {
    const v = variante.value;

    if (!v || agotado.value) {
        return;
    }

    const enCarrito = agregar(
        {
            variante_id: v.id,
            producto: props.producto.nombre,
            slug: props.producto.slug,
            etiqueta: props.producto.variantes.length > 1 ? v.etiqueta : null,
            sku: v.sku,
            precio: v.precio,
            imagen: props.producto.imagenes[0]?.url ?? null,
            stock: v.stock,
        },
        cantidad.value,
    );

    if (irAlCarrito) {
        router.visit('/tienda/carrito');

        return;
    }

    toast.success(`${props.producto.nombre} en tu carrito (${enCarrito}).`, {
        action: { label: 'Ver carrito', onClick: () => router.visit('/tienda/carrito') },
    });
}
</script>

<template>
    <div class="cc-shop__section">
        <Head :title="producto.nombre" />

        <nav class="cc-migas" aria-label="Ruta">
            <Link href="/tienda"><ChevronLeft :size="14" /> Catálogo</Link>
            <template v-if="producto.categoria">
                <span aria-hidden="true">/</span>
                <Link :href="`/tienda?categoria=${producto.categoria.slug}`">
                    {{ producto.categoria.nombre }}
                </Link>
            </template>
        </nav>

        <article class="cc-ficha">
            <div class="cc-ficha__galeria">
                <div class="cc-ficha__imagen">
                    <img
                        v-if="producto.imagenes.length"
                        :src="producto.imagenes[imagenActiva]?.url"
                        :alt="producto.imagenes[imagenActiva]?.alt"
                        width="560"
                        height="560"
                    />
                    <ImageOff v-else :size="48" aria-hidden="true" />
                </div>
                <div v-if="producto.imagenes.length > 1" class="cc-ficha__miniaturas">
                    <button
                        v-for="(img, i) in producto.imagenes"
                        :key="img.url"
                        type="button"
                        :class="{ 'is-active': i === imagenActiva }"
                        :aria-label="`Ver imagen ${i + 1}`"
                        @click="imagenActiva = i"
                    >
                        <img :src="img.url" :alt="img.alt" width="72" height="72" />
                    </button>
                </div>
            </div>

            <div class="cc-ficha__info">
                <p class="cc-kicker">
                    {{ [producto.marca?.nombre, producto.categoria?.nombre].filter(Boolean).join(' · ') }}
                </p>
                <h1>{{ producto.nombre }}</h1>

                <div class="cc-ficha__precio">
                    <strong>{{ bolivianos(variante?.precio ?? 0) }}</strong>
                    <s v-if="variante?.precio_anterior">{{ bolivianos(variante.precio_anterior) }}</s>
                </div>

                <p class="cc-ficha__stock" :data-estado="variante?.disponibilidad ?? 'AGOTADO'">
                    {{ textoStock }}
                </p>

                <div v-if="producto.variantes.length > 1" class="cc-ficha__variantes">
                    <span>Variante</span>
                    <div>
                        <button
                            v-for="v in producto.variantes"
                            :key="v.id"
                            type="button"
                            class="cc-chip"
                            :class="{ 'is-active': v.id === varianteId, 'is-off': v.stock <= 0 }"
                            :aria-pressed="v.id === varianteId"
                            @click="elegirVariante(v.id)"
                        >
                            {{ v.etiqueta }}
                        </button>
                    </div>
                </div>

                <div class="cc-ficha__compra">
                    <div class="cc-stepper" aria-label="Cantidad">
                        <button type="button" aria-label="Quitar una" :disabled="cantidad <= 1 || agotado" @click="cambiar(-1)">
                            <Minus :size="14" />
                        </button>
                        <output aria-live="polite">{{ cantidad }}</output>
                        <button type="button" aria-label="Agregar una" :disabled="cantidad >= maximo || agotado" @click="cambiar(1)">
                            <Plus :size="14" />
                        </button>
                    </div>
                    <button type="button" class="cc-button cc-button--solid" :disabled="agotado" @click="añadir()">
                        <ShoppingCart :size="16" />
                        Agregar al carrito
                    </button>
                    <button type="button" class="cc-button" :disabled="agotado" @click="añadir(true)">
                        Comprar ahora
                    </button>
                </div>

                <ul class="cc-ficha__beneficios">
                    <li><ShieldCheck :size="16" /> 3 meses de garantía de la tienda</li>
                    <li><Store :size="16" /> Retiro en tienda, Comercial Chiriguano</li>
                    <li><Eye :size="16" /> {{ producto.visitas }} {{ producto.visitas === 1 ? 'visita' : 'visitas' }}</li>
                </ul>

                <section v-if="producto.descripcion" class="cc-ficha__descripcion">
                    <h2>Descripción</h2>
                    <p>{{ producto.descripcion }}</p>
                </section>

                <dl class="cc-ficha__datos">
                    <div v-if="producto.marca"><dt>Marca</dt><dd>{{ producto.marca.nombre }}</dd></div>
                    <div v-if="producto.categoria"><dt>Categoría</dt><dd>{{ producto.categoria.nombre }}</dd></div>
                    <div v-if="variante"><dt>Código</dt><dd>{{ variante.sku }}</dd></div>
                    <div v-if="variante && producto.variantes.length > 1"><dt>Variante</dt><dd>{{ variante.etiqueta }}</dd></div>
                </dl>
            </div>
        </article>

        <section v-if="relacionados.length" class="cc-shop__section cc-shop__section--flush">
            <header class="cc-shop__section-head">
                <h2>También te puede interesar</h2>
            </header>
            <div class="cc-grid cc-grid--destacados">
                <TarjetaProducto v-for="p in relacionados" :key="p.id" :producto="p" />
            </div>
        </section>
    </div>
</template>
