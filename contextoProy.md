# contextoProy.md — Base de Conocimiento del Proyecto Computer City

> **Propósito de este archivo:** Proveer contexto estructurado a cualquier agente de IA o desarrollador que trabaje en el proyecto. Léelo antes de escribir cualquier código.

---

## 1. Identidad del Proyecto

| Campo | Valor |
|---|---|
| **Nombre** | Computer City |
| **Tipo** | Sistema web — Gestión de inventario y punto de venta |
| **Dominio** | Tienda de periféricos de computadora (mouse, teclados, audífonos, etc.) |
| **Ubicación física** | Comercial Chiriguano, Santa Cruz de la Sierra, Bolivia |
| **Moneda** | BOB (Bolivianos) |

---

## 2. Stack Tecnológico

| Capa | Tecnología | Notas |
|---|---|---|
| **Backend / API** | Laravel 12 (PHP 8.3+) | Inertia.js (servidor) |
| **Frontend** | Vue 3 + TypeScript | Inertia.js (cliente), Composition API + `<script setup>` |
| **Base de Datos** | PostgreSQL | Nombres de tablas en español, sin timestamps Eloquent estándar (`fecha_creacion`, `fecha_actualizacion`) |
| **Autenticación** | Laravel Fortify + OTP | 2FA, primer login, bloqueo por intentos |
| **Estilos** | Tailwind CSS v4 + CSS custom (`cc-admin.css`) | Tema claro/oscuro con variables CSS `--cc-*` |
| **Animaciones** | `motion-v` | `adminSpring` desde `@/lib/adminMotion` |
| **Iconos** | `@lucide/vue` | |
| **Notificaciones** | `vue-sonner` | |
| **Routing Vue** | `wayfinder` (tipado) + rutas Inertia | |

---

## 3. Módulos Principales

| # | Módulo | Permiso clave | Estado |
|---|---|---|---|
| 1 | **Seguridad** | `usuarios.administrar`, `roles.administrar`, `bitacora.ver` | ✅ Implementado |
| 2 | **Inventario y Productos** | `productos.gestionar`, `inventario.ver`, `inventario.ajustar` | 🔶 En desarrollo (CU07) |
| 3 | **Ventas y Clientes** | `ventas.crear`, `ventas.anular`, `clientes.gestionar`, `garantias.gestionar` | 🔲 Pendiente |
| 4 | **Compras y Proveedores** | `compras.crear`, `proveedores.gestionar` | 🔲 Pendiente |
| 5 | **Flujo de Caja y Reportes** | `gastos.gestionar`, `reportes.ver` | 🔲 Pendiente |
| 6 | **Catálogo Web** | Público / sin permiso | 🔲 Pendiente |

---

## 4. Actores del Sistema

| Actor | Rol en BD | Permisos | Restricciones |
|---|---|---|---|
| **Administrador** | `Administrador` | Todos | Ninguna |
| **Vendedor** | `Vendedor` | `inventario.ver`, `ventas.crear`, `clientes.gestionar`, `garantias.gestionar` | Solo lectura en productos; no puede crear/editar/cambiar estado |
| **Cliente** | — | Sin cuenta de sistema | Solo usa el catálogo web público |
| **Cliente Web** | — | Sin cuenta de sistema | Navega el catálogo público |
| **Proveedor** | — | Sin cuenta de sistema | Datos gestionados por admin |

---

## 5. Convenciones de Código

### 5.1 Backend (Laravel / PHP)

- **Tablas en singular español:** `producto`, `categoria`, `marca`, `variante_producto`, `imagen_producto`.
- **PK:** `id` (increments), sin UUID.
- **Timestamps personalizados:** `fecha_creacion` (CREATED_AT), `fecha_actualizacion` (sin UPDATED_AT automático o `null`).
- **Estados:** Columna `estado` tipo `string(20)` con valores `'ACTIVO'` / `'INACTIVO'`.
- **Soft deletes:** No se usa `deleted_at`; se hace baja lógica via `estado = 'INACTIVO'`.
- **Slugs:** Siempre `unique`, generados con `Str::slug()`.
- **Respuestas Inertia:** `Inertia::render('NombreVista', [...])` y `Inertia::flash('toast', [...])`.
- **Permisos:** Middleware `RequierePermiso` (`permiso:clave`); también `User::tienePermiso(string $clave)`.
- **Políticas:** Verificar `$user->tienePermiso('productos.gestionar')` para acciones de escritura.

### 5.2 Frontend (Vue 3 + TypeScript)

- **`<script setup lang="ts">`** siempre.
- **Layout:** `defineOptions({ layout: { breadcrumbs: [...] } })`.
- **Clases CSS del design system** (nunca inventar colores nuevos):
  - `.cc-admin`, `.cc-log__head`, `.cc-log__panel`, `.cc-log__table`, `.cc-button`, `.cc-log__badge`, etc.
  - Variables: `--cc-page`, `--cc-surface`, `--cc-text`, `--cc-muted`, `--cc-border`, `--cc-accent`, `--cc-error`, `--cc-success`.
- **Tema:** La clase `.dark` en el `<html>` activa el tema oscuro automáticamente via las variables CSS.
- **Animaciones:** `motion.div` + `adminSpring` para layout animations.
- **Permisos en Vue:** `page.props.auth.user.permisos` es un `string[]` con las claves. Usar `v-if="permisos.includes('productos.gestionar')"`.
- **Forms Inertia:** `useForm({...})` con `.post()`, `.put()`, `.patch()`.
- **Toasts:** `toast.success(...)` / `toast.error(...)` de `vue-sonner`.
- **Paginación:** Objeto `{ data: [], meta: { current_page, last_page, total, from, to } }`.

---

## 6. Esquema de Base de Datos — Módulo Productos

```sql
-- categoria
id, categoria_padre_id (null), nombre, slug (unique), descripcion, es_promocion,
texto_promocion, orden_visualizacion, activo (bool, default true), icono, fecha_creacion

-- marca
id, nombre, slug (unique), estado ('ACTIVO'|'INACTIVO', default 'ACTIVO')

-- producto
id, categoria_id (FK->categoria), marca_id (FK->marca, nullable),
nombre, slug (unique), descripcion, estado ('ACTIVO'|'INACTIVO', default 'ACTIVO'),
fecha_creacion, fecha_actualizacion

-- variante_producto
id, producto_id (FK->producto, cascade delete), sku (unique), codigo_barras (unique, nullable),
precio_venta (decimal 12,2), precio_anterior (nullable), estado ('ACTIVO'|'INACTIVO'),
fecha_creacion, fecha_actualizacion, stock_actual (int, default 0), stock_minimo (int, default 5)

-- imagen_producto
id, producto_id (FK->producto, cascade delete), url_webp (text),
orden_visualizacion (int, default 0), es_principal (bool, default false),
texto_alternativo (varchar 255, nullable)
```

---

## 7. Usuarios de Prueba Iniciales

| Email | Rol | Contraseña (bcrypt) |
|---|---|---|
| `ruben.morales@computercity.com` | Administrador | (ver poblacion.txt) |
| `maria.fernandez@computercity.com` | Vendedor | (ver poblacion.txt) |
| `carla.rojas@computercity.com` | Vendedor | (ver poblacion.txt) |

---

## 8. Archivos Clave del Proyecto

| Archivo | Descripción |
|---|---|
| `resources/css/app.css` | Variables CSS globales (`:root` y `.dark`), importa `cc-admin.css` |
| `resources/css/cc-admin.css` | Design system completo: botones, tablas, modales, chips, badges |
| `resources/js/pages/Usuarios.vue` | **Referencia** de patrón CRUD completo con tabla + modal |
| `resources/js/pages/Roles.vue` | **Referencia** de patrón sin paginación + panel lateral |
| `resources/js/components/AppSidebar.vue` | Navegación — filtrada por `permisos` del usuario |
| `app/Http/Middleware/HandleInertiaRequests.php` | Props compartidas: `auth.user.rol`, `auth.user.permisos` |
| `app/Http/Middleware/RequierePermiso.php` | Middleware de control de acceso por clave de permiso |
| `poblacion.txt` | Datos de prueba SQL (categorías, marcas, 20 productos, variantes) |
| `catalogo/` | Imágenes PNG de los 20 productos de prueba |

---

## 9. Casos de Uso Implementados

| CU | Nombre | Vista | Controlador |
|---|---|---|---|
| CU01–CU06 | Seguridad (usuarios, roles, bitácora) | `Usuarios.vue`, `Roles.vue`, `Bitacora.vue` | `UsuarioController`, `RolController`, `BitacoraController` |
| **CU07** | **Catálogo de Productos (Inventario)** | **`Productos.vue`** | **`ProductoController`** |

---

## 10. Notas de Arquitectura

- **Inertia.js:** No hay API REST separada; el backend devuelve props directamente a Vue via Inertia.
- **Imágenes de productos:** Se almacenan en `storage/app/public/catalogo/` y se sirven via symlink `/storage/catalogo/`. El campo `url_webp` almacena la ruta relativa.
- **Slugs:** Se generan automáticamente en backend con `Str::slug($nombre)` y se validan por unicidad excluyendo el registro actual en edición.
- **Control de acceso dual:** Middleware en rutas (Laravel) + `v-if` en componentes (Vue) para UX.
