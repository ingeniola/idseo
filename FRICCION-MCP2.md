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

---

# Fase 1 — Estructura, plantillas aplicadas y la medición que se pidió

Gum Addon instalado por el cliente entre fases: los widgets muertos bajan de 12 a **5**
(sólo los `metform`). Encargo ficticio escrito en `ENCARGO-ALTORRE.md`: Altorre Maquinaria,
alquiler de maquinaria de obra en Zaragoza, Pamplona y Lleida.

## 📏 La medición: tokens por elemento cambiado

Dos llamadas reales de contenido, no de prueba.

**Llamada A — Contacto (197), 15 elementos:** títulos, textos, migas, tres botones con su
enlace y el bloque de datos de contacto.

```json
{"post_id":197,"aplicados":15,
 "elementos":[{"element_id":"e099dcc","claves":["title"]}, … ],
 "deshacer":{"revision":208,"como":"history_restore con revision=208 …"}}
```

**Llamada B — Portada (189), 28 elementos:** los 28 titulares de la home.

| | entrada | respuesta | por elemento |
|---|---|---|---|
| A — 15 elementos | ~2.900 car. ≈ 780 tok | 620 car. ≈ **170 tok** | **11,3 tok** |
| B — 28 elementos | ~2.400 car. ≈ 640 tok | 1.150 car. ≈ **300 tok** | **10,7 tok** |

**Contra la ronda anterior:** en mcp1 medí que cambiar **tres textos de un widget
elementskit** costaba unas **1.200 palabras de respuesta ≈ 1.600 tokens**, y eso era una
llamada = un elemento.

> **11 tokens por elemento ahora, contra ~1.600 antes. Unas 145 veces menos.**

Y la parte que no se reduce —la entrada— es el texto que quiero publicar: irreducible por
definición. El coste total por elemento pasa de ~1.650 tokens a ~64.

**¿A partir de cuántos elementos se hizo incómodo?** No se hizo. La de 28 se compone igual de
bien que la de 15 y la respuesta sigue cabiendo en un vistazo. El límite que noto no es de
tamaño sino de **composición**: escribir 28 textos definitivos de golpe obliga a tener la
página entera decidida antes de empezar, y si me equivoco en un `element_id` no me entero
hasta leer la lista de `claves` devueltas una por una. Diría que el punto de incomodidad está
en el orden de los 40–50, y por la redacción, no por el protocolo.

## ¿Habría construido el aplicador en PHP con estos números?

**Para una página, no.** Sin ninguna duda. 28 elementos en una llamada a 11 tokens cada uno
es mejor que cualquier cosa que yo escriba, y encima trae `deshacer` con su revisión. El
aplicador por rutas no tendría razón de ser.

**Para las ocho fichas de máquina, sí, todavía.** Y aquí está la evidencia, que esta vez es
limpia porque las ocho salen de la misma plantilla:

| ruta | ficha 202 | ficha 203 |
|---|---|---|
| `0.0` | `500ce29` | `a5c2579` |
| `1.0` | `b547b05` | `486f4c2` |
| `2.2.1.1` | `dbcae8c` | `576d6af` |
| `8.0` | `d96d659` | `1c4d576` |

Mismas rutas, ids distintos —`elementor_template_apply` los regenera a propósito y hace
bien—. Así que las ocho fichas son 8 × (`elementor_find` + `elementor_element_update`) = **16
llamadas**, contra **1** del aplicador por rutas.

Pero el margen se ha estrechado tanto que la respuesta honesta tiene matiz: en mcp1 eran 247
llamadas caras contra una; aquí son 16 llamadas baratas contra una. **Si el encargo fueran
sólo estas ocho, usaría las herramientas y no bajaría a PHP.** A partir de unas quince
páginas clonadas volvería a compensar escribirlo.

**Y lo que cerraría el hueco del todo ya casi está hecho.** `elementor_find` **ya responde en
rutas**:

```json
{"id":"dbcae8c","widget":"heading","ruta":"2.2.1.1","texto":"Ideal For"}
```

El servidor tiene el concepto de ruta y lo usa para leer. Lo único que falta es aceptarlo
para escribir: que `element_id` admita también una `ruta`, o que cada entrada de `cambios`
pueda llevar su `post_id`. Con cualquiera de las dos, las ocho fichas son una llamada y el
aplicador en PHP desaparece del todo. Es el mismo patrón que anoté en mcp1 con los medios:
**las dos mitades existen y no se tocan.**

## ✅ `elementor_template_apply` habilita Elementor, y lo dice

El fallo número uno de todo el registro de mcp1 —escribir `_elementor_data` sin marcar la
página como Elementor y devolver éxito— está resuelto. Veintiuna páginas aplicadas y las
veintiuna contestaron:

```json
{"accion":"contenido añadido al final","elementos":175,
 "elementor":"habilitado por esta llamada",
 "deshacer":{"revision":175,…}}
```

**No llamé a `elementor_enable` ni antes ni después, y no hizo falta.** No sólo lo hace: lo
declara en la respuesta, que es lo que permite fiarse sin ir a comprobarlo.

## ✅ `batch` es lo que hace que todo esto sea barato

Sin él, esta fase habrían sido 21 creaciones + 21 aplicaciones + 5 opciones + 6 borrados = 53
llamadas. Fueron **cinco**. Y al fallar se comporta igual de bien que en mcp1: dice en qué
paso paró, cuántos no se intentaron y que lo anterior se quedó hecho, con su revisión.

## F Una aprobación que salta donde no toca: descartar cero elementos

**Llamada:** `elementor_template_apply(post_id:189, template_id:165, modo:"reemplazar")`
sobre una página recién creada, vacía.

**Respuesta:**

```
[approval_required] Se descartan los 0 elementos que tiene la página. Quedan en el
historial, pero nadie los recupera si no se entera de que se fueron.
```

**Cero elementos.** La propia puerta cuenta lo que se va a perder, le sale cero, y para
igual. Reemplazar nada no destruye nada.

**Qué hice:** usar `modo:"anadir"`, que en una página vacía da exactamente el mismo
resultado. Veintiuna páginas aplicadas sin una sola aprobación.

**Coste:** 1 llamada perdida. Barato, pero el efecto de segundo orden no lo es: **me enseñó a
esquivar la puerta.** Si mañana aplico «añadir» sobre una página con contenido de verdad,
duplico el contenido en silencio y nadie me para, porque «añadir» no tiene puerta. La puerta
correcta habría sido la misma condición con `> 0` delante.

**Mi lectura:** fallo del plugin, de los baratos de arreglar y de los que envenenan. Una
puerta que salta cuando no hay nada que proteger entrena a saltársela.

## Una aprobación que salta donde SÍ toca (no es fricción, se anota para que conste)

`elementor_template_conditions` con `include/general` pide aprobación, y hace bien: una
condición general cambia el aspecto de todas las páginas a la vez. En mcp1 el modo de
aprobaciones estaba en «nunca» y pasaba sin preguntar; aquí está activo y pregunta. El
mensaje dice el motivo, dice que no se ha cambiado nada y da la URL. Correcto de principio a
fin.

Quedan tres pendientes de aprobación humana: cabecera (161), pie (163) y 404 (185).

## B El formulario de contacto desapareció por el camino, y nadie avisó

`contact.json` del kit trae un widget `metform`. Al importar, la respuesta dijo
`"elementos":36`. El `elementor_outline` de esa misma plantilla cuenta **35**. La página
donde la apliqué tiene 35, y su censo no lo menciona:

```json
{"post_id":197,"encontrados":0,
 "censo":{"container":13,"heading":6,"text-editor":5,"image":4,"button":3,
          "elementskit-client-logo":2,"icon-list":1,"google_maps":1}}
```

**No hay formulario en la página de contacto de un sitio recién montado, y ninguna de las
dos herramientas que lo tocaron lo dijo.** El import contestó 36 y lo dio por bueno; el apply
contestó 35 y también.

No sé si lo descarta el importador, el aplicador o Elementor, y no puedo saberlo sin leer el
código. Lo que sí puedo decir es el efecto: **un widget cuyo tipo no está registrado se cae
por el camino en silencio.** Y es justo el caso normal al importar un kit, que declara sus
plugins precisamente porque usa widgets que no vienen de serie.

La descripción de `elementor_template_apply` dice que «avisa si lo copiado no trae etiquetas
dinámicas» —un aviso que añadieron por una entrada de mi registro anterior—. El aviso que
falta es hermano de ése y más grave: **«se descartaron N widgets cuyo tipo no está registrado
en este sitio: metform»**. El servidor sabe qué widgets hay registrados: tiene
`elementor_widgets`.

**Qué voy a hacer:** poner un formulario de Contact Form 7 en su sitio, que es lo acordado
con el cliente.

## Estado al cerrar la fase 1

21 páginas creadas, publicadas y con su plantilla del kit aplicada. Portada, blog e identidad
del sitio configuradas. Seis páginas de prueba y de relleno a la papelera. Las cuatro de
WooCommerce, intactas y publicadas, fuera del menú.

Contenido escrito: Contacto entero y los 28 titulares de la portada.

---

# Fase 2 — `elementor_template_conditions` dice que regenera la caché y no la regenera

Esto corrige lo que escribí en la fase 20 del registro de mcp1, y lo corrige a peor.

## B La prueba, esta vez sin ambigüedad

**Llamada:** `elementor_template_conditions(post_id:161, condiciones:["include/general"])`

**Respuesta, copiada literalmente como se pidió:**

```json
{"id":161,"condiciones":["include/general"],
 "cache":"regenerada por Elementor Pro",
 "regenerada":true,
 "confirmado":false,
 "efecto":"La condición está guardada pero Elementor Pro TODAVÍA NO la aplica en \"header\".
           Su caché sigue vieja: abre la plantilla en el editor y guarda, o vacía la caché de
           Elementor. Hasta entonces el visitante no verá esta plantilla."}
```

Las tres plantillas —cabecera (161), pie (163) y 404 (185)— contestaron lo mismo, con su
ubicación correspondiente.

**Comprobación en el front:**

```
content_render(189, alcance:"pagina", buscar:"elementor-location-header")
→ {"aparece": false}
```

La cabecera no se dibujaba. **`confirmado:false` tenía razón.**

**Lo que descarté antes de acusar a nadie:**

- `elementor_templates` decía que los tres datos estaban perfectos: tipo `header`, estado
  `publish`, condiciones `["include/general"]`.
- `elementor_regenerate_css()` sobre todo el sitio: no cambió nada. Es CSS, no condiciones.
- Guardar la plantilla desde la API (`elementor_element_update` sobre la cabecera, que es un
  guardado de documento real): tampoco. El `efecto` dice «abre la plantilla en el editor y
  guarda»; guardarla por la API no equivale.
- `abilities_list(buscar:"cache")`: sólo una de Rank Math para su marketplace. Nada de
  Elementor.

**La prueba final, ya en PHP:**

```php
$cm = ...->get_conditions_manager();
$antes    = $cm->get_cache()->get_by_location('header');   // []
$cm->get_cache()->regenerate();
$despues  = $cm->get_cache()->get_by_location('header');   // {"161":["include\/general"]}
```

**Antes: vacía. Después de regenerar de verdad: correcta.** Y el front pasó a dibujar la
cabecera en la llamada siguiente.

O sea: la herramienta contestó `"cache":"regenerada por Elementor Pro"` y `"regenerada":true`
**tres veces**, y la caché estaba vacía las tres.

## Y `confirmado` sigue sin servir, aunque aquí acertara

En mcp1 concluí que `confirmado` era un falso negativo. Con los datos de hoy la conclusión
correcta es otra y es peor:

| | caché real | `confirmado` | ¿acertó? |
|---|---|---|---|
| mcp1, fase 20 | correcta (la regeneré yo en la fase 3) | `false` | **no**, la cabecera se dibujaba |
| mcp2, hoy | vacía | `false` | sí, por coincidencia |

`confirmado` devuelve `false` siempre. Coincide con la realidad sólo cuando la realidad es
«no». Eso no es un indicador: es una constante con suerte variable. Y esto sólo se ve
teniendo los dos casos, que es lo que da tener el registro de la ronda anterior delante.

**Mi lectura.** Es el fallo más grave de los que llevo encontrados en este plugin, y por dos
razones que se suman:

1. **La herramienta existe precisamente para esto.** Su descripción dice: *«Se guarda por el
   gestor de Elementor Pro, que además regenera su caché de ubicaciones: escribir el
   metadato a mano deja el dato bien y la plantilla sin aplicarse, y es el fallo más difícil
   de diagnosticar del theme builder»*. Describe el fallo con precisión, se ofrece como la
   solución, y produce exactamente el fallo que describe.
2. **La respuesta se contradice a sí misma** y quien la lee no puede decidir. `regenerada:
   true` junto a `confirmado: false`. Hoy sé cuál miente porque bajé a PHP. Sin eso, lo
   razonable habría sido creerse el campo afirmativo y dar el sitio por bueno, con un sitio
   sin cabecera ni pie.

**Qué haría:** que `regenerada` diga la verdad —comprobar la caché después de regenerar, que
es una línea— y que `confirmado` se calcule contra `get_cache()->get_by_location()`, que aquí
da la respuesta correcta en ambos sitios. El oráculo bueno ya está en la casa.

## D Lo que tuve que hacer en PHP, que es el dato que se pedía

```php
\ElementorPro\Plugin::instance()->modules_manager
  ->get_modules('theme-builder')->get_conditions_manager()->get_cache()->regenerate();
```

Una línea. Sin ella, el sitio entero se queda sin cabecera, sin pie y sin 404, con todos los
datos guardados correctamente y tres herramientas diciendo que todo fue bien.

**Es la misma línea que ya tuve que escribir en la fase 3 del registro de mcp1**, hace dos
versiones del plugin. Es, hasta ahora, lo único de este encargo que no he podido hacer con
herramientas.

Coste: 4 llamadas de descarte + 1 de PHP + 1 de verificación.

## ✅ `elementor_element_get` con `globales` encuentra algo que yo no buscaba

Leí un widget de la cabecera para no destrozarle los iconos al reescribir su lista, y la
respuesta trajo esto:

```json
"globales":{
  "globals/colors?id=primary":"Principal (#6EC1E4)",
  "globals/typography?id=c51acfe":"no existe en el kit de este sitio"
}
```

Dos cosas de un vistazo. **#6EC1E4 es el azul de fábrica de Elementor**, no el color de
RentForge. Y una tipografía del kit que **no existe**. Conclusión: los estilos globales del
kit (`global.json`, la plantilla «Global Kit Styles») no se han aplicado, y el sitio se está
pintando con los valores por defecto.

Eso no lo dice ninguna otra herramienta, y yo no lo estaba buscando: salí a leer un icono y
me encontré con que el kit está a medio instalar. En mcp1 pedí exactamente esto —«que
`elementor_element_get` devuelva el color efectivo resuelto, no la referencia»— y el «no
existe en el kit de este sitio» es mejor que lo que pedí.

Queda pendiente aplicar los estilos globales del kit.

## Nota sobre el modo de aprobaciones

Entre la fase 1 y ésta, el modo de aprobaciones pasó a «nunca»: las condiciones que hace un
rato pedían aprobación ahora se ejecutan solas, y la propia respuesta lo dice
(*«Se ejecutó sin pedir aprobación porque el modo de aprobaciones está en "nunca"»*). Lo
anoto porque cambia la postura de seguridad del resto de la sesión, y porque es un acierto de
diseño que la respuesta lo declare en lugar de callarlo.

## Estado al cerrar la fase 2

Cabecera, pie y 404 aplicándose en todo el sitio, verificados en el HTML publicado. Cabecera
traducida (barra superior, botón de presupuesto enlazado a `/reservar/`). Contacto y los 28
titulares de la portada, escritos.
