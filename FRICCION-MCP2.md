# Registro de fricción — mcp2.webs27.online

Sitio nuevo desde cero con el kit **RentForge** (alquiler de maquinaria pesada de obra).
Ingenio MCP **1.10.1**, 164 herramientas. WordPress 7.1 · PHP 8.3.33 · Hello Elementor ·
Elementor 4.2.4 + Pro 4.2.3 · WooCommerce 11.1.0 · Rank Math Pro · ACF · Contact Form 7.

Mismo formato que el registro de mcp1: se anota sobre la marcha, no se apaña nada en
silencio, y una aprobación que salta donde debe no se anota.

---

# Fase 0 — Reconocimiento e importación del kit

## ✅ `elementor_template_import` con `ruta`: el bloqueo total de la ronda anterior, resuelto

En mcp1 esto fue **el** muro: los JSON de un kit pesan uno o dos megas y sólo se podían
pasar como objeto dentro de la llamada. Era imposible y lo dejé anotado como imposible.

**Llamada:**

```json
{"ruta":"uploads/template-kits/f9de0b00293efbbedd6a1bacfc20c4e7/templates/home.json",
 "titulo":"RentForge — Home","tipo":"page"}
```

**Respuesta, entera:**

```json
{"template_id":165,"titulo":"RentForge — Home","tipo":"page","estado":"draft",
 "nota":"Se creó como borrador…","elementos":177,
 "editar_en":"https://mcp2.webs27.online/wp-admin/post.php?post=165&action=elementor"}
```

Doce plantillas importadas, una llamada cada una, cinco líneas de respuesta cada una. La más
grande, Home, **177 elementos**. Coste total del trabajo que la vez pasada no se podía hacer:
doce llamadas y unas 900 palabras de respuesta sumadas.

Nada que objetar. Es el cambio más grande de los tres que he probado hasta ahora.

## ✅ La nota del import es más pesimista de lo necesario

Cada import contesta: *«Se creó como borrador. Publícala desde wp-admin cuando la hayas
revisado»*. Suena a que publicar no se puede desde aquí.

**Llamada:** `content_update(post_id:161, status:"publish")`
**Respuesta:** `{"fields_updated":["post_status"], …, "deshacer":{"revision":173}}`

Se puede, y con deshacer. Sugerencia de redacción: *«Se creó como borrador: publicar es una
decisión editorial. Cuando la hayas revisado, `content_update` con `status: "publish"`»*.
Dice lo mismo y no manda a nadie a wp-admin.

## A No se puede instalar un plugin, y el kit los exige

El manifiesto del kit declara cuatro plugins necesarios. `plugin_list` dice que faltan dos:

| Necesario por el kit | Estado |
|---|---|
| Elementor 4.2.4 | ✅ |
| ElementsKit Lite 4.0.3 | ✅ (hay 4.0.5) |
| **Gum Addon for Elementor 1.3.18** | ❌ no instalado |
| **MetForm 4.3.0** | ❌ no instalado |

**Qué quería hacer:** instalarlos, que es lo que hace cualquiera al importar un kit.

**Qué hay:** `plugin_list`, que lista. Nada que instale ni active. Busqué en el catálogo por
«plugin», «install» y «activate» y sólo salen `snippet_activate` (fragmentos de código) y
`ability_run`. La cuenta conectada **sí** tiene `install_plugins` y `activate_plugins`: el
límite es del catálogo, no del permiso.

**Qué hice:** contar el daño con `file_search` sobre los JSON del kit, que es barato y exacto:

```
metform          → single-detail, coming-soon, contact, book-equipment, home   (5)
gum_posts_grid   → testimonials, pricing, services-detail, blog, services, home (6)
gum_post_image   → single-detail                                                (1)
```

**Doce widgets muertos** repartidos por seis de las plantillas que necesito. Se dibujan como
huecos vacíos: el visitante no ve nada, y el HTML tampoco lo denuncia.

**Coste:** doce sustituciones a mano que no tendrían que existir. Voy a hacerlas con
`elementor_element_replace`, que para eso está, y contaré aparte lo que cuesta.

**Mi lectura:** hueco de diseño, y el más caro de este encargo. Importar un kit es un flujo
completo: descomprimir, **instalar lo que el kit pide**, importar las plantillas, aplicar los
estilos globales. El servidor ahora hace la tercera parte muy bien y no hace la segunda. Con
`plugin_install(slug)` + `plugin_activate(slug)` —con aprobación humana, que instalar código
de terceros es exactamente lo que debe pedirla— el kit entraría entero. Sin ellas, todo kit
comprado llega cojo y hay que reconstruir a mano lo que el kit ya traía hecho.

Aviso aparte, porque es una decisión y no un fallo: no voy a pedir que los instalen a mano.
Contact Form 7 **ya está activo**, y en mcp1 acabé migrando todos los formularios de MetForm
a CF7 de todas formas (fase 8 de aquel registro). Aquí voy directo a CF7 y sustituyo los
`gum_posts_grid` por widgets de Elementor Pro o de ElementsKit, que están. Lo anoto para que
conste que es una vuelta, no el camino recto.

## B `elementor_text_audit` no acepta `post_ids`, ni en 1.10.0 ni en 1.10.1

Es la herramienta que pedí en el cierre del registro de mcp1 y la que más ilusión me hacía
probar. No he conseguido ejecutarla ni una vez.

**Llamadas, todas con el mismo resultado:**

```
elementor_text_audit(post_ids:[112,149,150])          ← en mcp1, 1.10.0
elementor_text_audit(post_ids:[112])                  ← en mcp1
elementor_text_audit(post_ids:["112"])                ← en mcp1
elementor_text_audit(post_ids:[112], buscar:"grama")  ← en mcp1
elementor_text_audit(post_ids:[161])                  ← en mcp2, 1.10.1
```

**Respuesta, siempre idéntica:**

```
[not_found] No hay ningún contenido con el identificador 0.
```

**Lo que sé:** el mensaje habla de **un** identificador y dice **0**. Eso no es «la lista
está vacía», es «he convertido algo a entero y me ha salido cero», que es lo que pasa al
hacer `(int)` sobre una cadena no numérica: `(int)"[161]"` es `0`.

**Lo que descarta que sea el transporte en general:** en esta misma sesión funcionan otros
parámetros de lista. `elementor_element_update` con `cambios` (lista de objetos) escribió dos
elementos y contestó `"aplicados":2`. En mcp1 funcionaron `condiciones` (lista de cadenas) y
`palabras_clave` (lista de cadenas). Lo único que distingue a `post_ids` es que es **lista de
enteros**.

**Qué hice:** nada, está bloqueada. Para lo que necesitaba —saber qué widgets muertos hay y
dónde— usé `file_search` sobre los JSON del kit, que responde a otra pregunta pero me sirvió
para ésta.

**Coste:** 5 llamadas perdidas, y me quedo sin la herramienta para la parte que viene, que es
justo donde hacía falta: repasar el texto de doce plantillas de kit.

**Mi lectura:** fallo del plugin, casi seguro en la lectura del parámetro, no en la lógica de
la herramienta. Pero no puedo demostrarlo desde aquí sin leer el código, que no toca.

**Lo que pido, y es la primera vez que uso el comodín de esta ronda:** que la sesión de
Claude Desktop conectada a este mismo sitio ejecute
`elementor_text_audit(post_ids:[161])`. Si allí funciona, el fallo está entre mi cliente y el
plugin; si allí falla igual, está en el plugin. Es un experimento de una llamada que separa
las dos hipótesis, y yo no lo puedo hacer solo.

## C `file_search` dice que salta uploads, y no lo salta

Detalle pequeño con consecuencia real. La descripción de `file_search` termina: *«Salta
.git, node_modules, vendor, cache y **uploads**»*. La de `elementor_template_import` dice:
*«Búscalo con file_search si no sabes dónde está»*. Y los kits viven en
`uploads/template-kits/`.

Las dos descripciones se contradicen, y si me creo la primera no llego a intentar la segunda.
Lo intenté igual, dándole `ruta` explícita, y **funcionó**: encontró los 19 JSON revisando 41
ficheros.

**Mi lectura:** lo que falla es la descripción, no la herramienta. Debería decir que salta
uploads **salvo que le des una ruta dentro**, que es lo que de verdad hace y es el
comportamiento correcto.

## Estado al cerrar la fase 0

Importadas y en la biblioteca: Header (161, publicada), Footer (163, publicada), Home (165),
About (167), Services (169), Services Detail (171), Contact (173), Pricing (175), Book
Equipment (177), Blog (179), Team Detail (181), Single Detail (183), 404 (185),
Testimonials (187).

No importadas a propósito: `coming-soon` y los tres JSON de formularios, porque son de
MetForm y MetForm no está.
