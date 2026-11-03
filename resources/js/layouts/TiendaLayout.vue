<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    LayoutDashboard,
    LogOut,
    Package,
    Search,
    ShoppingCart,
    UserRound,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import { Toaster } from '@/components/ui/sonner';
import { useCarrito } from '@/composables/useCarrito';

type Horario = { dia: string; cerrado: boolean; horario: string | null };
type Tienda = {
    nombre: string;
    centro_comercial: string | null;
    direccion: string | null;
    telefono: string | null;
    whatsapp: string | null;
    email: string | null;
    horarios: Horario[];
};

const page = usePage();
const { cantidadTotal } = useCarrito();
const montado = ref(false);

const usuario = computed(() => page.props.auth?.user ?? null);
const tienda = computed(() => page.props.tienda as Tienda | undefined);
const busqueda = ref(
    typeof page.props.filtros === 'object' && page.props.filtros
        ? String((page.props.filtros as { q?: string }).q ?? '')
        : '',
);

watch(
    () => (page.props.filtros as { q?: string } | undefined)?.q,
    (valor) => {
        busqueda.value = valor ?? '';
    },
);

onMounted(() => {
    montado.value = true;
});

function buscar(): void {
    router.get('/tienda', busqueda.value.trim() ? { q: busqueda.value.trim() } : {});
}

function whatsapp(numero: string): string {
    return `https://wa.me/591${numero.replace(/\D/g, '')}`;
}
</script>

<template>
    <div class="cc-theme cc-shop">
        <header class="cc-shop__header">
            <div class="cc-shop__bar">
                <Link href="/tienda" class="cc-shop__brand" aria-label="Computer City, ir al catálogo">
                    <img src="/images/computer-city/logo.png?v=5" alt="Computer City" />
                </Link>

                <form class="cc-shop__search" role="search" @submit.prevent="buscar">
                    <Search :size="16" aria-hidden="true" />
                    <input
                        v-model="busqueda"
                        type="search"
                        name="q"
                        maxlength="100"
                        placeholder="Buscar mouse, teclados, audífonos…"
                        aria-label="Buscar productos"
                    />
                </form>

                <nav class="cc-shop__actions" aria-label="Cuenta y carrito">
                    <ThemeToggle class="cc-shop__theme" />

                    <template v-if="!usuario">
                        <Link href="/login" class="cc-shop__link">
                            <UserRound :size="16" />
                            <span>Ingresar</span>
                        </Link>
                        <Link href="/registro" class="cc-button cc-shop__cta">Crear cuenta</Link>
                    </template>
                    <template v-else-if="usuario.es_cliente">
                        <Link href="/tienda/pedidos" class="cc-shop__link">
                            <Package :size="16" />
                            <span>Mis pedidos</span>
                        </Link>
                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            class="cc-shop__link cc-shop__link--icon"
                            aria-label="Cerrar sesión"
                        >
                            <LogOut :size="16" />
                        </Link>
                    </template>
                    <Link v-else href="/dashboard" class="cc-shop__link">
                        <LayoutDashboard :size="16" />
                        <span>Panel</span>
                    </Link>

                    <Link href="/tienda/carrito" class="cc-shop__cart" aria-label="Ver carrito">
                        <ShoppingCart :size="18" />
                        <span v-if="montado && cantidadTotal > 0" class="cc-shop__cart-badge">
                            {{ cantidadTotal > 99 ? '99+' : cantidadTotal }}
                        </span>
                    </Link>
                </nav>
            </div>
        </header>

        <main class="cc-shop__main">
            <slot />
        </main>

        <footer id="tienda" class="cc-shop__footer">
            <div class="cc-shop__footer-grid">
                <div>
                    <img
                        class="cc-shop__footer-logo"
                        src="/images/computer-city/logo.png?v=5"
                        alt="Computer City"
                    />
                    <p>
                        Periféricos y servicio técnico en
                        {{ tienda?.centro_comercial ?? 'el Comercial Chiriguano' }}.
                        Comprá en línea y retirá en tienda.
                    </p>
                </div>
                <div v-if="tienda">
                    <h2>Visitanos</h2>
                    <p>{{ tienda.direccion }}</p>
                    <p v-if="tienda.telefono">Tel. {{ tienda.telefono }}</p>
                    <p v-if="tienda.email">{{ tienda.email }}</p>
                    <a
                        v-if="tienda.whatsapp"
                        :href="whatsapp(tienda.whatsapp)"
                        target="_blank"
                        rel="noopener"
                        class="cc-shop__footer-link"
                    >
                        Escribinos por WhatsApp
                    </a>
                </div>
                <div v-if="tienda?.horarios?.length">
                    <h2>Horarios</h2>
                    <dl class="cc-shop__hours">
                        <div v-for="h in tienda.horarios" :key="h.dia">
                            <dt>{{ h.dia }}</dt>
                            <dd>{{ h.cerrado ? 'Cerrado' : h.horario }}</dd>
                        </div>
                    </dl>
                </div>
            </div>
            <p class="cc-shop__copy">© {{ new Date().getFullYear() }} Computer City · Pagos seguros con Stripe</p>
        </footer>

        <Toaster />
    </div>
</template>
