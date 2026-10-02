<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { Sidebar, useSidebar } from '@/components/ui/sidebar';
import { dashboard, logout } from '@/routes';

type Modulo = {
    titulo: string;
    href?: string;
    permisos?: string[];
    icono: string;
    grupo: 'MENU' | 'FINANZAS' | 'SISTEMA';
};

const modulos: Modulo[] = [
    {
        titulo: 'Dashboard',
        href: dashboard().url,
        icono: 'nav-inventory.svg',
        grupo: 'MENU',
    },
    {
        titulo: 'Ventas',
        permisos: ['ventas.crear', 'ventas.anular', 'garantias.gestionar'],
        icono: 'nav-sales.svg',
        grupo: 'MENU',
    },
    {
        titulo: 'Productos',
        permisos: [
            'inventario.ver',
            'inventario.ajustar',
            'productos.gestionar',
        ],
        icono: 'nav-inventory.svg',
        grupo: 'MENU',
    },
    {
        titulo: 'Clientes',
        permisos: ['clientes.gestionar'],
        icono: 'nav-clients.svg',
        grupo: 'MENU',
    },
    {
        titulo: 'Compras',
        permisos: ['compras.crear'],
        icono: 'nav-new.svg',
        grupo: 'MENU',
    },
    {
        titulo: 'Proveedores',
        permisos: ['proveedores.gestionar'],
        icono: 'nav-clients.svg',
        grupo: 'MENU',
    },
    {
        titulo: 'Flujo de Caja',
        permisos: ['gastos.gestionar', 'reportes.ver'],
        icono: 'nav-cash.svg',
        grupo: 'FINANZAS',
    },
    {
        titulo: 'Reportes',
        permisos: ['reportes.ver', 'gastos.gestionar'],
        icono: 'nav-log.svg',
        grupo: 'FINANZAS',
    },
    {
        titulo: 'Usuarios',
        href: '/usuarios',
        permisos: ['usuarios.administrar'],
        icono: 'nav-user.svg',
        grupo: 'SISTEMA',
    },
    {
        titulo: 'Roles y permisos',
        href: '/roles',
        permisos: ['roles.administrar'],
        icono: 'nav-settings.svg',
        grupo: 'SISTEMA',
    },
    {
        titulo: 'Bitácora',
        href: '/bitacora',
        permisos: ['bitacora.ver'],
        icono: 'nav-log.svg',
        grupo: 'SISTEMA',
    },
];

const page = usePage();
const { isMobile, setOpenMobile } = useSidebar();

watch(
    () => page.url,
    () => {
        if (isMobile.value) {
            setOpenMobile(false);
        }
    },
);

const visibles = computed(() => {
    const claves = new Set(page.props.auth.user?.permisos ?? []);

    return modulos.filter(
        (modulo) =>
            !modulo.permisos ||
            modulo.permisos.some((clave) => claves.has(clave)),
    );
});

const grupos = computed(() =>
    (['MENU', 'FINANZAS', 'SISTEMA'] as const)
        .map((nombre) => ({
            nombre,
            modulos: visibles.value.filter((modulo) => modulo.grupo === nombre),
        }))
        .filter((grupo) => grupo.modulos.length > 0),
);

function activo(href?: string): boolean {
    if (!href) {
        return false;
    }

    const actual = page.url.split('?')[0] ?? '';

    return actual === href || actual.startsWith(`${href}/`);
}

function cerrarSesion(): void {
    router.flushAll();
}
</script>

<template>
    <Sidebar
        collapsible="offcanvas"
        variant="sidebar"
        class="cc-theme cc-sidebar"
    >
        <div class="cc-sidebar__brand">
            <img
                class="cc-sidebar__wordmark"
                src="/images/computer-city/logo.png?v=5"
                alt="Computer City"
            />
        </div>

        <nav class="cc-sidebar__nav" aria-label="Módulos">
            <div v-for="grupo in grupos" :key="grupo.nombre">
                <p>{{ grupo.nombre }}</p>
                <component
                    :is="modulo.href ? Link : 'div'"
                    v-for="modulo in grupo.modulos"
                    :key="modulo.titulo"
                    v-bind="modulo.href ? { href: modulo.href } : {}"
                    class="cc-sidebar__item"
                    :class="{ active: activo(modulo.href) }"
                    :aria-current="activo(modulo.href) ? 'page' : undefined"
                >
                    <img
                        :src="`/images/computer-city/${modulo.icono}`"
                        width="18"
                        height="18"
                        alt=""
                    />
                    <span>{{ modulo.titulo }}</span>
                </component>
            </div>
        </nav>

        <Link
            :href="logout()"
            method="post"
            as="button"
            class="cc-sidebar__logout"
            data-test="logout-button"
            @click="cerrarSesion"
        >
            <img
                src="/images/computer-city/nav-logout.svg"
                width="18"
                height="18"
                alt=""
            />
            <span>Cerrar Sesión</span>
        </Link>
    </Sidebar>
    <slot />
</template>
