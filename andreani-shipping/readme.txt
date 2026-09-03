=== Andreani WooCommerce ===
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Copyright: 2025 Andreani.com
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.6.7
Contributors: integracionandreani
Donate link:
Tags: woocommerce, shipping, andreani, envio, etiquetas

Cotizá tarifas, generá envíos, imprimí etiquetas y seguí tus pedidos con Andreani. El plugin oficial para WooCommerce en Argentina.

== Description ==

Plugin **oficial de Andreani** para integrar sus servicios de envío en tu tienda WooCommerce. Tus clientes eligen Andreani como opción de entrega durante el checkout, y vos gestionás todos los envíos —cotización, generación, etiquetas y seguimiento— desde el panel de administración, sin salir de WooCommerce.

**Disponible exclusivamente para tiendas con zonas de envío en Argentina.** Funciona para clientes **PyME, Corporativos y Middle Market**.

= Novedades de la versión 1.6.4 =

* **Pestaña "Mi cuenta"** — los datos de tu cuenta de Andreani, la dirección desde la que despachás y la sucursal de origen, todo en un solo lugar.
* **Sucursal de origen a tu elección** — fijá la sucursal desde la que salen tus envíos; si no elegís ninguna, Andreani la sigue asignando por tu código postal, igual que antes.
* **Tu tienda como remitente** — la etiqueta vuelve a mostrar el nombre y la dirección de tu tienda; el nombre se precarga con el de tu empresa en Andreani y lo podés editar.
* **Checkout por bloques de WooCommerce** — soporte nativo: el selector de sucursal de retiro y el DNI se integran solos, sin configurar nada. El checkout clásico sigue igual.
* **Botón "Pagar"** — desde la grilla de envíos vas directo al portal de Andreani Pymes a pagar los envíos pendientes.
* **Códigos postales en formato CPA** — un código postal de origen como C1425ABC ya no rompe el alta de los envíos.

= Qué podés hacer =

**En el checkout**

* Método de envío "Andreani Envios" integrado en las zonas de WooCommerce.
* Cálculo de tarifas en tiempo real durante el checkout.
* Selección de sucursales Andreani para envíos Puerta a Sucursal.
* Funciona con el checkout clásico y con el checkout por bloques de WooCommerce: el selector de sucursal y el DNI se integran solos.
* Cotizador opcional en la página de producto.
* Envío gratis configurable por monto mínimo y por modo de entrega.

**Gestión de envíos**

* Generación automática de la orden de envío al confirmar el pedido.
* Grilla administrativa para gestionar todos los envíos desde un solo lugar.
* Reintento de envíos que no se pudieron generar.
* Botón "Pagar" para ir al portal de Andreani Pymes a pagar los envíos pendientes, desde la grilla.
* Exportación a CSV de los envíos.

**Origen y remitente**

* Pestaña "Mi cuenta" con los datos de tu cuenta de Andreani, la dirección desde la que despachás y la sucursal de origen.
* Sucursal de origen fijable, o automática por tu código postal.
* La etiqueta sale con el nombre y la dirección de tu tienda como remitente.

**Etiquetas**

* Descarga de etiquetas en PDF para clientes PyME, Middle Market y Corporativos.
* Formato de impresión configurable: A4 o etiqueta térmica (Zebra), con cantidad de etiquetas por página.
* Descarga masiva desde la grilla.

**Seguimiento**

* Número de seguimiento visible en el pedido apenas se genera.
* Estado logístico real del envío, actualizado automáticamente desde Andreani.
* Línea de tiempo del envío en el detalle del pedido.

**Productos**

* Panel "Ver mis productos" con el peso, las dimensiones y los bultos de cada producto.
* Filtro para encontrar rápido los productos sin peso o sin dimensiones — los que no pueden cotizar en el checkout.
* Probador de cotización por código postal para verificar que un producto cotiza correctamente, sin tener que generar una orden.

**Compatibilidad**

* Compatible con Elementor, Divi, Bricks y otros page builders.
* Compatible con HPOS (High-Performance Order Storage) de WooCommerce.
* Compatible de forma nativa con el checkout por bloques (Gutenberg / WC Blocks) y con el checkout clásico.
* API pública para desarrolladores (ver sección "Para desarrolladores").

= Antes de empezar =

Necesitás generar tu **Credential ID** según tu tipo de cliente:

* **Clientes PyME:** https://pymes.andreani.com/integraciones/ (seleccioná la opción WooCommerce)
* **Clientes Corporativos:** https://corporativo.andreani.com/woocommerce

Una vez generada la credencial, configurás el método "Andreani Envios" en *WooCommerce → Ajustes → Envío → Zonas de envío*.

== Installation ==

= Requisitos previos =

* WordPress 5.8 o superior y WooCommerce 5.0 o superior.
* PHP 7.4 o superior.
* Una tienda con zonas de envío en Argentina.
* Tu **Credential ID** de Andreani (ver más abajo cómo obtenerlo).

= Paso 1 — Instalar el plugin =

Desde el panel de WordPress: andá a *Plugins → Añadir nuevo*, buscá **"Andreani WooCommerce"**, instalalo y activalo. También podés subir el archivo ZIP manualmente desde *Plugins → Añadir nuevo → Subir plugin*.

= Paso 2 — Obtener tu Credential ID =

Generá tu credencial según tu tipo de cliente:

* **PyME:** https://pymes.andreani.com/integraciones/ (elegí la opción WooCommerce).
* **Corporativo:** https://corporativo.andreani.com/woocommerce

= Paso 3 — Configurar el método de envío =

1. Andá a *WooCommerce → Ajustes → Envío → Zonas de envío*.
2. Entrá a la zona donde querés ofrecer Andreani (o creá una nueva para Argentina).
3. Agregá el método de envío **"Andreani Envios"**.
4. Editá el método y pegá tu **Credential ID**. El plugin detecta automáticamente tu tipo de cliente al validar la credencial.
5. Ajustá las opciones que necesites: contratos, costos adicionales, envío gratis, cotizador en producto y formato de impresión de etiquetas.

= Paso 4 — ¡Listo! =

Tus clientes ya pueden elegir Andreani en el checkout. Gestioná todos tus envíos desde *Andreani → Envíos* en el panel de administración.

== Frequently Asked Questions ==

= ¿Qué necesito para empezar a usar el plugin? =

Una tienda WooCommerce con zonas de envío en Argentina y tu **Credential ID** de Andreani. Lo generás en https://pymes.andreani.com/integraciones/ (PyME) o https://corporativo.andreani.com/woocommerce (Corporativo).

= ¿El plugin sirve para clientes PyME y Corporativos? =

Sí. Soporta clientes **PyME, Corporativos y Middle Market**. El plugin detecta tu tipo de cliente automáticamente cuando validás tu Credential ID, y habilita las funciones que correspondan.

= Andreani no aparece como opción en el checkout. ¿Qué reviso? =

Verificá que: (1) el método "Andreani Envios" esté agregado a la **zona de envío** que cubre el código postal del comprador, (2) tu **Credential ID** esté cargado y validado, y (3) el producto tenga **peso y dimensiones** cargados (sin esos datos no se puede cotizar). Desde la versión 1.6.0, el panel **Ver mis productos** (en el menú *Andreani*) te muestra de un vistazo qué productos no tienen peso o dimensiones, y te deja probar la cotización por código postal. Activá el modo debug (ver más abajo) para ver el detalle en los logs.

= ¿Puedo imprimir etiquetas siendo cliente PyME? =

Sí. Desde la versión 1.6.0, la descarga de etiquetas en PDF está disponible para clientes **PyME, Middle Market y Corporativos**. Antes era exclusivo de Corporativos.

= ¿Qué formatos de impresión de etiquetas soporta? =

Podés elegir entre **A4** o **etiqueta térmica (Zebra)**, y configurar la **cantidad de etiquetas por página**. Se configura en las opciones del método de envío.

= ¿Puedo descargar varias etiquetas a la vez? =

Sí. Desde la **grilla de envíos** (*Andreani → Envíos*) seleccionás los envíos que quieras y descargás todas las etiquetas en una sola operación.

= ¿Cómo veo el estado de un envío? =

El estado logístico (pendiente de ingreso, en camino, listo para retirar, entregado, no entregado) se muestra en la grilla de envíos y en el detalle de cada pedido, y se **actualiza automáticamente** desde Andreani. El número de seguimiento aparece apenas Andreani genera el envío.

= ¿Funciona con mi page builder (Elementor, Divi, Bricks)? =

Sí. El plugin es compatible con Elementor, Divi, Bricks y otros builders mediante shortcodes (`[andreani_sucursales]` y `[andreani_dni_field]`), sin configuración adicional. Ver la sección "Para desarrolladores".

= ¿Es compatible con HPOS (almacenamiento de pedidos de alto rendimiento)? =

Sí, el plugin es totalmente compatible con HPOS de WooCommerce.

= ¿Funciona con el checkout de bloques (Gutenberg / WC Blocks)? =

Sí. Desde 1.6.4 el soporte es nativo y automático: el selector de sucursal de retiro y el campo DNI se integran solos en el checkout por bloques, sin configurar nada. El checkout clásico y el modo manual con shortcodes siguen funcionando igual que antes.

= ¿Cómo activo los logs para diagnosticar un problema? =

Activá el modo debug en la configuración del plugin. Los registros quedan disponibles en *WooCommerce → Estado → Logs*.

== Screenshots ==

1. Grilla de envíos: gestioná todos tus envíos de Andreani desde un solo lugar, con estado logístico, filtros y descarga de etiquetas.
2. Detalle del pedido: número de seguimiento, línea de tiempo del envío y estado de pago siempre al día.
3. Configuración del método de envío "Andreani Envios": Credential ID, contratos y opciones.
4. Selección de sucursal Andreani durante el checkout.
5. Configuración del formato de impresión de etiquetas (A4 o térmica Zebra).
6. Cotizador de envío en la página de producto.

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
