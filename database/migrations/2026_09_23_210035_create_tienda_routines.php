<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_venta_restaurar_stock_anulacion ON public.venta;
DROP TRIGGER IF EXISTS trg_venta_generar_numero ON public.venta;
DROP TRIGGER IF EXISTS trg_venta_generar_movimiento ON public.venta;
DROP TRIGGER IF EXISTS trg_variante_set_fecha_actualizacion ON public.variante_producto;
DROP TRIGGER IF EXISTS trg_producto_set_fecha_actualizacion ON public.producto;
DROP TRIGGER IF EXISTS trg_detalle_venta_validar_y_garantia ON public.detalle_venta;
DROP TRIGGER IF EXISTS trg_detalle_venta_actualizar_stock ON public.detalle_venta;
DROP TRIGGER IF EXISTS trg_detalle_compra_actualizar_stock ON public.detalle_compra;
DROP TRIGGER IF EXISTS trg_compra_generar_movimiento ON public.compra;
DROP TRIGGER IF EXISTS trg_auditoria_cambio_precio ON public.variante_producto;

DROP FUNCTION IF EXISTS public.fn_validar_garantia(character varying);
DROP FUNCTION IF EXISTS public.fn_trg_venta_restaurar_stock_anulacion();
DROP FUNCTION IF EXISTS public.fn_trg_venta_generar_numero();
DROP FUNCTION IF EXISTS public.fn_trg_venta_generar_movimiento();
DROP FUNCTION IF EXISTS public.fn_trg_set_fecha_actualizacion();
DROP FUNCTION IF EXISTS public.fn_trg_detalle_venta_validar_y_garantia();
DROP FUNCTION IF EXISTS public.fn_trg_detalle_venta_actualizar_stock();
DROP FUNCTION IF EXISTS public.fn_trg_detalle_compra_actualizar_stock();
DROP FUNCTION IF EXISTS public.fn_trg_compra_generar_movimiento();
DROP FUNCTION IF EXISTS public.fn_trg_auditoria_cambio_precio();
DROP FUNCTION IF EXISTS public.fn_reporte_ventas_periodo(date, date);
DROP FUNCTION IF EXISTS public.fn_productos_mas_vendidos(date, date, integer);
DROP FUNCTION IF EXISTS public.fn_flujo_caja_periodo(date, date);
DROP FUNCTION IF EXISTS public.fn_alertas_stock_bajo();

DROP PROCEDURE IF EXISTS public.sp_registrar_venta(integer, integer, integer, character varying, jsonb);
DROP PROCEDURE IF EXISTS public.sp_registrar_proveedor(character varying, character varying, character varying, character varying, character varying, character varying);
DROP PROCEDURE IF EXISTS public.sp_registrar_gasto(integer, integer, integer, character varying, numeric, timestamp without time zone);
DROP PROCEDURE IF EXISTS public.sp_registrar_compra(integer, integer, integer, character varying, timestamp without time zone, jsonb);
DROP PROCEDURE IF EXISTS public.sp_registrar_cliente(character varying, character varying, character varying, character varying, character varying);
DROP PROCEDURE IF EXISTS public.sp_anular_venta(character varying, integer, text);
DROP PROCEDURE IF EXISTS public.sp_ajustar_stock(character varying, integer, integer, text);
DROP PROCEDURE IF EXISTS public.sp_actualizar_precio_variante(character varying, numeric, integer);

DROP SEQUENCE IF EXISTS public.seq_numero_venta;

CREATE SEQUENCE public.seq_numero_venta
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE FUNCTION public.fn_alertas_stock_bajo() RETURNS TABLE(sku character varying, producto character varying, stock_actual integer, stock_minimo integer)
    LANGUAGE sql
    AS $$
    SELECT vp.sku, p.nombre, vp.stock_actual, vp.stock_minimo
    FROM variante_producto vp
    JOIN producto p ON p.id = vp.producto_id
    WHERE vp.stock_actual <= vp.stock_minimo
      AND vp.estado = 'ACTIVO'
    ORDER BY vp.stock_actual ASC;
$$;

CREATE FUNCTION public.fn_flujo_caja_periodo(p_fecha_inicio date, p_fecha_fin date) RETURNS TABLE(concepto character varying, total numeric)
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

CREATE FUNCTION public.fn_productos_mas_vendidos(p_fecha_inicio date, p_fecha_fin date, p_limite integer DEFAULT 10) RETURNS TABLE(sku character varying, producto character varying, unidades_vendidas bigint, total_vendido numeric)
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

CREATE FUNCTION public.fn_reporte_ventas_periodo(p_fecha_inicio date, p_fecha_fin date) RETURNS TABLE(cantidad_ventas bigint, total_ingresos numeric, total_costo numeric, utilidad_bruta numeric)
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

CREATE FUNCTION public.fn_trg_auditoria_cambio_precio() RETURNS trigger
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

CREATE FUNCTION public.fn_trg_compra_generar_movimiento() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    INSERT INTO movimiento_inventario (usuario_id, compra_id, tipo, fecha, referencia)
    VALUES (NEW.usuario_id, NEW.id, 'ENTRADA', NEW.fecha_compra, NEW.numero_compra);
    RETURN NEW;
END;
$$;

CREATE FUNCTION public.fn_trg_detalle_compra_actualizar_stock() RETURNS trigger
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

CREATE FUNCTION public.fn_trg_detalle_venta_actualizar_stock() RETURNS trigger
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

CREATE FUNCTION public.fn_trg_detalle_venta_validar_y_garantia() RETURNS trigger
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

CREATE FUNCTION public.fn_trg_set_fecha_actualizacion() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    NEW.fecha_actualizacion := CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$;

CREATE FUNCTION public.fn_trg_venta_generar_movimiento() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    INSERT INTO movimiento_inventario (usuario_id, venta_id, tipo, fecha, referencia)
    VALUES (NEW.usuario_id, NEW.id, 'SALIDA', NEW.fecha, NEW.numero_venta);
    RETURN NEW;
END;
$$;

CREATE FUNCTION public.fn_trg_venta_generar_numero() RETURNS trigger
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

CREATE FUNCTION public.fn_trg_venta_restaurar_stock_anulacion() RETURNS trigger
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

CREATE FUNCTION public.fn_validar_garantia(p_numero_serie character varying) RETURNS TABLE(numero_venta character varying, producto character varying, fecha_venta timestamp without time zone, fecha_fin_garantia date, vigente boolean, estado_item character varying)
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

CREATE PROCEDURE public.sp_actualizar_precio_variante(IN p_sku character varying, IN p_nuevo_precio numeric, IN p_usuario_id integer)
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

CREATE PROCEDURE public.sp_ajustar_stock(IN p_sku character varying, IN p_cantidad integer, IN p_usuario_id integer, IN p_observacion text)
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

CREATE PROCEDURE public.sp_anular_venta(IN p_numero_venta character varying, IN p_usuario_id integer, IN p_motivo text)
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

CREATE PROCEDURE public.sp_registrar_cliente(IN p_tipo_documento character varying, IN p_numero_documento character varying, IN p_nombre character varying, IN p_telefono character varying DEFAULT NULL::character varying, IN p_email character varying DEFAULT NULL::character varying)
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

CREATE PROCEDURE public.sp_registrar_compra(IN p_proveedor_id integer, IN p_usuario_id integer, IN p_metodo_pago_id integer, IN p_numero_compra character varying, IN p_fecha_compra timestamp without time zone, IN p_items jsonb)
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

CREATE PROCEDURE public.sp_registrar_gasto(IN p_categoria_gasto_id integer, IN p_metodo_pago_id integer, IN p_usuario_id integer, IN p_concepto character varying, IN p_monto numeric, IN p_fecha timestamp without time zone DEFAULT CURRENT_TIMESTAMP)
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

CREATE PROCEDURE public.sp_registrar_proveedor(IN p_nit character varying, IN p_razon_social character varying, IN p_nombre_comercial character varying DEFAULT NULL::character varying, IN p_contacto character varying DEFAULT NULL::character varying, IN p_telefono character varying DEFAULT NULL::character varying, IN p_email character varying DEFAULT NULL::character varying)
    LANGUAGE plpgsql
    AS $$
BEGIN
    INSERT INTO proveedor (nit, razon_social, nombre_comercial, contacto, telefono, email)
    VALUES (p_nit, p_razon_social, p_nombre_comercial, p_contacto, p_telefono, p_email);
END;
$$;

CREATE PROCEDURE public.sp_registrar_venta(IN p_cliente_id integer, IN p_usuario_id integer, IN p_metodo_pago_id integer, IN p_numero_venta character varying, IN p_items jsonb)
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

CREATE TRIGGER trg_auditoria_cambio_precio AFTER UPDATE ON public.variante_producto FOR EACH ROW EXECUTE FUNCTION public.fn_trg_auditoria_cambio_precio();

CREATE TRIGGER trg_compra_generar_movimiento AFTER INSERT ON public.compra FOR EACH ROW EXECUTE FUNCTION public.fn_trg_compra_generar_movimiento();

CREATE TRIGGER trg_detalle_compra_actualizar_stock AFTER INSERT ON public.detalle_compra FOR EACH ROW EXECUTE FUNCTION public.fn_trg_detalle_compra_actualizar_stock();

CREATE TRIGGER trg_detalle_venta_actualizar_stock AFTER INSERT ON public.detalle_venta FOR EACH ROW EXECUTE FUNCTION public.fn_trg_detalle_venta_actualizar_stock();

CREATE TRIGGER trg_detalle_venta_validar_y_garantia BEFORE INSERT ON public.detalle_venta FOR EACH ROW EXECUTE FUNCTION public.fn_trg_detalle_venta_validar_y_garantia();

CREATE TRIGGER trg_producto_set_fecha_actualizacion BEFORE UPDATE ON public.producto FOR EACH ROW EXECUTE FUNCTION public.fn_trg_set_fecha_actualizacion();

CREATE TRIGGER trg_variante_set_fecha_actualizacion BEFORE UPDATE ON public.variante_producto FOR EACH ROW EXECUTE FUNCTION public.fn_trg_set_fecha_actualizacion();

CREATE TRIGGER trg_venta_generar_movimiento AFTER INSERT ON public.venta FOR EACH ROW EXECUTE FUNCTION public.fn_trg_venta_generar_movimiento();

CREATE TRIGGER trg_venta_generar_numero BEFORE INSERT ON public.venta FOR EACH ROW EXECUTE FUNCTION public.fn_trg_venta_generar_numero();

CREATE TRIGGER trg_venta_restaurar_stock_anulacion AFTER UPDATE ON public.venta FOR EACH ROW EXECUTE FUNCTION public.fn_trg_venta_restaurar_stock_anulacion();
SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS trg_venta_restaurar_stock_anulacion ON public.venta;
DROP TRIGGER IF EXISTS trg_venta_generar_numero ON public.venta;
DROP TRIGGER IF EXISTS trg_venta_generar_movimiento ON public.venta;
DROP TRIGGER IF EXISTS trg_variante_set_fecha_actualizacion ON public.variante_producto;
DROP TRIGGER IF EXISTS trg_producto_set_fecha_actualizacion ON public.producto;
DROP TRIGGER IF EXISTS trg_detalle_venta_validar_y_garantia ON public.detalle_venta;
DROP TRIGGER IF EXISTS trg_detalle_venta_actualizar_stock ON public.detalle_venta;
DROP TRIGGER IF EXISTS trg_detalle_compra_actualizar_stock ON public.detalle_compra;
DROP TRIGGER IF EXISTS trg_compra_generar_movimiento ON public.compra;
DROP TRIGGER IF EXISTS trg_auditoria_cambio_precio ON public.variante_producto;

DROP FUNCTION IF EXISTS public.fn_validar_garantia(character varying);
DROP FUNCTION IF EXISTS public.fn_trg_venta_restaurar_stock_anulacion();
DROP FUNCTION IF EXISTS public.fn_trg_venta_generar_numero();
DROP FUNCTION IF EXISTS public.fn_trg_venta_generar_movimiento();
DROP FUNCTION IF EXISTS public.fn_trg_set_fecha_actualizacion();
DROP FUNCTION IF EXISTS public.fn_trg_detalle_venta_validar_y_garantia();
DROP FUNCTION IF EXISTS public.fn_trg_detalle_venta_actualizar_stock();
DROP FUNCTION IF EXISTS public.fn_trg_detalle_compra_actualizar_stock();
DROP FUNCTION IF EXISTS public.fn_trg_compra_generar_movimiento();
DROP FUNCTION IF EXISTS public.fn_trg_auditoria_cambio_precio();
DROP FUNCTION IF EXISTS public.fn_reporte_ventas_periodo(date, date);
DROP FUNCTION IF EXISTS public.fn_productos_mas_vendidos(date, date, integer);
DROP FUNCTION IF EXISTS public.fn_flujo_caja_periodo(date, date);
DROP FUNCTION IF EXISTS public.fn_alertas_stock_bajo();

DROP PROCEDURE IF EXISTS public.sp_registrar_venta(integer, integer, integer, character varying, jsonb);
DROP PROCEDURE IF EXISTS public.sp_registrar_proveedor(character varying, character varying, character varying, character varying, character varying, character varying);
DROP PROCEDURE IF EXISTS public.sp_registrar_gasto(integer, integer, integer, character varying, numeric, timestamp without time zone);
DROP PROCEDURE IF EXISTS public.sp_registrar_compra(integer, integer, integer, character varying, timestamp without time zone, jsonb);
DROP PROCEDURE IF EXISTS public.sp_registrar_cliente(character varying, character varying, character varying, character varying, character varying);
DROP PROCEDURE IF EXISTS public.sp_anular_venta(character varying, integer, text);
DROP PROCEDURE IF EXISTS public.sp_ajustar_stock(character varying, integer, integer, text);
DROP PROCEDURE IF EXISTS public.sp_actualizar_precio_variante(character varying, numeric, integer);

DROP SEQUENCE IF EXISTS public.seq_numero_venta;
SQL);
    }
};
