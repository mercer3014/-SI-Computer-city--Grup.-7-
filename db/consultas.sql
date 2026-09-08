
--                  CONSULTAS SIMPLES

-- 1: Listar todos los clientes activos ordenados alfabéticamente (RF12)
SELECT tipo_documento, numero_documento, nombre, telefono, email, fecha_registro
FROM cliente
WHERE estado = 'ACTIVO'
ORDER BY nombre ASC;
-- Propósito: La administración y atención al cliente utilizan este reporte
--   para visualizar rápidamente a todos los clientes que se encuentran activos.
--   Esto facilita la búsqueda de un cliente al momento de facturar,
--   evitando registros duplicados o creando un nuevo registro innecesario.


-- 2: Productos habilitados para la venta (RF6)
SELECT nombre, slug, estado, fecha_creacion
FROM producto
WHERE estado = 'ACTIVO'
ORDER BY nombre ASC;
-- Propósito: El encargado de inventario emplea esta consulta para conocer
--   qué productos están actualmente habilitados para la venta.
--   Ayuda a auditar la base de datos de productos y asegurar que no
--   se muestren artículos descontinuados en los puntos de venta o catálogos.


-- 3: Categorías de gastos operativos (RF19)
SELECT nombre, descripcion, estado
FROM categoria_gasto
WHERE estado = 'ACTIVO'
ORDER BY nombre;
-- Propósito: Muestra un listado consolidado de todas las categorías de gasto
--   registradas en el sistema, como "Alquiler", "Servicios" o "Publicidad".
--   Permite a la gerencia verificar si están cubiertas todas las áreas
--   contables antes de asentar nuevos egresos y así mantener orden financiero.


-- 4: Bitácora de auditoría de los últimos 7 días (RF5)
SELECT modulo, accion, tipo_entidad, motivo, direccion_ip, fecha_hora
FROM bitacora_auditoria
WHERE fecha_hora >= CURRENT_DATE - INTERVAL '7 days'
ORDER BY fecha_hora DESC;
-- Propósito: Los administradores revisan constantemente esta auditoría para
--   detectar cualquier actividad sospechosa o inusual en la última semana.
--   Asegura la trazabilidad de acciones críticas y sirve como mecanismo 
--   de control interno contra fraudes o manipulaciones no autorizadas de datos.


-- 5: Directorio de proveedores registrados (RF15)
SELECT nit, razon_social, nombre_comercial, contacto, telefono
FROM proveedor
WHERE estado = 'ACTIVO'
ORDER BY razon_social;
-- Propósito: El personal de compras requiere un directorio actualizado con
--   los datos de contacto y la información legal (NIT) de los proveedores.
--   Esto agiliza el proceso de comunicación para solicitudes de cotización,
--   órdenes de compra y seguimiento de pedidos de mercadería.


--                  CONSULTAS DOBLES

-- 6: Catálogo de productos con sus respectivas marcas (RF6)
SELECT p.nombre AS producto, m.nombre AS marca, p.estado
FROM producto p
JOIN marca m ON p.marca_id = m.id
ORDER BY m.nombre, p.nombre;
-- Propósito: Relaciona la tabla de productos con su respectiva marca para
--   proporcionar una vista unificada del catálogo al personal de ventas.
--   Es fundamental para responder consultas de clientes que buscan una 
--   marca en específico y para organizar visualmente el inventario.


-- 7: Stock disponible frente a stock mínimo por variante (RF7, RF9)
SELECT p.nombre AS producto, vp.sku, vp.stock_actual, vp.stock_minimo
FROM variante_producto vp
JOIN producto p ON vp.producto_id = p.id
ORDER BY vp.stock_actual ASC;
-- Propósito: Cruza la información del producto principal con las existencias
--   reales de sus variantes (SKUs). El personal de almacén la usa
--   constantemente para comparar el stock actual frente al stock mínimo,
--   identificando rápidamente qué ítems requieren reposición urgente.


-- 8: Usuarios del sistema con su rol asignado (RF2)
SELECT u.email, r.nombre AS rol, u.estado, u.fecha_ultimo_login
FROM usuario u
JOIN rol r ON u.rol_id = r.id
ORDER BY r.nombre, u.email;
-- Propósito: Permite a los administradores del sistema auditar las cuentas
--   de acceso, verificando qué usuario (email) tiene asignado qué rol.
--   Es clave para garantizar que solo el personal autorizado tenga los 
--   privilegios adecuados y para monitorear cuentas inactivas.


-- 9: Historial de compras resumido por proveedor (RF17)
SELECT c.numero_compra, p.razon_social, c.fecha_compra, c.estado
FROM compra c
JOIN proveedor p ON c.proveedor_id = p.id
ORDER BY c.fecha_compra DESC;
-- Propósito: La gerencia y el área de abastecimiento utilizan este informe
--   para tener un historial cronológico de compras asociadas a cada proveedor.
--   Ayuda en la conciliación de pagos y permite evaluar con qué proveedores
--   se tiene mayor frecuencia de operaciones comerciales.


-- 10: Gastos operativos y su categoría correspondiente (RF19)
SELECT g.fecha, cg.nombre AS categoria, g.concepto, g.monto
FROM gasto g
JOIN categoria_gasto cg ON g.categoria_gasto_id = cg.id
ORDER BY g.fecha DESC;
-- Propósito: Cruza los egresos registrados con sus categorías contables
--   para proporcionar un detalle claro de en qué se está gastando el dinero.
--   Facilita la tarea de finanzas al momento de generar el reporte de 
--   gastos operativos para calcular las utilidades mensuales.


--               CONSULTAS MÚLTIPLES

-- 11: Detalle de venta con datos del cliente y método de pago (RF10, RF14)
SELECT v.numero_venta, c.nombre AS cliente, v.fecha, v.estado,
       pv.monto AS total_pagado, mp.nombre AS metodo_pago
FROM venta v
JOIN cliente c ON v.cliente_id = c.id
JOIN pago_venta pv ON v.id = pv.venta_id
JOIN metodo_pago mp ON pv.metodo_pago_id = mp.id
ORDER BY v.fecha DESC;
-- Propósito: El módulo de finanzas cruza las ventas con clientes y sus pagos
--   para obtener un panorama completo de cada transacción realizada.
--   Muestra qué cliente compró, el total pagado y el medio de pago, 
--   información indispensable para el cierre de caja diario y conciliación.


-- 12: Historial de compras con desglose de productos (RF16)
SELECT c.numero_compra, p.razon_social, vp.sku, dc.cantidad, dc.costo_unitario
FROM compra c
JOIN proveedor p ON c.proveedor_id = p.id
JOIN detalle_compra dc ON c.id = dc.compra_id
JOIN variante_producto vp ON dc.variante_id = vp.id
ORDER BY c.fecha_compra DESC;
-- Propósito: Desglosa minuciosamente las compras integrando datos del 
--   proveedor, el número de compra y los detalles de cada SKU adquirido.
--   Es vital para las auditorías de almacén, permitiendo verificar qué 
--   productos ingresaron en un lote específico y a qué costo unitario.


-- 13: Movimientos de inventario (Kardex) con responsable (RF7)
SELECT mi.fecha, mi.tipo, mi.referencia, 
       u.email AS responsable, vp.sku, dmi.cantidad
FROM movimiento_inventario mi
JOIN usuario u ON mi.usuario_id = u.id
JOIN detalle_movimiento_inventario dmi ON mi.id = dmi.movimiento_id
JOIN variante_producto vp ON dmi.variante_id = vp.id
ORDER BY mi.fecha DESC;
-- Propósito: Es el corazón del sistema de control (Kardex). Integra los
--   movimientos de inventario con el usuario responsable y el producto afectado.
--   Garantiza trazabilidad absoluta en caso de pérdidas, identificando 
--   quién autorizó el movimiento, la cantidad y la fecha exacta del cambio.


-- 14: Jerarquía completa: Categoría, Marca y Producto (RF6)
SELECT cat.nombre AS categoria, m.nombre AS marca, p.nombre AS producto, 
       vp.sku, vp.precio_venta
FROM variante_producto vp
JOIN producto p ON vp.producto_id = p.id
LEFT JOIN marca m ON p.marca_id = m.id
LEFT JOIN categoria cat ON p.categoria_id = cat.id
ORDER BY cat.nombre, p.nombre;
-- Propósito: Genera una vista panorámica de toda la jerarquía de catálogo,
--   desde la categoría y marca hasta el nombre del producto y su SKU final.
--   Se utiliza principalmente para exportar listas de precios estructuradas
--   o actualizar masivamente catálogos físicos y plataformas externas.


-- 15: Atributos detallados por variante de producto (RF6)
SELECT vp.sku, a.nombre AS atributo, va.valor
FROM variante_producto vp
JOIN variante_valor_atributo vva ON vp.id = vva.variante_id
JOIN valor_atributo va ON vva.valor_atributo_id = va.id
JOIN atributo a ON va.atributo_id = a.id
ORDER BY vp.sku, a.nombre;
-- Propósito: Muestra en detalle todas las características (como color, 
--   conectividad o tamaño) asociadas a una variante de producto específica.
--   El área de ventas lo consulta para describir exactamente el artículo
--   a un cliente exigente sin necesidad de revisar físicamente la caja.


-- 16: Control de garantías emitidas por cliente y producto (RF8, RF13)
SELECT v.numero_venta, v.fecha, cl.nombre AS cliente, 
       vp.sku, dv.numero_serie, dv.fecha_fin_garantia, dv.estado
FROM detalle_venta dv
JOIN venta v ON dv.venta_id = v.id
JOIN cliente cl ON v.cliente_id = cl.id
JOIN variante_producto vp ON dv.variante_id = vp.id
WHERE dv.numero_serie IS NOT NULL
ORDER BY dv.fecha_fin_garantia ASC;
-- Propósito: Une la información de ventas, clientes y los números de serie
--   de los productos despachados para formar el centro de control de garantías.
--   Cuando un cliente reclama, el técnico usa esta consulta para buscar el 
--   serial y comprobar inmediatamente si la fecha de cobertura sigue válida.


-- 17: Matriz de seguridad: Permisos asignados por rol (RF3)
SELECT r.nombre AS rol, p.modulo, p.nombre AS permiso
FROM rol_permiso rp
JOIN rol r ON rp.rol_id = r.id
JOIN permiso p ON rp.permiso_id = p.id
ORDER BY r.nombre, p.modulo, p.nombre;
-- Propósito: Construye una matriz de seguridad que lista claramente todos 
--   los permisos (acciones habilitadas) que posee cada rol en el sistema.
--   El administrador técnico requiere esta vista para diagnosticar problemas 
--   de acceso o confirmar que las políticas de seguridad se cumplen a cabalidad.


-- 18: Auditoría de ventas anuladas con usuario responsable (RF13, RF14)
SELECT v.numero_venta, cl.nombre AS cliente, v.fecha_anulacion, 
       u.email AS anulado_por, v.motivo_anulacion
FROM venta v
JOIN cliente cl ON v.cliente_id = cl.id
JOIN usuario u ON v.anulado_por = u.id
WHERE v.estado = 'ANULADA'
ORDER BY v.fecha_anulacion DESC;
-- Propósito: Reporte de control administrativo que audita todas las ventas
--   que fueron revertidas. Muestra a qué cliente pertenecía la venta, 
--   el motivo justificado de la anulación y qué usuario ejecutó la acción,
--   previniendo fraudes internos o cancelaciones no autorizadas.


-- 19: Pedidos de clientes con detalle de artículos (RF10)
SELECT pd.numero_pedido, c.nombre AS cliente, pd.estado,
       vp.sku, dp.cantidad, dp.precio_unitario
FROM pedido pd
JOIN cliente c ON pd.cliente_id = c.id
JOIN detalle_pedido dp ON pd.id = dp.pedido_id
JOIN variante_producto vp ON dp.variante_id = vp.id
ORDER BY pd.fecha DESC;
-- Propósito: El equipo de logística y entregas consolida los pedidos pendientes
--   cruzando la información del cliente con los productos requeridos.
--   Sirve como "hoja de ruta" o "picking list" para recolectar los artículos
--   en bodega antes de facturarlos o prepararlos para despacho.


-- 20: Consolidado general de salidas de caja (Flujo de caja - Egresos) (RF22)
SELECT g.fecha::DATE AS fecha, 'Gasto' AS tipo, cg.nombre AS categoria, g.concepto, g.monto, mp.nombre AS metodo
FROM gasto g
JOIN categoria_gasto cg ON g.categoria_gasto_id = cg.id
LEFT JOIN metodo_pago mp ON g.metodo_pago_id = mp.id
UNION ALL
SELECT c.fecha_compra::DATE, 'Compra', 'Proveedor', p.razon_social, (dc.cantidad * dc.costo_unitario), mp.nombre
FROM compra c
JOIN proveedor p ON c.proveedor_id = p.id
JOIN detalle_compra dc ON c.id = dc.compra_id
LEFT JOIN metodo_pago mp ON c.metodo_pago_id = mp.id
ORDER BY fecha DESC;
-- Propósito: Consulta analítica que consolida de forma inteligente todos 
--   los egresos (tanto compras de mercadería como gastos operativos).
--   Es fundamental para la construcción del reporte de Flujo de Caja, 
--   ya que unifica salidas de dinero de diferentes módulos en una sola tabla.


--                       SUBCONSULTAS

-- 21: Clientes registrados sin compras realizadas (RF12)
SELECT nombre, telefono, email
FROM cliente
WHERE id NOT IN (SELECT cliente_id FROM venta)
  AND numero_documento <> 'CF-0000'
ORDER BY nombre;
-- Propósito: El área comercial identifica a los clientes que se registraron
--   en la base de datos pero que aún no han concretado ninguna compra.
--   Esta información es muy valiosa para ejecutar campañas de marketing,
--   enviar promociones e incentivar la primera conversión de estos prospectos.


-- 22: Inventario inmovilizado: Productos sin historial de ventas (RF7)
SELECT sku, precio_venta, stock_actual
FROM variante_producto
WHERE id NOT IN (SELECT variante_id FROM detalle_venta)
ORDER BY stock_actual DESC;
-- Propósito: Analiza el inventario inmovilizado detectando aquellos SKUs
--   que tienen stock disponible pero jamás han figurado en una nota de venta.
--   Permite a la gerencia aplicar descuentos, armar combos o tomar la 
--   decisión de no volver a comprar ese producto por su nula rotación.


-- 23: Top 5 proveedores con mayor volumen de compra (RF17)
SELECT p.razon_social, sub.total_comprado
FROM proveedor p
JOIN (
    SELECT c.proveedor_id, SUM(dc.cantidad * dc.costo_unitario) AS total_comprado
    FROM compra c
    JOIN detalle_compra dc ON c.id = dc.compra_id
    GROUP BY c.proveedor_id
) sub ON p.id = sub.proveedor_id
ORDER BY sub.total_comprado DESC
LIMIT 5;
-- Propósito: Calcula el volumen económico movido con cada proveedor mediante
--   una subconsulta agregada y muestra el top 5 de proveedores principales.
--   Ayuda a la gerencia a negociar mejores márgenes de ganancia, líneas 
--   de crédito o beneficios exclusivos respaldados por el historial de compra.


-- 24: Ventas cuyo monto supera el promedio general de la tienda (RF20)
SELECT v.numero_venta, v.fecha, pv.monto
FROM venta v
JOIN pago_venta pv ON v.id = pv.venta_id
WHERE pv.monto > (SELECT AVG(monto) FROM pago_venta)
ORDER BY pv.monto DESC;
-- Propósito: Identifica aquellas ventas excepcionales que superan por sí 
--   solas el ticket promedio (monto promedio) de toda la tienda.
--   Permite estudiar el perfil de los clientes "mayoristas" o VIP, para
--   ofrecerles un trato preferencial o fidelizarlos con atención especializada.


-- 25: Resumen total de compras y montos por cada proveedor (RF17)
SELECT p.razon_social,
       (SELECT COUNT(*) FROM compra c WHERE c.proveedor_id = p.id) AS cantidad_compras,
       (SELECT COALESCE(SUM(dc.cantidad * dc.costo_unitario), 0)
        FROM compra c
        JOIN detalle_compra dc ON c.id = dc.compra_id
        WHERE c.proveedor_id = p.id) AS monto_total
FROM proveedor p
ORDER BY monto_total DESC;
-- Propósito: Utiliza subconsultas en el SELECT para construir un tablero 
--   resumido que indica cuántas operaciones se hicieron por proveedor y el
--   monto total invertido. Agiliza el cierre contable de cuentas por pagar
--   sin necesidad de múltiples GROUP BY complejos.


-- 26: Productos con stock inferior al promedio general (RF9)
SELECT vp.sku, p.nombre, vp.stock_actual
FROM variante_producto vp
JOIN producto p ON vp.producto_id = p.id
WHERE vp.stock_actual < (SELECT AVG(stock_actual) FROM variante_producto)
ORDER BY vp.stock_actual ASC;
-- Propósito: Detecta los productos cuyo stock se encuentra alarmantemente 
--   por debajo de la media de inventario de toda la tienda.
--   Funciona como un mecanismo estadístico avanzado para prever posibles 
--   quiebres de stock en artículos que rotan más rápido de lo habitual.


-- 27: Ranking de los 5 clientes que más ingresos han generado (RF12, RF20)
SELECT c.nombre, sub.total_gastado
FROM cliente c
JOIN (
    SELECT v.cliente_id, SUM(pv.monto) AS total_gastado
    FROM venta v
    JOIN pago_venta pv ON v.id = pv.venta_id
    WHERE v.estado = 'COMPLETADA'
    GROUP BY v.cliente_id
) sub ON c.id = sub.cliente_id
ORDER BY sub.total_gastado DESC
LIMIT 5;
-- Propósito: Procesa un ranking de los 5 clientes que mayor ganancia bruta
--   han aportado a la tienda, sumando el total de sus pagos validados.
--   Es el reporte definitivo para estrategias de fidelización (CRM), 
--   regalos corporativos a fin de año o membresías preferenciales.


-- 28: Última venta registrada para cada cliente (RF14)
SELECT c.nombre, v.numero_venta, v.fecha
FROM cliente c
JOIN venta v ON c.id = v.cliente_id
WHERE v.fecha = (
    SELECT MAX(fecha) 
    FROM venta v2 
    WHERE v2.cliente_id = c.id
)
ORDER BY v.fecha DESC;
-- Propósito: Cruza a los clientes con sus transacciones para extraer 
--   estrictamente la fecha de su última compra registrada.
--   Ideal para identificar la retención de clientes o contactar a aquellos
--   que llevan meses sin visitar la tienda, reactivando su interés comercial.


-- 29: Utilidad bruta exacta calculada por cada venta (RF20)
SELECT v.numero_venta, v.fecha,
       sub.ingreso_total, sub.costo_total,
       (sub.ingreso_total - sub.costo_total) AS utilidad_bruta
FROM venta v
JOIN (
    SELECT venta_id,
           SUM(cantidad * precio_unitario - descuento) AS ingreso_total,
           SUM(cantidad * costo_unitario_snapshot) AS costo_total
    FROM detalle_venta
    GROUP BY venta_id
) sub ON v.id = sub.venta_id
WHERE v.estado = 'COMPLETADA'
ORDER BY utilidad_bruta DESC;
-- Propósito: Para cada venta registrada, calcula internamente los ingresos
--   totales y resta los costos basados en el snapshot histórico de la compra.
--   Otorga a la dirección el margen de ganancia o utilidad bruta exacta 
--   operación por operación, evaluando la verdadera rentabilidad diaria.


-- 30: Métodos de pago más frecuentes en número de transacciones (RF22)
SELECT mp.nombre AS metodo_pago, sub.cantidad_pagos
FROM metodo_pago mp
JOIN (
    SELECT metodo_pago_id, COUNT(*) AS cantidad_pagos
    FROM pago_venta
    GROUP BY metodo_pago_id
) sub ON mp.id = sub.metodo_pago_id
ORDER BY sub.cantidad_pagos DESC;
-- Propósito: Agrupa y cuenta la cantidad de veces que se ha utilizado cada 
--   medio de pago (Efectivo, QR, Transferencia) mediante una tabla derivada.
--   Le indica a la gerencia si las pasarelas digitales están teniendo 
--   adopción, justificando la inversión en puntos POS o cuentas bancarias.
