# SISTEMA DE INFORMACIÓN PARA LA GESTIÓN DE INVENTARIO, VENTAS, COMPRAS Y CLIENTES DE LA TIENDA “COMPUTER CITY

# **1\. PERFIL**

## **1.1. Introducción**

En el dinámico sector de la comercialización de tecnología y periféricos de computadora, la agilidad y la precisión en la gestión de la información son fundamentales. En mercados altamente competitivos como el comercial "Chiriguano", los negocios buscan constantemente formas de destacar, no solo a través de la calidad de sus productos o el servicio técnico que ofrecen, sino también mediante la eficiencia en su atención al cliente.

Mientras que las grandes cadenas comerciales operan con sistemas automatizados, muchos negocios emergentes o medianos aún dependen de herramientas ofimáticas básicas como Excel o registros manuales en cuadernos. Esta dependencia de métodos tradicionales limita significativamente su capacidad para escalar, genera cuellos de botella en la atención y aumenta el riesgo de pérdida o alteración de datos financieros y de inventario.

En este contexto, el proyecto propone el desarrollo e implementación de un Sistema de Información (Punto de Venta y Gestión Administrativa) para la tienda "Computer City". Este sistema centralizará los módulos de contabilidad, ventas, compras e inventario, reemplazando los tediosos registros manuales. Al digitalizar y automatizar estos procesos, el negocio no solo ahorrará tiempo vital de operación diaria, sino que también obtendrá reportes financieros exactos, alertas de stock y un historial detallado de clientes, permitiendo una toma de decisiones basada en datos reales y mejorando la calidad del servicio técnico y de ventas.

## **1.2. Antecedentes**

"Computer City" es una tienda ubicada en el comercial Chiriguano, con aproximadamente 2 años de funcionamiento continuo. El negocio se dedica principalmente a la venta de periféricos de computadora (mouse, teclados, audífonos, etc.) y a brindar servicio técnico especializado.

Desde sus inicios, el negocio ha sido operado exclusivamente por dos personas: el propietario y su pareja. Han desarrollado una división de roles empírica pero funcional, donde el propietario asume la atención al cliente, el servicio técnico y toda el área de administración y control (inventario, registro de ventas y reportes financieros). Por su parte, su pareja se enfoca en la oferta, atención y venta directa de los productos.

Durante estos dos años, el crecimiento del flujo de ventas y reparaciones ha provocado que sus herramientas iniciales de gestión queden obsoletas. Actualmente, utilizan múltiples hojas de cálculo de Excel desconectadas entre sí para registrar inventario, ventas y compras, apoyándose en cuadernos físicos de uso rápido y talonarios manuales para la emisión de notas de venta con garantía. Aunque este método les ha permitido subsistir, el volumen actual de transacciones ha evidenciado que el sistema manual es insostenible a largo plazo.

## 

## **1.3. Justificación**

La implementación de un sistema de información en "Computer City" se justifica principalmente por la pérdida de tiempo operativo y la vulnerabilidad de sus datos actuales. En la actualidad, el propietario invierte aproximadamente dos horas diarias en transcribir datos de un cuaderno a hojas de Excel, lo que representa una carga administrativa severa para un negocio operado por solo dos personas.

Además, el uso de Excel requiere una sintaxis exacta; un simple espacio extra genera productos duplicados, lo que a su vez provoca discrepancias constantes entre el stock físico y el stock digital. Esta falta de precisión desencadena un problema mayor: la "falsedad de datos" en los reportes financieros. Al olvidar registrar una venta o compra por falta de tiempo, los reportes mensuales de utilidad e ingresos se vuelven inexactos, impidiendo conocer la verdadera rentabilidad de la tienda.

Otro punto crucial es la fidelización y el control de garantías. Actualmente, no existe una base de datos para clientes particulares, lo que impide conocer el historial de compras de un cliente recurrente. Asimismo, el control de garantías depende de la coincidencia visual de un número de serie escrito a mano en un talonario físico.

Un sistema automatizado unificará estos procesos, permitiendo buscar productos rápidamente por código durante una venta, registrar al cliente al instante, actualizar el inventario automáticamente y generar los reportes contables exactos que el negocio exige, erradicando el error humano y optimizando las dos horas diarias perdidas en registros manuales.

## **1.4. Descripción Del Problema**

El desarrollo del sistema para Computer City surge de la necesidad de resolver desafíos críticos en tres áreas fundamentales que actualmente limitan su operatividad y rentabilidad:

**Sector 1: Administración del Inventario y Compras**

1. **Discrepancia y duplicidad de datos:** El registro en Excel requiere coincidencia exacta de caracteres. Un error de tipeo genera duplicidad de productos, lo que causa constantes descuadres entre el stock registrado y el físico en tienda.  
2. **Carencia de control de stock automatizado:** Actualmente se enteran que un producto debe reponerse solo cuando la celda marca "0" o al notar la ausencia física en el estante, lo que en ocasiones los obliga a pedir mercancía a última hora frente al cliente o perder la venta si el proveedor exige 3 días de anticipación.  
3. **Actualización manual de ingresos:** La recepción de mercadería y la actualización de precios de compra (fluctuantes por la cotización del dólar) requieren un registro manual laborioso cruzando hojas de cálculo.

**Sector 2: Proceso de Ventas y Atención al Cliente**

1. **Doble trabajo en la facturación/nota de venta:** El proceso de venta exige llenar un talonario físico a mano, anotar en un cuaderno y, al final del día, transcribirlo a Excel. Esto genera lentitud en la atención y fatiga operativa.  
2. **Falta de Base de Datos de Clientes:** A los clientes particulares se les registra genéricamente como "Cliente Final" en Excel. No se guarda un historial de compras, dificultando el seguimiento para garantías y desaprovechando oportunidades de conocer el comportamiento de clientes frecuentes.  
3. **Control de garantías manual:** La verificación de devoluciones o cambios por garantía se realiza buscando en talonarios físicos y verificando los últimos 4 dígitos del número de serie, un proceso susceptible a pérdida de documentos.

**Sector 3: Administración y Reportes Financieros**

1. **Falsedad de datos por omisión:** La falta de tiempo hace que, en ocasiones, no se registren operaciones en el Excel. Esto genera un efecto dominó que contamina la veracidad de los reportes de ganancias y costos.  
2. **Generación de reportes ineficiente:** Consolidar la información para obtener los reportes de Ingresos Operativos, Costo de Ventas, Gastos Operativos y Utilidad demanda demasiado tiempo de procesamiento manual al cierre del mes.

## **1.5. Formulación Del Problema**

¿Cómo optimizar y centralizar los procesos de gestión de inventario, registro de ventas, control de compras a proveedores y generación de reportes financieros en la tienda "Computer City", para reducir el tiempo administrativo, minimizar los errores de duplicidad de datos y obtener información contable exacta y en tiempo real?

## **1.6. Objetivos**

### **1.6.1 Objetivo General**

Desarrollar un Sistema de Información integral para la administración, control de inventario, gestión de ventas, compras y generación de reportes contables de la tienda de tecnología "Computer City".

### **1.6.2 Objetivos Específicos**

* Recolectar los datos obtenidos mediante la entrevista realizada a los responsables de la tienda para identificar sus necesidades y procesos actuales.  
* Analizar la información recolectada en la entrevista para establecer los requerimientos funcionales del sistema.  
* Diseñar el SGBD(PostgreSQL) para gestionar la información de productos, categorías, proveedores, clientes, ventas, compras y usuarios.  
* Diseñar una arquitectura de despliegue que defina los componentes necesarios para el funcionamiento del sistema.  
* Implementar una interfaz de usuario web que permita realizar de forma ágil las operaciones contempladas en los módulos del sistema.  
* Realizar pruebas funcionales para verificar el correcto funcionamiento de los módulos y la integridad de la información registrada.


## **1.7. Alcance**

### **1.7.1 Requisitos funcionales**

**Módulo de Administración de Usuarios y Seguridad**

* **RF1: Inicio y cierre de sesión:** El sistema permitirá a los usuarios iniciar y cerrar sesión de forma segura, registrando la fecha y hora de cada acceso y salida.  
* **RF2: Administración de usuarios y perfiles:** El sistema permitirá registrar, modificar, habilitar y deshabilitar usuarios, así como asignar perfiles según sus funciones dentro de la tienda.  
* **RF3: Gestión de privilegios de acceso:** El sistema permitirá definir y administrar los privilegios de cada perfil, controlando el acceso a las diferentes funciones y módulos.  
* **RF4: Gestión de contraseñas:** El sistema permitirá administrar y actualizar las contraseñas de los usuarios.  
* **RF5: Bitácora de actividades:** El sistema registrará las acciones realizadas por los usuarios, permitiendo conocer quién ingresó al sistema, cuándo ingresó, qué acciones realizó y cuándo cerró sesión.

**Módulo de Inventario y Productos**

* **RF6: Gestión de Productos:** El sistema permitirá registrar, modificar y dar de baja productos. La información principal incluirá código/SKU, nombre del producto, marca, modelo y categoría, además de los datos necesarios para el control de costos, precios y existencias.  
* **RF7: Control de Inventario:** El sistema permitirá registrar y consultar las existencias de los productos, controlando las entradas y salidas generadas por compras y ventas.  
* **RF8: Control de Seriales y Garantías:** El sistema permitirá registrar el número de serie del producto o los últimos dígitos/letras utilizados para identificarlo durante una garantía, relacionándolo con la venta correspondiente.  
* **RF9: Alertas de Stock:** El sistema podrá mostrar advertencias cuando un producto llegue a stock cero o alcance un nivel mínimo definido, facilitando la reposición anticipada.

**Módulo de Ventas y Clientes**

* **RF10: Registro de Ventas:** El sistema permitirá registrar ventas al contado, incluyendo la fecha, cliente, productos, cantidades, precios y método de pago. Los métodos de pago contemplados serán efectivo, QR, transferencia y pagos combinados.  
* **RF11: Generación de Notas de Venta:** El sistema permitirá generar una nota de venta o recibo con el detalle de la operación, los datos del producto, el número de serie cuando corresponda y la información de garantía.  
* **RF12: Gestionar Clientes:** El sistema permitirá registrar y modificar los datos de clientes particulares y tiendas recurrentes, manteniendo el historial de compras asociado.  
* **RF13: Gestión de Devoluciones y Cambios:** El sistema permitirá consultar ventas anteriores para validar las condiciones de devolución o garantía mediante la nota de venta y/o número de serie, registrando el cambio y la diferencia de precio cuando corresponda.  
* **RF14: Consulta de Ventas:** El sistema permitirá consultar las ventas realizadas para facilitar la atención al cliente y el seguimiento de garantías.

**Módulo de Compras y Proveedores**

* **RF15: Gestionar Proveedores:** El sistema permitirá registrar, modificar y consultar la información de los proveedores con los que trabaja la tienda.  
* **RF16: Registro de Compras:** El sistema permitirá registrar compras realizadas a proveedores, incluyendo productos, cantidades, costos y fecha, actualizando las existencias correspondientes.  
* **RF17: Control de Compras por Proveedor:** El sistema permitirá consultar las compras realizadas a cada proveedor.  
* **RF18: Compras al contado o por pagar:** Las compras a proveedores se registrarán como operaciones al contado y compras por pagar.

**Módulo de Flujo de Caja y Reportes**

* **RF19: Registro de Ingresos y Gastos:** El sistema permitirá registrar y organizar los ingresos y gastos necesarios para conocer el movimiento de efectivo del negocio.  
* **RF20: Reportes de Gestión:** El sistema generará reportes diarios, semanales y mensuales sobre ventas, costos, gastos, ingresos y utilidades, tomando como referencia la información que actualmente se maneja en Excel.  
* **RF21: Productos Más Vendidos:** El sistema permitirá generar un reporte de los productos con mayor rotación para apoyar las decisiones de compra e inversión.  
* **RF22: Consulta del Flujo de Caja:** El sistema permitirá consultar los ingresos y egresos registrados durante un período determinado para conocer el movimiento económico del negocio.

### **1.7.2 Requisitos no funcionales**

* **RNF1:** El sistema no realizará la gestión contable completa de la empresa.  
* **RNF2:** El sistema no gestionará el pago de salarios ni planillas de empleados.  
* **RNF3:** El sistema no realizará ventas en línea ni funcionará como una tienda de comercio electrónico.  
* **RNF4:** El sistema no gestionará servicios de entrega o distribución de productos a domicilio.  
* **RNF5:** El sistema no administrará múltiples sucursales; estará orientado a la gestión de la tienda Computer City.  
* **RNF6:** El sistema no contará con una aplicación móvil nativa; el acceso se realizará mediante la aplicación web.  
* **RNF7:** El sistema no realizará reparaciones ni diagnósticos técnicos de los periféricos comercializados.  
* **RNF8:** El sistema no se encargará de procesos externos pertenecientes a entidades bancarias o plataformas de pago.