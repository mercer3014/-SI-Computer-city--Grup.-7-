# Diagrama de la base de datos

Esquema de dominio según las migraciones actuales (PostgreSQL). Las tablas de infraestructura de Laravel (`users`, `sessions`, `password_reset_tokens`, `cache`, `jobs`, `passkeys`) no forman parte del modelo de la tienda: la autenticación usa `usuario`.

Cardinalidad: `1` obligatorio, `0..1` opcional, `1..N` varios.

## Relaciones (vista general)

```mermaid
erDiagram
    rol ||--o{ usuario : tiene
    rol ||--o{ rol_permiso : otorga
    permiso ||--o{ rol_permiso : asignado_en
    personal ||--o| usuario : cuenta
    cliente ||--o| usuario : cuenta_web
    cliente ||--o{ pedido : realiza
    cliente ||--o{ cotizacion : recibe
    cliente ||--o{ venta : compra
    cliente ||--o{ calificacion : emite

    usuario ||--o{ cotizacion : emite
    usuario ||--o{ venta : registra
    usuario ||--o{ compra : registra
    usuario ||--o{ pedido : titula
    usuario ||--o{ gasto : registra
    usuario ||--o{ movimiento_caja : registra
    usuario ||--o{ movimiento_inventario : registra
    usuario ||--o{ bitacora_auditoria : deja
    usuario ||--o{ conversacion_asistente : habla
    usuario ||--o{ plantilla_reporte : guarda
    usuario ||--o{ calificacion : emite
    usuario ||--o{ cotizacion : actualiza_estado
    usuario ||--o{ venta : anula
    usuario ||--o{ calificacion : modera

    categoria ||--o{ categoria : padre
    categoria ||--o{ producto : agrupa
    marca ||--o{ producto : marca
    producto ||--o{ variante_producto : variantes
    producto ||--o{ imagen_producto : imagenes
    producto ||--o{ evento_producto : eventos
    producto ||--o{ calificacion : recibe
    atributo ||--o{ valor_atributo : valores
    variante_producto ||--o{ variante_valor_atributo : usa
    valor_atributo ||--o{ variante_valor_atributo : aplicado_a
    variante_producto ||--o{ detalle_pedido : lineas
    variante_producto ||--o{ detalle_cotizacion : lineas
    variante_producto ||--o{ detalle_venta : lineas
    variante_producto ||--o{ detalle_compra : lineas
    variante_producto ||--o{ detalle_movimiento_inventario : lineas

    configuracion_tienda ||--o{ horario_atencion : horarios

    pedido ||--o{ detalle_pedido : items
    pedido ||--o| venta : convierte
    pedido ||--o{ calificacion : califica

    cotizacion ||--o{ detalle_cotizacion : items
    cotizacion ||--o| venta : convierte

    venta ||--o{ detalle_venta : items
    venta ||--o{ pago_venta : pagos
    venta ||--o{ movimiento_inventario : kardex
    metodo_pago ||--o{ pago_venta : medio
    metodo_pago ||--o{ compra : medio
    metodo_pago ||--o{ gasto : medio
    metodo_pago ||--o{ movimiento_caja : medio

    proveedor ||--o{ compra : abastece
    compra ||--o{ detalle_compra : items
    compra ||--o{ movimiento_inventario : kardex
    movimiento_inventario ||--o{ detalle_movimiento_inventario : items

    categoria_gasto ||--o{ gasto : clasifica
    gasto ||--o{ movimiento_caja : asiento

    conversacion_asistente ||--o{ mensaje_asistente : mensajes
```

## Seguridad y personas

```mermaid
erDiagram
    rol {
        int id PK
        varchar_100 nombre
        text descripcion "nullable"
        boolean es_sistema "default false"
        varchar_20 estado "default ACTIVO"
    }
    permiso {
        int id PK
        varchar_100 clave UK
        varchar_150 nombre
        text descripcion "nullable"
        varchar_100 modulo "nullable"
        varchar_20 estado "default ACTIVO"
    }
    rol_permiso {
        int id PK
        int rol_id FK
        int permiso_id FK
    }
    personal {
        int id PK
        varchar_200 nombre_completo
        varchar_30 ci UK "nullable"
        varchar_30 telefono "nullable"
        varchar_100 cargo "nullable"
        varchar_20 estado "default ACTIVO"
        timestamp_6 fecha_registro "default now"
    }
    cliente {
        int id PK
        varchar_30 tipo_documento "nullable"
        varchar_50 numero_documento UK "nullable"
        varchar_200 nombre
        varchar_200 razon_social "nullable"
        varchar_50 telefono "nullable"
        varchar_150 email "nullable"
        text direccion "nullable"
        text observacion "nullable"
        varchar_20 estado "default ACTIVO"
        timestamp_6 fecha_registro "default now"
    }
    usuario {
        int id PK
        int personal_id FK "nullable, SET NULL"
        int rol_id FK
        int cliente_id FK "nullable, unique, SET NULL"
        varchar_150 email UK
        varchar_255 password_hash
        varchar_20 estado "default ACTIVO"
        int intentos_fallidos "default 0"
        boolean bloqueado "default false"
        boolean primer_login "default true"
        timestamp_6 fecha_ultimo_login "nullable"
        timestamp_6 fecha_creacion "default now"
        timestamp_6 fecha_bloqueo "nullable"
        int nivel_bloqueo "default 0"
    }
    rol ||--o{ usuario : rol_id
    personal ||--o| usuario : personal_id
    cliente ||--o| usuario : cliente_id
    rol ||--o{ rol_permiso : rol_id
    permiso ||--o{ rol_permiso : permiso_id
```

Único: `rol_permiso (rol_id, permiso_id)`.

## Catálogo e inventario

```mermaid
erDiagram
    categoria {
        int id PK
        int categoria_padre_id FK "nullable, SET NULL"
        varchar_150 nombre
        varchar_180 slug UK
        text descripcion "nullable"
        boolean es_promocion "default false"
        text texto_promocion "nullable"
        int orden_visualizacion "default 0"
        boolean activo "default true"
        varchar_255 icono "nullable"
        timestamp_6 fecha_creacion "default now"
    }
    marca {
        int id PK
        varchar_120 nombre
        varchar_150 slug UK
        varchar_20 estado "default ACTIVO"
    }
    producto {
        int id PK
        int categoria_id FK
        int marca_id FK "nullable"
        varchar_200 nombre
        varchar_250 slug UK
        text descripcion "nullable"
        varchar_20 estado "default ACTIVO"
        timestamp_6 fecha_creacion "default now"
        timestamp_6 fecha_actualizacion "default now"
    }
    atributo {
        int id PK
        varchar_100 nombre
        varchar_20 estado "default ACTIVO"
    }
    valor_atributo {
        int id PK
        int atributo_id FK "CASCADE"
        varchar_150 valor
        int orden_visualizacion "default 0"
        varchar_20 estado "default ACTIVO"
    }
    variante_producto {
        int id PK
        int producto_id FK "CASCADE"
        varchar_100 sku UK
        varchar_100 codigo_barras UK "nullable"
        decimal_12_2 precio_venta "CHECK > 0"
        decimal_12_2 precio_anterior "nullable"
        varchar_20 estado "default ACTIVO"
        timestamp_6 fecha_creacion "default now"
        timestamp_6 fecha_actualizacion "default now"
        int stock_actual "default 0"
        int stock_minimo "default 5"
    }
    variante_valor_atributo {
        int id PK
        int variante_id FK "CASCADE"
        int valor_atributo_id FK "CASCADE"
    }
    imagen_producto {
        int id PK
        int producto_id FK "CASCADE"
        text url_webp
        int orden_visualizacion "default 0"
        boolean es_principal "default false"
        varchar_255 texto_alternativo "nullable"
    }
    evento_producto {
        bigint id PK
        int producto_id FK "CASCADE"
        varchar_50 tipo_evento
        varchar_100 session_id "nullable"
        timestamp_6 fecha_hora "default now"
    }
    categoria ||--o{ categoria : categoria_padre_id
    categoria ||--o{ producto : categoria_id
    marca ||--o{ producto : marca_id
    producto ||--o{ variante_producto : producto_id
    producto ||--o{ imagen_producto : producto_id
    producto ||--o{ evento_producto : producto_id
    atributo ||--o{ valor_atributo : atributo_id
    variante_producto ||--o{ variante_valor_atributo : variante_id
    valor_atributo ||--o{ variante_valor_atributo : valor_atributo_id
```

Único: `variante_valor_atributo (variante_id, valor_atributo_id)`.

## Configuración de la tienda

```mermaid
erDiagram
    configuracion_tienda {
        int id PK
        varchar_150 nombre_tienda
        varchar_150 centro_comercial "nullable"
        text direccion "nullable"
        varchar_50 numero_pasillo "nullable"
        varchar_50 numero_local "nullable"
        varchar_30 telefono "nullable"
        varchar_30 whatsapp "nullable"
        varchar_150 email "nullable"
        decimal_10_8 latitud "nullable"
        decimal_11_8 longitud "nullable"
        text google_maps_url "nullable"
        text logo_url "nullable"
        varchar_10 moneda "default BOB"
        text anuncio "nullable"
    }
    horario_atencion {
        int id PK
        int configuracion_tienda_id FK "CASCADE"
        smallint dia_semana
        time_6 hora_apertura "nullable"
        time_6 hora_cierre "nullable"
        boolean cerrado "default false"
    }
    configuracion_tienda ||--o{ horario_atencion : configuracion_tienda_id
```

## Pedidos, cotizaciones y ventas

```mermaid
erDiagram
    pedido {
        int id PK
        int cliente_id FK
        int usuario_id FK "nullable, SET NULL"
        varchar_50 numero_pedido UK
        varchar_150 nombre_contacto "nullable"
        varchar_50 telefono_contacto "nullable"
        varchar_100 metodo_entrega "nullable"
        text direccion_entrega "nullable"
        varchar_30 estado "default PENDIENTE"
        varchar_50 origen "nullable"
        text observacion "nullable"
        timestamp_6 fecha "default now"
        decimal_12_2 total "nullable"
        varchar_100 stripe_payment_intent_id UK "nullable"
        timestamp_6 fecha_pago "nullable"
        text error_pago "nullable"
    }
    detalle_pedido {
        int id PK
        int pedido_id FK "CASCADE"
        int variante_id FK
        int cantidad "CHECK > 0"
        decimal_12_2 precio_unitario "CHECK >= 0"
        decimal_12_2 descuento "default 0"
        varchar_200 nombre_producto_snapshot "nullable"
        varchar_200 variante_snapshot "nullable"
    }
    cotizacion {
        int id PK
        int cliente_id FK
        int usuario_id FK
        int actualizado_por FK "nullable, SET NULL"
        varchar_50 codigo_cotizacion UK
        timestamp_6 fecha_emision "default now"
        date fecha_vencimiento "nullable"
        varchar_30 estado "default PENDIENTE"
        text observacion "nullable"
        timestamp_6 fecha_creacion "default now"
        varchar_20 origen "default TIENDA_WEB, CHECK TIENDA_WEB|MOSTRADOR"
        timestamp_6 fecha_estado "nullable"
    }
    detalle_cotizacion {
        int id PK
        int cotizacion_id FK "CASCADE"
        int variante_id FK
        int cantidad "CHECK > 0"
        decimal_12_2 precio_unitario "CHECK >= 0"
        decimal_12_2 descuento "default 0"
        text descripcion "nullable"
    }
    venta {
        int id PK
        int cliente_id FK
        int pedido_id FK "nullable, SET NULL"
        int cotizacion_id FK "nullable, SET NULL"
        int usuario_id FK
        int anulado_por FK "nullable, SET NULL"
        varchar_50 numero_venta UK
        timestamp_6 fecha "default now"
        varchar_30 estado "default COMPLETADA"
        decimal_12_2 descuento_global "default 0"
        text observacion "nullable"
        timestamp_6 fecha_anulacion "nullable"
        text motivo_anulacion "nullable"
    }
    detalle_venta {
        int id PK
        int venta_id FK "CASCADE"
        int variante_id FK
        int cantidad "CHECK > 0"
        decimal_12_2 precio_unitario "CHECK >= 0"
        decimal_12_2 descuento "default 0"
        decimal_12_2 costo_unitario_snapshot "nullable"
        varchar_200 nombre_producto_snapshot "nullable"
        varchar_200 variante_snapshot "nullable"
        varchar_100 numero_serie "nullable"
        int meses_garantia "default 3"
        date fecha_fin_garantia "nullable"
        varchar_30 estado "default VIGENTE"
    }
    pago_venta {
        int id PK
        int venta_id FK "CASCADE"
        int metodo_pago_id FK
        decimal_12_2 monto "CHECK > 0"
        varchar_100 referencia "nullable"
        timestamp_6 fecha "default now"
        text observacion "nullable"
    }
    metodo_pago {
        int id PK
        varchar_50 codigo UK
        varchar_100 nombre
        varchar_20 estado "default ACTIVO"
    }
    calificacion {
        int id PK
        int cliente_id FK
        int usuario_id FK
        int pedido_id FK
        int producto_id FK
        int moderado_por FK "nullable"
        smallint puntuacion "CHECK 1..5"
        varchar_500 comentario "nullable"
        varchar_20 estado "default PENDIENTE, CHECK PENDIENTE|PUBLICADA|OCULTA"
        text motivo_ocultamiento "nullable"
        timestamp_6 fecha_moderacion "nullable"
        timestamp_6 fecha "default now"
    }
    cliente ||--o{ pedido : cliente_id
    usuario ||--o{ pedido : usuario_id
    pedido ||--o{ detalle_pedido : pedido_id
    variante_producto ||--o{ detalle_pedido : variante_id
    cliente ||--o{ cotizacion : cliente_id
    usuario ||--o{ cotizacion : usuario_id
    usuario ||--o{ cotizacion : actualizado_por
    cotizacion ||--o{ detalle_cotizacion : cotizacion_id
    variante_producto ||--o{ detalle_cotizacion : variante_id
    cliente ||--o{ venta : cliente_id
    pedido ||--o| venta : pedido_id
    cotizacion ||--o| venta : cotizacion_id
    usuario ||--o{ venta : usuario_id
    usuario ||--o{ venta : anulado_por
    venta ||--o{ detalle_venta : venta_id
    variante_producto ||--o{ detalle_venta : variante_id
    venta ||--o{ pago_venta : venta_id
    metodo_pago ||--o{ pago_venta : metodo_pago_id
    cliente ||--o{ calificacion : cliente_id
    usuario ||--o{ calificacion : usuario_id
    pedido ||--o{ calificacion : pedido_id
    producto ||--o{ calificacion : producto_id
    usuario ||--o{ calificacion : moderado_por
```

Único: `calificacion (pedido_id, producto_id)`. Índice: `calificacion (producto_id, estado)`, `cotizacion (estado)`.

## Compras, kardex, caja y bitácora

```mermaid
erDiagram
    proveedor {
        int id PK
        varchar_50 nit "nullable"
        varchar_200 razon_social
        varchar_200 nombre_comercial "nullable"
        varchar_150 contacto "nullable"
        varchar_50 telefono "nullable"
        varchar_150 email "nullable"
        text direccion "nullable"
        text observacion "nullable"
        varchar_20 estado "default ACTIVO"
        timestamp_6 fecha_registro "default now"
    }
    compra {
        int id PK
        int proveedor_id FK
        int usuario_id FK
        int metodo_pago_id FK "nullable"
        varchar_50 numero_compra UK
        timestamp_6 fecha_compra
        varchar_50 tipo_documento "nullable"
        varchar_100 numero_documento "nullable"
        varchar_30 estado "default COMPLETADO"
        text observacion "nullable"
        timestamp_6 fecha_registro "default now"
    }
    detalle_compra {
        int id PK
        int compra_id FK "CASCADE"
        int variante_id FK
        int cantidad "CHECK > 0"
        decimal_12_2 costo_unitario "CHECK >= 0"
        decimal_12_2 descuento "default 0"
        varchar_100 numero_serie "nullable"
    }
    movimiento_inventario {
        int id PK
        int usuario_id FK
        int venta_id FK "nullable, SET NULL"
        int compra_id FK "nullable, SET NULL"
        varchar_50 tipo
        timestamp_6 fecha "default now"
        varchar_100 referencia "nullable"
        text observacion "nullable"
    }
    detalle_movimiento_inventario {
        int id PK
        int movimiento_id FK "CASCADE"
        int variante_id FK
        int cantidad "CHECK > 0"
        decimal_12_2 costo_unitario "default 0, nullable"
    }
    categoria_gasto {
        int id PK
        varchar_150 nombre
        text descripcion "nullable"
        varchar_20 estado "default ACTIVO"
    }
    gasto {
        int id PK
        int categoria_gasto_id FK
        int metodo_pago_id FK "nullable"
        int usuario_id FK
        timestamp_6 fecha
        varchar_255 concepto
        decimal_12_2 monto "CHECK > 0"
        varchar_100 referencia "nullable"
        text observacion "nullable"
        varchar_20 estado "default ACTIVO"
        timestamp_6 fecha_registro "default now"
        text motivo_anulacion "nullable"
    }
    movimiento_caja {
        int id PK
        int usuario_id FK
        int gasto_id FK "nullable, SET NULL"
        int metodo_pago_id FK "nullable"
        varchar_50 tipo
        varchar_100 categoria "nullable"
        decimal_12_2 monto "CHECK > 0"
        timestamp_6 fecha "default now"
        text descripcion "nullable"
        varchar_100 referencia "nullable"
        varchar_20 estado "default ACTIVO"
        text motivo_anulacion "nullable"
    }
    bitacora_auditoria {
        bigint id PK
        int usuario_id FK "nullable, SET NULL"
        varchar_100 modulo
        varchar_100 accion
        varchar_100 tipo_entidad "nullable"
        bigint entidad_id "nullable"
        jsonb valor_anterior "nullable"
        jsonb valor_nuevo "nullable"
        text motivo "nullable"
        varchar_45 direccion_ip "nullable"
        timestamp_6 fecha_hora "default now"
    }
    proveedor ||--o{ compra : proveedor_id
    usuario ||--o{ compra : usuario_id
    metodo_pago ||--o{ compra : metodo_pago_id
    compra ||--o{ detalle_compra : compra_id
    variante_producto ||--o{ detalle_compra : variante_id
    usuario ||--o{ movimiento_inventario : usuario_id
    venta ||--o{ movimiento_inventario : venta_id
    compra ||--o{ movimiento_inventario : compra_id
    movimiento_inventario ||--o{ detalle_movimiento_inventario : movimiento_id
    variante_producto ||--o{ detalle_movimiento_inventario : variante_id
    categoria_gasto ||--o{ gasto : categoria_gasto_id
    metodo_pago ||--o{ gasto : metodo_pago_id
    usuario ||--o{ gasto : usuario_id
    usuario ||--o{ movimiento_caja : usuario_id
    gasto ||--o{ movimiento_caja : gasto_id
    metodo_pago ||--o{ movimiento_caja : metodo_pago_id
    usuario ||--o{ bitacora_auditoria : usuario_id
```

## Asistente virtual

```mermaid
erDiagram
    conversacion_asistente {
        int id PK
        int usuario_id FK "CASCADE"
        varchar_150 titulo "nullable"
        timestamp fecha_inicio "default now"
        timestamp fecha_actualizacion "default now"
    }
    mensaje_asistente {
        int id PK
        int conversacion_id FK "CASCADE"
        varchar_15 rol "CHECK USUARIO|ASISTENTE"
        text contenido
        varchar_10 origen "default TEXTO, CHECK TEXTO|VOZ"
        varchar_20 tipo "default TEXTO, CHECK TEXTO|CONSULTA|REPORTE|ERROR|NO_SOPORTADO"
        jsonb especificacion "nullable"
        text interpretacion "nullable"
        varchar_255 enlace "nullable"
        timestamp fecha "default now"
    }
    plantilla_reporte {
        int id PK
        int usuario_id FK "CASCADE"
        varchar_100 nombre
        jsonb especificacion
        timestamp fecha_creacion "default now"
    }
    usuario ||--o{ conversacion_asistente : usuario_id
    conversacion_asistente ||--o{ mensaje_asistente : conversacion_id
    usuario ||--o{ plantilla_reporte : usuario_id
```

Único: `plantilla_reporte (usuario_id, nombre)`.
