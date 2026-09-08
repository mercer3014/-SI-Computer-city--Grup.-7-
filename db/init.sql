-- 1. SEGURIDAD, ROLES Y ACCESOS
CREATE TABLE rol (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT,
    es_sistema BOOLEAN DEFAULT FALSE,
    estado VARCHAR(20) DEFAULT 'ACTIVO'
);

CREATE TABLE permiso (
    id SERIAL PRIMARY KEY,
    clave VARCHAR(100) UNIQUE NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    modulo VARCHAR(100),
    estado VARCHAR(20) DEFAULT 'ACTIVO'
);

CREATE TABLE rol_permiso (
    id SERIAL PRIMARY KEY,
    rol_id INT NOT NULL REFERENCES rol(id) ON DELETE CASCADE,
    permiso_id INT NOT NULL REFERENCES permiso(id) ON DELETE CASCADE,
    CONSTRAINT uq_rol_permiso UNIQUE (rol_id, permiso_id)
);

CREATE TABLE personal (
    id SERIAL PRIMARY KEY,
    nombre_completo VARCHAR(200) NOT NULL,
    ci VARCHAR(30) UNIQUE,
    telefono VARCHAR(30),
    cargo VARCHAR(100),
    estado VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE usuario (
    id SERIAL PRIMARY KEY,
    personal_id INT REFERENCES personal(id) ON DELETE SET NULL,
    rol_id INT NOT NULL REFERENCES rol(id),
    email VARCHAR(150) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    estado VARCHAR(20) DEFAULT 'ACTIVO',
    intentos_fallidos INT DEFAULT 0,
    bloqueado BOOLEAN DEFAULT FALSE,
    primer_login BOOLEAN DEFAULT TRUE,
    fecha_ultimo_login TIMESTAMP,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_bloqueo TIMESTAMP,
    nivel_bloqueo INT DEFAULT 0
);

CREATE TABLE bitacora_auditoria (
    id BIGSERIAL PRIMARY KEY,
    usuario_id INT REFERENCES usuario(id) ON DELETE SET NULL,
    modulo VARCHAR(100) NOT NULL,
    accion VARCHAR(100) NOT NULL,
    tipo_entidad VARCHAR(100),
    entidad_id BIGINT,
    valor_anterior JSONB,
    valor_nuevo JSONB,
    motivo TEXT,
    direccion_ip VARCHAR(45),
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. CONFIGURACIÓN DE TIENDA Y HORARIOS
CREATE TABLE configuracion_tienda (
    id SERIAL PRIMARY KEY,
    nombre_tienda VARCHAR(150) NOT NULL,
    centro_comercial VARCHAR(150),
    direccion TEXT,
    numero_pasillo VARCHAR(50),
    numero_local VARCHAR(50),
    telefono VARCHAR(30),
    whatsapp VARCHAR(30),
    email VARCHAR(150),
    latitud NUMERIC(10, 8),
    longitud NUMERIC(11, 8),
    google_maps_url TEXT,
    logo_url TEXT,
    moneda VARCHAR(10) DEFAULT 'BOB',
    anuncio TEXT
);

CREATE TABLE horario_atencion (
    id SERIAL PRIMARY KEY,
    configuracion_tienda_id INT NOT NULL REFERENCES configuracion_tienda(id) ON DELETE CASCADE,
    dia_semana SMALLINT NOT NULL, -- 1 = Lunes, ..., 7 = Domingo
    hora_apertura TIME,
    hora_cierre TIME,
    cerrado BOOLEAN DEFAULT FALSE
);

-- 3. CATÁLOGO DE PRODUCTOS Y VARIANTES
CREATE TABLE categoria (
    id SERIAL PRIMARY KEY,
    categoria_padre_id INT REFERENCES categoria(id) ON DELETE SET NULL,
    nombre VARCHAR(150) NOT NULL,
    slug VARCHAR(180) UNIQUE NOT NULL,
    descripcion TEXT,
    es_promocion BOOLEAN DEFAULT FALSE,
    texto_promocion TEXT,
    orden_visualizacion INT DEFAULT 0,
    activo BOOLEAN DEFAULT TRUE,
    icono VARCHAR(255),
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE marca (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(120) NOT NULL,
    slug VARCHAR(150) UNIQUE NOT NULL,
    estado VARCHAR(20) DEFAULT 'ACTIVO'
);

CREATE TABLE producto (
    id SERIAL PRIMARY KEY,
    categoria_id INT NOT NULL REFERENCES categoria(id),
    marca_id INT REFERENCES marca(id),
    nombre VARCHAR(200) NOT NULL,
    slug VARCHAR(250) UNIQUE NOT NULL,
    descripcion TEXT,
    estado VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE imagen_producto (
    id SERIAL PRIMARY KEY,
    producto_id INT NOT NULL REFERENCES producto(id) ON DELETE CASCADE,
    url_webp TEXT NOT NULL,
    orden_visualizacion INT DEFAULT 0,
    es_principal BOOLEAN DEFAULT FALSE,
    texto_alternativo VARCHAR(255)
);

CREATE TABLE evento_producto (
    id BIGSERIAL PRIMARY KEY,
    producto_id INT NOT NULL REFERENCES producto(id) ON DELETE CASCADE,
    tipo_evento VARCHAR(50) NOT NULL,
    session_id VARCHAR(100),
    fecha_hora TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE atributo (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    estado VARCHAR(20) DEFAULT 'ACTIVO'
);

CREATE TABLE valor_atributo (
    id SERIAL PRIMARY KEY,
    atributo_id INT NOT NULL REFERENCES atributo(id) ON DELETE CASCADE,
    valor VARCHAR(150) NOT NULL,
    orden_visualizacion INT DEFAULT 0,
    estado VARCHAR(20) DEFAULT 'ACTIVO'
);

CREATE TABLE variante_producto (
    id SERIAL PRIMARY KEY,
    producto_id INT NOT NULL REFERENCES producto(id) ON DELETE CASCADE,
    sku VARCHAR(100) UNIQUE NOT NULL,
    codigo_barras VARCHAR(100) UNIQUE,
    precio_venta NUMERIC(12, 2) NOT NULL,
    precio_anterior NUMERIC(12, 2),
    estado VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    stock_actual INT NOT NULL DEFAULT 0,
    stock_minimo INT NOT NULL DEFAULT 5
);

CREATE TABLE variante_valor_atributo (
    id SERIAL PRIMARY KEY,
    variante_id INT NOT NULL REFERENCES variante_producto(id) ON DELETE CASCADE,
    valor_atributo_id INT NOT NULL REFERENCES valor_atributo(id) ON DELETE CASCADE,
    CONSTRAINT uq_variante_valor UNIQUE (variante_id, valor_atributo_id)
);

-- 4. PROVEEDORES, COMPRAS Y FINANZAS
CREATE TABLE proveedor (
    id SERIAL PRIMARY KEY,
    nit VARCHAR(50),
    razon_social VARCHAR(200) NOT NULL,
    nombre_comercial VARCHAR(200),
    contacto VARCHAR(150),
    telefono VARCHAR(50),
    email VARCHAR(150),
    direccion TEXT,
    observacion TEXT,
    estado VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE metodo_pago (
    id SERIAL PRIMARY KEY,
    codigo VARCHAR(50) UNIQUE NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    estado VARCHAR(20) DEFAULT 'ACTIVO'
);

CREATE TABLE compra (
    id SERIAL PRIMARY KEY,
    proveedor_id INT NOT NULL REFERENCES proveedor(id),
    usuario_id INT NOT NULL REFERENCES usuario(id),
    metodo_pago_id INT REFERENCES metodo_pago(id),
    numero_compra VARCHAR(50) UNIQUE NOT NULL,
    fecha_compra TIMESTAMP NOT NULL,
    tipo_documento VARCHAR(50),
    numero_documento VARCHAR(100),
    estado VARCHAR(30) DEFAULT 'COMPLETADO',
    observacion TEXT,
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE detalle_compra (
    id SERIAL PRIMARY KEY,
    compra_id INT NOT NULL REFERENCES compra(id) ON DELETE CASCADE,
    variante_id INT NOT NULL REFERENCES variante_producto(id),
    cantidad INT NOT NULL CHECK (cantidad > 0),
    costo_unitario NUMERIC(12, 2) NOT NULL CHECK (costo_unitario >= 0),
    descuento NUMERIC(12, 2) DEFAULT 0,
    numero_serie VARCHAR(100)
);

CREATE TABLE categoria_gasto (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT,
    estado VARCHAR(20) DEFAULT 'ACTIVO'
);

CREATE TABLE gasto (
    id SERIAL PRIMARY KEY,
    categoria_gasto_id INT NOT NULL REFERENCES categoria_gasto(id),
    metodo_pago_id INT REFERENCES metodo_pago(id),
    usuario_id INT NOT NULL REFERENCES usuario(id),
    fecha TIMESTAMP NOT NULL,
    concepto VARCHAR(255) NOT NULL,
    monto NUMERIC(12, 2) NOT NULL CHECK (monto > 0),
    referencia VARCHAR(100),
    observacion TEXT,
    estado VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE movimiento_caja (
    id SERIAL PRIMARY KEY,
    usuario_id INT NOT NULL REFERENCES usuario(id),
    tipo VARCHAR(50) NOT NULL, -- INGRESO / EGRESO
    categoria VARCHAR(100),
    monto NUMERIC(12, 2) NOT NULL CHECK (monto > 0),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    descripcion TEXT
);

-- 5. CLIENTES, COTIZACIONES, PEDIDOS Y VENTAS
CREATE TABLE cliente (
    id SERIAL PRIMARY KEY,
    tipo_documento VARCHAR(30),
    numero_documento VARCHAR(50) UNIQUE,
    nombre VARCHAR(200) NOT NULL,
    razon_social VARCHAR(200),
    telefono VARCHAR(50),
    email VARCHAR(150),
    direccion TEXT,
    observacion TEXT,
    estado VARCHAR(20) DEFAULT 'ACTIVO',
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE cotizacion (
    id SERIAL PRIMARY KEY,
    cliente_id INT NOT NULL REFERENCES cliente(id),
    usuario_id INT NOT NULL REFERENCES usuario(id),
    codigo_cotizacion VARCHAR(50) UNIQUE NOT NULL,
    fecha_emision TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    fecha_vencimiento DATE,
    estado VARCHAR(30) DEFAULT 'PENDIENTE',
    observacion TEXT,
    fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE detalle_cotizacion (
    id SERIAL PRIMARY KEY,
    cotizacion_id INT NOT NULL REFERENCES cotizacion(id) ON DELETE CASCADE,
    variante_id INT NOT NULL REFERENCES variante_producto(id),
    cantidad INT NOT NULL CHECK (cantidad > 0),
    precio_unitario NUMERIC(12, 2) NOT NULL CHECK (precio_unitario >= 0),
    descuento NUMERIC(12, 2) DEFAULT 0,
    descripcion TEXT
);

CREATE TABLE pedido (
    id SERIAL PRIMARY KEY,
    cliente_id INT NOT NULL REFERENCES cliente(id),
    numero_pedido VARCHAR(50) UNIQUE NOT NULL,
    nombre_contacto VARCHAR(150),
    telefono_contacto VARCHAR(50),
    metodo_entrega VARCHAR(100),
    direccion_entrega TEXT,
    estado VARCHAR(30) DEFAULT 'PENDIENTE',
    origen VARCHAR(50),
    observacion TEXT,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE detalle_pedido (
    id SERIAL PRIMARY KEY,
    pedido_id INT NOT NULL REFERENCES pedido(id) ON DELETE CASCADE,
    variante_id INT NOT NULL REFERENCES variante_producto(id),
    cantidad INT NOT NULL CHECK (cantidad > 0),
    precio_unitario NUMERIC(12, 2) NOT NULL CHECK (precio_unitario >= 0),
    descuento NUMERIC(12, 2) DEFAULT 0,
    nombre_producto_snapshot VARCHAR(200),
    variante_snapshot VARCHAR(200)
);

CREATE TABLE venta (
    id SERIAL PRIMARY KEY,
    cliente_id INT NOT NULL REFERENCES cliente(id),
    pedido_id INT REFERENCES pedido(id) ON DELETE SET NULL,
    cotizacion_id INT REFERENCES cotizacion(id) ON DELETE SET NULL,
    usuario_id INT NOT NULL REFERENCES usuario(id),
    numero_venta VARCHAR(50) UNIQUE NOT NULL,
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    estado VARCHAR(30) DEFAULT 'COMPLETADA',
    descuento_global NUMERIC(12, 2) DEFAULT 0,
    observacion TEXT,
    fecha_anulacion TIMESTAMP,
    anulado_por INT REFERENCES usuario(id) ON DELETE SET NULL,
    motivo_anulacion TEXT
);

CREATE TABLE detalle_venta (
    id SERIAL PRIMARY KEY,
    venta_id INT NOT NULL REFERENCES venta(id) ON DELETE CASCADE,
    variante_id INT NOT NULL REFERENCES variante_producto(id),
    cantidad INT NOT NULL CHECK (cantidad > 0),
    precio_unitario NUMERIC(12, 2) NOT NULL CHECK (precio_unitario >= 0),
    descuento NUMERIC(12, 2) DEFAULT 0,
    costo_unitario_snapshot NUMERIC(12, 2),
    nombre_producto_snapshot VARCHAR(200),
    variante_snapshot VARCHAR(200),
    numero_serie VARCHAR(100),
    meses_garantia INT NOT NULL DEFAULT 3,
    fecha_fin_garantia DATE,
    estado VARCHAR(30) NOT NULL DEFAULT 'VIGENTE' -- VIGENTE / CAMBIADO / DEVUELTO
);

CREATE TABLE pago_venta (
    id SERIAL PRIMARY KEY,
    venta_id INT NOT NULL REFERENCES venta(id) ON DELETE CASCADE,
    metodo_pago_id INT NOT NULL REFERENCES metodo_pago(id),
    monto NUMERIC(12, 2) NOT NULL CHECK (monto > 0),
    referencia VARCHAR(100),
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    observacion TEXT
);

-- 6. CONTROL DE MOVIMIENTOS DE INVENTARIO (KARDEX)
CREATE TABLE movimiento_inventario (
    id SERIAL PRIMARY KEY,
    usuario_id INT NOT NULL REFERENCES usuario(id),
    venta_id INT REFERENCES venta(id) ON DELETE SET NULL,
    compra_id INT REFERENCES compra(id) ON DELETE SET NULL,
    tipo VARCHAR(50) NOT NULL, -- ENTRADA, SALIDA, AJUSTE
    fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    referencia VARCHAR(100),
    observacion TEXT
);

CREATE TABLE detalle_movimiento_inventario (
    id SERIAL PRIMARY KEY,
    movimiento_id INT NOT NULL REFERENCES movimiento_inventario(id) ON DELETE CASCADE,
    variante_id INT NOT NULL REFERENCES variante_producto(id),
    cantidad INT NOT NULL CHECK (cantidad > 0),
    costo_unitario NUMERIC(12, 2) DEFAULT 0
);

-- 7. ÍNDICES DE RENDIMIENTO PARA CLAVES FORÁNEAS Y BÚSQUEDAS FRECUENTES
CREATE INDEX idx_producto_categoria ON producto(categoria_id);
CREATE INDEX idx_producto_marca ON producto(marca_id);
CREATE INDEX idx_variante_producto ON variante_producto(producto_id);
CREATE INDEX idx_venta_cliente ON venta(cliente_id);
CREATE INDEX idx_venta_fecha ON venta(fecha);
CREATE INDEX idx_compra_proveedor ON compra(proveedor_id);
CREATE INDEX idx_mov_inv_fecha ON movimiento_inventario(fecha);






/* ============================================================================
   5. PROCEDIMIENTOS ALMACENADOS (PROCEDURE)
   -
   NOTA: estos procedimientos se definen antes de usarse en las secciones 2-4;
   en un despliegue real, este bloque (5 y 6) debe ejecutarse primero.
   ============================================================================ */

-- 1: Registrar un nuevo cliente en el sistema
CREATE OR REPLACE PROCEDURE sp_registrar_cliente(
    p_tipo_documento   VARCHAR,
    p_numero_documento VARCHAR,
    p_nombre           VARCHAR,
    p_telefono         VARCHAR DEFAULT NULL,
    p_email            VARCHAR DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF EXISTS (SELECT 1 FROM cliente WHERE numero_documento = p_numero_documento) THEN
        RAISE EXCEPTION 'Ya existe un cliente registrado con el documento %', p_numero_documento;
    END IF;

    INSERT INTO cliente (tipo_documento, numero_documento, nombre, telefono, email)
    VALUES (p_tipo_documento, p_numero_documento, p_nombre, p_telefono, p_email);
END;
$$;
-- Propósito: Permite registrar un nuevo cliente validando previamente
--   que el documento de identidad o NIT no se encuentre duplicado. 
--   Esto ayuda a mantener un registro limpio de la clientela,
--   facilitando la posterior facturación o historial de compras
--   y evitando las inconsistencias de datos en la base.

-- 2: Registrar un nuevo proveedor en el sistema
CREATE OR REPLACE PROCEDURE sp_registrar_proveedor(
    p_nit             VARCHAR,
    p_razon_social    VARCHAR,
    p_nombre_comercial VARCHAR DEFAULT NULL,
    p_contacto        VARCHAR DEFAULT NULL,
    p_telefono        VARCHAR DEFAULT NULL,
    p_email           VARCHAR DEFAULT NULL
)
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO proveedor (nit, razon_social, nombre_comercial, contacto, telefono, email)
    VALUES (p_nit, p_razon_social, p_nombre_comercial, p_contacto, p_telefono, p_email);
END;
$$;
-- Propósito: Registra de manera formal la información detallada de 
--   los proveedores con los que opera la tienda actualmente.
--   Esto es vital para llevar un control estricto de a quién se le 
--   compran los productos, facilitar futuros contactos de reabastecimiento
--   y asociar correctamente las compras a sus respectivos proveedores.

-- 3: Registrar una nueva compra y sus detalles
-- p_items: arreglo JSON [{ "sku": "...", "cantidad": n, "costo_unitario": n }, ...]
CREATE OR REPLACE PROCEDURE sp_registrar_compra(
    p_proveedor_id   INT,
    p_usuario_id     INT,
    p_metodo_pago_id INT,
    p_numero_compra  VARCHAR,
    p_fecha_compra   TIMESTAMP,
    p_items          JSONB
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_compra_id INT;
    v_item      JSONB;
    v_variante_id INT;
BEGIN
    INSERT INTO compra (proveedor_id, usuario_id, metodo_pago_id, numero_compra, fecha_compra)
    VALUES (p_proveedor_id, p_usuario_id, p_metodo_pago_id, p_numero_compra, p_fecha_compra)
    RETURNING id INTO v_compra_id;
    -- El trigger trg_compra_generar_movimiento crea automáticamente el
    -- encabezado de kardex (movimiento_inventario tipo ENTRADA) para esta compra.

    FOR v_item IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP
        SELECT id INTO v_variante_id FROM variante_producto WHERE sku = v_item->>'sku';
        IF v_variante_id IS NULL THEN
            RAISE EXCEPTION 'No existe ninguna variante de producto con el SKU %', v_item->>'sku';
        END IF;

        INSERT INTO detalle_compra (compra_id, variante_id, cantidad, costo_unitario, numero_serie)
        VALUES (v_compra_id, v_variante_id,
                (v_item->>'cantidad')::INT,
                (v_item->>'costo_unitario')::NUMERIC,
                v_item->>'numero_serie');
        -- El trigger trg_detalle_compra_actualizar_stock incrementa el stock
        -- y registra el detalle correspondiente en el kardex.
    END LOOP;
END;
$$;
-- Propósito: Recibe la información de cabecera de una nueva compra y
--   un documento JSON estructurado con el detalle de los productos.
--   Se encarga de iterar los ítems, validar la existencia del SKU, 
--   guardar el registro y desencadenar los triggers que alimentarán 
--   el kardex de entradas y aumentarán el stock físico disponible.

-- 4: Registrar una nueva venta y sus detalles
-- p_items: [{ "sku":"...", "cantidad":n, "precio_unitario":n, "numero_serie":"..",
--              "meses_garantia":n, "descuento":n }, ...]
CREATE OR REPLACE PROCEDURE sp_registrar_venta(
    p_cliente_id     INT,
    p_usuario_id     INT,
    p_metodo_pago_id INT,
    p_numero_venta   VARCHAR,
    p_items          JSONB
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_venta_id    INT;
    v_item        JSONB;
    v_variante_id INT;
    v_subtotal    NUMERIC := 0;
    v_total       NUMERIC := 0;
BEGIN
    INSERT INTO venta (cliente_id, usuario_id, numero_venta)
    VALUES (p_cliente_id, p_usuario_id, p_numero_venta)
    RETURNING id INTO v_venta_id;
    -- El trigger trg_venta_generar_movimiento crea el encabezado de kardex
    -- (movimiento_inventario tipo SALIDA) para esta venta.

    FOR v_item IN SELECT * FROM jsonb_array_elements(p_items)
    LOOP
        SELECT id INTO v_variante_id FROM variante_producto WHERE sku = v_item->>'sku';
        IF v_variante_id IS NULL THEN
            RAISE EXCEPTION 'No existe ninguna variante de producto con el SKU %', v_item->>'sku';
        END IF;

        v_subtotal := (v_item->>'cantidad')::NUMERIC * (v_item->>'precio_unitario')::NUMERIC
                      - COALESCE((v_item->>'descuento')::NUMERIC, 0);
        v_total := v_total + v_subtotal;

        INSERT INTO detalle_venta (venta_id, variante_id, cantidad, precio_unitario, descuento,
                                    numero_serie, meses_garantia)
        VALUES (v_venta_id, v_variante_id,
                (v_item->>'cantidad')::INT,
                (v_item->>'precio_unitario')::NUMERIC,
                COALESCE((v_item->>'descuento')::NUMERIC, 0),
                v_item->>'numero_serie',
                COALESCE((v_item->>'meses_garantia')::INT, 3));
        -- Los triggers de detalle_venta validan stock disponible, calculan la
        -- fecha de fin de garantía y descuentan el stock automáticamente.
    END LOOP;

    INSERT INTO pago_venta (venta_id, metodo_pago_id, monto)
    VALUES (v_venta_id, p_metodo_pago_id, v_total);
END;
$$;
-- Propósito: Gestiona de forma transaccional el proceso de venta completo,
--   recibiendo la cabecera y el detalle de ítems en formato JSON.
--   Calcula los subtotales, valida el SKU de los productos, descuenta el
--   inventario automáticamente mediante triggers, registra el inicio de 
--   las garantías e inserta el asiendo correspondiente del pago recibido.

-- 5: Anular una venta existente
CREATE OR REPLACE PROCEDURE sp_anular_venta(
    p_numero_venta VARCHAR,
    p_usuario_id   INT,
    p_motivo       TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_venta_id INT;
    v_estado   VARCHAR;
BEGIN
    SELECT id, estado INTO v_venta_id, v_estado FROM venta WHERE numero_venta = p_numero_venta;

    IF v_venta_id IS NULL THEN
        RAISE EXCEPTION 'No existe ninguna venta con el número %', p_numero_venta;
    END IF;
    IF v_estado = 'ANULADA' THEN
        RAISE EXCEPTION 'La venta % ya se encuentra anulada', p_numero_venta;
    END IF;

    UPDATE venta
    SET estado = 'ANULADA',
        fecha_anulacion = CURRENT_TIMESTAMP,
        anulado_por = p_usuario_id,
        motivo_anulacion = p_motivo
    WHERE id = v_venta_id;
    -- El trigger trg_venta_restaurar_stock_anulacion se encarga de devolver
    -- el stock de cada producto vendido y de registrar el ajuste en el kardex.
END;
$$;
-- Propósito: Permite anular o revertir una venta ya registrada, 
--   en caso de errores de facturación o una devolución total del cliente.
--   Cambia el estado de la venta, marca la fecha y el usuario que anula,
--   y activa de inmediato un trigger que se encarga de reingresar 
--   todo el stock de los productos devueltos directamente al kardex.

-- 6: Registrar un gasto operativo
CREATE OR REPLACE PROCEDURE sp_registrar_gasto(
    p_categoria_gasto_id INT,
    p_metodo_pago_id     INT,
    p_usuario_id         INT,
    p_concepto           VARCHAR,
    p_monto              NUMERIC,
    p_fecha              TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_monto <= 0 THEN
        RAISE EXCEPTION 'El monto del gasto debe ser mayor a cero';
    END IF;

    INSERT INTO gasto (categoria_gasto_id, metodo_pago_id, usuario_id, fecha, concepto, monto)
    VALUES (p_categoria_gasto_id, p_metodo_pago_id, p_usuario_id, p_fecha, p_concepto, p_monto);

    INSERT INTO movimiento_caja (usuario_id, tipo, categoria, monto, descripcion)
    VALUES (p_usuario_id, 'EGRESO', 'Gasto operativo', p_monto, p_concepto);
END;
$$;
-- Propósito: Centraliza el registro de todos los egresos de efectivo 
--   tales como el pago de alquileres, servicios básicos o viáticos.
--   Al registrar un gasto, inserta datos en la tabla principal y además
--   genera automáticamente el egreso en la tabla de movimientos de caja,
--   manteniendo siempre cuadrado y actualizado el flujo de efectivo.

-- 7: Actualizar el precio de venta de una variante de producto
CREATE OR REPLACE PROCEDURE sp_actualizar_precio_variante(
    p_sku          VARCHAR,
    p_nuevo_precio NUMERIC,
    p_usuario_id   INT
)
LANGUAGE plpgsql
AS $$
BEGIN
    IF p_nuevo_precio <= 0 THEN
        RAISE EXCEPTION 'El precio de venta debe ser mayor a cero';
    END IF;

    UPDATE variante_producto
    SET precio_anterior = precio_venta,
        precio_venta = p_nuevo_precio
    WHERE sku = p_sku;
    -- El trigger trg_auditoria_cambio_precio registra el cambio en bitacora_auditoria.

    IF NOT FOUND THEN
        RAISE EXCEPTION 'No existe ninguna variante de producto con el SKU %', p_sku;
    END IF;
END;
$$;
-- Propósito: Facilita la modificación rápida del precio de venta final
--   de cualquier variante de producto, manteniendo el precio viejo para 
--   futuras referencias o consultas de auditoría. 
--   Este procedimiento es el responsable de disparar el trigger de 
--   auditoría que guarda quién y cuándo hizo los cambios en los precios.

-- 8: Ajustar stock de inventario de forma manual (mercadería dañada, pérdida, conteo físico, etc.)
CREATE OR REPLACE PROCEDURE sp_ajustar_stock(
    p_sku         VARCHAR,
    p_cantidad    INT,       -- positivo = incrementa, negativo = disminuye
    p_usuario_id  INT,
    p_observacion TEXT
)
LANGUAGE plpgsql
AS $$
DECLARE
    v_variante_id INT;
    v_movimiento_id INT;
BEGIN
    SELECT id INTO v_variante_id FROM variante_producto WHERE sku = p_sku;
    IF v_variante_id IS NULL THEN
        RAISE EXCEPTION 'No existe ninguna variante de producto con el SKU %', p_sku;
    END IF;

    INSERT INTO movimiento_inventario (usuario_id, tipo, referencia, observacion)
    VALUES (p_usuario_id, 'AJUSTE', 'AJUSTE-MANUAL', p_observacion)
    RETURNING id INTO v_movimiento_id;

    INSERT INTO detalle_movimiento_inventario (movimiento_id, variante_id, cantidad, costo_unitario)
    VALUES (v_movimiento_id, v_variante_id, ABS(p_cantidad), 0);
    -- El trigger trg_detalle_mov_inventario_aplicar_stock suma o resta según
    -- corresponda; para diferenciar la dirección del ajuste se usa el signo
    -- de p_cantidad, que queda registrado en la observación del movimiento.

    UPDATE movimiento_inventario
    SET observacion = observacion || CASE WHEN p_cantidad < 0 THEN ' (SALIDA)' ELSE ' (ENTRADA)' END
    WHERE id = v_movimiento_id;

    IF p_cantidad < 0 THEN
        UPDATE variante_producto SET stock_actual = stock_actual - ABS(p_cantidad) - ABS(p_cantidad)
        WHERE id = v_variante_id;
        -- Se resta dos veces para anular el "+ABS" que ya aplicó el trigger
        -- de inserción (que siempre suma) y dejar el efecto neto en negativo.
    END IF;
END;
$$;
-- Propósito: Brinda una herramienta administrativa para realizar un ajuste 
--   manual y directo de inventario, ya sea por mermas, productos dañados, 
--   o diferencias encontradas en un conteo físico. 
--   Registra el movimiento en el kardex con su respectiva observación 
--   para dejar constancia formal del cambio y actualiza el stock final.


/* ============================================================================
   6. FUNCIONES DE CONSULTA / REPORTES (FUNCTION)
   ============================================================================ */

-- 9: Generar reporte de ventas en un periodo dado (RF20)
CREATE OR REPLACE FUNCTION fn_reporte_ventas_periodo(p_fecha_inicio DATE, p_fecha_fin DATE)
RETURNS TABLE (
    cantidad_ventas   BIGINT,
    total_ingresos    NUMERIC,
    total_costo       NUMERIC,
    utilidad_bruta    NUMERIC
)
LANGUAGE sql
AS $$
    SELECT
        COUNT(DISTINCT v.id),
        COALESCE(SUM(dv.cantidad * dv.precio_unitario - dv.descuento), 0),
        COALESCE(SUM(dv.cantidad * dv.costo_unitario_snapshot), 0),
        COALESCE(SUM(dv.cantidad * dv.precio_unitario - dv.descuento
                     - dv.cantidad * dv.costo_unitario_snapshot), 0)
    FROM venta v
    JOIN detalle_venta dv ON dv.venta_id = v.id
    WHERE v.estado <> 'ANULADA'
      AND v.fecha::date BETWEEN p_fecha_inicio AND p_fecha_fin;
$$;
-- Propósito: Genera un reporte resumido y consolidado de todas las ventas
--   realizadas dentro de un rango de fechas especificado por el usuario.
--   Calcula con exactitud los ingresos totales, los costos snapshot 
--   de la mercadería despachada y la utilidad bruta final del negocio.

-- 10: Obtener los productos más vendidos (RF21)
CREATE OR REPLACE FUNCTION fn_productos_mas_vendidos(
    p_fecha_inicio DATE,
    p_fecha_fin    DATE,
    p_limite       INT DEFAULT 10
)
RETURNS TABLE (
    sku               VARCHAR,
    producto          VARCHAR,
    unidades_vendidas BIGINT,
    total_vendido     NUMERIC
)
LANGUAGE sql
AS $$
    SELECT vp.sku, p.nombre, SUM(dv.cantidad), SUM(dv.cantidad * dv.precio_unitario - dv.descuento)
    FROM detalle_venta dv
    JOIN venta v ON v.id = dv.venta_id
    JOIN variante_producto vp ON vp.id = dv.variante_id
    JOIN producto p ON p.id = vp.producto_id
    WHERE v.estado <> 'ANULADA'
      AND v.fecha::date BETWEEN p_fecha_inicio AND p_fecha_fin
    GROUP BY vp.sku, p.nombre
    ORDER BY SUM(dv.cantidad) DESC
    LIMIT p_limite;
$$;
-- Propósito: Procesa las ventas para generar un ranking descendente de 
--   los productos con mayor rotación en un rango de fechas. 
--   Esta información es esencial para tomar decisiones estratégicas 
--   de reabastecimiento, identificar artículos estrella del catálogo 
--   y negociar mejores precios de compra por volumen con proveedores.

-- 11: Consultar alertas de stock bajo (RF9)
CREATE OR REPLACE FUNCTION fn_alertas_stock_bajo()
RETURNS TABLE (
    sku           VARCHAR,
    producto      VARCHAR,
    stock_actual  INT,
    stock_minimo  INT
)
LANGUAGE sql
AS $$
    SELECT vp.sku, p.nombre, vp.stock_actual, vp.stock_minimo
    FROM variante_producto vp
    JOIN producto p ON p.id = vp.producto_id
    WHERE vp.stock_actual <= vp.stock_minimo
      AND vp.estado = 'ACTIVO'
    ORDER BY vp.stock_actual ASC;
$$;
-- Propósito: Evalúa el catálogo de productos activos y retorna de forma 
--   inmediata una lista con aquellos cuyo stock actual sea menor o igual 
--   al stock mínimo configurado. 
--   Esta función actúa como base para los módulos de alertas preventivas, 
--   permitiendo reposiciones a tiempo y evitando pérdidas de ventas.

-- 12: Generar reporte de flujo de caja (RF22)
CREATE OR REPLACE FUNCTION fn_flujo_caja_periodo(p_fecha_inicio DATE, p_fecha_fin DATE)
RETURNS TABLE (concepto VARCHAR, total NUMERIC)
LANGUAGE sql
AS $$
    SELECT 'Ingresos por ventas'::VARCHAR,
           COALESCE(SUM(pv.monto), 0)
    FROM pago_venta pv
    JOIN venta v ON v.id = pv.venta_id
    WHERE v.estado <> 'ANULADA' AND pv.fecha::date BETWEEN p_fecha_inicio AND p_fecha_fin
    UNION ALL
    SELECT 'Gastos operativos'::VARCHAR,
           COALESCE(SUM(g.monto), 0)
    FROM gasto g
    WHERE g.fecha::date BETWEEN p_fecha_inicio AND p_fecha_fin
    UNION ALL
    SELECT 'Compras a proveedores'::VARCHAR,
           COALESCE(SUM(dc.cantidad * dc.costo_unitario), 0)
    FROM detalle_compra dc
    JOIN compra c ON c.id = dc.compra_id
    WHERE c.fecha_compra::date BETWEEN p_fecha_inicio AND p_fecha_fin;
$$;
-- Propósito: Construye una vista rápida y directa de la liquidez del negocio 
--   en un periodo determinado. 
--   Agrupa y totaliza por un lado los ingresos provenientes de las ventas, 
--   y por el otro, los egresos de compras a proveedores y gastos operativos, 
--   dando un estado financiero claro de los movimientos de efectivo reales.

-- 13: Consultar el estado de garantía de un producto vendido (RF13)
CREATE OR REPLACE FUNCTION fn_validar_garantia(p_numero_serie VARCHAR)
RETURNS TABLE (
    numero_venta       VARCHAR,
    producto           VARCHAR,
    fecha_venta        TIMESTAMP,
    fecha_fin_garantia DATE,
    vigente            BOOLEAN,
    estado_item        VARCHAR
)
LANGUAGE sql
AS $$
    SELECT v.numero_venta, p.nombre, v.fecha, dv.fecha_fin_garantia,
           (dv.fecha_fin_garantia >= CURRENT_DATE) AS vigente,
           dv.estado
    FROM detalle_venta dv
    JOIN venta v ON v.id = dv.venta_id
    JOIN variante_producto vp ON vp.id = dv.variante_id
    JOIN producto p ON p.id = vp.producto_id
    WHERE dv.numero_serie = p_numero_serie
    ORDER BY v.fecha DESC;
$$;
-- Propósito: Proporciona un mecanismo de búsqueda cruzada para localizar
--   rápidamente un producto mediante su número de serie físico.
--   Muestra los detalles de la venta original, la fecha exacta de expiración
--   y un booleano que certifica si la garantía sigue vigente o si ya venció, 
--   agilizando enormemente el servicio técnico frente al cliente.


/* ============================================================================
   7. TRIGGERS (DISPARADORES)
   ============================================================================ */

-- 14: Mantener fecha_actualizacion en producto y variante_producto
CREATE OR REPLACE FUNCTION fn_trg_set_fecha_actualizacion()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.fecha_actualizacion := CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_producto_set_fecha_actualizacion
BEFORE UPDATE ON producto
FOR EACH ROW EXECUTE FUNCTION fn_trg_set_fecha_actualizacion();

CREATE TRIGGER trg_variante_set_fecha_actualizacion
BEFORE UPDATE ON variante_producto
FOR EACH ROW EXECUTE FUNCTION fn_trg_set_fecha_actualizacion();
-- Propósito: Es una función disparadora utilitaria que automatiza la 
--   asignación de la fecha de actualización actual (timestamp).
--   Aplica a tablas principales como los productos y sus variantes, 
--   asegurando que cualquier `UPDATE` realizado deje un rastro cronológico 
--   sin necesidad de hacerlo manualmente desde la lógica del sistema.


-- 15: Kardex automático de compras (RF16, RF7)
CREATE OR REPLACE FUNCTION fn_trg_compra_generar_movimiento()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO movimiento_inventario (usuario_id, compra_id, tipo, fecha, referencia)
    VALUES (NEW.usuario_id, NEW.id, 'ENTRADA', NEW.fecha_compra, NEW.numero_compra);
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_compra_generar_movimiento
AFTER INSERT ON compra
FOR EACH ROW EXECUTE FUNCTION fn_trg_compra_generar_movimiento();

CREATE OR REPLACE FUNCTION fn_trg_detalle_compra_actualizar_stock()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_movimiento_id INT;
BEGIN
    SELECT id INTO v_movimiento_id FROM movimiento_inventario
    WHERE compra_id = NEW.compra_id
    ORDER BY id DESC LIMIT 1;

    INSERT INTO detalle_movimiento_inventario (movimiento_id, variante_id, cantidad, costo_unitario)
    VALUES (v_movimiento_id, NEW.variante_id, NEW.cantidad, NEW.costo_unitario);

    UPDATE variante_producto
    SET stock_actual = stock_actual + NEW.cantidad
    WHERE id = NEW.variante_id;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_detalle_compra_actualizar_stock
AFTER INSERT ON detalle_compra
FOR EACH ROW EXECUTE FUNCTION fn_trg_detalle_compra_actualizar_stock();
-- Propósito: Cada vez que se registra de forma exitosa una compra completa,
--   estos disparadores asumen el trabajo de generar automáticamente el
--   documento de ingreso en el kardex (movimiento de inventario) y 
--   actualizan progresivamente el stock sumando cada producto recibido.


-- 16: Kardex y control automático de ventas (RF10, RF7, RF8, RF13)
CREATE OR REPLACE FUNCTION fn_trg_venta_generar_movimiento()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    INSERT INTO movimiento_inventario (usuario_id, venta_id, tipo, fecha, referencia)
    VALUES (NEW.usuario_id, NEW.id, 'SALIDA', NEW.fecha, NEW.numero_venta);
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_venta_generar_movimiento
AFTER INSERT ON venta
FOR EACH ROW EXECUTE FUNCTION fn_trg_venta_generar_movimiento();

-- Valida stock disponible y calcula la fecha de fin de garantía antes de insertar
CREATE OR REPLACE FUNCTION fn_trg_detalle_venta_validar_y_garantia()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_stock_disponible INT;
    v_fecha_venta      TIMESTAMP;
BEGIN
    SELECT stock_actual INTO v_stock_disponible FROM variante_producto WHERE id = NEW.variante_id;

    IF v_stock_disponible IS NULL THEN
        RAISE EXCEPTION 'La variante de producto % no existe', NEW.variante_id;
    END IF;

    IF v_stock_disponible < NEW.cantidad THEN
        RAISE EXCEPTION 'Stock insuficiente para el producto (disponible: %, solicitado: %)',
            v_stock_disponible, NEW.cantidad;
    END IF;

    SELECT fecha INTO v_fecha_venta FROM venta WHERE id = NEW.venta_id;
    NEW.fecha_fin_garantia := (v_fecha_venta + (NEW.meses_garantia || ' months')::INTERVAL)::DATE;

    -- Guarda el costo vigente del producto para que los reportes de utilidad
    -- reflejen el costo real al momento de la venta (RF20)
    SELECT dc.costo_unitario INTO NEW.costo_unitario_snapshot
    FROM detalle_compra dc
    WHERE dc.variante_id = NEW.variante_id
    ORDER BY dc.id DESC LIMIT 1;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_detalle_venta_validar_y_garantia
BEFORE INSERT ON detalle_venta
FOR EACH ROW EXECUTE FUNCTION fn_trg_detalle_venta_validar_y_garantia();

-- Descuenta stock y registra el detalle del kardex después de insertar
CREATE OR REPLACE FUNCTION fn_trg_detalle_venta_actualizar_stock()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_movimiento_id INT;
BEGIN
    SELECT id INTO v_movimiento_id FROM movimiento_inventario
    WHERE venta_id = NEW.venta_id
    ORDER BY id DESC LIMIT 1;

    INSERT INTO detalle_movimiento_inventario (movimiento_id, variante_id, cantidad, costo_unitario)
    VALUES (v_movimiento_id, NEW.variante_id, NEW.cantidad, COALESCE(NEW.costo_unitario_snapshot, 0));

    UPDATE variante_producto
    SET stock_actual = stock_actual - NEW.cantidad
    WHERE id = NEW.variante_id;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_detalle_venta_actualizar_stock
AFTER INSERT ON detalle_venta
FOR EACH ROW EXECUTE FUNCTION fn_trg_detalle_venta_actualizar_stock();
-- Propósito: Conjunto de validaciones críticas previas a la venta.
--   Verifican en tiempo real que exista stock suficiente del producto,
--   calculan dinámicamente la fecha de expiración para su garantía,
--   y finalmente, asientan el movimiento de salida en el historial del kardex
--   descontando permanentemente la cantidad del inventario principal.


-- 17: Restaurar stock al anular una venta (RF13, devoluciones)
CREATE OR REPLACE FUNCTION fn_trg_venta_restaurar_stock_anulacion()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
DECLARE
    v_movimiento_id INT;
    r RECORD;
BEGIN
    IF NEW.estado = 'ANULADA' AND OLD.estado <> 'ANULADA' THEN
        INSERT INTO movimiento_inventario (usuario_id, venta_id, tipo, referencia, observacion)
        VALUES (NEW.anulado_por, NEW.id, 'AJUSTE', NEW.numero_venta,
                'Reingreso de stock por anulación de venta: ' || COALESCE(NEW.motivo_anulacion, ''))
        RETURNING id INTO v_movimiento_id;

        FOR r IN SELECT variante_id, cantidad FROM detalle_venta WHERE venta_id = NEW.id
        LOOP
            INSERT INTO detalle_movimiento_inventario (movimiento_id, variante_id, cantidad, costo_unitario)
            VALUES (v_movimiento_id, r.variante_id, r.cantidad, 0);

            UPDATE variante_producto
            SET stock_actual = stock_actual + r.cantidad
            WHERE id = r.variante_id;
        END LOOP;

        UPDATE detalle_venta SET estado = 'DEVUELTO' WHERE venta_id = NEW.id;
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_venta_restaurar_stock_anulacion
AFTER UPDATE ON venta
FOR EACH ROW EXECUTE FUNCTION fn_trg_venta_restaurar_stock_anulacion();
-- Propósito: Disparador que intercepta las actualizaciones de estado en
--   las ventas. Si detecta un cambio hacia "ANULADA", procede 
--   automáticamente a iterar por cada ítem de esa venta, devolviendo 
--   el stock reservado al almacén y registrando este ajuste compensatorio 
--   de tipo entrada de vuelta al sistema de kardex para auditorías.


-- 18: Auditoría de cambios de precio (RF5, P16)
CREATE OR REPLACE FUNCTION fn_trg_auditoria_cambio_precio()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.precio_venta IS DISTINCT FROM OLD.precio_venta THEN
        INSERT INTO bitacora_auditoria (modulo, accion, tipo_entidad, entidad_id,
                                         valor_anterior, valor_nuevo, motivo)
        VALUES ('Inventario', 'ACTUALIZAR_PRECIO', 'variante_producto', NEW.id,
                jsonb_build_object('precio_venta', OLD.precio_venta),
                jsonb_build_object('precio_venta', NEW.precio_venta),
                'Actualización de precio de venta');
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_auditoria_cambio_precio
AFTER UPDATE ON variante_producto
FOR EACH ROW EXECUTE FUNCTION fn_trg_auditoria_cambio_precio();
-- Propósito: Opera como un centinela de control de precios. 
--   Cada vez que se efectúa un `UPDATE` en los precios de las variantes, 
--   intercepta el cambio y registra en la bitácora de auditoría 
--   el precio antiguo versus el nuevo, permitiendo rastrear a futuro 
--   cualquier manipulación de los valores o errores humanos de digitación.


-- 19: Generar número de venta automático si no se especifica
CREATE SEQUENCE IF NOT EXISTS seq_numero_venta START 1;

CREATE OR REPLACE FUNCTION fn_trg_venta_generar_numero()
RETURNS TRIGGER
LANGUAGE plpgsql
AS $$
BEGIN
    IF NEW.numero_venta IS NULL THEN
        NEW.numero_venta := 'V-' || TO_CHAR(CURRENT_DATE, 'YYYY') || '-'
                             || LPAD(nextval('seq_numero_venta')::TEXT, 4, '0');
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_venta_generar_numero
BEFORE INSERT ON venta
FOR EACH ROW EXECUTE FUNCTION fn_trg_venta_generar_numero();
-- Propósito: Asegura que cada venta registrada siempre posea un
--   código correlativo único, inyectando un identificador con el
--   formato amigable "V-AÑO-####" en caso de que el sistema cliente 
--   haya omitido este dato, previniendo así errores de registros nulos.


/* ============================================================================
   2. POBLACIÓN DE DATOS (INSERT)
   ============================================================================ */

-- 2.1 Seguridad, roles y accesos ---
INSERT INTO rol (nombre, descripcion, es_sistema) VALUES
('Administrador', 'Acceso total al sistema: inventario, ventas, compras, clientes, proveedores, usuarios y reportes', TRUE),
('Vendedor', 'Acceso a ventas, consulta de productos, clientes y garantías', TRUE);
 
INSERT INTO permiso (clave, nombre, modulo) VALUES
('usuarios.administrar',  'Administrar usuarios y roles',        'Seguridad'),
('productos.gestionar',   'Gestionar productos y catálogo',      'Inventario'),
('inventario.ver',        'Consultar inventario',                'Inventario'),
('inventario.ajustar',    'Ajustar existencias manualmente',     'Inventario'),
('ventas.crear',          'Registrar ventas',                    'Ventas'),
('ventas.anular',         'Anular ventas',                       'Ventas'),
('clientes.gestionar',    'Gestionar clientes',                  'Ventas'),
('garantias.gestionar',   'Gestionar garantías y devoluciones',  'Ventas'),
('compras.crear',         'Registrar compras a proveedores',     'Compras'),
('proveedores.gestionar', 'Gestionar proveedores',               'Compras'),
('gastos.gestionar',      'Registrar ingresos y gastos',         'Finanzas'),
('reportes.ver',          'Consultar reportes financieros',      'Reportes');
 
-- Administrador: todos los permisos
INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT (SELECT id FROM rol WHERE nombre = 'Administrador'), id FROM permiso;
 
-- Vendedor: subconjunto operativo
INSERT INTO rol_permiso (rol_id, permiso_id)
SELECT (SELECT id FROM rol WHERE nombre = 'Vendedor'), id
FROM permiso
WHERE clave IN ('inventario.ver','ventas.crear','clientes.gestionar','garantias.gestionar');
 
-- 2.2 Personal y usuarios 
INSERT INTO personal (nombre_completo, ci, telefono, cargo) VALUES
('Ruben Morales',       '5896321 SC', '77712345', 'Gerente General / Propietario'),
('Maria Fernandez',     '6541230 SC', '76654321', 'Vendedora'),
('Carla Rojas Salvatierra', '8452201 SC', '70011223', 'Vendedora (soporte fines de semana)');
 
INSERT INTO usuario (personal_id, rol_id, email, password_hash) VALUES
((SELECT id FROM personal WHERE ci = '5896321 SC'),
 (SELECT id FROM rol WHERE nombre = 'Administrador'),
 'ruben.morales@computercity.com', '$2y$10$hashDeEjemploAdmin000000000000000000000000000'),
((SELECT id FROM personal WHERE ci = '6541230 SC'),
 (SELECT id FROM rol WHERE nombre = 'Vendedor'),
 'maria.fernandez@computercity.com', '$2y$10$hashDeEjemploVend0000000000000000000000000000'),
((SELECT id FROM personal WHERE ci = '8452201 SC'),
 (SELECT id FROM rol WHERE nombre = 'Vendedor'),
 'carla.rojas@computercity.com', '$2y$10$hashDeEjemploVend20000000000000000000000000000');
 
-- 2.3 Configuración de la tienda 
INSERT INTO configuracion_tienda (nombre_tienda, centro_comercial, direccion, telefono, whatsapp, email, moneda)
VALUES ('Computer City', 'Comercial Chiriguano', 'Comercial Chiriguano, Santa Cruz de la Sierra',
        '77712345', '77712345', 'contacto@computercity.com', 'BOB');
 
INSERT INTO horario_atencion (configuracion_tienda_id, dia_semana, hora_apertura, hora_cierre, cerrado)
SELECT (SELECT id FROM configuracion_tienda LIMIT 1), dia, '09:00', '19:00', FALSE
FROM generate_series(1,6) AS dia;              -- Lunes a Sábado
INSERT INTO horario_atencion (configuracion_tienda_id, dia_semana, cerrado)
VALUES ((SELECT id FROM configuracion_tienda LIMIT 1), 7, TRUE);  -- Domingo cerrado
 
-- 2.4 Catálogo: categorías y marcas -
INSERT INTO categoria (nombre, slug, descripcion) VALUES
('Mouse', 'mouse', 'Mouses alámbricos e inalámbricos'),
('Teclados', 'teclados', 'Teclados mecánicos y de membrana'),
('Audífonos', 'audifonos', 'Audífonos y diademas gamer'),
('Micrófonos', 'microfonos', 'Micrófonos para streaming y videollamadas'),
('Cámaras Web', 'camaras-web', 'Cámaras web para videollamadas y streaming'),
('Parlantes y Barras de Sonido', 'parlantes-barras-sonido', 'Parlantes, barras de sonido y equipos de audio'),
('Mousepads', 'mousepads', 'Mousepads y superficies para mouse'),
('Accesorios y Adaptadores', 'accesorios-adaptadores', 'Hubs, adaptadores, cargadores y accesorios varios');
 
INSERT INTO marca (nombre, slug) VALUES
('Logitech', 'logitech'),
('Redragon', 'redragon'),
('HyperX', 'hyperx'),
('JBL', 'jbl'),
('Genius', 'genius');
 
-- 2.5 Productos y variantes (20 productos/variantes) 
INSERT INTO producto (categoria_id, marca_id, nombre, slug, descripcion) VALUES
((SELECT id FROM categoria WHERE slug='mouse'), (SELECT id FROM marca WHERE slug='logitech'), 'Mouse Logitech G203', 'mouse-logitech-g203', 'Mouse Logitech G203'),
((SELECT id FROM categoria WHERE slug='mouse'), (SELECT id FROM marca WHERE slug='redragon'), 'Mouse Redragon Cobra M711', 'mouse-redragon-cobra-m711', 'Mouse Redragon Cobra M711'),
((SELECT id FROM categoria WHERE slug='mouse'), (SELECT id FROM marca WHERE slug='genius'), 'Mouse Genius DX-110', 'mouse-genius-dx-110', 'Mouse Genius DX-110'),
((SELECT id FROM categoria WHERE slug='teclados'), (SELECT id FROM marca WHERE slug='redragon'), 'Teclado Mecánico Redragon K552', 'teclado-mecanico-redragon-k552', 'Teclado Mecánico Redragon K552'),
((SELECT id FROM categoria WHERE slug='teclados'), (SELECT id FROM marca WHERE slug='logitech'), 'Teclado Logitech K120', 'teclado-logitech-k120', 'Teclado Logitech K120'),
((SELECT id FROM categoria WHERE slug='teclados'), (SELECT id FROM marca WHERE slug='hyperx'), 'Teclado HyperX Alloy Origins', 'teclado-hyperx-alloy-origins', 'Teclado HyperX Alloy Origins'),
((SELECT id FROM categoria WHERE slug='audifonos'), (SELECT id FROM marca WHERE slug='hyperx'), 'Audífonos HyperX Cloud Stinger', 'audifonos-hyperx-cloud-stinger', 'Audífonos HyperX Cloud Stinger'),
((SELECT id FROM categoria WHERE slug='audifonos'), (SELECT id FROM marca WHERE slug='logitech'), 'Audífonos Logitech H390', 'audifonos-logitech-h390', 'Audífonos Logitech H390'),
((SELECT id FROM categoria WHERE slug='audifonos'), (SELECT id FROM marca WHERE slug='jbl'), 'Audífonos JBL Tune 500', 'audifonos-jbl-tune-500', 'Audífonos JBL Tune 500'),
((SELECT id FROM categoria WHERE slug='microfonos'), (SELECT id FROM marca WHERE slug='genius'), 'Micrófono Genius MIC-01', 'microfono-genius-mic-01', 'Micrófono Genius MIC-01'),
((SELECT id FROM categoria WHERE slug='microfonos'), (SELECT id FROM marca WHERE slug='hyperx'), 'Micrófono HyperX SoloCast', 'microfono-hyperx-solocast', 'Micrófono HyperX SoloCast'),
((SELECT id FROM categoria WHERE slug='camaras-web'), (SELECT id FROM marca WHERE slug='logitech'), 'Cámara Web Logitech C920', 'camara-web-logitech-c920', 'Cámara Web Logitech C920'),
((SELECT id FROM categoria WHERE slug='camaras-web'), (SELECT id FROM marca WHERE slug='genius'), 'Cámara Web Genius FaceCam', 'camara-web-genius-facecam', 'Cámara Web Genius FaceCam'),
((SELECT id FROM categoria WHERE slug='parlantes-barras-sonido'), (SELECT id FROM marca WHERE slug='jbl'), 'Barra de Sonido JBL Bar 2.0', 'barra-de-sonido-jbl-bar-2-0', 'Barra de Sonido JBL Bar 2.0'),
((SELECT id FROM categoria WHERE slug='parlantes-barras-sonido'), (SELECT id FROM marca WHERE slug='logitech'), 'Parlante Logitech Z200', 'parlante-logitech-z200', 'Parlante Logitech Z200'),
((SELECT id FROM categoria WHERE slug='mousepads'), (SELECT id FROM marca WHERE slug='redragon'), 'Mousepad Redragon Archelon', 'mousepad-redragon-archelon', 'Mousepad Redragon Archelon'),
((SELECT id FROM categoria WHERE slug='accesorios-adaptadores'), (SELECT id FROM marca WHERE slug='genius'), 'Hub USB Genius GH-U', 'hub-usb-genius-gh-u', 'Hub USB Genius GH-U'),
((SELECT id FROM categoria WHERE slug='accesorios-adaptadores'), (SELECT id FROM marca WHERE slug='logitech'), 'Adaptador Bluetooth Logitech', 'adaptador-bluetooth-logitech', 'Adaptador Bluetooth Logitech'),
((SELECT id FROM categoria WHERE slug='accesorios-adaptadores'), (SELECT id FROM marca WHERE slug='redragon'), 'Cargador USB-C Redragon', 'cargador-usb-c-redragon', 'Cargador USB-C Redragon'),
((SELECT id FROM categoria WHERE slug='accesorios-adaptadores'), (SELECT id FROM marca WHERE slug='jbl'), 'Ring Light para Streaming JBL Stream', 'ring-light-para-streaming-jbl-stream', 'Ring Light para Streaming JBL Stream');
 
INSERT INTO variante_producto (producto_id, sku, codigo_barras, precio_venta, stock_minimo) VALUES
((SELECT id FROM producto WHERE slug='mouse-logitech-g203'), 'MOU-LOG-G203-NEG', '750123450011', 120.00, 5),
((SELECT id FROM producto WHERE slug='mouse-redragon-cobra-m711'), 'MOU-RED-COBRA-NEG', '750123450012', 95.00, 5),
((SELECT id FROM producto WHERE slug='mouse-genius-dx-110'), 'MOU-GEN-DX110-NEG', '750123450013', 60.00, 5),
((SELECT id FROM producto WHERE slug='teclado-mecanico-redragon-k552'), 'TEC-RED-K552-NEG', '750123450014', 260.00, 3),
((SELECT id FROM producto WHERE slug='teclado-logitech-k120'), 'TEC-LOG-K120-NEG', '750123450015', 90.00, 4),
((SELECT id FROM producto WHERE slug='teclado-hyperx-alloy-origins'), 'TEC-HYX-ALORG-NEG', '750123450016', 620.00, 2),
((SELECT id FROM producto WHERE slug='audifonos-hyperx-cloud-stinger'), 'AUD-HYX-CSTG-NEG', '750123450017', 350.00, 4),
((SELECT id FROM producto WHERE slug='audifonos-logitech-h390'), 'AUD-LOG-H390-NEG', '750123450018', 150.00, 4),
((SELECT id FROM producto WHERE slug='audifonos-jbl-tune-500'), 'AUD-JBL-T500-NEG', '750123450019', 210.00, 3),
((SELECT id FROM producto WHERE slug='microfono-genius-mic-01'), 'MIC-GEN-MIC01-NEG', '750123450020', 180.00, 3),
((SELECT id FROM producto WHERE slug='microfono-hyperx-solocast'), 'MIC-HYX-SOLO-NEG', '750123450021', 380.00, 2),
((SELECT id FROM producto WHERE slug='camara-web-logitech-c920'), 'CAM-LOG-C920-NEG', '750123450022', 480.00, 3),
((SELECT id FROM producto WHERE slug='camara-web-genius-facecam'), 'CAM-GEN-FACE-NEG', '750123450023', 210.00, 3),
((SELECT id FROM producto WHERE slug='barra-de-sonido-jbl-bar-2-0'), 'PAR-JBL-BAR20-NEG', '750123450024', 690.00, 2),
((SELECT id FROM producto WHERE slug='parlante-logitech-z200'), 'PAR-LOG-Z200-NEG', '750123450025', 160.00, 3),
((SELECT id FROM producto WHERE slug='mousepad-redragon-archelon'), 'MSP-RED-ARCH-NEG', '750123450026', 85.00, 5),
((SELECT id FROM producto WHERE slug='hub-usb-genius-gh-u'), 'HUB-GEN-GHU-NEG', '750123450027', 70.00, 5),
((SELECT id FROM producto WHERE slug='adaptador-bluetooth-logitech'), 'ADP-LOG-BT01-NEG', '750123450028', 55.00, 5),
((SELECT id FROM producto WHERE slug='cargador-usb-c-redragon'), 'CAR-RED-USBC-NEG', '750123450029', 65.00, 5),
((SELECT id FROM producto WHERE slug='ring-light-para-streaming-jbl-stream'), 'ACC-JBL-RING-NEG', '750123450030', 140.00, 4);
 
-- 2.5b Atributos y valores (color / conectividad) para algunas variantes 
INSERT INTO atributo (nombre) VALUES ('Color'), ('Conectividad');
 
INSERT INTO valor_atributo (atributo_id, valor) VALUES
((SELECT id FROM atributo WHERE nombre='Color'), 'Negro'),
((SELECT id FROM atributo WHERE nombre='Color'), 'Blanco'),
((SELECT id FROM atributo WHERE nombre='Conectividad'), 'USB Alámbrico'),
((SELECT id FROM atributo WHERE nombre='Conectividad'), 'Bluetooth');
 
INSERT INTO variante_valor_atributo (variante_id, valor_atributo_id) VALUES
((SELECT id FROM variante_producto WHERE sku='MOU-LOG-G203-NEG'), (SELECT id FROM valor_atributo WHERE valor='Negro')),
((SELECT id FROM variante_producto WHERE sku='MOU-LOG-G203-NEG'), (SELECT id FROM valor_atributo WHERE valor='USB Alámbrico')),
((SELECT id FROM variante_producto WHERE sku='AUD-HYX-CSTG-NEG'), (SELECT id FROM valor_atributo WHERE valor='Negro')),
((SELECT id FROM variante_producto WHERE sku='ADP-LOG-BT01-NEG'), (SELECT id FROM valor_atributo WHERE valor='Bluetooth'));
 
-- 2.6 Proveedores y métodos de pago (10 proveedores) 
INSERT INTO proveedor (nit, razon_social, nombre_comercial, contacto, telefono, email) VALUES
('1023456011', 'Tecno Import S.R.L.', 'Tecno Import', 'Jorge Salvatierra', '70123456', 'ventas@tecnoimport.com'),
('1023456022', 'Global Compu Parts S.A.', 'Global Compu', 'Ana Aguilar', '70223344', 'contacto@globalcompu.com'),
('1023456033', 'Distribuidora ABC', 'ABC Perifericos', 'Luis Vargas', '70334455', 'abc@distribuidora.com'),
('1023456044', 'ImportTech Bolivia', 'ImportTech', 'Diego Suarez', '70445566', 'info@importtech.bo'),
('1023456055', 'Comercial Andina SRL', 'Andina Tech', 'Paola Melgar', '70556677', 'ventas@andinatech.com'),
('1023456066', 'PC Wholesale Bolivia', 'PC Wholesale', 'Ivan Rocha', '70667788', 'ventas@pcwholesale.bo'),
('1023456077', 'Perifericos del Oriente', 'Perif. Oriente', 'Marcela Peña', '70778899', 'contacto@perifericosoriente.com'),
('1023456088', 'Santa Cruz Digital Import', 'SC Digital', 'Fabian Cuellar', '70889900', 'ventas@scdigital.bo'),
('1023456099', 'Redes y Perifericos SRL', 'Redes y Perif.', 'Tania Justiniano', '70990011', 'info@redesyperifericos.com'),
('1023456100', 'TecnoBol Distribuciones', 'TecnoBol', 'Oscar Antelo', '70001122', 'ventas@tecnobol.com');
 
INSERT INTO metodo_pago (codigo, nombre) VALUES
('EFECTIVO', 'Efectivo'),
('QR', 'Pago con QR'),
('TRANSFERENCIA', 'Transferencia bancaria'),
('COMBINADO', 'Pago combinado');
 
-- 2.7 Categorías de gasto y gastos (10 gastos) --
INSERT INTO categoria_gasto (nombre, descripcion) VALUES
('Alquiler', 'Alquiler mensual del local en el comercial Chiriguano'),
('Servicios Básicos', 'Luz, agua, internet'),
('Publicidad', 'Publicidad y redes sociales'),
('Viáticos', 'Movilidad y gastos operativos varios');
 
INSERT INTO gasto (categoria_gasto_id, metodo_pago_id, usuario_id, fecha, concepto, monto) VALUES
((SELECT id FROM categoria_gasto WHERE nombre='Alquiler'), (SELECT id FROM metodo_pago WHERE codigo='TRANSFERENCIA'),
 (SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), CURRENT_DATE - INTERVAL '5 days', 'Alquiler del mes', 1500.00),
((SELECT id FROM categoria_gasto WHERE nombre='Servicios Básicos'), (SELECT id FROM metodo_pago WHERE codigo='EFECTIVO'),
 (SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), CURRENT_DATE - INTERVAL '3 days', 'Pago de internet y luz', 320.00),
((SELECT id FROM categoria_gasto WHERE nombre='Publicidad'), (SELECT id FROM metodo_pago WHERE codigo='QR'),
 (SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), CURRENT_DATE - INTERVAL '12 days', 'Publicidad en redes sociales', 250.00),
((SELECT id FROM categoria_gasto WHERE nombre='Viáticos'), (SELECT id FROM metodo_pago WHERE codigo='EFECTIVO'),
 (SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), CURRENT_DATE - INTERVAL '9 days', 'Movilidad para recojo de mercadería', 80.00),
((SELECT id FROM categoria_gasto WHERE nombre='Alquiler'), (SELECT id FROM metodo_pago WHERE codigo='TRANSFERENCIA'),
 (SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), CURRENT_DATE - INTERVAL '35 days', 'Alquiler del mes anterior', 1500.00),
((SELECT id FROM categoria_gasto WHERE nombre='Servicios Básicos'), (SELECT id FROM metodo_pago WHERE codigo='EFECTIVO'),
 (SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), CURRENT_DATE - INTERVAL '20 days', 'Pago de agua', 60.00),
((SELECT id FROM categoria_gasto WHERE nombre='Publicidad'), (SELECT id FROM metodo_pago WHERE codigo='QR'),
 (SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), CURRENT_DATE - INTERVAL '18 days', 'Impresión de catálogo físico', 150.00),
((SELECT id FROM categoria_gasto WHERE nombre='Viáticos'), (SELECT id FROM metodo_pago WHERE codigo='EFECTIVO'),
 (SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), CURRENT_DATE - INTERVAL '6 days', 'Combustible para entregas', 120.00),
((SELECT id FROM categoria_gasto WHERE nombre='Servicios Básicos'), (SELECT id FROM metodo_pago WHERE codigo='TRANSFERENCIA'),
 (SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), CURRENT_DATE - INTERVAL '27 days', 'Plan de datos móvil del negocio', 90.00),
((SELECT id FROM categoria_gasto WHERE nombre='Publicidad'), (SELECT id FROM metodo_pago WHERE codigo='EFECTIVO'),
 (SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), CURRENT_DATE - INTERVAL '2 days', 'Impulso de publicaciones en Facebook', 100.00);
 
INSERT INTO movimiento_caja (usuario_id, tipo, categoria, monto, descripcion) VALUES
((SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), 'INGRESO', 'Aporte de capital', 3000.00, 'Aporte inicial para compra de mercadería'),
((SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), 'EGRESO',  'Gastos operativos', 3720.00, 'Pago acumulado de alquiler y servicios básicos');
 
-- 2.8 Clientes (20 personas + 2 empresas + Cliente Final) -
-- Cliente genérico para ventas de mostrador sin datos completos
INSERT INTO cliente (tipo_documento, numero_documento, nombre) VALUES
('S/N', 'CF-0000', 'Cliente Final');
 
INSERT INTO cliente (tipo_documento, numero_documento, nombre, telefono, email) VALUES
('CI', '4521230 SC', 'Juan Perez Rojas', '76541230', 'juanperez@gmail.com'),
('CI', '7845201 SC', 'Andrea Gutierrez Vaca', '77896541', 'andrea.gutierrez@gmail.com'),
('CI', '3312045 SC', 'Carlos Mendez Ortiz', '70112233', 'carlos.mendez@gmail.com'),
('CI', '5589012 SC', 'Fernanda Justiniano Rios', '76223344', 'fernanda.justiniano@gmail.com'),
('CI', '6023341 SC', 'Miguel Angel Vargas', '70334455', 'miguel.vargas@gmail.com'),
('CI', '7712233 SC', 'Daniela Suarez Peña', '77445566', 'daniela.suarez@gmail.com'),
('CI', '8894455 SC', 'Rodrigo Alba Cuellar', '70556677', 'rodrigo.alba@gmail.com'),
('CI', '9021156 SC', 'Camila Rocha Salvatierra', '76667788', 'camila.rocha@gmail.com'),
('CI', '4456712 SC', 'Sergio Paz Gutierrez', '70778899', 'sergio.paz@gmail.com'),
('CI', '5567823 SC', 'Valeria Antelo Melgar', '77889900', 'valeria.antelo@gmail.com'),
('CI', '6678934 SC', 'Diego Rivero Suarez', '70990011', 'diego.rivero@gmail.com'),
('CI', '7789045 SC', 'Paola Justiniano Vaca', '76001122', 'paola.justiniano@gmail.com'),
('CI', '8890156 SC', 'Alejandro Salvatierra Melgar', '70112244', 'alejandro.salvatierra@gmail.com'),
('CI', '9901267 SC', 'Gabriela Peña Rocha', '77223355', 'gabriela.pena@gmail.com'),
('CI', '4012378 SC', 'Raul Antelo Cuellar', '70334466', 'raul.antelo@gmail.com'),
('CI', '5123489 SC', 'Lucia Vaca Aguilar', '76445577', 'lucia.vaca@gmail.com'),
('CI', '6234590 SC', 'Marcelo Ortiz Rivero', '70556688', 'marcelo.ortiz@gmail.com'),
('CI', '7345601 SC', 'Ximena Melgar Justiniano', '77667799', 'ximena.melgar@gmail.com'),
('CI', '8456712 SC', 'Hugo Cuellar Salvatierra', '70778800', 'hugo.cuellar@gmail.com'),
('CI', '9567823 SC', 'Renata Aguilar Vargas', '76889911', 'renata.aguilar@gmail.com');
 
INSERT INTO cliente (tipo_documento, numero_documento, nombre, razon_social, telefono, email) VALUES
('NIT', '1099887766', 'Cyber Cabinas Central', 'Cyber Cabinas Central S.R.L.', '70998877', 'compras@cybercentral.com'),
('NIT', '1099887777', 'Internet Point Norte', 'Internet Point Norte S.R.L.', '70998878', 'compras@ipnorte.com');
-- 2.9 Ajuste inicial de inventario (saldo de apertura, vía kardex tipo AJUSTE) --
INSERT INTO movimiento_inventario (usuario_id, tipo, fecha, referencia, observacion)
VALUES ((SELECT id FROM usuario WHERE email='ruben.morales@computercity.com'), 'AJUSTE', CURRENT_DATE - INTERVAL '15 days',
        'AJUSTE-INICIAL-001', 'Carga de saldo inicial de inventario al implementar el sistema');
 
INSERT INTO detalle_movimiento_inventario (movimiento_id, variante_id, cantidad, costo_unitario)
SELECT (SELECT id FROM movimiento_inventario WHERE referencia='AJUSTE-INICIAL-001'), v.id, saldo.cantidad, saldo.costo
FROM (VALUES
    ('MOU-LOG-G203-NEG', 15, 75.00),
    ('MOU-RED-COBRA-NEG', 12, 55.00),
    ('MOU-GEN-DX110-NEG', 10, 35.00),
    ('TEC-RED-K552-NEG', 8, 170.00),
    ('TEC-LOG-K120-NEG', 10, 55.00),
    ('TEC-HYX-ALORG-NEG', 5, 430.00),
    ('AUD-HYX-CSTG-NEG', 10, 230.00),
    ('AUD-LOG-H390-NEG', 8, 95.00),
    ('AUD-JBL-T500-NEG', 8, 140.00),
    ('MIC-GEN-MIC01-NEG', 6, 110.00),
    ('MIC-HYX-SOLO-NEG', 5, 260.00),
    ('CAM-LOG-C920-NEG', 5, 320.00),
    ('CAM-GEN-FACE-NEG', 6, 140.00),
    ('PAR-JBL-BAR20-NEG', 3, 450.00),
    ('PAR-LOG-Z200-NEG', 8, 100.00),
    ('MSP-RED-ARCH-NEG', 12, 50.00),
    ('HUB-GEN-GHU-NEG', 10, 40.00),
    ('ADP-LOG-BT01-NEG', 10, 30.00),
    ('CAR-RED-USBC-NEG', 10, 38.00),
    ('ACC-JBL-RING-NEG', 6, 90.00)
) AS saldo(sku, cantidad, costo)
JOIN variante_producto v ON v.sku = saldo.sku;
 
UPDATE variante_producto vp
SET stock_actual = vp.stock_actual + saldo.cantidad
FROM (VALUES
    ('MOU-LOG-G203-NEG', 15),
    ('MOU-RED-COBRA-NEG', 12),
    ('MOU-GEN-DX110-NEG', 10),
    ('TEC-RED-K552-NEG', 8),
    ('TEC-LOG-K120-NEG', 10),
    ('TEC-HYX-ALORG-NEG', 5),
    ('AUD-HYX-CSTG-NEG', 10),
    ('AUD-LOG-H390-NEG', 8),
    ('AUD-JBL-T500-NEG', 8),
    ('MIC-GEN-MIC01-NEG', 6),
    ('MIC-HYX-SOLO-NEG', 5),
    ('CAM-LOG-C920-NEG', 5),
    ('CAM-GEN-FACE-NEG', 6),
    ('PAR-JBL-BAR20-NEG', 3),
    ('PAR-LOG-Z200-NEG', 8),
    ('MSP-RED-ARCH-NEG', 12),
    ('HUB-GEN-GHU-NEG', 10),
    ('ADP-LOG-BT01-NEG', 10),
    ('CAR-RED-USBC-NEG', 10),
    ('ACC-JBL-RING-NEG', 6)
) AS saldo(sku, cantidad)
WHERE vp.sku = saldo.sku;
 
-- 2.10 Compras a proveedores (8 compras vía procedimiento) 
 
DO $$
DECLARE
    v_proveedor_id   INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_proveedor_id   FROM proveedor WHERE nit = '1023456011';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'ruben.morales@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_compra(
        p_proveedor_id   => v_proveedor_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_compra  => 'COM-2026-0001',
        p_fecha_compra   => CURRENT_DATE - INTERVAL '13 days',
        p_items          => '[
            {"sku":"MOU-LOG-G203-NEG","cantidad":10,"costo_unitario":78.00},
            {"sku":"TEC-RED-K552-NEG","cantidad":5,"costo_unitario":169.00}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_proveedor_id   INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_proveedor_id   FROM proveedor WHERE nit = '1023456022';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'ruben.morales@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_compra(
        p_proveedor_id   => v_proveedor_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_compra  => 'COM-2026-0002',
        p_fecha_compra   => CURRENT_DATE - INTERVAL '12 days',
        p_items          => '[
            {"sku":"AUD-HYX-CSTG-NEG","cantidad":6,"costo_unitario":227.50},
            {"sku":"MIC-GEN-MIC01-NEG","cantidad":4,"costo_unitario":117.00}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_proveedor_id   INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_proveedor_id   FROM proveedor WHERE nit = '1023456033';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'ruben.morales@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_compra(
        p_proveedor_id   => v_proveedor_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_compra  => 'COM-2026-0003',
        p_fecha_compra   => CURRENT_DATE - INTERVAL '11 days',
        p_items          => '[
            {"sku":"CAM-LOG-C920-NEG","cantidad":4,"costo_unitario":312.00},
            {"sku":"CAM-GEN-FACE-NEG","cantidad":5,"costo_unitario":136.50}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_proveedor_id   INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_proveedor_id   FROM proveedor WHERE nit = '1023456044';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'ruben.morales@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_compra(
        p_proveedor_id   => v_proveedor_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_compra  => 'COM-2026-0004',
        p_fecha_compra   => CURRENT_DATE - INTERVAL '10 days',
        p_items          => '[
            {"sku":"PAR-JBL-BAR20-NEG","cantidad":3,"costo_unitario":448.50},
            {"sku":"PAR-LOG-Z200-NEG","cantidad":6,"costo_unitario":104.00}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_proveedor_id   INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_proveedor_id   FROM proveedor WHERE nit = '1023456055';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'ruben.morales@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_compra(
        p_proveedor_id   => v_proveedor_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_compra  => 'COM-2026-0005',
        p_fecha_compra   => CURRENT_DATE - INTERVAL '9 days',
        p_items          => '[
            {"sku":"MOU-RED-COBRA-NEG","cantidad":8,"costo_unitario":61.75},
            {"sku":"MOU-GEN-DX110-NEG","cantidad":8,"costo_unitario":39.00}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_proveedor_id   INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_proveedor_id   FROM proveedor WHERE nit = '1023456066';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'ruben.morales@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_compra(
        p_proveedor_id   => v_proveedor_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_compra  => 'COM-2026-0006',
        p_fecha_compra   => CURRENT_DATE - INTERVAL '8 days',
        p_items          => '[
            {"sku":"TEC-LOG-K120-NEG","cantidad":6,"costo_unitario":58.50},
            {"sku":"TEC-HYX-ALORG-NEG","cantidad":4,"costo_unitario":403.00}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_proveedor_id   INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_proveedor_id   FROM proveedor WHERE nit = '1023456077';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'ruben.morales@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_compra(
        p_proveedor_id   => v_proveedor_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_compra  => 'COM-2026-0007',
        p_fecha_compra   => CURRENT_DATE - INTERVAL '7 days',
        p_items          => '[
            {"sku":"MSP-RED-ARCH-NEG","cantidad":10,"costo_unitario":55.25},
            {"sku":"HUB-GEN-GHU-NEG","cantidad":8,"costo_unitario":45.50},
            {"sku":"ADP-LOG-BT01-NEG","cantidad":8,"costo_unitario":35.75}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_proveedor_id   INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_proveedor_id   FROM proveedor WHERE nit = '1023456088';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'ruben.morales@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_compra(
        p_proveedor_id   => v_proveedor_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_compra  => 'COM-2026-0008',
        p_fecha_compra   => CURRENT_DATE - INTERVAL '6 days',
        p_items          => '[
            {"sku":"CAR-RED-USBC-NEG","cantidad":8,"costo_unitario":42.25},
            {"sku":"ACC-JBL-RING-NEG","cantidad":5,"costo_unitario":91.00},
            {"sku":"MIC-HYX-SOLO-NEG","cantidad":4,"costo_unitario":247.00}
        ]'::jsonb
    );
END;
$$;
 
-- 2.11 Ventas registradas a través del procedimiento (20 ventas, genera kardex y garantía) --
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '4521230 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'carla.rojas@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'EFECTIVO';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0001',
        p_items          => '[
            {"sku":"MOU-LOG-G203-NEG","cantidad":1,"precio_unitario":120.00,"numero_serie":"0001A","meses_garantia":3},
            {"sku":"MOU-RED-COBRA-NEG","cantidad":1,"precio_unitario":95.00,"numero_serie":"0001B","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '7845201 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'maria.fernandez@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0002',
        p_items          => '[
            {"sku":"MOU-GEN-DX110-NEG","cantidad":1,"precio_unitario":60.00,"numero_serie":"0002A","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '3312045 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'carla.rojas@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'COMBINADO';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0003',
        p_items          => '[
            {"sku":"TEC-RED-K552-NEG","cantidad":1,"precio_unitario":260.00,"numero_serie":"0003A","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '5589012 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'maria.fernandez@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'QR';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0004',
        p_items          => '[
            {"sku":"TEC-LOG-K120-NEG","cantidad":1,"precio_unitario":90.00,"numero_serie":"0004A","meses_garantia":3},
            {"sku":"TEC-HYX-ALORG-NEG","cantidad":1,"precio_unitario":620.00,"numero_serie":"0004B","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '6023341 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'carla.rojas@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'EFECTIVO';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0005',
        p_items          => '[
            {"sku":"AUD-HYX-CSTG-NEG","cantidad":1,"precio_unitario":350.00,"numero_serie":"0005A","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '7712233 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'maria.fernandez@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0006',
        p_items          => '[
            {"sku":"AUD-LOG-H390-NEG","cantidad":1,"precio_unitario":150.00,"numero_serie":"0006A","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '8894455 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'carla.rojas@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'COMBINADO';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0007',
        p_items          => '[
            {"sku":"AUD-JBL-T500-NEG","cantidad":1,"precio_unitario":210.00,"numero_serie":"0007A","meses_garantia":3},
            {"sku":"MIC-GEN-MIC01-NEG","cantidad":1,"precio_unitario":180.00,"numero_serie":"0007B","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '9021156 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'maria.fernandez@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'QR';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0008',
        p_items          => '[
            {"sku":"MIC-HYX-SOLO-NEG","cantidad":1,"precio_unitario":380.00,"numero_serie":"0008A","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '4456712 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'carla.rojas@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'EFECTIVO';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0009',
        p_items          => '[
            {"sku":"CAM-LOG-C920-NEG","cantidad":1,"precio_unitario":480.00,"numero_serie":"0009A","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '5567823 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'maria.fernandez@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0010',
        p_items          => '[
            {"sku":"CAM-GEN-FACE-NEG","cantidad":1,"precio_unitario":210.00,"numero_serie":"000AA","meses_garantia":3},
            {"sku":"PAR-JBL-BAR20-NEG","cantidad":1,"precio_unitario":690.00,"numero_serie":"000AB","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '6678934 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'carla.rojas@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'COMBINADO';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0011',
        p_items          => '[
            {"sku":"PAR-LOG-Z200-NEG","cantidad":1,"precio_unitario":160.00,"numero_serie":"000BA","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '7789045 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'maria.fernandez@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'QR';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0012',
        p_items          => '[
            {"sku":"MSP-RED-ARCH-NEG","cantidad":1,"precio_unitario":85.00,"numero_serie":"000CA","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '8890156 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'carla.rojas@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'EFECTIVO';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0013',
        p_items          => '[
            {"sku":"HUB-GEN-GHU-NEG","cantidad":1,"precio_unitario":70.00,"numero_serie":"000DA","meses_garantia":3},
            {"sku":"ADP-LOG-BT01-NEG","cantidad":1,"precio_unitario":55.00,"numero_serie":"000DB","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '9901267 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'maria.fernandez@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0014',
        p_items          => '[
            {"sku":"CAR-RED-USBC-NEG","cantidad":1,"precio_unitario":65.00,"numero_serie":"000EA","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '4012378 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'carla.rojas@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'COMBINADO';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0015',
        p_items          => '[
            {"sku":"ACC-JBL-RING-NEG","cantidad":1,"precio_unitario":140.00,"numero_serie":"000FA","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '5123489 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'maria.fernandez@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'QR';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0016',
        p_items          => '[
            {"sku":"MOU-LOG-G203-NEG","cantidad":1,"precio_unitario":120.00,"numero_serie":"0010A","meses_garantia":3},
            {"sku":"MOU-RED-COBRA-NEG","cantidad":1,"precio_unitario":95.00,"numero_serie":"0010B","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '6234590 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'carla.rojas@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'EFECTIVO';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0017',
        p_items          => '[
            {"sku":"MOU-GEN-DX110-NEG","cantidad":1,"precio_unitario":60.00,"numero_serie":"0011A","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '7345601 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'maria.fernandez@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'TRANSFERENCIA';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0018',
        p_items          => '[
            {"sku":"TEC-RED-K552-NEG","cantidad":1,"precio_unitario":260.00,"numero_serie":"0012A","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '8456712 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'carla.rojas@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'COMBINADO';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0019',
        p_items          => '[
            {"sku":"TEC-LOG-K120-NEG","cantidad":1,"precio_unitario":90.00,"numero_serie":"0013A","meses_garantia":3},
            {"sku":"TEC-HYX-ALORG-NEG","cantidad":1,"precio_unitario":620.00,"numero_serie":"0013B","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;
 
DO $$
DECLARE
    v_cliente_id     INT;
    v_usuario_id     INT;
    v_metodo_pago_id INT;
BEGIN
    SELECT id INTO v_cliente_id     FROM cliente WHERE numero_documento = '9567823 SC';
    SELECT id INTO v_usuario_id     FROM usuario WHERE email = 'maria.fernandez@computercity.com';
    SELECT id INTO v_metodo_pago_id FROM metodo_pago WHERE codigo = 'QR';
 
    CALL sp_registrar_venta(
        p_cliente_id     => v_cliente_id,
        p_usuario_id     => v_usuario_id,
        p_metodo_pago_id => v_metodo_pago_id,
        p_numero_venta   => 'V-2026-0020',
        p_items          => '[
            {"sku":"AUD-HYX-CSTG-NEG","cantidad":1,"precio_unitario":350.00,"numero_serie":"0014A","meses_garantia":3}
        ]'::jsonb
    );
END;
$$;

-- ============================================================================
-- 2.12 Datos adicionales para pruebas de consultas específicas 
-- ============================================================================

-- Para Consulta 4: Bitácora de auditoría de los últimos 7 días
-- y Consulta 18: Auditoría de ventas anuladas con usuario responsable
DO $$
DECLARE
    v_usuario_id INT;
BEGIN
    SELECT id INTO v_usuario_id FROM usuario WHERE email='ruben.morales@computercity.com';

    -- Bitácora (Generamos 7 registros actualizando precios)
    CALL sp_actualizar_precio_variante('MOU-LOG-G203-NEG', 125.00, v_usuario_id);
    CALL sp_actualizar_precio_variante('MOU-RED-COBRA-NEG', 98.00, v_usuario_id);
    CALL sp_actualizar_precio_variante('MOU-GEN-DX110-NEG', 65.00, v_usuario_id);
    CALL sp_actualizar_precio_variante('TEC-RED-K552-NEG', 265.00, v_usuario_id);
    CALL sp_actualizar_precio_variante('TEC-LOG-K120-NEG', 95.00, v_usuario_id);
    CALL sp_actualizar_precio_variante('TEC-HYX-ALORG-NEG', 630.00, v_usuario_id);
    CALL sp_actualizar_precio_variante('AUD-HYX-CSTG-NEG', 360.00, v_usuario_id);

    -- Anulación (Anulamos 6 ventas para tener 6 registros)
    CALL sp_anular_venta('V-2026-0015', v_usuario_id, 'El cliente no contaba con fondos');
    CALL sp_anular_venta('V-2026-0016', v_usuario_id, 'Error en el sistema de cobro');
    CALL sp_anular_venta('V-2026-0017', v_usuario_id, 'Producto dañado en exhibición');
    CALL sp_anular_venta('V-2026-0018', v_usuario_id, 'Duplicidad de factura');
    CALL sp_anular_venta('V-2026-0019', v_usuario_id, 'Cliente desistió de la compra');
    CALL sp_anular_venta('V-2026-0020', v_usuario_id, 'El cliente canceló el pedido antes del despacho');
END;
$$;

-- Para Consulta 19: Pedidos de clientes con detalle de artículos (Agregamos 6 pedidos, sumando 8 detalles en total)
INSERT INTO pedido (cliente_id, numero_pedido, nombre_contacto, telefono_contacto, metodo_entrega, direccion_entrega, estado, origen) VALUES
((SELECT id FROM cliente WHERE numero_documento = '4521230 SC'), 'PED-2026-0001', 'Juan Perez Rojas', '76541230', 'DELIVERY', 'Av. Banzer 4to Anillo', 'PENDIENTE', 'WEB'),
((SELECT id FROM cliente WHERE numero_documento = '7845201 SC'), 'PED-2026-0002', 'Andrea Gutierrez', '77896541', 'TIENDA', 'Recojo en sucursal', 'CONFIRMADO', 'WHATSAPP'),
((SELECT id FROM cliente WHERE numero_documento = '3312045 SC'), 'PED-2026-0003', 'Carlos Mendez', '70112233', 'DELIVERY', 'Plan 3000', 'EN CAMINO', 'WEB'),
((SELECT id FROM cliente WHERE numero_documento = '5589012 SC'), 'PED-2026-0004', 'Fernanda Justiniano', '76223344', 'TIENDA', 'Recojo en sucursal', 'PENDIENTE', 'FACEBOOK'),
((SELECT id FROM cliente WHERE numero_documento = '6023341 SC'), 'PED-2026-0005', 'Miguel Vargas', '70334455', 'DELIVERY', 'Equipetrol', 'CONFIRMADO', 'WEB'),
((SELECT id FROM cliente WHERE numero_documento = '7712233 SC'), 'PED-2026-0006', 'Daniela Suarez', '77445566', 'DELIVERY', 'Zona Sur', 'PENDIENTE', 'WHATSAPP');

INSERT INTO detalle_pedido (pedido_id, variante_id, cantidad, precio_unitario) VALUES
((SELECT id FROM pedido WHERE numero_pedido = 'PED-2026-0001'), (SELECT id FROM variante_producto WHERE sku = 'TEC-RED-K552-NEG'), 1, 260.00),
((SELECT id FROM pedido WHERE numero_pedido = 'PED-2026-0001'), (SELECT id FROM variante_producto WHERE sku = 'MOU-RED-COBRA-NEG'), 1, 95.00),
((SELECT id FROM pedido WHERE numero_pedido = 'PED-2026-0002'), (SELECT id FROM variante_producto WHERE sku = 'AUD-LOG-H390-NEG'), 2, 150.00),
((SELECT id FROM pedido WHERE numero_pedido = 'PED-2026-0003'), (SELECT id FROM variante_producto WHERE sku = 'CAM-LOG-C920-NEG'), 1, 480.00),
((SELECT id FROM pedido WHERE numero_pedido = 'PED-2026-0004'), (SELECT id FROM variante_producto WHERE sku = 'PAR-JBL-BAR20-NEG'), 1, 690.00),
((SELECT id FROM pedido WHERE numero_pedido = 'PED-2026-0004'), (SELECT id FROM variante_producto WHERE sku = 'ACC-JBL-RING-NEG'), 1, 140.00),
((SELECT id FROM pedido WHERE numero_pedido = 'PED-2026-0005'), (SELECT id FROM variante_producto WHERE sku = 'MIC-HYX-SOLO-NEG'), 1, 380.00),
((SELECT id FROM pedido WHERE numero_pedido = 'PED-2026-0006'), (SELECT id FROM variante_producto WHERE sku = 'TEC-LOG-K120-NEG'), 3, 90.00);

-- Para Consulta 22: Inventario inmovilizado (Productos sin historial de ventas, agregamos 6 productos en total)
INSERT INTO producto (categoria_id, marca_id, nombre, slug, descripcion) VALUES
((SELECT id FROM categoria WHERE slug='mouse'), (SELECT id FROM marca WHERE slug='logitech'), 'Mouse Logitech M170 Inalámbrico', 'mouse-logitech-m170', 'Mouse básico inalámbrico Logitech'),
((SELECT id FROM categoria WHERE slug='teclados'), (SELECT id FROM marca WHERE slug='genius'), 'Teclado Genius KB-118', 'teclado-genius-kb-118', 'Teclado clásico resistente a derrames'),
((SELECT id FROM categoria WHERE slug='audifonos'), (SELECT id FROM marca WHERE slug='jbl'), 'Audífonos JBL Quantum 100', 'audifonos-jbl-quantum-100', 'Audífonos gamer JBL'),
((SELECT id FROM categoria WHERE slug='camaras-web'), (SELECT id FROM marca WHERE slug='genius'), 'Cámara Web Genius QCam', 'camara-web-genius-qcam', 'Cámara web con micrófono incorporado'),
((SELECT id FROM categoria WHERE slug='mousepads'), (SELECT id FROM marca WHERE slug='redragon'), 'Mousepad Redragon Suzaku', 'mousepad-redragon-suzaku', 'Superficie de tela extendida'),
((SELECT id FROM categoria WHERE slug='accesorios-adaptadores'), (SELECT id FROM marca WHERE slug='logitech'), 'Presentador Inalámbrico Logitech', 'presentador-logitech-r400', 'Puntero láser para presentaciones');

INSERT INTO variante_producto (producto_id, sku, codigo_barras, precio_venta, stock_minimo, stock_actual) VALUES
((SELECT id FROM producto WHERE slug='mouse-logitech-m170'), 'MOU-LOG-M170-NEG', '750123450099', 85.00, 5, 20),
((SELECT id FROM producto WHERE slug='teclado-genius-kb-118'), 'TEC-GEN-KB118-NEG', '750123450100', 70.00, 5, 15),
((SELECT id FROM producto WHERE slug='audifonos-jbl-quantum-100'), 'AUD-JBL-Q100-NEG', '750123450101', 280.00, 3, 8),
((SELECT id FROM producto WHERE slug='camara-web-genius-qcam'), 'CAM-GEN-QCAM-NEG', '750123450102', 120.00, 4, 12),
((SELECT id FROM producto WHERE slug='mousepad-redragon-suzaku'), 'MSP-RED-SUZ-NEG', '750123450103', 150.00, 5, 10),
((SELECT id FROM producto WHERE slug='presentador-logitech-r400'), 'PRE-LOG-R400-NEG', '750123450104', 350.00, 2, 6);

