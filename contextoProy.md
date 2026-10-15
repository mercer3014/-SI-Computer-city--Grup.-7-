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
| 2 | **Inventario y Productos** | `productos.gestionar`, `inventario.ver`, `inventario.ajustar` | ✅ CU07 + CU08 + CU09 |
| 3 | **Ventas y Clientes** | `ventas.crear`, `ventas.anular`, `clientes.gestionar`, `garantias.gestionar` | ✅ CU10 + CU11 |
| 4 | **Compras y Proveedores** | `compras.crear`, `proveedores.gestionar` | 🔲 Pendiente |
| 5 | **Flujo de Caja y Reportes** | `gastos.gestionar`, `reportes.ver` | 🔲 Pendiente |
| 6 | **Catálogo Web** | Público / sin permiso | 🔲 Pendiente |

---

## 4. Actores del Sistema

| Actor | Rol en BD | Permisos | Restricciones |
|---|---|---|---|
| **Administrador** | `Administrador` | Todos | Ninguna |
| **Vendedor** | `Vendedor` | `inventario.ver`, `ventas.crear`, `clientes.gestionar`, `garantias.gestionar` | Solo lectura en productos e inventario; no ajusta stock |
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
| `resources/js/pages/Variantes.vue` | **CU08** — variantes, precios, historial y rotación |
| `resources/js/pages/Inventario.vue` | **CU09** — existencias, ajustes, kardex y serie |
| `resources/js/pages/Clientes.vue` | **CU10** — clientes, ficha, historial y frecuentes |
| `resources/js/pages/Ventas.vue` | **CU11** — POS: carrito, pagos múltiples, nota PDF |
| `resources/js/components/AppSidebar.vue` | Navegación — filtrada por `permisos`. Incluye **Inventario**, **Clientes** y **Ventas**. |
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
| **CU08** | **Variantes y Precios** | **`Variantes.vue`** | **`VarianteController`** |
| **CU09** | **Control de Inventario (Kardex)** | **`Inventario.vue`** | **`InventarioController`** |
| **CU10** | **Gestión de Clientes** | **`Clientes.vue`** | **`ClienteController`** |
| **CU11** | **Registro de Ventas (POS)** | **`Ventas.vue`** | **`VentaController`** |

### CU08 — Variantes y Precios (integrado)

El precio y el stock **no viven en `Producto`**. `Producto` es el catálogo (nombre, categoría, marca, estado); cada unidad vendible es una `VarianteProducto` con su propio `sku`, `codigo_barras`, `precio_venta`, `precio_anterior`, `stock_actual` y `stock_minimo`. Relación **1 Producto → 0..\* VarianteProducto**, diferenciadas por la combinación de atributos en `variante_valor_atributo`.

Alta (`store`): SKU y código de barras únicos, combinación de atributos no repetida en el mismo producto, `precio_venta > 0`, `stock_actual = 0`. Cambio de precio (`updatePrecio`): transacción PostgreSQL que guarda el valor vigente en `precio_anterior`, aplica el nuevo y dispara `trg_auditoria_cambio_precio` → `bitacora_auditoria` (`ACTUALIZAR_PRECIO`, usuario, IP, precio anterior y nuevo). En SQLite el controlador escribe el mismo registro. Rotación: `VarianteProducto::sinRotacion($meses)` lista mercadería activa **con stock** sin ventas no anuladas en 3/6/12 meses.

### CU09 — Control de Inventario (integrado)

El stock de una variante **empieza en 0** (CU08) y se inyecta con **ajustes manuales** en el Kardex (`/inventario`, ítem **Inventario** del sidebar). `InventarioController::ajustar()` emula `sp_ajustar_stock()` dentro de `DB::transaction`: inserta `movimiento_inventario` (tipo `AJUSTE`) + `detalle_movimiento_inventario` y actualiza `variante_producto.stock_actual`. Una salida no puede superar el stock (EX-02); el motivo es obligatorio (EX-03); el Vendedor consulta (`inventario.ver`) pero no ajusta (`inventario.ajustar`, EX-05). Las ventas (CU11) generan SALIDA por los triggers de PostgreSQL al insertar `detalle_venta`; las compras lo harán cuando exista ese módulo.

### CU10 — Gestión de Clientes (integrado)

El módulo **Clientes** (`/clientes`, ítem del sidebar) registra particulares y tiendas con CI/NIT único, en lugar de depender del genérico “Cliente Final”. `ClienteController` lista, crea, edita y da de baja (ACTIVO/INACTIVO). La ficha muestra el historial de compras y garantías vigentes; la pestaña **Clientes frecuentes** ordena por cantidad de ventas y monto en un período. EX-04: si el cliente tiene ventas, no se elimina físicamente — hay que dejarlo INACTIVO. Permiso: `clientes.gestionar` (Administrador y Vendedor).

### CU11 — Registro de Ventas / POS (integrado)

El núcleo de ventas está en `/ventas` (ítem **Ventas** del sidebar). `Ventas.vue` es el POS: buscador de variantes con stock en vivo (EX-01), carrito con serie y meses de garantía (default 3), selector de cliente (alta inline CU10), pagos múltiples (efectivo + QR, etc.) que deben igualar el total (EX-02 / EX-03) y no se confirma sin productos ni cliente (EX-04).

`VentaController::store()` corre dentro de `DB::transaction()`: genera el correlativo `V-AAAA-0001`, inserta **Venta → DetalleVenta → PagoVenta** (precio y costo tomados de la variante / última compra, no del cliente) y deja bitácora `REGISTRAR_VENTA`. **No actualiza `stock_actual` ni escribe el Kardex a mano.** En PostgreSQL lo hacen los triggers existentes (`trg_venta_generar_movimiento`, `trg_detalle_venta_validar_y_garantia`, `trg_detalle_venta_actualizar_stock`). En SQLite (tests) `VentaKardexEmulador` replica el mismo efecto. La nota de venta es un PDF generado al vuelo con `barryvdh/laravel-dompdf` (`NotaVentaPdf`); si falla el correo (EX-05) la venta queda grabada y se puede descargar o reenviar. Anular exige `ventas.anular` (Administrador) y un motivo (EX-07); `trg_venta_restaurar_stock_anulacion` reingresa stock, marca ítems `DEVUELTO` y deja un AJUSTE en el Kardex. Una venta ya anulada no se vuelve a anular (EX-06).

---

## 10. Notas de Arquitectura

- **Inertia.js:** No hay API REST separada; el backend devuelve props directamente a Vue via Inertia.
- **Imágenes de productos:** Se almacenan en `storage/app/public/catalogo/` y se sirven via symlink `/storage/catalogo/`. El campo `url_webp` almacena la ruta relativa.
- **Slugs:** Se generan automáticamente en backend con `Str::slug($nombre)` y se validan por unicidad excluyendo el registro actual en edición.
- **Control de acceso dual:** Middleware en rutas (Laravel) + `v-if` en componentes (Vue) para UX.
