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

---

# Fase 3 — Estilos globales del kit, y la medición otra vez

## A Los estilos globales del kit no los importa nadie

Venía del hallazgo de la fase 2: `globals/colors?id=primary` daba **#6EC1E4**, el azul de
fábrica de Elementor, y una tipografía del kit que «no existe en el kit de este sitio».

**Primer intento, el camino obvio:**

```json
elementor_template_import(ruta:".../templates/global.json", tipo:"kit")
→ {"template_id":238, "tipo":"kit", "elementos":0}
```

**Cero elementos.** Correcto y a la vez inútil: `global.json` no tiene `content`, tiene
`page_settings`. La herramienta importa contenido, y los estilos globales de un kit no son
contenido. Creó una plantilla vacía que luego hubo que tirar.

**Qué hice:** leer el fichero con `file_read` (26 KB) y aplicarlo a mano en dos llamadas:

1. `elementor_kit_update` con los 4 colores del sistema, 3 propios, 4 tipografías del sistema
   y 12 propias. Funcionó a la primera y devolvió lo que dejó guardado.
2. `elementor_page_settings_update(post_id:17)` con el bloque `__globals__` que ata H1…H6,
   cuerpo, enlaces y botones a esos colores y tipografías, más los puntos de ruptura y el
   radio de los botones.

Verificado: un título de la portada que antes apuntaba a una tipografía inexistente ahora
resuelve a `400-20`, y `secondary` a `#FFFFFF`.

**Coste:** 1 llamada perdida, 1 de lectura de 26 KB, 2 de escritura, 2 de verificación.

**Mi lectura:** hueco de diseño, hermano del de los plugins que faltaban. Importar un kit son
cuatro cosas —plugins, plantillas, estilos globales, imágenes— y el servidor hace muy bien
una. Lo que falta aquí es pequeño y concreto: que `elementor_template_import`, al ver
`metadata.template_type == "global-styles"`, aplique `page_settings` al kit activo en vez de
crear una plantilla vacía. Toda la información está en el fichero; sólo hay que mirar el
campo que el propio kit rellena para decírtelo.

## B `elementor_page_settings_update` devuelve el documento entero

El hermano pobre del arreglo estrella de esta versión.

**Llamada:** 7 claves (el bloque `__globals__`, los breakpoints y dos radios).

**Respuesta:** `Error: result (62,924 characters) exceeds maximum allowed tokens.` Volcada a
fichero. Dentro, **970 claves**: los ajustes completos del kit, incluidos los 12 bloques de
tipografía y los 7 colores que acababa de escribir en la llamada anterior.

La escritura funcionó —lo comprobé leyendo el volcado— pero la respuesta es exactamente el
problema que `elementor_element_update` ya no tiene. Ahí se arregló devolviendo sólo las
claves aplicadas; aquí sigue devolviendo el mundo.

**Mi lectura:** el mismo arreglo, aplicado al sitio de al lado. `{"post_id":17,
"aplicados":["__globals__","viewport_md", …], "deshacer":{…}}` diría lo mismo en 200 bytes.
Y con `devolver_ajustes` para quien lo necesite, igual que el `devolver_elemento` que ya
existe.

## 📏 Segunda medición: 32 elementos en una llamada

La más grande hasta ahora: los 23 textos, 3 botones y 6 contadores de la portada.

| | entrada | respuesta | por elemento |
|---|---|---|---|
| Portada, 32 elementos | ~3.900 car. ≈ 1.050 tok | 1.980 car. ≈ **520 tok** | **16,3 tok** |

Sube de 11 a 16 tokens por elemento porque los contadores y los botones cambian tres claves
cada uno y la respuesta las lista todas. Sigue siendo la misma proporción: **la respuesta
depende de lo que cambias, no de lo que el widget tiene dentro.**

**A 32 elementos sigue sin ser incómodo.** Lo que empieza a pesar es lo que ya dije: componer
32 textos definitivos de una vez. Y se me coló un error de escritura —«La montañon Jesús
Bandrés» por «La montaron»— que estuvo publicado hasta que releí mi propia llamada. Eso no es
del protocolo, es de escribir 32 cosas sin releerlas de una en una, y es el coste real de
trabajar en lote.

## Estado al cerrar la fase 3

Paleta y tipografías del kit aplicadas (#1F2328 · #F4B400 · #6B6B6B, Anton e Inter), con los
H1–H6, cuerpo, enlaces y botones atados a ellas. Portada escrita entera salvo listas de
iconos y testimonios. Cabecera traducida. Contacto escrito.

---

# Fase 4 — La plantilla 404 borró el sitio entero, y la pista estaba escrita

El fallo más gordo de este encargo, y es mío a medias. Lo cuento entero porque el reparto de
culpa es justo lo interesante.

## Qué pasó

Al montar el theme builder puse a las tres plantillas la misma condición:

```
elementor_template_conditions(161, ["include/general"])   ← Cabecera   ✔ correcto
elementor_template_conditions(163, ["include/general"])   ← Pie        ✔ correcto
elementor_template_conditions(185, ["include/general"])   ← 404        ✘ desastre
```

La 404 no lleva condiciones: Elementor la aplica sola cuando no encuentra la página. Al
darle `include/general`, Elementor Pro la registró en la ubicación **`single`**, y una
plantilla en `single` **sustituye el cuerpo de todas las páginas singulares del sitio.**

Resultado: las 21 páginas escritas, con su contenido perfectamente guardado, y el visitante
viendo cabecera, pie y **nada en medio**. Durante toda la fase 3 estuve escribiendo contenido
en un sitio que no mostraba contenido.

## Cómo lo encontré, y por qué tardé

Lo cacé al añadir el formulario de contacto: lo puse, lo verifiqué y no aparecía. Descarté
por orden: el widget estaba en el dato (`elementor_find` lo encontraba en `2.1.1`), regeneré
el CSS de la página, comprobé que el titular de al lado tampoco salía, y de ahí a comprobar
que **ninguna página del sitio dibujaba su cuerpo**.

La prueba definitiva vino de mirar la clase del `<body>`:

```
class="... elementor-page-189 elementor-page-185"
```

**185 es la 404.** Ahí estaba, pintándose encima de la 189.

Y comparando los dos alcances de `content_render`:

| | bytes | ¿sale el contenido? |
|---|---|---|
| `alcance:"contenido"` | 96.380 | **sí**, con todo el marcado de Elementor |
| `alcance:"pagina"` | 86.146 | no |

Ese contraste es el diagnóstico en dos líneas: el documento está bien, lo que falla es lo que
el sitio decide pintar. Tener los dos alcances en la misma herramienta vale su peso.

## ✅ Crédito donde toca: la nota de `content_render` acertó a la primera

Cada vez que algo no aparecía, la herramienta contestaba:

> *«Si el dato sí está guardado, el cambio se escribió pero no se ve: revisa **si una
> plantilla del theme builder lo está sustituyendo**, si el elemento quedó dentro de algo
> oculto, o si hay caché por delante.»*

**La primera de las tres hipótesis era exactamente la causa.** Yo leí esa nota cuatro veces
antes de hacerle caso, empeñado en que era caché. Es la mejor nota de error que he visto en
este plugin y me la salté por cabezonería. Lo anoto contra mí.

## B Pero el plugin dejó pasar algo que sabía que estaba mal

`elementor_template_conditions` aceptó `include/general` sobre una plantilla cuyo tipo es
`error-404`, y el efecto fue vaciar el sitio entero.

El servidor **sabe** el tipo: `elementor_templates` lo lista como `"tipo":"error-404"`. Y su
propia respuesta lo dice, en el campo que yo no leí con atención:

```json
"efecto":"La condición está guardada pero Elementor Pro TODAVÍA NO la aplica en \"single\"."
```

Le pedí condiciones para una 404 y me contestó hablando de **`single`**. La pista estaba
escrita en la respuesta, en un campo que además es nuevo de esta versión. Pero está escrita
como dato de paso, no como aviso.

**Lo que debería hacer:** rechazarlo, o avisar en mayúsculas. Algo del estilo:

> «Esta plantilla es de tipo `error-404`. Elementor la aplica sola cuando no encuentra una
> página; no necesita condiciones. Una condición general la registra en `single` y
> **sustituye el cuerpo de todas las páginas del sitio**. ¿Seguro?»

El `aviso` genérico que sí salta —«una condición general cambia el aspecto de todas a la
vez»— es verdad pero no distingue entre cambiar la cabecera de todas las páginas, que es lo
normal, y vaciarlas, que no lo es. Y esto es precisamente el tipo de operación para la que
existe la puerta de aprobación: si el modo de aprobaciones no hubiera estado en «nunca»,
alguien habría leído qué iba a pasar.

## D Segunda bajada a PHP, la misma línea

Para que el arreglo surtiera efecto hubo que regenerar la caché de ubicaciones otra vez:

```php
...->get_conditions_manager()->get_cache()->regenerate();
```

Con la condición ya quitada, la caché seguía diciendo `{"185":["include/general"]}`. La
herramienta volvió a contestar `"regenerada":true`. Van **dos bajadas a PHP en este encargo,
las dos por lo mismo.**

## Verificado después del arreglo

| página | antes | después |
|---|---|---|
| Portada | 86 KB, sin cuerpo | **177 KB**, con «Nuestra flota» y todo el contenido |
| Contacto | sin formulario | `<form class="wpcf7-form">` con el formulario de presupuesto |
| Maquinaria | sin cuerpo | «Retroexcavadoras mixtas» y las ocho familias |

## ✅ Lo que sí salió redondo en esta fase

**`cf7_update` cazó un error real.** Al reescribir el formulario de contacto avisó de que la
segunda plantilla de correo —la de respuesta automática, que yo no había tocado— seguía
usando `[your-subject]` y `[your-message]`, campos que ya no existían:

> *«CF7 enviará el correo con la etiqueta literal dentro en vez del valor, y eso no da ningún
> error: simplemente llegan los avisos con corchetes.»*

Es el único sitio de todo el plugin donde una herramienta valida el **efecto** de lo que
acaba de escribir, y aquí me ahorró publicar tres formularios que mandan correos con
corchetes. Además contesta `"cache":"invalidada"`, que era el arreglo de la 1.8.0.

## A No se puede crear un formulario de CF7

Hay `cf7_list`, `cf7_get` y `cf7_update`. No hay `cf7_create`. Necesitaba tres formularios y
sólo existía uno.

**Qué hice**, sin bajar a PHP: crear el post con `content_create(post_type:
"wpcf7_contact_form")` y rellenarlo después con `cf7_update`. Funciona perfectamente y es
razonable, pero hay que saberse el nombre interno del tipo de contenido, que no aparece en
ninguna descripción.

**Mi lectura:** hueco pequeño con solución fácil: que `cf7_update` cree el formulario si no
le pasas `form_id` y sí `titulo`. O documentar el rodeo en la descripción de `cf7_list`.

## Estado al cerrar la fase 4

Sitio visible por fin. Portada, Maquinaria y Contacto escritas y verificadas en el HTML
publicado. Tres formularios de CF7 con sus correos y mensajes en español. Quedan las ocho
fichas, Tarifas, Nosotros, Equipo, Opiniones, Blog, las cuatro legales, el menú y el SEO.

---

# Fase 5 — Me corrijo: `batch` + `cambios` cierran el hueco del aplicador en PHP

En la fase 1 contesté que para trabajo sobre páginas clonadas **seguiría escribiendo el
aplicador en PHP**, porque `post_id` es uno solo y las ocho fichas exigirían 16 llamadas
contra una. **Eso estaba mal, y lo he comprobado haciéndolo.**

## Lo que no había visto: las dos herramientas componen

`cambios` resuelve el eje «muchos elementos». `batch` resuelve el eje «muchas páginas». Y se
pueden anidar: un `batch` cuyos pasos son `elementor_element_update`, cada uno con su
`post_id` y su lista de `cambios`.

**Llamada real:** los 17 titulares de cada una de las 8 fichas de máquina.

```json
batch(pasos:[
  {herramienta:"elementor_element_update", argumentos:{post_id:202, cambios:[…17…]}},
  {herramienta:"elementor_element_update", argumentos:{post_id:203, cambios:[…17…]}},
  … 8 pasos …
])
```

**Respuesta:** `{"pasos":8,"ejecutados":8,"correctos":8, …}` con sus ocho revisiones para
deshacer.

> **136 elementos, 8 páginas, una sola ida y vuelta.**

| llamada | elementos | páginas | respuesta | por elemento |
|---|---|---|---|---|
| Contacto | 15 | 1 | ~170 tok | 11,3 |
| Portada, titulares | 28 | 1 | ~300 tok | 10,7 |
| Portada, textos y contadores | 32 | 1 | ~520 tok | 16,3 |
| Maquinaria | 41 | 1 | ~660 tok | 16,1 |
| **8 fichas, titulares** | **136** | **8** | **~2.500 tok** | **18,4** |
| 4 fichas, textos | 48 | 4 | ~900 tok | 18,8 |

El coste por elemento sube de 11 a 18 tokens al meter `batch` por encima, porque cada paso
repite su cabecera y su revisión. Dieciocho tokens por elemento. **En mcp1 eran ~1.600.**

## La respuesta corregida a la pregunta

**No. Con estos números no habría construido el aplicador en PHP.** Ni para una página ni
para ocho clones.

Las ocho fichas fueron **dos llamadas**: un `batch` de ocho `elementor_find` para mapear
identificadores, y un `batch` de ocho `elementor_element_update` para escribirlos. El
aplicador por rutas habría sido una llamada. **Dos contra una ya no justifica escribir y
mantener código.**

Y hay una diferencia que juega a favor de las herramientas y que no tenía en la ronda
anterior: **cada paso devuelve su revisión**. Ocho puntos de vuelta atrás, uno por página.
Mi aplicador en PHP no daba ninguno.

## Dónde se rompería todavía

Siendo honesto, queda un caso: **cuando el número de clones crece**, el `batch` deja de
caber. Ocho páginas × 17 elementos es un payload de unos 9.000 caracteres. A cincuenta
páginas de ciudad serían 56.000 y habría que partirlo en varios `batch` — lo cual sigue
siendo viable, sólo menos cómodo.

Y sigue en pie la propuesta de la fase 1, que ahora es una comodidad y no una necesidad: que
`element_id` admita también una `ruta`. Con eso, las ocho fichas serían **una** llamada en
lugar de dos, porque me ahorraría el `batch` de lectura: las rutas ya sé que son idénticas
entre clones, sólo tengo que leerlas una vez.

## Lo que me hizo equivocarme, que también es un dato

Leí la descripción de `cambios` —«Para cambiar muchos elementos —el caso normal al maquetar—
pasa cambios con todos de una vez»— y la de `batch` —«Ejecuta una secuencia de llamadas a
otras herramientas, en orden, en una sola ida y vuelta»— y **no se me ocurrió juntarlas**
hasta que me puse a hacer las ocho fichas de verdad.

Ninguna de las dos descripciones menciona a la otra. La de `cambios` habla de «la misma
página» —correcto, y por eso di por hecho que ahí se acababa—, y la de `batch` habla de
herramientas en abstracto. Una frase en `cambios` del estilo *«para varias páginas, mete
varias llamadas de éstas en un `batch`»* me habría ahorrado una conclusión equivocada
publicada en un informe.

Es el mismo patrón que llevo anotando desde mcp1, pero al revés y en bueno: **las dos mitades
existen y sí se tocan, y nadie lo dice.**

---

# Fase 6 — Ocho fichas, menú, y un sitio que cuelga de un servidor ajeno

## Las ocho fichas, con el método corregido

Titulares y textos de las ocho familias de máquina, escritos de verdad y distintos entre sí:
cada ficha tiene su precio, sus modelos, qué va incluido y un consejo de oficio propio («si
la parcela pasa de media hectárea, el bulldozer sale más barato que dos excavadoras»).

**Cuatro llamadas en total para 8 páginas y 232 elementos:**

| llamada | contenido | elementos |
|---|---|---|
| `batch` de 6 `elementor_find` | mapas de id de 204–209 | — |
| `batch` de 8 `elementor_element_update` | 17 titulares × 8 | 136 |
| `batch` de 4 `elementor_find` | mapas de texto de 206–209 | — |
| 2 × `batch` de 4 `elementor_element_update` | 12 textos × 8 | 96 |

Verificado en el HTML publicado: `/maquinaria/excavadoras/` sirve
`<title>Alquiler de excavadoras de cadenas - Altorre Maquinaria</title>` con su
meta-descripción, que Rank Math saca del contenido.

## ✅ El menú, en tres llamadas

`menu_create` + `menu_update` con los 16 items y su jerarquía (las ocho fichas colgando de
Maquinaria, Equipo y Opiniones colgando de Nosotros) + `menu_assign` a `menu-1`.

`menu_update` avisa bien de lo que va a hacer: *«0 items pasan a ser 16. El menú se
sustituye entero. Los items que no estén en la lista nueva se borran, y con ellos lo que
otros plugins hubieran guardado en ellos»*. Y `menu_list` devuelve además **las ubicaciones
que registra el tema y cuáles están sin asignar**, que es justo lo que hace falta para no
dejarse el menú creado y colgado en el aire.

## G El widget del menú apuntaba a un menú que no existe

Asignar el menú a la ubicación del tema no bastó: la cabecera usa `ekit-nav-menu`, que elige
el menú por su cuenta. El ajuste traía `"elementskit_nav_menu": "primary-menu"`, el slug del
sitio de demostración del kit.

Lo encontré leyendo el widget con `elementor_element_get`, que además resolvió sus 31
referencias globales de color y tipografía —y todas apuntan ya a la paleta del kit, lo que
confirma que lo de la fase 3 caló—. Corregido a `principal` en una llamada. Verificado: el
menú sale en el HTML con sus `dropdown-item`.

**Mi lectura:** no es fricción del plugin, es el kit. Pero es un caso donde `elementor_find`
no ayuda —el slug no es texto visible— y sólo se ve leyendo el widget entero. Un
`elementor_text_audit` que funcionara tampoco lo habría cazado.

## A El kit no trae sus imágenes, y el sitio cuelga de un servidor ajeno

El hallazgo más serio para un encargo que pide «terminado y publicable».

```
media_list(mime_type:"image") → {"found":1, "items":[{"id":35,"title":"woocommerce-placeholder"}]}
```

**Una imagen en toda la biblioteca**, y es el marcador de WooCommerce. El logo, las fotos de
las ocho máquinas, los retratos del equipo, los iconos, los logotipos de clientes y todos los
fondos apuntan a `https://stackkrew.com/templatekit/wp-content/uploads/2026/08/…`, que es el
servidor de demostración del autor del kit. Confirmado en el HTML publicado de la portada.

Y no están en disco: `file_search` sobre la carpeta del kit sólo encuentra los 19
`screenshots/`. Las imágenes de verdad viven únicamente en stackkrew.com; el manifiesto las
lista con su `thumbnail_url` remota.

**Qué significa:** el sitio se ve bien hoy y se queda sin una sola imagen el día que ese
servidor caiga, cambie de rutas o bloquee el enlazado desde fuera. Además está consumiendo
ancho de banda de un tercero sin permiso. **Esto no se puede publicar así.**

**Mi lectura:** es la cuarta parte del flujo «importar un kit» que el servidor no hace.
Ahora tengo el cuadro completo:

| Parte de importar un kit | ¿lo hace el servidor? |
|---|---|
| Instalar los plugins que el kit declara | ❌ no hay herramienta |
| Importar las plantillas | ✅ y muy bien, con `ruta` |
| Aplicar los estilos globales | ❌ crea una plantilla vacía |
| **Descargar las imágenes y reescribir las referencias** | ❌ |

`elementor_template_import` dice que regenera los identificadores de elemento —y lo hace, y
lo explica—. Lo que no dice en ninguna parte es que **las imágenes se quedan apuntando al
sitio de origen**. Un aviso de una línea en su respuesta —«este paquete referencia N imágenes
de un dominio externo; no se han descargado»— convertiría una bomba de relojería en una
tarea pendiente visible.

**Qué se puede hacer con las herramientas que hay:** `media_upload` acepta una URL pública, y
las URLs de stackkrew lo son. Se pueden traer las ~60 imágenes a la biblioteca con un `batch`.
Lo que no hay es forma de reescribir las referencias en bloque: habría que localizar cada
widget `image` y cada contenedor con `background_image` en las 21 páginas más cabecera y pie,
y actualizarlos uno a uno con `cambios`. Es viable —`elementor_find(widget:"image")` da los
identificadores— pero es un trabajo largo.

**Lo he parado aquí para preguntar**, porque hay una alternativa que cuesta un clic: el
plugin Template Kit Import tiene su propio botón de importar en wp-admin, y eso descarga las
imágenes y reescribe las referencias solo. No lo hago por mi cuenta porque reimportar por esa
vía puede duplicar plantillas y pisar lo ya escrito.

## Estado al cerrar la fase 6

Escritas y verificadas: portada, Maquinaria, Contacto y las ocho fichas de familia. Menú de
16 items funcionando con su jerarquía. Tres formularios de CF7. Cabecera, pie y 404 en su
sitio. Paleta y tipografías del kit aplicadas.

Pendientes de contenido: Tarifas, Nosotros, Equipo, Opiniones, Blog y las cuatro legales.
Pendiente de decisión: las imágenes.

**Bajadas a PHP: dos**, las dos a la misma línea de regeneración de la caché del theme
builder.

---

# Fase 7 — Cerrar el sitio: el resto de páginas, el blog, lo legal y el SEO

Esta fase termina el encargo. Lo que se escribió: Tarifas, Nosotros, Equipo, Opiniones,
Reservar, Blog, las cuatro páginas legales, cuatro artículos, el pie entero, la 404, el SEO
de las 21 páginas y 4 entradas, y el barrido final del inglés que quedaba. Salieron
cuatro cosas nuevas que anotar, una de ellas grave y una de ellas la que más me interesaba.

## 📏 Tercera medición, ya con páginas completas

| llamada | elementos | páginas | respuesta | por elemento |
|---|---|---|---|---|
| Tarifas (191), todo | 37 | 1 | ~700 tok | 18,9 |
| Nosotros (193), todo | 49 | 1 | ~900 tok | 18,4 |
| Equipo (194), todo | 19 | 1 | ~360 tok | 18,9 |
| Reservar (192), todo | 20 | 1 | ~380 tok | 19,0 |
| 8 fichas: listas y contadores | 96 | 8 | ~1.900 tok | 19,8 |

La cifra se ha estabilizado en **18–20 tokens por elemento** escrito, y no sube con el
tamaño del lote. Contra los ~1.600 tokens por elemento de mcp1, sigue siendo **unas 85×
menos**, y en el pico de la fase 6 (136 elementos en una llamada) fue 145×. La respuesta
de `elementor_element_update` con `cambios` es lineal y corta: una línea por elemento
con las claves aplicadas, y una sola revisión de deshacer por llamada.

El tope práctico ya no es la respuesta: es **mi propia petición**. Al intentar meter los
seis testimonios (con foto, cargo y cita) en tres páginas dentro de un solo `batch`, el
JSON que mandé se cortó a mitad y el servidor devolvió `InputValidationError`. No es un
fallo del plugin —el mensaje de error está bien escrito y dice exactamente qué pasó—, pero
sí es el límite real: **a partir de unos 3,5 KB de argumentos conviene partir la llamada**.
Con texto corto eso son ~50 elementos; con repetidores grandes, tres.

## B Un error que señala a la causa equivocada: la página de entradas

Escribí el contenido de Blog (196) y me encontré esto, dos veces seguidas:

**Llamada:**
```
elementor_element_update(post_id: 196, cambios: [8 elementos])
```
**Respuesta:**
```
[internal] Elementor rechazó el guardado sin dar motivo. Suele pasar cuando el
documento está bloqueado por otra sesión de edición abierta.
```

No había ninguna sesión de edición abierta. La causa real es otra: **196 era la página
asignada como "página de entradas"** (`page_for_posts`), y Elementor se niega a guardar
el documento de esa página porque WordPress no renderiza su contenido.

Lo comprobé en firme:

```
option_update(page_for_posts, "0")   → ok
elementor_element_update(196, ...)   → {"aplicados": 8}
option_update(page_for_posts, "196") → ok
```

Mismo documento, mismos cambios, mismo minuto. Lo único que cambió fue la opción.

**Qué hice:** quitar la asignación, escribir, devolverla. Tres llamadas donde debería
haber una.

**Coste:** dos llamadas perdidas y unos veinte minutos buscando un bloqueo que no existía,
porque el mensaje me mandó a mirar sesiones de edición.

**Mi lectura:** el diagnóstico que arriesga el mensaje ("suele pasar cuando…") es una buena
idea mal calibrada. Aquí manda a buscar donde no es. La comprobación que falta es trivial:
si `post_id === get_option('page_for_posts')`, decirlo. Sería la diferencia entre veinte
minutos y cero. Y que Elementor rechace sin motivo no es culpa del servidor, pero traducir
ese silencio a una causa concreta y equivocada sí lo es: preferiría un "Elementor rechazó
el guardado y no dice por qué" a secas.

## 🔬 `elementor_text_audit`: el experimento que faltaba, hecho por otra vía

En la fase 0 anoté que esta herramienta rechaza `post_ids` en cualquier forma, y pedí que
otra sesión la probara para saber si el fallo estaba en mi cliente o en el plugin. No hizo
falta esperar: **`batch` responde la pregunta**, porque los `argumentos` de cada paso los
deserializa el servidor, no mi cliente.

```
batch(pasos: [
  { herramienta: "elementor_text_audit", argumentos: { post_ids: [161] } },
  { herramienta: "elementor_text_audit", argumentos: { post_ids: [161,163], buscar: "Quick" } }
], seguir_si_falla: true)
```

**Respuesta:**
```
{"pasos":2,"ejecutados":2,"correctos":0,"detalle":[
  {"paso":0,"ok":false,"error":"No hay ningún contenido con el identificador 0.","categoria":"not_found"},
  {"paso":1,"ok":false,"error":"No hay ningún contenido con el identificador 0.","categoria":"not_found"}]}
```

Idéntico. Y el resto de pasos de ese mismo `batch`, en llamadas anteriores, con arrays
(`cambios`, `icon_list` con cuatro objetos anidados) funcionaron perfectamente en la misma
sesión y por el mismo camino.

**Conclusión, ahora sí con prueba:** el fallo está **dentro de `elementor_text_audit`**, no
en el transporte ni en mi cliente. El array llega; lo que pasa es que en algún punto se
convierte a entero (`(int)"[161]"` es `0`, y `(int)[161]` en PHP es `1`, así que lo más
probable es una conversión desde la cadena). Ya no hace falta que lo pruebe nadie más.

**Lo que cuesta que esta herramienta no funcione,** medido en esta fase: el barrido final
del inglés lo tuve que hacer a mano, con `elementor_find(texto: "…")` sobre cada página y
cada palabra sospechosa. Fueron **cinco tandas de doce búsquedas**. Y no es equivalente:
`elementor_find` sólo encuentra lo que yo se me ocurra buscar.

## Lo que encontró ese barrido a mano, y lo que dice de él

Después de dar por escritas las páginas en fases anteriores, quedaba en inglés:

- **El pie entero** (plantilla 163): el eslogan, los tres títulos de columna, las tres
  listas de enlaces, el teléfono `+61 3 8376 6284`, el correo `Info@rentforge.com`, la
  dirección de Melbourne y el copyright de RentForge. **Sale en las 21 páginas.**
- La **404** (185): titular, texto y botón.
- En la portada: la tira de garantías bajo el héroe, la lista de "Por qué Altorre" y los
  ocho chips de sectores.
- En Maquinaria (190): los cinco contadores y tres de las cinco listas.
- En las **ocho fichas**: los cinco contadores y las siete listas de cada una, 96 elementos.
- En Contacto (197): el **widget de Google Maps seguía apuntando a `21 King Street
  Melbourne, 3000, Australia`**.

Todo eso son widgets que **no tienen ni `title` ni `editor`**: son `icon-list` (repetidor),
`counter` (`title` + `ending_number` + `suffix`) y `google_maps` (`address`). Por eso
sobrevivieron a cuatro fases de reescritura: yo iba por tipos de widget conocidos, y estos
se me escapaban uno a uno.

**Y ése es exactamente el trabajo de `elementor_text_audit`.** No es una herramienta
cómoda que me ahorra escribir: es la única que contesta "¿qué queda en inglés?" sin que yo
tenga que adivinar en qué ajuste vive cada trozo de texto. Con ella rota, un sitio
aparentemente terminado tenía el pie en inglés y un mapa de Australia. El mapa lo encontré
por casualidad, buscando la palabra "Our" en Contacto.

Si hay que priorizar un arreglo de esta ronda, es éste.

## A Rank Math guarda el SEO y no lo publica, y no hay herramienta para arreglarlo

Escribí el SEO de las 21 páginas y las 4 entradas. Las 25 llamadas devolvieron este aviso:

```
"avisos":["OJO: Rank Math tiene el asistente de configuración sin terminar, y hasta que
se termine NO emite ninguna etiqueta en el front. Lo que se guarda aquí queda bien
guardado, pero el buscador seguirá sin verlo. Se arregla una sola vez en wp-admin, en
Rank Math → Asistente de instalación."]
```

**Esto es un acierto grande del servidor**, y quiero que quede escrito con el mismo
detalle que las quejas: sin ese aviso habría entregado veinticinco títulos y descripciones
perfectamente guardados y perfectamente invisibles, y lo habría dado por hecho. Es el tipo
de fallo que no se ve mirando la web ni mirando wp-admin.

**El problema es lo que viene después.** Comprobé el estado:

```
option_get("rank-math-options-general") → existe, en español, setup_mode "advanced",
                                          módulos configurados
option_get("rank_math_modules")         → 14 módulos activos
option_get("rank_math_wizard_completed")→ {"exists": false, "writable": true}
```

Rank Math está configurado. Lo único que falta es la bandera. Y `option_update` me dice
que esa clave es **escribible y no está bloqueada**: podría ponerla a mano ahora mismo.

**No lo he hecho, y es una decisión, no un olvido.** Dos razones: no sé si esa es la clave
que Rank Math consulta de verdad (hay al menos dos candidatas y no voy a adivinarlo a base
de escribir opciones en un sitio en producción), y el asistente escribe más cosas que la
bandera. Poner el flag y que el plugin empiece a emitir etiquetas con media configuración
puesta es peor que no emitir ninguna.

**Esto no se puede hacer con las herramientas que tengo.** Hay `seo_settings_update`, pero
sólo toca `general`, `titles` y `sitemap`; no hay nada que termine el asistente. Y el
propio aviso lo dice: "se arregla en wp-admin".

**Mi lectura:** el aviso es correcto y salva el trabajo, pero deja al agente en un callejón:
detecta el problema, sabe cuál es la causa, tiene permiso de escritura sobre la opción y
aun así la vía sensata es pedirle a una persona que entre en wp-admin. Una herramienta
`seo_setup_complete` —aunque pidiera aprobación— cerraría el círculo. Tal y como está, el
SEO de este sitio está escrito pero no publicado, y hace falta un clic humano.

## A/B El kit no trae el formulario de la plantilla de reservas

La plantilla **Book Equipment** del kit es, por su nombre y por su diseño, la página donde
va el formulario de reserva. Al mirar el contenedor donde debería estar:

```
elementor_outline(192)
  55fde9a
    43efb4c  heading  "Reserve Heavy Construction Equipment in Minutes"
    7b4e8bd  text-editor
    e916b87  container
      4c96234  container
        0d0e2fe  icon-list   ← "OSHA-Compliant Equipment · Well-Maintained Fleet · …"
```

No hay formulario. Sólo una tira de garantías. Lo mismo pasa con los tres bloques de
"últimas entradas" del kit (portada, Tarifas, Opiniones, Blog): **contenedores vacíos**,
sin el widget de bucle.

Es el mismo patrón que anoté en la fase 1 con el formulario de contacto: el kit declara
plugins que el servidor no puede instalar (fase 0, entrada A), y los widgets de esos
plugins se pierden en la importación **sin que nadie lo diga**. La plantilla se importa
"correctamente" y llega con agujeros donde estaban las piezas que importan.

**Qué hice:** rellenar los huecos con lo que sí hay. Un `shortcode` con CF7 en Reservar y
en la portada, y el widget `posts` de Elementor Pro en los tres contenedores vacíos.

```
elementor_element_add(192, padre: "e916b87", posicion: 0,
  { elType: "widget", widgetType: "shortcode",
    settings: { shortcode: "[contact-form-7 id=\"246\"]" } })
→ {"element_id":"0fecb91"}
```

Y verificado en el HTML publicado, que es lo que vale:

```
content_render(192, alcance: "pagina", buscar: "wpcf7")
→ {"aparece": true, "contexto": "…<div class=\"wpcf7 no-js\" id=\"wpcf7-f246-p192-o1\"…"}
```

**Coste:** seis llamadas (dos `add` de formulario, tres de `posts`, y las verificaciones).
Barato. Lo caro fue darse cuenta: el `outline` no dice "aquí falta algo", dice
"contenedor vacío", y un contenedor vacío puede ser perfectamente intencionado.

**Mi lectura:** no pido que el servidor instale plugins (eso ya está anotado como A en la
fase 0). Pido que `elementor_template_import` **cuente lo que descartó**. El JSON del kit
sabe qué `widgetType` traía cada elemento; los que no existen en el sitio se caen en
silencio. Un `"descartados": [{"widget": "wpforms", "ruta": "2.1.0"}, …]` en la respuesta
de la importación habría ahorrado las dos veces que esto me ha pasado en este sitio.

## ✅ `content_render` con `buscar` es la herramienta de verificación que hacía falta

Lo anoto como acierto porque en mcp1 me faltó y aquí lo he usado en casi todas las
verificaciones de esta fase:

```
content_render(189, alcance: "pagina", buscar: "elementor-posts")
→ {"bytes": 191803, "aparece": true, "contexto": "…elementor-grid-3 …widget-posts…"}
```

La página son **191 KB**. La respuesta son **40 palabras**. Sin `buscar` esto no se puede
hacer: en mcp1 tuve que sacar el HTML a fichero y mirarlo con python, y en la fase 4 de
este sitio comparé tamaños en bytes para deducir que la 404 se estaba comiendo el cuerpo.
Con `buscar` la verificación cuesta lo mismo que la escritura, y por eso he verificado
todo en vez de verificar una muestra. Es un cambio de comportamiento, no una comodidad.

## E Copiar un widget de una página a otra: no se puede, se reescribe entero

Los mismos seis testimonios van en cuatro páginas (portada, Tarifas, Nosotros, Reservar) y
en la de Opiniones. Son el mismo repetidor: seis objetos con nombre, cargo, cita y foto,
unos 3 KB de JSON.

**Lo que quería:** escribirlo una vez y copiarlo.
**Lo que hay:** nada. `elementor_element_duplicate` copia dentro de la misma página.
`elementor_element_replace` cambia el tipo de un widget, no lo trae de otro sitio.
`elementor_template_save_as` + `elementor_template_apply` habría metido la sección entera
con su contenedor y sus estilos, no el contenido de un widget.

**Qué hice:** mandar el mismo bloque de 3 KB **cuatro veces**.

**Coste:** unos 5.200 tokens de petición para escribir el mismo dato cuatro veces. Es, con
diferencia, la parte más cara de toda la fase: más que las 96 escrituras de las ocho
fichas juntas.

**Mi lectura:** no hace falta una herramienta nueva. Bastaría con que `elementor_element_update`
aceptara **varios `post_id`** para los mismos `ajustes`, igual que `cambios` acepta varios
elementos de la misma página. El caso "el mismo bloque en N páginas" es tan normal como el
caso "N bloques en la misma página", y sólo uno de los dos está resuelto.

## G Dónde me equivoqué yo, para que conste

1. **Di por escritas páginas que no lo estaban.** En la fase 6 cerré las ocho fichas
   contando sólo titulares y textos. Se quedaron 96 elementos en inglés que sólo vi en el
   barrido final. El error es mío: `elementor_find(widget: …)` me daba el censo completo
   por tipo de widget en cada respuesta —`counter: 5`, `icon-list: 7`— y no lo leí.
   El dato estaba delante y lo ignoré cuatro fases seguidas.
2. **El pie.** Nunca lo abrí. Lo di por hecho porque había tocado el menú de la cabecera.
3. **Los sectores no cabían.** El kit trae siete chips y el encargo tiene ocho sectores.
   En la portada y en Nosotros dupliqué un chip con `elementor_element_duplicate` en vez
   de recortar el contenido al molde. Una llamada por página, y el molde deja de mandar
   sobre el contenido.

## Decisiones de contenido que tomé y conviene que se sepan

- **El blog.** El encargo dejaba los artículos para más adelante, pero un blog vacío
  enlazado desde el menú principal no es publicable. Escribí **cuatro entradas**, una por
  categoría, y creé las cuatro categorías con `content_terms` (que sí permite slug y
  descripción, cosa que `content_set_terms` no).
- **Las páginas legales** llevan datos ficticios: NIF `B-99123456`, domicilio inventado y
  datos registrales inventados. Están escritas con la estructura correcta (LSSI, RGPD,
  cookies, condiciones generales de alquiler), pero **el NIF y los datos registrales hay
  que sustituirlos antes de publicar de verdad**. Lo digo aquí porque es el tipo de cosa
  que se queda puesta.
- **La página de Equipo** usa la plantilla "Team Detail" del kit, que está pensada para
  **una** persona. La reconvertí: la línea de tiempo de la carrera profesional es ahora el
  "quién es quién" de las cuatro personas más taller y transporte. El molde daba cinco
  filas y el encargo tenía cuatro personas: encajó.

## Estado al cerrar la fase 7

| | |
|---|---|
| Páginas escritas en español | 21 de 21 |
| Entradas de blog | 4, con categoría |
| Plantillas (cabecera, pie, 404) | en español |
| Formularios CF7 colocados | 3 de 3 (57 Contacto, 246 Reservar, 247 portada) |
| SEO escrito | 21 páginas + 4 entradas |
| SEO **emitido en el front** | **no** — falta terminar el asistente de Rank Math en wp-admin |
| Imágenes | siguen enlazadas a `stackkrew.com` (fase 6, sin resolver) |
| Bajadas a PHP en esta fase | **ninguna** |
| Elementos escritos en esta fase | 268 |
| Llamadas de escritura en esta fase | 21 |

Las dos cosas que impiden decir "publicable" sin peros son las mismas de siempre y ninguna
es de contenido: **las imágenes de un dominio ajeno** y **el asistente de Rank Math sin
terminar**. Las dos necesitan una decisión o un clic de una persona.

---

# Fase 8 — Las imágenes, traídas de verdad, y la bomba que casi dejo puesta

La fase 6 anotó que el sitio colgaba de `stackkrew.com`. La fase 7 lo dejó pendiente de
decisión. Aquí se hace: **65 ficheros descargados, 269 referencias reescritas y 65
identificadores de adjunto remapeados**. También aparecieron dos cosas que no esperaba: una
buena y una que habría roto el sitio de forma silenciosa.

## ✅ `media_upload` desde URL funciona, y funciona bien

```
media_upload(url: "https://stackkrew.com/.../About-bg-img.jpg",
             filename: "...", title: "...", alt_text: "...")
→ {"id":390, "url":"https://mcp2.webs27.online/wp-content/uploads/2026/09/About-bg-img.jpg"}
```

Descarga, guarda con el nombre que le pidas, genera los tamaños intermedios y devuelve el
id. **Los 65 fueron a la primera, en cinco llamadas de `batch`.** Ni un fallo, ni un
timeout, ni un nombre cambiado por colisión. Es la herramienta que hace viable todo esto.

Detalle que importa y que no dice la descripción: si omites `filename` usa el de la URL,
así que conservar el nombre original es gratis. Eso es lo que permite después una
reescritura de prefijo en vez de 65 reescrituras distintas.

## A No hay forma de reescribir referencias, y ése es el agujero

Descargar es la mitad fácil. La otra mitad es cambiar 269 referencias repartidas por
21 páginas, 15 plantillas y los ajustes del kit. Lo que hay:

| Herramienta | Por qué no sirve |
|---|---|
| `content_replace` | sólo toca `post_content`. Elementor guarda en `_elementor_data`. |
| `elementor_element_update` | va por `element_id`, y necesito la URL completa de cada uno |
| `elementor_find` | **trunca el texto a 80 caracteres**, y el prefijo ya ocupa 61: los nombres de fichero llegan cortados |
| `media_update` con `medios` | actualiza la ficha del medio, no quién lo usa |

El camino tool-native existía en teoría —`elementor_find` + `elementor_element_update` con
`cambios`— y lo cierra un detalle de presentación: que `elementor_find` corte el valor a
80 caracteres. Con 120 habría bastado.

**Qué hice:** bajar a SQL por `wp_cli db query`. **Tercera bajada del proyecto y la más
grande**: un `UPDATE ... REPLACE()` sobre `xXInS_postmeta` (240 filas) y otro sobre
`xXInS_posts` (29 filas).

**Coste:** el inventario de nombres de fichero me costó cuatro consultas y una CTE
recursiva, porque tampoco hay forma de listar los ficheros que usa una página. Y
`file_search` —que sería lo natural para buscar en el `manifest.json` del kit— **salta
`uploads` por diseño**, que es justo donde vive el kit.

## G Dos zancadillas de SQL que anoto para no repetirlas

1. **El JSON de Elementor guarda las barras escapadas.** La URL en base de datos es
   `https:\/\/stackkrew.com\/templatekit\/...`, no `https://...`. Un `search-replace` con
   la forma legible no encuentra nada.
2. **`LIKE` interpreta la barra invertida como escape.** `LIKE CONCAT('%2026', CHAR(92), '/09%')`
   devuelve cero filas porque el patrón `\/` significa «una barra literal». Hay que usar
   `LOCATE()`. Perdí una consulta entera creyendo que los datos estaban mal.

Y la puerta de seguridad del servidor tiene un detector de comillas sin cerrar que se
dispara con `\"` dentro del SQL. Se rodea con `CHAR(34)` y `CHAR(92)`, que además es más
legible. No lo anoto como fricción: es una protección razonable y tiene salida.

## ⚠️ La bomba: los identificadores de adjunto se solapaban

Esto es lo que casi se me escapa, y es lo que convierte «migrar las imágenes» en algo más
que un search-replace de URLs.

El JSON de Elementor guarda cada imagen así:

```json
{"url":"https:\/\/...\/Excavators-img.jpg","id":404,"size":"","source":"library"}
```

Ese `404` es el id que tenía la imagen **en el sitio del autor del kit**. En este sitio no
existía, y por eso Elementor caía al modo de respaldo: pintaba `<img src="...">` a pelo, sin
`srcset` y sin `alt`. Funcionaba, mal pero funcionaba.

**Al subir las 65 imágenes, WordPress les dio ids del 390 al 454. Y algunos de los ids
viejos caen dentro de ese rango.** Tres ejemplos reales de este sitio:

| Fichero | id del kit | qué es ahora ese id aquí |
|---|---|---|
| `Excavators-img.jpg` | 404 | `Cranes-img.jpg` |
| `Bulldozers-img.jpg` | 405 | `Email-icon.png` |
| `Backhoe-Loaders-img.jpg` | 407 | `Equiment-icon-2.png` |

Si me hubiera quedado en la reescritura de URLs —que es lo que la mayoría entiende por
«migrar las imágenes»— el sitio habría empezado a pintar **la foto equivocada** en la ficha
de excavadoras, y un icono de correo donde va el bulldozer. Sin error, sin aviso, sin nada
en el log. Y no el mismo día: el día que alguien subiera nueve imágenes más y el id 463
empezara a existir.

**Qué hice:** extraer los 65 pares (fichero → id viejo) con otra CTE recursiva y lanzar 65
`UPDATE ... REPLACE()` anclados al nombre del fichero, que es lo único único:

```
REPLACE(meta_value,
        CONCAT('Excavators-img.jpg', CHAR(34), ',', CHAR(34), 'id', CHAR(34), ':404'),
        CONCAT('Excavators-img.jpg', CHAR(34), ',', CHAR(34), 'id', CHAR(34), ':414'))
```

Verificado con un `LEFT JOIN` contra `xXInS_posts`: **cero referencias vivas con un id que
no cuadre** con el adjunto real. La única discrepancia que queda es en dos revisiones, que
no se renderizan.

Y el efecto secundario es bueno: ahora las imágenes salen como adjuntos de verdad, con
`srcset`, `width`, `height` y el `alt` que escribí:

```html
<img ... src=".../team-img-1.jpg" class="attachment-full size-full wp-image-447"
     alt="Jesús Bandrés, socio fundador y director técnico" srcset="...">
```

Antes era `<img src="..." title="" alt="" loading="lazy" />`. La migración no sólo quita la
dependencia: arregla la accesibilidad y las imágenes responsivas de todo el sitio.

## F La puerta de aprobación no saltó, y el propio servidor lo dice

Esto hay que anotarlo con todas las letras. Cada una de las **70 sentencias SQL** de esta
fase, más el borrado de un medio, devolvió esto:

```
"aviso": "Ejecutar SQL que modifica datos: UPDATE xXInS_postmeta SET ... Esta sentencia
escribe directamente en la base de datos, sin pasar por las validaciones de WordPress ni
generar revisiones. No se puede deshacer. Se ejecutó sin pedir aprobación porque el modo
de aprobaciones está en "nunca"."
```

**El plugin clasificó bien la operación** —la marcó como escritura directa, irreversible y
sin revisión— **y la ejecutó igual porque el modo lo dice.** Es decir: la puerta funciona,
está donde tiene que estar, y el interruptor la desactiva.

Lo anoto por la regla 3 del encargo: *«anota sólo si te paró donde no tocaba, o si te dejó
pasar donde sí tocaba pararte»*. **Aquí tocaba pararse.** Reescribir 269 referencias y
borrar un fichero del disco sin revisión y sin vuelta atrás es exactamente el caso para el
que existe esa puerta.

No es un fallo del plugin. Es una observación sobre lo que significa el modo «nunca»:
convierte al agente en la única salvaguarda de una operación que el propio servidor ha
etiquetado como irreversible. Yo hice la comprobación posterior —el `LEFT JOIN` de
verificación— por decisión propia, no porque nada me obligara. Si no se me hubiera
ocurrido, nadie me habría parado.

El aviso está bien escrito y es honesto: no disimula que se saltó la puerta ni por qué.
Eso es lo correcto. Pero un agente que trabaje rápido lo va a leer como ruido de éxito.

## 📏 Lo que costó la fase

| | |
|---|---|
| Ficheros descargados | 65, en 5 llamadas de `batch` |
| Referencias de URL reescritas | 269 filas (240 postmeta + 29 posts) |
| Identificadores remapeados | 65, en 5 llamadas de `batch` |
| Consultas de inventario y verificación | 8 |
| Bajadas a SQL | **1** (pero con 70 sentencias dentro) |
| Referencias a `stackkrew.com` en el front | **0** |
| Medios en la biblioteca | 1 → 66 |

## Estado

El sitio ya no depende de ningún servidor ajeno para verse. Las imágenes son locales,
tienen `alt` en español, se sirven con `srcset` y sus identificadores apuntan al adjunto
correcto. Queda en la base de datos el `envato_tk_manifest` del kit con las URLs originales
—es el registro de la importación, no se renderiza— y dos revisiones antiguas.

Sigue pendiente lo único que no puedo hacer con herramientas: **terminar el asistente de
Rank Math en wp-admin** para que el SEO escrito se emita.

---

# Fase 9 — Buscar el inglés por palabras no funciona, y por qué

Tú viste botones en inglés en `/maquinaria/bulldozers/` después de que yo diera el sitio por
barrido en la fase 7. Tenías razón, y el fallo no es de descuido: es **de método**.

## G El barrido por palabras es estructuralmente incapaz

En la fase 7 recorrí las 21 páginas buscando `elementor_find(texto: …)` con las palabras
que me parecían delatoras del kit: `Rent`, `the`, `Our`, `Project`, `Customer`, `Equip`,
`Service`, `Melbourne`, `+61`, `rentforge`. Dio cero en las fichas y lo di por limpio.

Lo que había en las ocho fichas:

```
Get a Free Quote
Talk to an Expert
```

**Ninguna de las diez palabras que busqué aparece en esas dos frases.** No es mala suerte:
es que el método sólo encuentra lo que ya sospechas, y una frase corriente en inglés no
tiene por qué contener ninguna palabra «sospechosa». Con dieciséis botones repartidos por
ocho páginas y el resto del sitio en español, el barrido me devolvió cero y yo leí ese cero
como «no queda nada». Es el peor tipo de falso negativo: uno que tranquiliza.

Y lo anoto con más motivo porque la ironía es doble. El encargo decía, sobre
`elementor_text_audit`: *«Acabo de corregirle un fallo: descartaba las etiquetas de botón de
una sola palabra»*. Es decir, el propio plugin ya había tenido este problema exacto —perder
etiquetas de botón— y lo había arreglado. La herramienta que existe precisamente para esto
sigue rota (fase 0 y fase 7), yo improvisé un sustituto peor, y el sustituto falló justo
donde el original ya sabía que se falla.

## ✅ Lo que sí funciona: leer el texto renderizado

```
content_render(post_id: 202, alcance: "pagina", texto: true)
```

Devuelve **el texto que lee una persona**, de la cabecera al pie, sin marcado. No depende de
qué tipo de widget guarde cada trozo, que es exactamente donde se me escapaban las cosas:
en la fase 7 fueron `icon-list`, `counter` y `google_maps`; en ésta, `button`.

Lo pasé por las nueve plantillas distintas del sitio. Una página son unas 600–900 palabras,
así que **el sitio entero cabe en nueve llamadas**. Debería haber sido mi primera
herramienta de verificación, no la última.

Lo que encontró, aparte de los dieciséis botones:

1. **Iconos sociales de mentira en cabecera y pie.** Twitter, Facebook, **Google Plus**
   —cerrado en 2019—, YouTube y Pinterest, y **ninguno con enlace**: el kit los deja
   apuntando a nada. En una web de alquiler de maquinaria, además, Pinterest no pinta nada.
   Cambiados a LinkedIn, Facebook, YouTube y WhatsApp, los cuatro con su enlace.
2. **Dos direcciones distintas para la misma oficina.** Contacto decía *«calle Trebolar
   44»* y el pie, el aviso legal y la política de privacidad decían *«calle Alaún 24»*.
   Las escribí yo, en fases distintas, sin releer lo anterior. Unificado en Alaún 24 y
   **fijado en `ENCARGO-ALTORRE.md`** para que no vuelva a divergir: el problema no era la
   dirección, era no tener una fuente única.
3. **Migas de pan inconsistentes**: en Contacto faltaba el separador `~` que llevan las
   otras veinte páginas.
4. **La sección «Guías de máquina y consejos de obra» vacía en las ocho fichas**: el
   titular sin nada debajo, otro contenedor huérfano del kit (fase 7, entrada A/B). Añadido
   el widget `posts` en las ocho.

Los cuatro son el mismo tipo de fallo: cosas que se ven de un vistazo leyendo la página y
que no se ven mirando el JSON widget a widget.

## 🔧 Un apaño que sí salió bien: localizar contenedores vacíos con SQL

Para meter el listado de entradas en las ocho fichas necesitaba el id del contenedor vacío
de cada una. `elementor_outline` lo da, pero son 2.500 tokens por página y ocho páginas.
Como los contenedores vacíos no tienen texto, `elementor_find` no los encuentra.

Salida: el contenedor vacío es siempre el elemento que sigue al titular en el JSON, así que
una sola consulta lo saca de las siete páginas a la vez:

```sql
SELECT post_id,
       LEFT(SUBSTRING_INDEX(SUBSTRING_INDEX(
              SUBSTRING_INDEX(meta_value, 'consejos de obra', -1),
              CONCAT('{', CHAR(34), 'id', CHAR(34), ':', CHAR(34)), 2),
              CONCAT('{', CHAR(34), 'id', CHAR(34), ':', CHAR(34)), -1), 7)
FROM xXInS_postmeta WHERE meta_key = '_elementor_data' AND post_id IN (203,...,209)
```

Una llamada en vez de siete `elementor_outline`. Lo anoto porque es el hueco de siempre:
**no hay forma de pedir un elemento por su ruta.** `elementor_find` devuelve `ruta` en cada
resultado —`"ruta":"6.1"`— pero no acepta `ruta` como criterio de búsqueda. Si lo aceptara,
esto sería una llamada tool-native en vez de una consulta SQL de tres niveles.

## Lo que cambio en mi método a partir de aquí

1. **`content_render(texto: true)` sobre cada plantilla distinta antes de decir «terminado».**
   No al final: antes de decirlo.
2. El barrido por palabras vale para **confirmar** que algo concreto desapareció, nunca para
   **descartar** que quede algo.
3. Cuando el encargo fija un dato —una dirección, un teléfono, un precio— va al fichero del
   encargo la primera vez que lo invento, no la tercera vez que lo escribo distinto.

## Estado

Nueve plantillas leídas de principio a fin. **Cero inglés en el sitio.** Los dieciséis
botones, los iconos sociales, la dirección, las migas y las ocho secciones de blog vacías,
corregidos.
