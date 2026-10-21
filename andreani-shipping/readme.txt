=== Andreani WooCommerce ===
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Copyright: 2025 Andreani.com
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.8.6
Contributors: integracionandreani
Donate link:
Tags: woocommerce, shipping, andreani, envio, etiquetas

Cotizá tarifas, generá envíos, imprimí etiquetas y seguí tus pedidos con Andreani. El plugin oficial para WooCommerce en Argentina.

== Description ==

Plugin **oficial de Andreani** para WooCommerce. Tus clientes cotizan y eligen Andreani en el checkout, y vos generás los envíos, imprimís las etiquetas y seguís cada pedido sin salir de tu panel. Y antes de vender, ves cómo viaja cada producto: con qué servicio, en cuántas cajas y cuánto cuesta.

**Para tiendas con envíos en Argentina.** Funciona para clientes **PyME, Corporativos y Middle Market**.

= Novedades de la versión 1.8.6 =

* **Promociones de envío** — armá descuentos o recargos sobre el precio del envío, en porcentaje o monto fijo.
* **Por servicio y por provincia** — elegís a qué servicio de Andreani aplica y en qué provincias, con un mapa para marcarlas.
* **Con fechas** — definís desde y hasta cuándo vale, para Hot Sale, Black Friday o cualquier fecha.
* **Tus clientes ven el precio final** — el checkout muestra el nombre de la promoción junto al envío, y el pedido guarda cuánto se ajustó.
* **El costo adicional ahora es una promoción** — el que tenías cargado en cada modo de entrega se pasa solo a una promoción de recargo llamada "Costo adicional".
* **No cambia lo que factura Andreani** — las promociones ajustan solo lo que paga tu cliente; Andreani te factura la tarifa de siempre.
* **Envío gratis primero** — si un modo de entrega tiene envío gratis, no se suma la promoción ni el costo adicional.

= Paquetería o Bigger: el plugin lo resuelve =

Cada producto se clasifica solo, con las mismas reglas que usa Andreani:

* **Paquetería** — hasta 50 kg, ningún lado de más de 165 cm y la suma de los tres lados hasta 300 cm. Todo el pedido viaja en una sola caja, con una etiqueta, a domicilio o a sucursal.
* **Bigger** — cuando el pedido supera alguno de esos límites. Cada producto viaja en su propia caja, solo a domicilio.

Si un producto se apila (sillas, banquetas) o viaja en varias cajas (un aire split, un mueble desarmado), lo configurás con una sola pregunta y el plugin calcula el resto.

= Qué podés hacer =

**En el checkout**

* Método de envío "Andreani Envios" integrado en las zonas de WooCommerce.
* Tarifas en tiempo real, a domicilio y a sucursal.
* Selección de la sucursal Andreani de retiro.
* Funciona con el checkout clásico y con el checkout por bloques: el selector de sucursal y el DNI se integran solos.
* Cotizador opcional en la página de producto.
* Envío gratis por monto mínimo y por modo de entrega.

**Tus productos**

* "Ver mis productos": servicio de cada producto con su motivo, medidas, peso y forma de despacho, con filtros y búsqueda por nombre o SKU.
* Tres formas de despacho: en su caja, se apilan o en varias cajas, con un dibujo de cómo queda el envío para la cantidad que elijas.
* Probar cotización a cualquier código postal, incluso con cambios que todavía no guardaste.
* Simulador de carrito para cotizar compras con varios productos.
* Aviso de los productos sin medidas, que no se pueden enviar con Andreani.
* SKU de Andreani opcional: si tu producto ya tiene SKU en WooCommerce, se usa ese.

**Gestión de envíos**

* La orden de envío se genera sola al confirmarse el pedido.
* "Ver mis envíos": todos tus envíos en un solo lugar, con búsqueda por pedido, cliente o número de seguimiento, y filtros por estado.
* Caja sugerida para armar cada pedido.
* Reintento de los envíos que no se pudieron generar.
* Botón "Pagar" para ir al portal de Andreani Pymes a pagar los envíos pendientes.
* Exportación de los envíos a CSV.

**Etiquetas**

* Etiquetas en PDF para clientes PyME, Middle Market y Corporativos.
* Formato A4 o térmica (Zebra), con vista previa y cantidad de etiquetas por página.
* Descarga masiva desde "Ver mis envíos".

**Seguimiento**

* Número de seguimiento en el pedido apenas se genera el envío.
* Estado del envío actualizado automáticamente desde Andreani.
* Línea de tiempo del envío en el detalle del pedido.

**Origen y remitente**

* Pestaña "Mi cuenta" con los datos de tu cuenta de Andreani, la dirección desde la que despachás y la sucursal de origen.
* Sucursal de origen a tu elección, o automática por tu código postal.
* La etiqueta sale con el nombre y la dirección de tu tienda como remitente.

**Compatibilidad**

* Compatible con HPOS (almacenamiento de pedidos de alto rendimiento) y con el checkout por bloques de WooCommerce.
* Compatible con Elementor, Divi, Bricks y otros page builders, mediante shortcodes.
* Liviano para tu servidor: consultas acotadas y procesos en segundo plano.
* API pública para desarrolladores (ver "Para desarrolladores").

= Antes de empezar =

Necesitás tu **Credential ID** de Andreani:

* **Clientes PyME:** https://pymes.andreani.com/integraciones/ (elegí la opción WooCommerce)
* **Clientes Corporativos:** https://corporativo.andreani.com/woocommerce

Con la credencial, configurás el método "Andreani Envios" en *WooCommerce → Ajustes → Envío → Zonas de envío*.

== Installation ==

= Requisitos =

* WordPress 5.8 o superior y WooCommerce 7.1 o superior.
* PHP 7.4 o superior.
* Una tienda con zonas de envío en Argentina.
* Tu **Credential ID** de Andreani (en el paso 2 te contamos cómo obtenerlo).

= Paso 1 — Instalá el plugin =

En el panel de WordPress, andá a *Plugins → Añadir nuevo*, buscá **"Andreani WooCommerce"**, instalalo y activalo. También podés subir el ZIP desde *Plugins → Añadir nuevo → Subir plugin*.

= Paso 2 — Obtené tu Credential ID =

* **PyME:** https://pymes.andreani.com/integraciones/ (elegí la opción WooCommerce).
* **Corporativo:** https://corporativo.andreani.com/woocommerce

= Paso 3 — Configurá el método de envío =

1. Andá a *WooCommerce → Ajustes → Envío → Zonas de envío*.
2. Entrá a la zona donde querés ofrecer Andreani, o creá una para Argentina.
3. Agregá el método **"Andreani Envios"**.
4. Pegá tu **Credential ID**. El plugin detecta solo tu tipo de cliente.
5. Ajustá lo que necesites: origen y remitente, servicios, envío gratis, cotizador en producto y formato de etiquetas.

También llegás a esta pantalla desde *Andreani → Configuración*.

= Paso 4 — Revisá tus productos =

Andá a *Andreani → Ver mis productos*. Ahí ves qué productos ya están listos para enviar, cuáles van por Bigger y cuáles no tienen medidas. Sin peso y medidas, un producto no se puede cotizar ni enviar con Andreani.

Si algún producto se apila o viaja en varias cajas, configuralo desde la misma fila y probá la cotización antes de vender.

= Paso 5 — ¡Listo! =

Tus clientes ya pueden elegir Andreani en el checkout. Los envíos se generan solos al confirmarse cada pedido, y los gestionás desde *Andreani → Ver mis envíos*.

== Frequently Asked Questions ==

= ¿Qué necesito para empezar? =

Una tienda WooCommerce con zonas de envío en Argentina y tu **Credential ID** de Andreani. Lo generás en https://pymes.andreani.com/integraciones/ (PyME) o https://corporativo.andreani.com/woocommerce (Corporativo). El plugin detecta solo si sos cliente **PyME, Corporativo o Middle Market** y habilita lo que corresponde.

= Andreani no aparece en el checkout. ¿Qué reviso? =

1. Que el método "Andreani Envios" esté agregado a la **zona de envío** que cubre el código postal del comprador.
2. Que tu **Credential ID** esté cargado y validado.
3. Que los productos del carrito tengan **peso y medidas**. En *Andreani → Ver mis productos* ves cuáles no los tienen y podés probar la cotización.

Si sigue sin aparecer, activá el modo debug y revisá los registros en *WooCommerce → Estado → Logs*.

= ¿Cuándo un envío va por Paquetería y cuándo por Bigger? =

Va por **Paquetería** si el pedido pesa hasta 50 kg, ningún lado mide más de 165 cm y la suma de los tres lados no pasa de 300 cm. Si supera alguno de esos límites, va por **Bigger**. En *Ver mis productos* cada producto muestra su servicio y el motivo, por ejemplo "Pesa 57 kg (máx. 50)".

= ¿Cuántas cajas y etiquetas genera un pedido? =

En **Paquetería**, todo el pedido viaja en una sola caja, con una etiqueta. En **Bigger**, cada producto viaja en su propia caja, con su etiqueta, y solo a domicilio. Con el **Simulador de carrito** ves cuántas cajas y etiquetas genera una compra antes de que pase.

= Mi producto viaja en varias cajas o se apila. ¿Cómo lo configuro? =

En *Ver mis productos*, abrí el producto y elegí cómo se despacha:

* **En su caja:** cada unidad viaja en su propia caja.
* **Se apilan:** varias unidades viajan una sobre otra, como sillas o banquetas. Indicás cuántas entran por pila y cuánto crece la pila con cada una.
* **Varias cajas:** una sola unidad viaja en más de una caja, como un aire split o un mueble desarmado. Cargás las medidas y el peso de cada caja.

El dibujo y la cotización se actualizan mientras cargás, antes de guardar.

= ¿La caja sugerida es exacta? =

Es orientativa. Se calcula con las medidas y el peso que tenés cargados en tus productos: si están bien, la sugerencia es confiable. Te sirve para elegir la caja al preparar el pedido.

= ¿Puedo probar cuánto cuesta un envío antes de vender? =

Sí. En *Ver mis productos* cada producto tiene **Probar cotización**, a cualquier código postal y para 1 a 99 unidades. Para compras con varios productos, usá el **Simulador de carrito**.

= ¿Puedo imprimir etiquetas siendo cliente PyME? =

Sí. Las etiquetas en PDF están disponibles para clientes **PyME, Middle Market y Corporativos**, en formato A4 o térmica (Zebra). Desde *Andreani → Ver mis envíos* podés seleccionar varios envíos y descargar todas las etiquetas juntas.

= ¿Cómo veo el estado de un envío? =

El estado (pendiente de ingreso, en camino, listo para retirar, entregado, no entregado) se muestra en *Ver mis envíos* y en el detalle de cada pedido, y se **actualiza automáticamente** desde Andreani durante los primeros 30 días del envío. Los envíos más viejos se actualizan cuando los abrís.

= ¿El plugin hace más lenta mi tienda? =

No. Las consultas a la base de datos están acotadas, el análisis de tus productos corre en segundo plano y las llamadas a Andreani tienen un tiempo máximo de espera. Las pantallas del plugin responden igual con 10 que con miles de productos y pedidos.

= ¿Funciona con mi page builder o con el checkout por bloques? =

Sí. El checkout por bloques de WooCommerce tiene soporte nativo: el selector de sucursal y el DNI se integran solos. Con Elementor, Divi, Bricks y otros builders podés usar los shortcodes `[andreani_sucursales]` y `[andreani_dni_field]`. Ver "Para desarrolladores".

= ¿Es compatible con HPOS? =

Sí. El plugin es compatible con el almacenamiento de pedidos de alto rendimiento (HPOS) de WooCommerce y con el almacenamiento clásico.

== Screenshots ==

1. Mis productos: cómo viaja cada producto, con su servicio, su paquete y su forma de despacho.
2. Decidí cómo viaja cada producto: cuántos entran en una caja y si lo que no entra va todo junto o cada caja con su etiqueta.
3. Cómo armar cada pedido: el paquete de tu tienda que corresponde, con el dibujo del armado.
4. Simulador de carrito: cuántas cajas y etiquetas genera una compra y cuánto sale el envío.
5. Paquetes guardados: tus cajas, sobres, bolsas y juegos de cajas.
6. Probar cotización: cotizá un producto a cualquier código postal antes de vender.
7. Ver mis envíos: todos tus envíos de Andreani en un solo lugar, con su estado.
8. Retiro en sucursal: tus clientes eligen la sucursal Andreani en el checkout.
9. Cotizador en la página de producto: tus clientes calculan el envío antes de comprar.
10. Configurá todo en un solo lugar: tu cuenta, el origen, los modos de entrega, tu tienda, las etiquetas y las opciones avanzadas.
11. Promociones de envío: descuentos o recargos en porcentaje o monto fijo, por servicio, provincia y fechas. No cambia lo que factura Andreani.

== External services ==

Este plugin se conecta a las APIs de Andreani para obtener información de envíos, calcular tarifas y gestionar órdenes de envío.

**Servicio:** APIs de Andreani
**Propósito:** Cálculo de tarifas de envío, obtención de información de sucursales, generación de órdenes de envío y descarga de etiquetas
**Datos enviados:**
- Información del producto (peso, dimensiones, valor)
- Código postal de origen y destino
- Credenciales de autenticación del cliente con Andreani
- Datos de la orden de compra (cuando se genera un envío)
- Información del destinatario (nombre, dirección, teléfono, email)

**Cuándo se envían los datos:**
- Durante el cálculo de tarifas de envío en el checkout
- Al consultar sucursales disponibles para envíos a sucursal
- Al generar una orden de envío después de una compra exitosa
- Al descargar la etiqueta de un envío y al consultar su estado de seguimiento
- Al configurar la sucursal de origen desde el panel de administración

**Endpoints adicionales consultados desde el panel de administración:**
- `GET https://woocommerce-api-acom.andreani.com/api/v1/Branch/origin?postalCode={cp}` — lista las sucursales habilitadas como origen para el código postal de la tienda, para que elijas desde cuál despachás. Se consulta solo en la pantalla de configuración del método de envío.
- `GET https://woocommerce-api-acom.andreani.com/api/v1/Branch/default-origin?postalCode={cp}` — informa qué sucursal asigna hoy Andreani para el código postal de la tienda. Se consulta solo en la pantalla de configuración del método de envío.
- `PATCH https://woocommerce-api-acom.andreani.com/api/v1/Settings/origin` — al guardar la configuración con la credencial vinculada, informa a Andreani desde dónde despacha la tienda: calle, número, piso, localidad, provincia, código postal de origen, nombre del remitente y, si la fijaste, el código de la sucursal de origen. Se envía solo desde el panel de administración; si Andreani no responde, se reintenta más tarde.

Los dos `GET` reciben únicamente el código postal de origen de la tienda y la credencial de autenticación; el `PATCH` envía además la dirección de origen y el nombre del remitente descritos arriba. No se envían datos de compradores ni de pedidos.


**Términos y condiciones:** https://www.andreani.com/terminos-y-condiciones
**Política de privacidad:** https://www.andreani.com/politica-de-privacidad

== Para desarrolladores ==

Guía técnica del **contrato público estable a partir de 1.5.0**. Todo lo listado acá es seguro de usar desde temas, page builders o plugins custom. Los cambios breaking se anuncian en el Changelog.

= Modelo de integración =

El plugin es **zero-config** en page builders. No detecta Elementor, Divi, Bricks, etc. — los shortcodes encolan sus assets al momento de renderizarse, así que funcionan automáticamente en cualquier builder que respete el contrato de shortcodes de WordPress.

**Modo automático** (default): los hooks de WooCommerce inyectan el selector de sucursales y los campos DNI en el checkout clásico.
**Modo manual**: los hooks quedan desactivados, el integrador usa los shortcodes donde quiera.

Se configura en *WooCommerce → Envío → (tu zona) → Andreani Envios → Modo de renderizado del checkout*.

= Shortcodes =

* `[andreani_sucursales]` — Selector de sucursales. Carga las sucursales según el CP presente en el formulario más cercano. Soporta múltiples instancias por página.
* `[andreani_dni_field context="billing|shipping"]` — Campo DNI/CUIT. El atributo `context` acepta `billing` (por defecto) o `shipping`.

Los shortcodes encolan sus assets on-demand — no requieren tildar *Forzar carga de assets*.

= Clases CSS públicas =

Contrato estable. Seguras de usar en CSS custom:

* `.andreani-sucursales-standalone` — Wrapper del shortcode de sucursales.
* `.andreani-sucursales-select` — El `<select>` de sucursales (funciona en el `<tr>` legacy y en el wrapper standalone).
* `.andreani-sucursales-details` — Bloque con nombre y dirección de la sucursal elegida (dentro del wrapper standalone).
* `.andreani-sucursales-row` — Fila del checkout clásico (legacy, solo en modo `auto`).
* `.andreani-dni-field-shortcode` — Wrapper del shortcode de DNI.

El CSS del plugin solo aplica estilos estructurales (layout, spacing). Color, tipografía y font-weight se heredan del tema.

**Ejemplo de override** desde *Apariencia → Personalizar → CSS adicional*:

`.andreani-sucursales-standalone { background: #f7f7f7; border-radius: 8px; padding: 1rem; }`
`.andreani-sucursales-select { border: 2px solid #333; }`

= Filters PHP =

* `andreani_sucursales_markup( string $html, int $instance_id, string $cp_destino )` — Modifica el markup del selector.
* `andreani_dni_field_markup( string $html, string $context, array $field_args )` — Modifica el markup del campo DNI del shortcode.
* `andreani_should_enqueue_checkout( bool $should, string $razon )` — Fuerza o bloquea el encolado eager de assets. `$razon` puede ser `is_checkout`, `force_enqueue` o `''`.

Ejemplo:

`add_filter( 'andreani_sucursales_markup', function( $html, $instance_id, $cp ) {`
`    return '<div class="mi-wrapper-custom">' . $html . '</div>';`
`}, 10, 3 );`

= Eventos JS =

Emitidos en `document` como eventos jQuery y `CustomEvent` nativo — compatibles con listeners tradicionales y modernos.

* `andreani:ready` — El plugin terminó de bindearse. `detail: { wcClassic: boolean }`.
* `andreani:cp-changed` — El CP cambió en algún input. `detail: { postcode }`.
* `andreani:sucursal-selected` — El usuario seleccionó una sucursal. `detail: { code, nombre, direccion, wrapper, postcode }`.
* `andreani:error` — Error de AJAX o validación. `detail: { code, message?, postcode?, wrapper? }`.

Ejemplo (jQuery):

`jQuery( document ).on( 'andreani:sucursal-selected', function( e, detail ) {`
`    console.log( 'Sucursal elegida:', detail.code, detail.nombre );`
`} );`

Ejemplo (vanilla JS):

`document.addEventListener( 'andreani:cp-changed', function( e ) {`
`    console.log( 'Nuevo CP:', e.detail.postcode );`
`} );`

= API JavaScript =

El objeto `window.andreaniCheckout` expone:

* `andreaniCheckout.ajaxUrl` — URL de admin-ajax.
* `andreaniCheckout.nonce` — token (acción `andreani_checkout_nonce`).
* `andreaniCheckout.i18n` — strings traducidas.
* `andreaniCheckout.refresh( wrapper? )` — recarga sucursales para un wrapper específico o todos si se omite el argumento.
* `andreaniCheckout.getSelected( wrapper? )` — devuelve `{ code, nombre, direccion }` de la selección actual o `null`.
* `andreaniCheckout.init( wrapper? )` — bindea selects inyectados dinámicamente (modals, popups de Elementor, etc.).

Ejemplo:

`const info = window.andreaniCheckout.getSelected();`
`if ( info ) console.log( info.nombre );`

= AJAX y nonce =

Endpoint público: `andreani_get_sucursales` (acepta usuarios no logueados).

Acepta dos nonces durante el ciclo 1.5.x:
* `nonce` con acción `andreani_checkout_nonce` (recomendado, propio del plugin).
* `security` con acción `update-order-review` (legacy de WC, para código custom que lo use).

El nonce legacy se remueve en una versión mayor futura.

= Compatibilidad =

* **Classic WooCommerce Checkout**: soporte completo en modo `auto` o `manual`.
* **Elementor / Divi / Bricks / Beaver Builder / Breakdance / Oxygen / cualquier builder futuro**: modo `manual` con shortcodes. Funciona sin configuración adicional.
* **WC Blocks Checkout (Gutenberg)**: soporte nativo desde 1.6.4 (selector de sucursal y DNI integrados por Store API, sin configuración).

= Migración desde 1.4.x =

La actualización a 1.5.0 es transparente — el upgrader corre automáticamente al entrar al panel de WP admin y:

1. Siembra los defaults de `checkout_modo` y `checkout_force_enqueue` si faltan.
2. Normaliza keys de `config_por_modo` a slug ascii-safe (fix del envío gratis con nombres acentuados).
3. Fuerza un re-login contra la API de Andreani para sincronizar credenciales con la nueva persistencia de sesión.

**Si tenías código custom que dependía de:**

* `andreani_has_shortcode` / `andreani_builder_meta_keys` (filters internos que no documentamos públicamente): **removidos**. Ya no son necesarios — los shortcodes encolan solos.
* El checkbox *Forzar carga de assets*: **sigue funcionando** pero casi nunca es necesario. Úsalo solo como último recurso.

== Changelog ==

= 1.8.6 =
* Nuevo: Promociones de envío, con descuento o recargo en porcentaje o monto fijo, por servicio, por provincia y con fechas de vigencia
* Nuevo: el costo adicional de cada modo de entrega pasa a ser una promoción de recargo llamada "Costo adicional"
* Nuevo: el pedido muestra la promoción aplicada y no cambia lo que factura Andreani
* Mejorado: el pedido ya no muestra datos internos del envío
* Corregido: el simulador de carrito muestra una sola etiqueta en Paquetería, como sale el envío

= 1.8.5 =
* Corregido: el Simulador de carrito y "Cómo armarlo" muestran la cantidad real de etiquetas y nombran tu paquete guardado solo para los productos que van adentro

= 1.8.4 =
* Corregido: al borrar un paquete guardado, los productos que lo usaban quedan limpios; si era un juego de cajas, el producto avisa que faltan cajas en lugar de cotizarse como una sola
* Corregido: "Ver mis envíos" ahora incluye los envíos del día elegido al filtrar solo por "Desde" o "Hasta", y "Hoy" muestra los de hoy
* Corregido: la clasificación de "Mis productos" se completa sola y se actualiza al cambiar un paquete
* Corregido: al volver a "En una caja" en el editor de un producto se conserva el paquete asignado
* Corregido: el cotizador de la ficha del producto se ve bien en más temas y celulares, y ya no tapa el contenido de abajo
* Corregido: el DNI ya no se pide en pedidos que no llevan envío, y la exportación a CSV es más segura y liviana

= 1.8.3 =
* Corregido: un producto con un paquete guardado asignado se cotizaba con una caja más grande que la suya

= 1.8.2 =
* Nuevo: Unidades por caja en cada producto y elección "Todo junto" o "Cada caja con su etiqueta" (Bigger)
* Nuevo: Paquetes guardados (cajas, sobres o bolsas, y juegos de cajas) que se asignan a los productos y se eligen para declarar el envío
* Nuevo: "Mis productos" rediseñado con "Cómo viaja" y simulador de carrito
* Nuevo: "Cómo armarlo" indica el paquete asignado
* Nuevo: Metabox de Andreani en el pedido y sección de Novedades
* Mejora: Menú y Configuración reordenados (Modos de entrega, En tu tienda, Etiquetas)
* Mejora: Estados de envío unificados entre "Ver mis envíos" y el pedido
* Cambio: Los productos que vienen en varias cajas ahora viajan siempre como Bigger, con una etiqueta por caja y solo a domicilio
* Fix: Los chips de estado de "Ver mis envíos" filtran por todos los estados
* Mejora: Al editar o borrar un paquete guardado, los productos se vuelven a clasificar en segundo plano

= 1.8.1 =
* Corregido: el cotizador de la ficha de producto ahora cotiza la variación elegida en los productos variables

= 1.8.0 =
* Nuevo: "Ver mis productos" muestra cómo viaja cada producto: el servicio (Paquetería o Bigger), cómo se acomodan varias unidades y un dibujo de la caja. Editás medidas y despacho en la misma fila, sin salir de la lista
* Nuevo: Simulador de carrito para ver cuántas cajas y etiquetas genera una compra y cotizarla antes de vender
* Nuevo: En "Ver mis envíos", cada pedido muestra cómo armar la caja, con una medida sugerida
* Nuevo: Campo SKU opcional para tus productos en Andreani, sin tocar el SKU de WooCommerce
* Mejora: La lista de productos, los filtros y los buscadores responden rápido aunque tengas miles de productos
* Mejora: El seguimiento automático se configura desde Configuración › Avanzado
* Mejora: El seguimiento automático consulta los envíos de los últimos 30 días y se pausa si la API de Andreani no responde. Los envíos más viejos se actualizan al verlos en "Ver mis envíos" o al abrir el pedido
* Mejora: La cotización y la validación de la credencial esperan como máximo 10 segundos a la API
* Fix: Una variación con su propia forma de despacho ya no toma las cajas del producto padre
* Fix: El filtro por estado de "Ver mis envíos" y la exportación filtran correctamente con el almacenamiento de pedidos de alto rendimiento (HPOS)
* Fix: El interruptor de seguimiento automático se mostraba vacío y se desactivaba al guardar
* Cambio: Requiere WooCommerce 7.1 o superior

= 1.7.0 =
* Mejora: Nuevo diseño del panel de Andreani, más claro y alineado a la imagen de Andreani

= 1.6.12 =
* Fix: La cotización ahora usa las medidas reales del producto, con decimales. Antes cada medida se redondeaba a centímetros enteros con un mínimo de 1 cm, y un producto muy fino, como un sticker o una lámina, se cotizaba como si ocupara mucho más espacio: al comprar muchas unidades el envío salía bastante más caro de lo que correspondía. Ahora la cotización declara las mismas medidas que el envío. Si tus productos tienen medidas con decimales (por ejemplo 2,4 cm), el costo de envío puede variar levemente, porque ya no se redondean
* Fix: El valor declarado del envío se toma del precio al que se vendió cada unidad en el pedido, y no del precio de lista del producto. Si usás precios por cantidad o mayoristas, antes se declaraba de más
* Nuevo: En la ficha del producto y en "Ver mis productos" aparece el bloque "Así se cotiza", que te muestra qué se le declara a Andreani para 1, 10, 50 y 200 unidades: cuántos bultos, qué volumen, el peso real y el peso aforado, y cuál de los dos pesos es el que se cobra. Se actualiza mientras cargás las medidas, sin guardar el producto

= 1.6.11 =
* Cambio: El peso mínimo que se declara a Andreani por paquete pasa de 1 kg a 1 g. Un producto liviano, como un sticker o un sobre, se cotiza y se despacha con su peso real en lugar de 1 kg, y aplica la tarifa que corresponde a ese peso. Un producto sin peso cargado se sigue cotizando, con el mínimo de 1 g

= 1.6.10 =
* Mejora: La ficha del producto ahora te pregunta directamente cómo se despacha, con tres opciones: en un solo paquete, varias unidades que viajan juntas (apilado), o una unidad que viaja en varias piezas. Antes eran dos configuraciones separadas de nombre parecido y era fácil elegir la equivocada: quien vendía productos que se apilan terminaba cargándolos como si cada unidad viajara en varias cajas, y el envío se cotizaba mucho más caro de lo que correspondía
* Mejora: Al cargar piezas adicionales el plugin te avisa que cada una se cotiza y se despacha como un paquete más por cada unidad vendida. Esa opción es para un producto que físicamente no entra en una sola caja, como un aire acondicionado con su unidad interior y su unidad exterior
* Mejora: La etiqueta "Bigger" o "Paquete estándar" (antes "Paquete común") pasó a estar debajo de las medidas y ahora te dice el motivo: qué peso o qué medida supera el límite, y cuál es ese límite. Antes figuraba al lado del título de los bultos y parecía que dependía de cuántos bultos cargabas
* Mejora: Si cargás una pieza con las mismas medidas que el bulto principal, el plugin te avisa que probablemente lo que buscás es el apilado, y te ofrece cambiarlo con un clic
* Fix: Una configuración de apilado incompleta ya no se pierde en silencio. Si el límite de unidades o el crecimiento de la pila no estaban bien cargados, el plugin guardaba igual y después descartaba la configuración sin avisar, y el producto seguía cotizando una caja por unidad. Ahora el error se muestra junto al campo y el guardado se detiene
* Mejora: "Probar cotización" en "Ver mis productos" cotiza con lo que tenés cargado en pantalla, sin guardar el producto, y la cantidad de unidades puede ser cualquiera entre 1 y 99 en los tres modos de despacho
* Fix: Una configuración de varias piezas sin ninguna pieza completa ya no se guarda volviendo a un solo paquete sin avisar: el plugin avisa junto al bloque de piezas y conserva lo que estaba guardado
* Fix: Los errores de validación del servidor, como un apilado inválido o un código postal vacío, ahora se muestran con su mensaje en vez de un error de red genérico

= 1.6.9 =
* Fix: El peso que se declara a Andreani nunca es menor a 1 kg. Un producto que pesaba menos, o que no tenía el peso cargado, podía despacharse con un peso muy por debajo del real: en tiendas que miden en gramos, con apenas unos gramos. El mínimo se aplica al bulto, así que si el comprador lleva varias unidades se sigue declarando el peso real de todas
* Fix: Un producto sin peso cargado ya no deja el checkout sin opciones de envío. Antes Andreani no aparecía y parecía un problema de cobertura del código postal; ahora se cotiza con el mínimo de 1 kg

= 1.6.8 =
* Nuevo: Apilado de bultos. Si vendés productos que se encastran entre sí (sillas, macetas, cajones), ahora podés indicar hasta cuántas unidades entran en una pila y cuánto crece esa pila por cada unidad adicional. Cuando alguien compra varias, el envío se cotiza y se despacha como una sola pila en lugar de una unidad suelta por producto
* Nuevo: Lo configurás en la ficha del producto, pestaña "Envío", o desde "Ver mis productos" si querés cargarlo variación por variación. Un producto se envía en varios bultos o se apila, pero no las dos cosas: si tiene bultos adicionales cargados, el panel te lo avisa y el apilado no se aplica
* Mejora: La etiqueta "Bigger" ahora contempla la pila completa. Antes, un producto apilable que superaba los límites recién al apilarse figuraba como "Paquete común"
* Fix: Un mismo pedido ya no puede generar dos envíos. Si el comprador recargaba la página de gracias, o la pasarela de pago avisaba dos veces, el pedido se daba de alta dos veces y quedaban dos números de seguimiento para la misma venta
* Fix: Los clientes con cuenta corporativa vuelven a ver la opción Bigger en el checkout. Si el contrato Bigger se había dado de alta después de la última vez que la tienda se conectó con Andreani, el checkout mostraba el resto de las opciones pero nunca Bigger, sin ningún aviso. Ahora los contratos se interpretan en cada cotización, así que la opción aparece sin necesidad de volver a conectar la cuenta

= 1.6.7 =
* Fix: "Ver mis envíos" volvía a cargar muy lento, o directamente fallaba por tiempo, en tiendas con muchos pedidos. Las estadísticas y el listado ahora solo recorren los pedidos enviados con Andreani

= 1.6.6 =
* Fix: Andreani vuelve a aparecer en el checkout cuando el carrito mezcla un producto grande (Bigger) con uno de paquetería. Antes, agregar cualquier producto chico a un carrito que ya tenía uno voluminoso hacía desaparecer la opción de envío sin ningún aviso; ahora el carrito se cotiza completo, como un único envío Bigger
* Mejora: El botón "Actualizar contratos" ya no aparece duplicado. Queda solo en la pestaña "Servicios", que es donde ves y configurás tus contratos
* Mejora: Si un producto del carrito no tiene el peso o alguna medida cargada, Andreani no puede cotizar y ahora te lo dice: en WooCommerce > Estado > Registros aparece qué producto es y qué dato le falta. Antes la opción de envío simplemente no aparecía y no había forma de saber por qué

= 1.6.5 =
* Fix: Validación incorrecta en la configuración del plugin

= 1.6.4 =
* Nuevo: Pestaña "Mi cuenta" — los datos de tu cuenta de Andreani, la dirección desde la que despachás y la sucursal de origen, todo en un solo lugar
* Nuevo: Podés fijar la sucursal desde la que salen tus envíos. Si no elegís ninguna, Andreani la sigue asignando por tu código postal, igual que antes
* Fix: La etiqueta vuelve a mostrar el nombre y la dirección de tu tienda como remitente. Antes salía el titular de la cuenta de Andreani y la dirección de una sucursal. El nombre lo configurás en "Mi cuenta" y se precarga con el de tu empresa en Andreani
* Mejora: La sucursal de origen que se ve en el detalle del pedido ahora es la que realmente se usó en el alta, y se actualiza si volvés a generar el envío
* Fix: Un código postal de origen en formato CPA (ej: C1425ABC) ya no rompe el alta de los envíos con un error que parecía de contratos
* Nuevo: Soporte nativo del checkout por bloques de WooCommerce. El selector de sucursal de retiro y el campo DNI se integran solos, sin configurar nada; el checkout clásico sigue igual
* Mejora: El plugin declara compatibilidad con el checkout por bloques (ya no aparece como incompatible en WooCommerce)
* Nuevo: Botón "Pagar" en la grilla de envíos (en la barra de acciones y en cada envío pendiente de pago) que te lleva al portal de Andreani Pymes para pagar los envíos generados
* Mejora: Se quita la acción masiva "Marcar enviados" de la grilla; el estado se sincroniza solo desde la 1.6.0

= 1.6.3 =
* Fix: Cuando tu servidor no logra conectarse con Andreani, el plugin ahora te lo dice con claridad en vez de avisarte que la Credencial ID no es válida. Antes las dos situaciones mostraban el mismo mensaje y te mandaban a regenerar una credencial que estaba bien
* Mejora: Los errores quedan registrados en WooCommerce > Estado > Registros aunque el Modo Debug esté apagado, así podés ver qué falló al configurar la credencial. Los datos de tus compradores se siguen registrando solo con el Modo Debug activo

= 1.6.2 =
* Fix: El costo del envío ahora respeta la unidad de medida configurada en tu tienda. Si tenías las dimensiones de tus productos cargadas en milímetros, metros o pulgadas, la cotización y el alta del envío se calculaban con un tamaño equivocado y el precio podía salir muy por encima del real. Ahora se convierten solas y no tenés que cambiar nada

= 1.6.1 =
* Fix: La sincronización automática de seguimiento ya no genera advertencias ni sobrecarga la base de datos en tiendas con el almacenamiento de pedidos clásico al usar WooCommerce 9.2 o superior. La consulta de pedidos ahora funciona con ambos modos de almacenamiento (clásico y HPOS)
* Mejora: Cuando hay muchos envíos por sincronizar, el proceso avanza de forma escalonada para no sobrecargar la tienda
* Nuevo: Interruptor para activar o desactivar la sincronización automática de seguimiento desde la grilla de envíos

= 1.6.0 =
* Nuevo: Descarga de etiquetas en PDF para clientes PyME y Middle Market (antes disponible solo para Corporativos)
* Nuevo: Configuración del formato de impresión de etiquetas — A4 o etiqueta térmica (Zebra) y cantidad por página
* Nuevo: Descarga masiva de etiquetas desde la grilla de envíos
* Nuevo: Seguimiento en tiempo real — el número de seguimiento aparece en el pedido apenas Andreani lo genera, sin recargar la página
* Nuevo: Estado logístico real del envío (pendiente de ingreso, en camino, listo para retirar, entregado, no entregado) en la grilla y el detalle, actualizado automáticamente desde Andreani
* Nuevo: Panel "Ver mis productos" — revisá el peso y las dimensiones de tus productos, encontrá los que no tienen esos datos cargados (los que impiden cotizar) y probá la cotización por código postal para verificar que funcione
* Mejora: Detalle del pedido rediseñado, con línea de tiempo del envío y estado de pago siempre al día
* Mejora: El estado del envío ya no se carga a mano — se sincroniza solo; se quitó el paso manual de "marcar como enviado"

= 1.5.3 =
* Fix: Productos con alguna dimensión menor a 1 cm (ej. sobres o bolsas planas) ahora cotizan correctamente en lugar de ocultar Andreani en el checkout
* Fix: Al vincular la credencial por primera vez, el Código Postal de origen se valida correctamente sin necesidad de guardar dos veces

= 1.5.2 =
* Nuevo: Soporte para clientes Middle Market
* Nuevo: Hidratación de la grilla de envíos contra el back de Andreani
* Nuevo: Paginación en la grilla de envíos
* Mejora: Indicador de error en la grilla para los envíos que no se pudieron empaquetar
* Mejora: Filtros de la grilla simplificados
* Fix: Reintento de envíos ahora respeta el cliente activo del plugin cuando se cambia de tipo de cliente entre la creación de la orden y el retry
* Fix: El estado en la grilla ya no queda desfasado al marcar un envío como enviado

= 1.5.1 =
* Rediseño de la grilla de envíos con filtros agrupados y buscador más compacto
* Nueva columna Servicio con iconos por modo de entrega
* Filtros de rango de fechas personalizado y vista por defecto del día
* Más datos visibles en columnas Cliente y Destino
* Fix: Corrección en envíos desde checkouts no clásicos (WC Blocks y similares)
* Fix: Compatibilidad con HPOS en conteos de envíos
* Seguridad: Verificación de certificado SSL en llamadas a la API
* Mejoras en diagnóstico de errores

= 1.5.0 =
* Rediseño del panel de administración.
* Mejoras en la grilla de envíos.
* Mejoras en la gestión de estados y filtros.
* Soporte de shortcodes para page builders.
* Mejoras en el diagnóstico de errores.
* Arreglos en envíos gratis y reintentos.

= 1.4.10 =
* Mejora en compatibilidad del selector de sucursales con temas y plugins de checkout personalizados
* Fix: Corrección en reintento de envíos fallidos desde la grilla de envíos
* Detección automática de producto Bigger en configuración de producto

= 1.4.9 =
* Mejoras de rendimiento en la grilla de envíos y configuración del plugin
* Optimización en la carga de productos y órdenes en tiendas con alto volumen

= 1.4.8 =
* Mejora en el diagnóstico de errores al generar envíos
* Fix: Corrección de error en productos sin precio o dimensiones cargadas

= 1.4.7 =
* Nueva configuración de múltiples bultos por producto desde la ficha de producto
* Fix: Corrección de error al agregar productos sin peso o dimensiones al carrito
* Mejoras de interfaz y estilos en configuración

= 1.4.6 =
* Rediseño de la grilla de envíos con carga asíncrona y mejoras visuales
* Nueva opción para volver un envío a estado pendiente
* Mejoras en búsqueda y manejo de direcciones

= 1.4.5 =
* Nueva funcionalidad: Cotizador de envío en páginas de producto
* Mejora: Configuración individual de costo adicional y envío gratis por modo de entrega
* Fix: Correcciones en la visualización de información de cliente

= 1.4.4 =
* Fix: Corrección de duplicación de costos adicionales en el total
* Mejoras de interfaz y estilos en configuración

= 1.4.3 =
* Nueva funcionalidad: Configuración por modo de entrega con costos adicionales personalizados
* Mejoras de interfaz y estilos en configuración

= 1.4.2 =
* Nueva columna "Servicio" en la grilla de envíos
* Mejoras de interfaz y estilos en configuración

= 1.4.1 =
* Nueva funcionalidad para activar y desactivar contratos de forma individual
* Rediseño del selector de contratos en la configuración del método de envío
* Mejoras visuales en el panel de administración

= 1.4.0 =
* Nueva grilla administrativa para gestionar todos los envíos de Andreani desde un solo lugar
* Mejora en la visualización de tracking e información de envíos
* Mejoras internas de rendimiento y estabilidad

= 1.3.0 =
* Nueva funcionalidad: Configuración de monto mínimo personalizado para envío gratis por método de envío
* Nueva herramienta administrativa: Botón para refrescar contratos corporativos vía AJAX sin recargar la página
* Mejoras en la interfaz de administración con mejor feedback visual y estados de carga
* Optimización en el manejo de contratos Andreani con validación mejorada

= 1.2.0 =
* Detección automática del tipo de cliente (Pyme/Corporativo) al validar credenciales
* Nueva opción para configurar envíos gratuitos a partir de un monto mínimo
* Modo debug: registro de logs en WooCommerce > Estado > Logs para diagnóstico
* Mejoras en la interfaz de configuración del plugin

= 1.1.0 =
* Versión inicial publicada en el repositorio de WordPress

== Upgrade Notice ==

= 1.8.6 =
Nuevas promociones de envío. El costo adicional de cada modo de entrega se pasa solo a una promoción de recargo; no cambia lo que factura Andreani.

= 1.8.5 =
Corrige la cantidad de etiquetas y el paquete sugerido en el Simulador de carrito y en Cómo armarlo.

= 1.8.4 =
Corrige paquetes guardados borrados, filtros de fecha en Ver mis envíos, el cotizador de la ficha y el DNI en pedidos sin envío.

= 1.8.3 =
Corrige que un producto con un paquete guardado asignado se cotizaba con una caja más grande que la suya.

= 1.8.2 =
Nuevos paquetes guardados y unidades por caja. Atención: los productos que vienen en varias cajas ahora viajan siempre como Bigger (una etiqueta por caja, solo a domicilio), por lo que su cotización puede cambiar.

= 1.8.0 =
Nueva pantalla de productos: ves cómo viaja cada producto, simulás un carrito y en cada pedido te sugerimos la caja. Tus productos se analizan solos en segundo plano; no tenés que configurar nada.

= 1.7.0 =
Nuevo diseño del panel de Andreani. Todo sigue funcionando igual y no tenés que configurar nada.

= 1.6.12 =
La cotización pasa a usar las medidas reales del producto, con decimales. Si vendés productos muy finos o muy chicos, el costo de envío que ven tus clientes puede bajar; si tus medidas tienen decimales, puede variar levemente. No tenés que configurar nada.

= 1.6.10 =
La configuración de bultos de la ficha del producto se simplificó en una sola pregunta sobre cómo se despacha. Lo que ya tenías cargado se sigue leyendo igual y no tenés que reconfigurar nada.

= 1.6.7 =
Corrige la carga de "Ver mis envíos" en tiendas con muchos pedidos. La actualización es transparente y no tenés que configurar nada.

= 1.6.6 =
Si vendés productos grandes junto con otros más chicos, Andreani vuelve a cotizar cuando el comprador los mezcla en el mismo carrito. La actualización es transparente y no tenés que configurar nada.

= 1.6.5 =
Corrige una validación incorrecta en la configuración del plugin.

= 1.6.4 =
Nueva pestaña "Mi cuenta" con la dirección de origen y la posibilidad de fijar la sucursal desde la que despachás, y soporte nativo del checkout por bloques de WooCommerce (el selector de sucursal y el DNI se integran solos). Si no configurás nada, tus envíos siguen funcionando exactamente igual que hasta ahora.

= 1.6.0 =
Etiquetas para clientes PyME y Middle Market, formato de impresión configurable (A4 o térmica), descarga masiva y seguimiento en tiempo real con estado logístico automático. La actualización es transparente.
