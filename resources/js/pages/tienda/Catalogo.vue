<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ChevronLeft,
    ChevronRight,
    CreditCard,
    MousePointerClick,
    RefreshCw,
    ShoppingCart,
    SlidersHorizontal,
    Store,
    X,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import TarjetaProducto, {
    type ProductoTarjeta,
} from '@/components/tienda/TarjetaProducto.vue';

type Filtros = {
    q: string;
    categoria: string;
    marca: string;
    precio_min: string;
    precio_max: string;
    orden: string;
    disponibles: boolean;
};

const props = defineProps<{
    productos: {
        data: ProductoTarjeta[];
        meta: { current_page: number; last_page: number; total: number };
    };
    opciones: {
        categorias: { nombre: string; slug: string; total: number }[];
        marcas: { nombre: string; slug: string }[];
        precio: { min: number; max: number };
    };
    destacados: ProductoTarjeta[];
    filtros: Filtros;
    error: boolean;
}>();

const form = reactive<Filtros>({ ...props.filtros });
const panelAbierto = ref(false);

watch(
    () => props.filtros,
    (filtros) => Object.assign(form, filtros),
);

const hayFiltros = computed(
    () =>
        form.q !== '' ||
        form.categoria !== '' ||
        form.marca !== '' ||
        form.precio_min !== '' ||
        form.precio_max !== '' ||
        form.disponibles,
);

const portada = computed(
    () => !hayFiltros.value && props.productos.meta.current_page === 1,
);

const ordenes = [
    { value: 'relevancia', label: 'Recomendados' },
    { value: 'populares', label: 'Más vistos' },
    { value: 'precio_asc', label: 'Precio: menor a mayor' },
    { value: 'precio_desc', label: 'Precio: mayor a menor' },
    { value: 'nombre', label: 'Nombre (A–Z)' },
];

function consulta(pagina?: number): Record<string, string | number> {
    const query: Record<string, string | number> = {};

    if (form.q) query.q = form.q;
    if (form.categoria) query.categoria = form.categoria;
    if (form.marca) query.marca = form.marca;
    if (form.precio_min !== '') query.precio_min = form.precio_min;
    if (form.precio_max !== '') query.precio_max = form.precio_max;
    if (form.disponibles) query.disponibles = 1;
    if (form.orden !== 'relevancia') query.orden = form.orden;
    if (pagina && pagina > 1) query.page = pagina;

    return query;
}

function aplicar(pagina?: number): void {
    router.get('/tienda', consulta(pagina), {
        preserveState: true,
        preserveScroll: pagina === undefined,
        onSuccess: () => {
            panelAbierto.value = false;

            if (pagina !== undefined) {
                document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' });
            }
        },
    });
}

function elegirCategoria(slug: string): void {
    form.categoria = form.categoria === slug ? '' : slug;
    aplicar();
}

function limpiar(): void {
    Object.assign(form, {
        q: '',
        categoria: '',
        marca: '',
        precio_min: '',
        precio_max: '',
        orden: 'relevancia',
        disponibles: false,
    });
    aplicar();
}

const chips = computed(() => {
    const lista: { clave: keyof Filtros; texto: string }[] = [];
    const categoria = props.opciones.categorias.find((c) => c.slug === form.categoria);
    const marca = props.opciones.marcas.find((m) => m.slug === form.marca);

    if (form.q) lista.push({ clave: 'q', texto: `“${form.q}”` });
    if (categoria) lista.push({ clave: 'categoria', texto: categoria.nombre });
    if (marca) lista.push({ clave: 'marca', texto: marca.nombre });
    if (form.precio_min !== '') lista.push({ clave: 'precio_min', texto: `Desde Bs ${form.precio_min}` });
    if (form.precio_max !== '') lista.push({ clave: 'precio_max', texto: `Hasta Bs ${form.precio_max}` });
    if (form.disponibles) lista.push({ clave: 'disponibles', texto: 'Con stock' });

    return lista;
});

function quitar(clave: keyof Filtros): void {
    if (clave === 'disponibles') {
        form.disponibles = false;
    } else {
        (form[clave] as string) = '';
    }

    aplicar();
}

function reintentar(): void {
    router.reload();
}

function irAlCatalogo(): void {
    document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' });
}
</script>

<template>
    <div>
        <Head title="Tienda de periféricos" />

        <section v-if="portada && !error" class="cc-hero">
            <div class="cc-hero__texto">
                <p class="cc-kicker">Comercial Chiriguano · Santa Cruz</p>
                <h1>Periféricos para jugar, trabajar y crear.</h1>
                <p>
                    Mouse, teclados, audífonos, cámaras y más, con garantía de la
                    tienda. Elegí en línea, pagá con tarjeta y retirá en el local.
                </p>
                <div class="cc-hero__acciones">
                    <button type="button" class="cc-button cc-button--solid" @click="irAlCatalogo">
                        Ver catálogo
                    </button>
                    <a href="#tienda" class="cc-button cc-button--ghost">Dónde estamos</a>
                </div>
            </div>
            <ol class="cc-pasos" aria-label="Cómo comprar">
                <li>
                    <MousePointerClick :size="20" />
                    <span><b>Elegí</b> tus productos</span>
                </li>
                <li>
                    <ShoppingCart :size="20" />
                    <span><b>Agregá</b> al carrito</span>
                </li>
                <li>
                    <CreditCard :size="20" />
                    <span><b>Pagá</b> con tarjeta aquí mismo</span>
                </li>
                <li>
                    <Store :size="20" />
                    <span><b>Retirá</b> en la tienda</span>
                </li>
            </ol>
        </section>

        <section v-if="portada && destacados.length" class="cc-shop__section">
            <header class="cc-shop__section-head">
                <h2>Los más vistos</h2>
            </header>
            <div class="cc-grid cc-grid--destacados">
                <TarjetaProducto v-for="p in destacados" :key="`d-${p.id}`" :producto="p" />
            </div>
        </section>

        <section id="catalogo" class="cc-shop__section">
            <header class="cc-shop__section-head">
                <div>
                    <h2>{{ hayFiltros ? 'Resultados' : 'Catálogo completo' }}</h2>
                    <p v-if="!error">
                        {{ productos.meta.total }}
                        {{ productos.meta.total === 1 ? 'producto' : 'productos' }}
                    </p>
                </div>
                <div class="cc-shop__section-tools">
                    <label class="cc-select">
                        <span class="sr-only">Ordenar por</span>
                        <select v-model="form.orden" @change="aplicar()">
                            <option v-for="o in ordenes" :key="o.value" :value="o.value">
                                {{ o.label }}
                            </option>
                        </select>
                    </label>
                    <button
                        type="button"
                        class="cc-button cc-button--ghost cc-filtros-toggle"
                        :aria-expanded="panelAbierto"
                        @click="panelAbierto = !panelAbierto"
                    >
                        <SlidersHorizontal :size="16" />
                        Filtros
                    </button>
                </div>
            </header>

            <nav v-if="opciones.categorias.length" class="cc-chips" aria-label="Categorías">
                <button
                    type="button"
                    class="cc-chip"
                    :class="{ 'is-active': form.categoria === '' }"
                    @click="elegirCategoria('')"
                >
                    Todas
                </button>
                <button
                    v-for="c in opciones.categorias"
                    :key="c.slug"
                    type="button"
                    class="cc-chip"
                    :class="{ 'is-active': form.categoria === c.slug }"
                    :aria-pressed="form.categoria === c.slug"
                    @click="elegirCategoria(c.slug)"
                >
                    {{ c.nombre }} <small>{{ c.total }}</small>
                </button>
            </nav>

            <div class="cc-catalogo">
                <aside class="cc-filtros" :class="{ 'is-open': panelAbierto }" aria-label="Filtros">
                    <form @submit.prevent="aplicar()">
                        <label class="cc-filtro">
                            <span>Marca</span>
                            <span class="cc-select">
                                <select v-model="form.marca" @change="aplicar()">
                                    <option value="">Todas las marcas</option>
                                    <option v-for="m in opciones.marcas" :key="m.slug" :value="m.slug">
                                        {{ m.nombre }}
                                    </option>
                                </select>
                            </span>
                        </label>

                        <fieldset class="cc-filtro">
                            <legend>Precio (Bs)</legend>
                            <div class="cc-filtro__rango">
                                <input
                                    v-model="form.precio_min"
                                    type="number"
                                    min="0"
                                    inputmode="numeric"
                                    :placeholder="`Mín. ${opciones.precio.min}`"
                                    aria-label="Precio mínimo"
                                />
                                <input
                                    v-model="form.precio_max"
                                    type="number"
                                    min="0"
                                    inputmode="numeric"
                                    :placeholder="`Máx. ${opciones.precio.max}`"
                                    aria-label="Precio máximo"
                                />
                            </div>
                        </fieldset>

                        <label class="cc-check">
                            <input v-model="form.disponibles" type="checkbox" @change="aplicar()" />
                            <span>Solo con stock</span>
                        </label>

                        <div class="cc-filtros__acciones">
                            <button type="submit" class="cc-button">Aplicar</button>
                            <button
                                v-if="hayFiltros"
                                type="button"
                                class="cc-button cc-button--ghost"
                                @click="limpiar"
                            >
                                Limpiar
                            </button>
                        </div>
                    </form>
                </aside>

                <div class="cc-resultados">
                    <div v-if="chips.length" class="cc-chips cc-chips--activos">
                        <button
                            v-for="chip in chips"
                            :key="chip.clave"
                            type="button"
                            class="cc-chip is-active"
                            :aria-label="`Quitar filtro ${chip.texto}`"
                            @click="quitar(chip.clave)"
                        >
                            {{ chip.texto }} <X :size="12" />
                        </button>
                    </div>

                    <div v-if="error" class="cc-vacio" role="alert">
                        <strong>No pudimos cargar el catálogo</strong>
                        <p>Hubo un problema de conexión. Intentá de nuevo en unos minutos.</p>
                        <button type="button" class="cc-button" @click="reintentar">
                            <RefreshCw :size="16" /> Reintentar
                        </button>
                    </div>

                    <div v-else-if="productos.data.length === 0" class="cc-vacio">
                        <strong>No encontramos productos</strong>
                        <p>Probá con otra búsqueda o quitá algunos filtros.</p>
                        <button type="button" class="cc-button" @click="limpiar">Ver todo el catálogo</button>
                    </div>

                    <div v-else class="cc-grid">
                        <TarjetaProducto v-for="p in productos.data" :key="p.id" :producto="p" />
                    </div>

                    <nav
                        v-if="productos.meta.last_page > 1"
                        class="cc-paginas"
                        aria-label="Páginas del catálogo"
                    >
                        <button
                            type="button"
                            aria-label="Página anterior"
                            :disabled="productos.meta.current_page <= 1"
                            @click="aplicar(productos.meta.current_page - 1)"
                        >
                            <ChevronLeft :size="16" />
                        </button>
                        <span>Página {{ productos.meta.current_page }} de {{ productos.meta.last_page }}</span>
                        <button
                            type="button"
                            aria-label="Página siguiente"
                            :disabled="productos.meta.current_page >= productos.meta.last_page"
                            @click="aplicar(productos.meta.current_page + 1)"
                        >
                            <ChevronRight :size="16" />
                        </button>
                    </nav>
                </div>
            </div>
        </section>
    </div>
</template>
