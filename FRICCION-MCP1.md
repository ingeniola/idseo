# Registro de fricción — Ingenio MCP (mcp1.webs27.online)

Sesión iniciada el 2026-09-15. Se anota **en el momento**, no al final.
Herramientas visibles del servidor `MCP_1`: **161** (recuento manual sobre el
listado de herramientas diferidas; coincide con lo esperado).

Convenio: cada entrada lleva la letra del tipo de fricción (A–G) del encargo.

---

## Fase 0 — Análisis del Template Kit instalado (previo al encargo)

### D — Inspeccionar un Template Kit instalado (qué trae, qué widgets usa, qué plugins exige)

**Llamada:** no existe ninguna. Busqué con `ToolSearch("template kit import envato instalar kit")`
y el catálogo devolvió sólo `elementor_kit_get/update` (colores y tipografías globales) y
`elementor_template_import`. Nada sobre Template Kits.

**Respuesta:** ninguna herramienta del servidor conoce el tipo de contenido `envato_tk_import`
ni el directorio `uploads/template-kits/`.

**Qué hice:** tres apaños encadenados.
1. `content_get(post_id: 12)` — acerté porque el ID venía en la URL que me dio la persona.
   Esto sí funcionó bien: devolvió el `envato_tk_manifest` entero en `meta`. Buen resultado
   por accidente, no por diseño: si no tuviera el ID no habría sabido que el tipo se llama
   `envato_tk_import` (`content_search` con `post_type:"any"` no lo lista por defecto).
2. `file_list` sobre `uploads/template-kits/<hash>/` para ver la estructura.
3. `ingenio_execute_php` para contar los `widgetType` de los 18 JSON y las librerías de iconos.

**Coste:** ~4 llamadas de más, y una de ellas ejecutando PHP.

**Mi lectura:** límite razonable en parte — un servidor MCP de WordPress genérico no tiene por
qué conocer un plugin de terceros. Pero el hueco concreto sí duele: **no hay forma de saber
qué widgets usa un JSON de Elementor sin leerlo entero o ejecutar PHP.** Los ficheros aquí
pesan hasta 1,8 MB; `file_read` me los devolvería enteros y me comería el contexto. Falta algo
como `elementor_template_analyze(ruta|post_id)` que devuelva el censo de `widgetType`,
librerías de iconos y fuentes. Ese análisis es el primer paso de *cualquier* trabajo con
plantillas importadas.

### A/E — Importar una plantilla de kit desde un fichero del servidor

**Llamada:** la única candidata es
`elementor_template_import(paquete: <objeto>, tipo, titulo)`.

**Respuesta:** (no ejecutada) su descripción exige pasar **el objeto exportado inline**.

**Qué hice:** nada todavía. Lo anoto porque es un muro previsible: `home.json` pesa 1,8 MB y
`about-us.json` 1,0 MB. Pasarlos como argumento JSON en una llamada MCP no es viable ni por
contexto ni por tamaño de payload.

**Coste:** 0 por ahora, bloqueo potencial completo después.

**Mi lectura:** **fallo de diseño**, no límite razonable. `elementor_template_import` debería
aceptar además una **ruta dentro de `wp-content`** (`ruta: "uploads/.../home.json"`), igual que
`file_read` ya acepta rutas. El servidor tiene `file_read`, `file_write` y `bundle_import`;
que el importador de plantillas sea el único que obliga a viajar por el contexto del modelo
convierte la ruta natural (kit en disco → biblioteca) en imposible.


---

## Fase 1 — Preparar el sitio para el kit

### A/D — Instalar y activar plugins

**Llamada:** busqué con `ToolSearch("+plugin instalar activar plugin install activate")`.

**Respuesta:** de `MCP_1` sólo aparece `plugin_list` ("Lista los plugins instalados con su
estado y versión"). No hay `plugin_install`, `plugin_activate`, `plugin_update` ni
`plugin_search`. Dato llamativo: **los otros servidores WordPress conectados a esta misma
sesión (`emcp-*`) sí traen `search-plugins`** contra el directorio de wordpress.org. Ingenio
MCP no.

**Qué hice:** recurrir a `wp_cli("plugin install <slug> --activate")` — ver resultado abajo.

**Coste:** 1 llamada de búsqueda + 4 de `wp_cli` que deberían haber sido 1 herramienta.

**Mi lectura:** **hueco claro**. El servidor tiene 161 herramientas, incluidas cosas muy
específicas (`elementor_global_classes_reorder`, `wc_order_note`, `seo_redirect_delete`), y
sin embargo instalar un plugin —la operación más común al montar un sitio— obliga a bajar a
WP-CLI. Además es incoherente con el resto: hay `snippet_write`, `theme_file_write` y
`file_write`, todas capaces de meter código ejecutable en el sitio, pero no un instalador
de plugins que al menos pasaría por el repositorio oficial y firmado de wordpress.org.
Faltan `plugin_install`, `plugin_activate`/`plugin_deactivate` y `plugin_search`.

**Resultado de los `wp_cli`:** los cuatro instalados y activados a la primera. Slugs acertados
sin probar porque el propio manifiesto del kit trae la ruta del fichero
(`elementskit-lite/elementskit-lite.php`), así que de ahí salió el slug. Sin el manifiesto
habría sido adivinar (entrada tipo G evitada por suerte, no por diseño).

### F — Instalar código de terceros no pidió aprobación

**Llamada:** `wp_cli("plugin install elementskit-lite --activate")`

**Respuesta (literal):**
```
{"results":{"elementskit-lite":"instalado y activado"},"command":"wp plugin install",
"tier":"write","aviso":"Instalar plugins: elementskit-lite. Instalar descarga código de
fuera y lo deja en el servidor. Aunque no se active, queda ahí. Se ejecutó sin pedir
aprobación porque el modo de aprobaciones está en \"nunca\"."}
```

**Qué hice:** nada, siguió adelante. Instalé 4 plugins y 1 tema igual.

**Coste:** 0 llamadas.

**Mi lectura:** **no es fallo del plugin, y lo anoto porque el encargo pide anotar cuando NO
te para donde parecía que debía.** Descargar y ejecutar código de terceros es de lo más
sensible que se puede hacer en un WordPress, y pasó sin freno. Dicho eso, el servidor lo hace
bien: el sitio está configurado con el modo de aprobaciones en "nunca", la herramienta lo
ejecuta **y te lo dice en el propio resultado**, explicando qué implica y por qué no paró.
Ese `aviso` es buen diseño — la decisión ya la tomó un humano al configurar el sitio, y el
servidor no la esconde. Lo que sí chirría es la incoherencia: **una operación `tier: write`
que trae código nuevo al servidor debería estar en el mismo escalón que borrar un usuario**,
y con el modo en "nunca" ninguna de las dos para. Si el modo "nunca" se pensó para agilizar
ediciones de contenido, instalar plugins probablemente no debería vivir bajo esa misma llave.

### A/D — Activar un tema

**Llamada:** `ToolSearch("+theme activar tema cambiar tema activo switch")`

**Respuesta:** sólo `theme_draft_create`, `theme_file_list`, `theme_file_read`. Hay once
herramientas de tema (`theme_list`, `theme_draft_*` ×4, `theme_file_*` ×5, `theme_preview_*` ×3)
y **ninguna instala ni activa un tema**.

**Qué hice:** `wp_cli("theme install hello-elementor")` + `wp_cli("theme activate hello-elementor")`.

**Coste:** 1 búsqueda + 2 `wp_cli`.

**Mi lectura:** mismo hueco que con los plugins, y aquí se nota más porque el bloque de temas
está muy desarrollado: todo el flujo de borrador/preview/publicar está pensado para **editar
el tema que ya hay**, y no contempla que montar un sitio empieza por **elegir cuál**. El kit
Plantix exige Hello Elementor (su `global.json` trae `default_page_template:
"elementor_header_footer"` y `hello_footer_copyright_text`) y el sitio venía con
Twenty Twenty-Five, un tema de bloques. Faltan `theme_install` y `theme_activate`.

---

## Fase 2 — Importar el kit

### D — Importar 17 plantillas de Elementor desde ficheros del servidor

**Llamada:** la única candidata sigue siendo
`elementor_template_import(paquete: <objeto inline>, tipo, titulo)`.

**Respuesta:** no llegué a llamarla. Para usarla tendría que meter el JSON entero como
argumento. Tamaños reales de este kit: `home.json` 1,88 MB, `about-us.json` 1,06 MB,
`services.json` 0,97 MB. Sólo la Home no cabe en una llamada, y aunque cupiera significaría
leerla con `file_read` y arrastrarla por mi contexto dos veces (una al leer, otra al escribir).

**Qué hice:** `ingenio_execute_php` llamando a la maquinaria propia de Elementor:
```php
$src = \Elementor\Plugin::$instance->templates_manager->get_source('local');
$src->import_template('Home', $dir . 'home.json');
```
Cinco llamadas de PHP en lotes (las grandes por separado, porque la Home sola tardó 17,8 s y
tocó 124 MB de pico de memoria). Resultado: **17 plantillas y 51 imágenes** descargadas desde
`kits.roxthemes.com` con las URLs remapeadas. Funcionó perfecto — pero lo hizo Elementor, no
Ingenio MCP.

**Coste:** 5 llamadas de PHP. Con una herramienta decente habrían sido 1 o 2.

**Mi lectura:** **el hueco más grande que me he encontrado hasta ahora.** No es que falte una
herramienta exótica: es que `elementor_template_import` está a un parámetro de servir y no lo
tiene. Bastaría con aceptar `ruta` además de `paquete`:

> `elementor_template_import(ruta: "uploads/template-kits/<hash>/templates/home.json", titulo: "Home")`

El servidor ya sabe leer rutas de `wp-content` (`file_read`, `file_list`, `file_search` viven
ahí), ya tiene el concepto de operación larga (`wp_cli_job`, `wp_cli_jobs`), y Elementor expone
el método público que hace el trabajo, descarga de imágenes incluida. Tal como está, la
herramienta sólo sirve para plantillas pequeñas creadas en la propia conversación, que es
justo el caso que menos falta hace. **Un kit comprado es el caso de uso normal y es
exactamente el que no cubre.**

### ✅ Lo que sí funcionó bien: `elementor_kit_update`

Merece constar porque contrasta con lo anterior. Le pasé las cuatro listas completas de
colores y tipografías del `global.json` y devolvió:

```
"tocado":["colores_sistema","colores_propios","tipografia_sistema","tipografia_propia"],
"deshacer":{"revision":1,"como":"history_restore con revision=1 devuelve esto a como estaba."},
"aviso":"... Se ejecutó sin pedir aprobación porque tiene vuelta atrás: history_restore
         con revision=1 la deshace."
```

Tres cosas bien hechas a la vez: dice **qué** tocó, da el **camino exacto de vuelta atrás**, y
justifica **por qué no pidió aprobación** (porque es reversible). La advertencia de la
descripción —"cada lista se sustituye entera, manda la lista completa"— también me ahorró un
error: sin ella habría mandado sólo los colores cambiados y me habría cargado el resto.
Si el resto del servidor estuviera a este nivel, este registro sería más corto.

### A — Ajustes globales del kit que no son color ni tipografía

**Llamada:** `ToolSearch("+kit ajustes globales botones ancho contenedor breakpoints kit settings layout")`

**Respuesta:** sólo `elementor_kit_get` / `elementor_kit_update` / `elementor_get_kit`, y las
tres se ocupan **únicamente** de colores y tipografías.

**Qué hice:** `ingenio_execute_php` escribiendo directamente
`_elementor_page_settings` del kit (post 9) con las 28 claves restantes del `global.json`.

**Coste:** 1 búsqueda + 1 llamada de PHP.

**Mi lectura:** **hueco real, y de los que dejan el sitio "raro a medias"** (regla 5). Lo que
se queda fuera no es decoración:

| Clave | Qué pasa si no se aplica |
|---|---|
| `__globals__` (23 entradas) | H1–H6 no apuntan a las tipografías del kit: **todos los títulos con el tamaño por defecto** |
| `button_border_radius: 50px` | Botones cuadrados en vez de píldora, en todo el sitio |
| `container_width: 1300` | Ancho de contenedor equivocado en todas las páginas |
| `viewport_md/lg: 768/1025` | Breakpoints distintos a los que se diseñó el kit |

Y esto es lo peligroso: **importas el kit, lo ves y "casi" está bien.** Los colores están, las
fuentes están, pero los títulos no escalan y los botones no tienen la forma. Nadie lo achaca a
una importación incompleta; lo achaca al kit. Falta un `elementor_kit_settings_get/update`
genérico, o que `elementor_kit_update` acepte un objeto `ajustes` libre además de las cuatro
listas. La descripción actual, además, **no avisa de que sólo cubre colores y tipografías**:
dice "Cambia los colores o las tipografías del kit", que es literalmente cierto, pero se lee
como "esta es la herramienta del kit" cuando el kit tiene bastante más dentro.

---

## Fase 3 — Aplicar el kit (cabecera, pie, páginas)

### E — Convertir una plantilla importada en cabecera del theme builder

Las 17 plantillas entraron con el `type` que traía su JSON: **Header V1 y Footer V1 quedaron
como `section`**, no como `header`/`footer`. Elementor importa fielmente; el kit las marca así
porque el importador oficial de Envato les cambia el tipo después. Para dejarlas utilizables
hacen falta **3 llamadas por plantilla**:

```
elementor_template_create(titulo:"Cabecera", tipo:"header")      -> id 99, borrador
elementor_template_apply(post_id:99, template_id:17, modo:"reemplazar")
elementor_template_conditions(post_id:99, condiciones:["include/general"])
content_update(post_id:99, status:"publish")
```

Cuatro, en realidad. **Coste:** 8 llamadas para cabecera y pie.

**Mi lectura:** fricción menor pero evitable. `elementor_template_create` acepta `tipo` y
`elementor_template_apply` acepta contenido; falta poder decir "esta plantilla ya importada
pásala a tipo header", o que `template_create` acepte un `desde_template_id`. Nada grave por
sí solo — pero es el primer eslabón de la cadena de fallos que viene ahora.

### 🔴 B — `elementor_template_apply` dice que sí y deja la página invisible

**Llamada:**
```
content_create(title:"Inicio", post_type:"page", slug:"inicio", status:"publish")   -> id 103
elementor_template_apply(post_id:103, template_id:83, modo:"reemplazar")
```

**Respuesta (literal):**
```
{"post_id":103,"template_id":83,"accion":"contenido sustituido","elementos":372,
 "deshacer":{"revision":6,...},"aviso":"Sustituir el contenido de la página 103 por la
 plantilla 83. Se descartan los 0 elementos que tiene la página. ..."}
```

Éxito rotundo: 372 elementos. **Y la página quedaba rota.** Lo comprobé:

```
_elementor_edit_mode      -> ""            (vacío)
_elementor_template_type  -> "wp-page"
_elementor_data           -> 435.286 bytes (ahí está todo)
is_built_with_elementor() -> false
```

En el front: `body class="... page-template-default ..."`, **cero** apariciones de
`elementor-element-`, y el contenido escupido como HTML desnudo — `<h1>`, `<ul><li>`, SVGs
sueltos y enlaces sin estilo, uno detrás de otro. La página "funcionaba" (HTTP 200, el texto
estaba) y era **basura visual**.

**Qué hice:** `elementor_enable(post_id:103)` — que falló, ver la entrada siguiente — y luego
con `force:true`. Tras eso: 372 elementos renderizados, `elementor-page-103`,
`elementor-template-full-width`. Correcto.

**Coste:** 6 llamadas de diagnóstico (3 de ellas PHP) + 2 de arreglo.

**Mi lectura:** **el fallo más grave de la sesión, y justo del tipo que avisa la regla 5.** No
revienta: te devuelve un éxito con un número que da confianza —372 elementos— y deja el sitio
publicado y feo. Si no llego a mirar el HTML del front, habría seguido creando ocho páginas
más exactamente igual de rotas y las habría dado por buenas. `elementor_template_apply`
tiene que hacer una de dos cosas, y me da igual cuál: **o marca la página como Elementor al
escribirla, o se niega a escribir** como promete `elementor_enable`. Devolver "contenido
sustituido, 372 elementos" sobre una página que Elementor no va a dibujar es lo peor de los
dos mundos.

Nota aparte: el orden correcto (`content_create` → `elementor_enable` → `template_apply`) **no
está dicho en ninguna descripción**. `elementor_enable` dice "hace falta al crear una página
nueva", pero `elementor_template_apply` no menciona el requisito, y su texto —"Vuelca el
contenido de una plantilla en una página"— invita justo al orden equivocado.

### C/B — `elementor_enable` se protege de datos que escribió el propio servidor

**Llamada:** `elementor_enable(post_id:103)`

**Respuesta (literal):**
```
[security_gate] Esa página ya tiene contenido del editor de bloques. Elementor no lo importa:
al activarlo dejaría de verse sin llegar a borrarse. Si de verdad quieres convertirla, pasa force.
```

**Qué hice:** miré qué había realmente en `post_content`: **44.143 bytes de HTML aplanado**
—`<img srcset=...>`, `<h1>`, `<p>`— que **no los puso ningún humano en Gutenberg**: los acababa
de escribir `elementor_template_apply` dos llamadas antes (Elementor guarda el render en
`post_content` al salvar un documento). Repetí con `force:true` y pasó.

**Coste:** 1 llamada fallida + 1 de PHP para entender qué protegía + 1 repetición.

**Mi lectura:** **la puerta está bien puesta y mal informada.** Proteger contenido de bloques
es correcto y el mensaje explica bien el riesgo. El problema es que aquí protegía **su propio
vómito**, y para saberlo tuve que ir a mirar `post_content` por PHP: el error no dice *qué* hay
dentro. Con un `force` a ciegas, alguien con contenido de Gutenberg de verdad lo pierde de
vista igual. Bastaría con que el error dijera los primeros 200 caracteres y el tamaño, o mejor:
que detectara que el contenido es un render de Elementor —lleva `_elementor_data` al lado— y no
se activara la puerta. Esto es consecuencia directa del fallo anterior: arreglado aquél,
esta puerta deja de saltar donde no toca.

### 🔴 B — `elementor_template_conditions` dice "cache regenerada" y no la regenera

**Llamada:**
```
elementor_template_conditions(post_id:99, condiciones:["include/general"])
```

**Respuesta (literal):**
```
{"id":99,"condiciones":["include/general"],"cache":"regenerada por Elementor Pro",
 "regenerada":true,"aviso":"Cambiar dónde se aplica la plantilla \"Cabecera\" (#99). ..."}
```

**Qué comprobé:** la cabecera **no salía en el sitio**. El front seguía pintando la cabecera
propia de Hello Elementor (el título "MCP 1" en un `<header id="site-header">`) y en el HTML
sólo había `data-elementor-type="wp-page"`. Preguntando a Elementor Pro directamente:

```
get_documents_for_location('header') -> []
get_documents_for_location('footer') -> []
get_post_meta(99,'_elementor_conditions') -> ["include/general"]   <- el dato SÍ está
get_option('elementor_pro_theme_builder_conditions') -> false      <- la caché NO
```

Repetí la llamada con las plantillas ya publicadas (por si era cosa del estado borrador).
**Mismo resultado: `"regenerada":true` y la caché seguía en `false`.**

**Qué hice:** regenerarla por PHP:
```php
\ElementorPro\Modules\ThemeBuilder\Module::instance()
    ->get_conditions_manager()->get_cache()->regenerate();
```
Inmediatamente después la opción pasó a
`{"footer":{"100":["include/general"]},"header":{"99":["include/general"]}}` y el front empezó a
devolver `data-elementor-type` = header + wp-page + footer.

**Coste:** 2 llamadas inútiles a la herramienta + 4 de PHP diagnosticando + 1 de arreglo.

**Mi lectura:** **fallo del plugin, y con agravante.** La descripción de esta herramienta dice,
literalmente:

> "Se guarda por el gestor de Elementor Pro, que además regenera su caché de ubicaciones:
> escribir el metadato a mano deja el dato bien y la plantilla sin aplicarse, y es el fallo
> más difícil de diagnosticar del theme builder."

Es decir: la herramienta **existe para evitar exactamente este fallo**, lo describe con
precisión quirúrgica, afirma en su respuesta que lo ha evitado (`"regenerada": true`) — **y
produce el fallo.** Que el campo diga `true` sin comprobar nada es lo que convierte un bug en
una trampa: con `"regenerada": false` habría sabido en 10 segundos qué pasaba. Tal como está,
me costó seis llamadas y tener que preguntarle a Elementor Pro por debajo.

Y encaja con el patrón del fallo anterior: **el servidor escribe el dato bien y no ejecuta el
efecto secundario que hace que el dato sirva.** En `template_apply` era la marca de Elementor;
aquí es la caché de ubicaciones. Si tuviera que apostar dónde hay más bugs iguales, apostaría
a "en todo sitio donde el plugin escribe un metadato de Elementor sin pasar por el guardado
del documento".

---

## Fase 4 — Menú, portada y formularios

### E/A — Un menú se crea en 4 llamadas y ninguna te da el dato que necesitas

**Llamadas:**
```
menu_create(nombre:"Menú principal")   -> {"menu_id":6,"nombre":"Menú principal"}
menu_update(menu:"Menú principal", items:[...8 items...])
menu_assign(menu:"Menú principal", ubicacion:"menu-1")
menu_get(menu:"6")
```

El widget de cabecera del kit es `ekit-nav-menu` y guarda el menú **por slug**:
`{"elementskit_nav_menu": "plantix"}` — el slug del sitio demo, que aquí no existe. Para
apuntarlo al menú nuevo necesitaba **el slug del menú recién creado**.

**Respuesta:** ni `menu_create` ni `menu_get` ni `menu_list` devuelven el slug. `menu_create`
da `menu_id` y `nombre`; `menu_get` da `menu_id`, `nombre`, `total` e `items`. El slug no
aparece en ninguna parte.

**Qué hice:** primero probé `wp_cli("menu list")`:
```
[not_supported] El comando "menu list" no está en la allowlist de este servidor.
Usa wp_cli_catalogue para ver todo lo soportado. Si lo que quieres es tocar contenido,
ficheros de tema o medios, hay herramientas MCP dedicadas que son mejores que el emulador.
```
Y acabé en `ingenio_execute_php`: `wp_get_nav_menu_object(6)->slug` → `"menu-principal"`.

**Coste:** 1 llamada fallida + 1 de PHP.

**Mi lectura:** hueco pequeño y molesto. Es un campo. El slug es **el identificador con el que
medio ecosistema de Elementor referencia un menú** (ElementsKit, Jeg Kit y el propio widget de
Elementor Pro lo hacen así), y el servidor lo esconde justo en el bloque de herramientas que
existe para gestionarlos. Añadir `slug` a la respuesta de `menu_create` y `menu_get` cierra
esto entero.

Comentario aparte sobre el mensaje de error de `wp_cli`: la frase "hay herramientas MCP
dedicadas que son mejores que el emulador" es correcta de intención y **falsa en este caso
concreto** — bajé a `wp_cli` precisamente porque la herramienta dedicada no daba el dato. Un
error que te reorienta a donde ya has estado cuesta una llamada.

### 🔴 B — Los formularios del kit se importan vacíos y nadie avisa

**Qué pasó:** tras montar las 9 páginas, todas devolvían HTTP 200, con cabecera y pie, sin
un solo widget roto y sin imágenes rotas. **La página de Contacto tenía cero campos de
formulario.** Lo vi contando `<input>` en el HTML del front:

```
campos_en_el_front: []
formularios_metform_existentes: []
```

El widget estaba ahí y su ajuste era:
```
{"mf_form_id": "1990***1787482008290"}
```
El ID del formulario **del sitio demo del kit**. MetForm guarda los formularios en un tipo de
contenido propio (`metform-form`) y el kit los trae como **secciones sueltas de la biblioteca**
(`Metform - Contact 01/02/Newsletter`), no como formularios. Nada los conecta.

Tres widgets afectados: Contacto (`110`), Portada (`103`) y **el pie (`100`), que sale en todas
las páginas del sitio**.

**Qué hice:** crear los tres formularios como `metform-form` y volcar en ellos las secciones
importadas, luego reapuntar los widgets:
```
content_create(post_type:"metform-form") x3  ->  136, 137, 138
elementor_enable x3
elementor_template_apply(136<-49, 137<-51, 138<-53)
elementor_element_update(110, "98d6f57", {mf_form_id:136})
elementor_element_update(103, "4aaff3f", {mf_form_id:137})
elementor_element_update(100, "4b557a4", {mf_form_id:138})
```
Comprobado después: `["mf-first-name","mf-last-name","mf-email","mf-subject","mf-comment"]`.

**Coste:** 12 llamadas, más 3 de diagnóstico por PHP para entender qué referenciaba el widget.

**Mi lectura:** la causa es del kit y de MetForm, **no del servidor MCP**. Pero el encargo
pregunta qué me frenó, y esto me frenó: lo que falta es una herramienta que conteste
**"¿hay widgets en este sitio apuntando a cosas que no existen?"**. Hay `security_audit`,
`seo_audit`, `site_performance` y `content_links` (enlaces rotos) — o sea, el servidor ya tiene
la idea de auditar. Falta la auditoría que importa al maquetar: referencias rotas de widget
(formularios, menús por slug, plantillas, IDs de medios). **Es el fallo más caro de todos
porque no se ve**: un formulario de contacto que no existe en una web de servicios significa
que el negocio deja de recibir clientes y nadie se entera hasta semanas después.

Segunda observación: `elementor_find` **sí** me dio la pista, y rápido. Su campo `texto`
mostró `"1990***1787482008290"` en el widget `metform`, que es lo que me hizo sospechar. Buena
herramienta.

### E — `elementor_element_update` devuelve el widget entero

Cada llamada a `elementor_element_update` me devolvió **los ajustes completos del widget**. En
el caso del `ekit-nav-menu` eso fueron unas 4.000 palabras de JSON —80 claves de fondos vacíos,
sombras y bordes de tablet— para confirmar un cambio de un campo:
`{"elementskit_nav_menu": "menu-principal"}`.

**Mi lectura:** no es un fallo, es un coste. Y es un coste raro porque la propia descripción de
`elementor_find` dice la frase correcta: *"Úsala en vez de traerte la página entera: sobre una
página real el JSON completo son cientos de kilobytes y casi todo sobra"*. El servidor entiende
el problema y luego lo comete en la herramienta de al lado. Con devolver sólo `{id, cambiado:
["elementskit_nav_menu"], deshacer}` bastaría; o un parámetro `devolver: "minimo"|"completo"`.
Repetido 6 veces en esta sesión, es contexto que no me pude gastar en trabajo.

---

# Parte 2 — Encargo real: Jardines del Valle

Sitio de una empresa de jardinería y paisajismo en Honduras. 33 páginas en alcance
(el propio documento deja fuera, explícitamente, las 18 páginas de ciudad y los 12
artículos del blog: "Pendientes para la siguiente fase").

## Fase 5 — Estructura de páginas

### ✅ Lo que funcionó: `batch`

Es la herramienta que hace viable este volumen. Crear las 12 páginas de servicio, las 6 de
proyecto y las 6 sueltas fueron **4 llamadas** en vez de 24; aplicar plantilla a 23 páginas
fueron **4 llamadas** en vez de 46. Su descripción es honesta donde importa: *"No es una
transacción: lo ya ejecutado se queda hecho, y por eso devuelve la revisión de cada paso"*.
Eso es exactamente lo que necesitas saber antes de mandar 12 pasos de golpe.

Una pega: **la respuesta de `batch` repite el `aviso` completo de cada paso**. Doce pasos de
`elementor_template_apply` devolvieron doce veces el mismo párrafo de "Se descartan los 0
elementos que tiene la página. Quedan en el historial, pero nadie los recupera...". El aviso
está bien escrito y es útil una vez; doce veces seguidas es ruido que desplaza trabajo. Un
`avisos: "agrupados"` que lo diga una sola vez arreglaría esto.

### A — No se puede cambiar el padre de una página existente

**Qué quería:** mover la página 112 (`Mantenimiento de jardines residenciales`) bajo
`/servicios/`, para que su URL fuera `/servicios/mantenimiento-de-jardines/`.

**Llamada:** `content_update(post_id:112, title:..., slug:"mantenimiento-de-jardines")`

**Respuesta:** correcta, pero la URL quedó en `/mantenimiento-de-jardines/`. **`content_update`
no acepta `parent`.** Sus campos son `content`, `excerpt`, `meta`, `post_id`, `slug`, `status`,
`title`. `content_create` **sí** acepta `parent`.

**Qué hice:** probé `wp_cli("post update 112 --post_parent=105")`:
```
[not_supported] El comando "post update" no está en la allowlist de este servidor.
Sí están disponibles: wp post meta get, wp post meta list.
```
Y acabé en PHP: `wp_update_post(['ID'=>112,'post_parent'=>105])`.

**Coste:** 1 llamada fallida + 1 de PHP.

**Mi lectura:** **asimetría sin motivo.** Si `content_create` sabe poner un padre,
`content_update` debería saber cambiarlo: es el mismo campo de la misma tabla. Y no es un caso
raro — reorganizar la jerarquía de un sitio es de las cosas más normales que se hacen al
maquetar. Nota sobre la allowlist de `wp_cli`: deja instalar plugins y cambiar el tema activo
—las dos operaciones más peligrosas del día— pero no deja cambiar el padre de una página.
El criterio de qué está dentro y qué fuera no sigue el riesgo.

### C/F — `option_update` dice que la identidad del sitio está bloqueada, y no lo está

**Descripción de la herramienta:**
> "Las opciones de identidad del sitio y las claves criptográficas están bloqueadas sin
> excepción; las opciones grandes de constructores piden aprobación."

**Llamada:** `option_update(name:"blogname", value:"Jardines del Valle")`

**Respuesta:**
```
{"name":"blogname","action":"actualizada","stored":"Jardines del Valle",
 "deshacer":{"revision":44,...}}
```

Lo mismo con `blogdescription`. `blogname` **es** la identidad del sitio: es el nombre que sale
en el `<title>`, en la cabecera y en los correos que manda WordPress.

**Qué hice:** nada, lo necesitaba y funcionó.

**Coste:** 0.

**Mi lectura:** **no es un fallo de comportamiento — el comportamiento es el que quiero — es un
fallo de la descripción, y de los que salen caros.** Una descripción que anuncia un bloqueo
inexistente hace que ni lo intentes: yo estuve a punto de irme a `wp_cli` directamente. Es el
espejo del problema de `elementor_template_conditions`: allí la herramienta prometía hacer algo
y no lo hacía; aquí promete no dejarte hacer algo y sí te deja. En ambos casos **la
descripción no corresponde al código**, y en un servidor MCP la descripción *es* la
documentación: no hay otra. Si "identidad del sitio" se refiere a `siteurl` y `home` —que sí
tiene todo el sentido bloquear, porque tumban el sitio— la frase debería decir eso:
"`siteurl` y `home` están bloqueadas".

## Fase 6 — Contenido: reescribir la portada

### ✅ `content_render` con `buscar`

Para comprobar que un cambio llegó al front:
```
content_render(post_id:103, alcance:"pagina", buscar:"Su jardín bien cuidado")
-> {"bytes":276558,"aparece":true,"contexto":"...<h1 class=\"elementor-heading-title...\">Su
    jardín bien cuidado, todo el año y en cualquier parte de Honduras</h1>..."}
```
Devuelve 250 caracteres de contexto en vez de 276 KB de HTML, y con el marcado alrededor, que
es justo lo que hace falta para saber si salió como `<h1>` o como texto suelto. Esta herramienta
es la que me habría ahorrado media fase 3: es exactamente "compruébame que se ve de verdad".
Su descripción lo dice con todas las letras —*"Es la forma de comprobar que un cambio se ve de
verdad y no sólo que se guardó"*— y es cierto.

### 🔴 E — El coste de contexto de `elementor_element_update`, con números

Reescribí los **24 titulares** de la portada. Lo que mandé y lo que me devolvieron:

| | Enviado | Devuelto |
|---|---|---|
| 21 `heading` | ~40 caracteres cada uno (`{"title": "..."}`) | ~450 palabras cada uno |
| 3 `elementskit-heading` | 3 campos | **~1.200 palabras cada uno** |

El widget `elementskit-heading` devolvió, por cambiar tres textos, las 40 claves de
`..._secondary_bg_slideshow_gallery`, `..._border_color_right_video_fallback` y compañía, todas
vacías. **La respuesta pesa unas 30 veces lo que la petición.**

Y esto escala mal de forma brutal. La portada tiene 411 elementos, de los cuales unos 95
llevan texto: 24 titulares (hechos), 13 `text-editor`, 14 botones, 17 `icon-box`, 15
`icon-list`, 5 contadores, más testimonios, acordeón y fichas. **Son ~95 escrituras para UNA
página.** El sitio tiene 33. Del orden de **1.500 escrituras de elemento**, y en cada una el
servidor me devuelve el widget entero.

**Mi lectura:** no es un bug, es la decisión de diseño que más limita al servidor en trabajo
real. Y tiene arreglo barato: `elementor_element_update` ya sabe qué claves tocó —lo sabe
porque las fusionó—, así que devolver
```json
{"post_id":103,"element_id":"ffcc7f9","cambiado":["ekit_heading_title","ekit_heading_sub_title"],
 "deshacer":{"revision":120}}
```
sería suficiente en el 95% de los casos. Un parámetro `devolver:"minimo"|"completo"` lo
resolvería sin romper a nadie. Comparado con esto, que falte `plugin_install` es una molestia;
esto es lo que decide si el servidor sirve para maquetar un sitio entero o sólo para retoques.

### B — El campo `texto` de `elementor_find` miente en `text-editor`

**Llamada:** `elementor_find(post_id:103, widget:"text-editor")`

**Respuesta (extracto literal):**
```json
{"id":"fa633e1","widget":"text-editor","ruta":"1.1.0.2","texto":"px"}
{"id":"f98351b","widget":"text-editor","ruta":"2.0.1.0","texto":"end"}
{"id":"3c54c2c","widget":"text-editor","ruta":"7.0.1.0.1","texto":"px"}
{"id":"b94e29e","widget":"text-editor","ruta":"11.0.1.0","texto":"end"}
```
De 13 `text-editor`, **5 devolvieron `"px"` o `"end"`**: son valores de una unidad CSS y de una
alineación, no el contenido del widget. Los otros 8 sí traían texto real.

**Qué hice:** todavía nada; los 5 hay que abrirlos uno a uno para saber qué dicen.

**Coste:** 5 llamadas extra que la herramienta existía para evitar.

**Mi lectura:** **fallo, y de los sutiles.** Parece que `texto` coge *la primera clave string
que encuentra* en los ajustes en lugar de la clave de contenido del widget (`editor` en
`text-editor`). Cuando acierta es utilísimo —fue lo que me destapó el `mf_form_id` del sitio
demo en los formularios— y cuando falla no te avisa: te devuelve `"px"` con toda la
naturalidad, y si te fías, te saltas un widget con texto en inglés dentro. El arreglo es
mapear la clave de contenido por tipo de widget (`title` en heading, `editor` en text-editor,
`text` en button) en vez de adivinar.

### 🔴 A — Los repetidores no se pueden parchear: o los reescribes enteros, o nada

**Qué quería:** cambiar el nombre, el cargo y el texto de 3 testimonios, y el título y
contenido de 5 preguntas del acordeón. Nueve campos de texto en total.

**Lo que dice la herramienta** (`elementor_element_update`):
> "Los mapas se mezclan clave a clave; **las listas (repetidores: diapositivas, elementos de
> menú) se sustituyen enteras.**"

**El problema:** un ítem de `elementskit-testimonial` tiene **51 claves**. No son decorativas:
```
client_photo: {"id":59,"url":".../01.webp"}
client_logo:  {"id":81,"url":".../placeholder.png"}
client_logo_active, link, ekit_testimonial_background_group_color_stop,
ekit_testimonial_background_group_gradient_angle, ..._slideshow_gallery,
..._slideshow_slide_duration: 5000, ... (y 40 más)
```
Para cambiar tres textos tengo que reenviar **51 claves × 3 ítems = 153 campos**, y si me dejo
`client_photo` fuera, los tres clientes se quedan sin foto. En un widget que no avisa de nada.

**Qué hice:** leer el documento con la API de Elementor, parchear **sólo** los tres campos de
texto de cada ítem conservando las otras 48 claves intactas, y guardar por el pipeline oficial
para que se regenerara el CSS y quedara revisión:
```php
$doc = \Elementor\Plugin::$instance->documents->get(103);
$data = $doc->get_elements_data();
// ... $base = $items[$i]; $base['review'] = '...';   // conserva las 51 claves
$doc->save(['elements' => $data]);
```

**Coste:** 2 llamadas de PHP y una de reconocimiento previo para contar las claves.

**Mi lectura:** **el segundo hueco más grave, después del coste de contexto.** Y los dos son el
mismo problema visto desde dos ángulos: la herramienta no sabe hablar de *una parte* de un
widget. Para los mapas sí —los fusiona clave a clave, y eso está muy bien resuelto—, pero se
rinde justo donde más falta hace, porque **los repetidores son donde vive el contenido
editorial**: testimonios, FAQ, listas de precios, diapositivas, pestañas. Es literalmente lo
que uno va a querer cambiar en un sitio ya montado.

Lo que falta es un direccionamiento de ítem:
```
elementor_element_update(post_id, element_id,
  repetidor: "ekit_testimonial_data", indice: 0,
  ajustes: {client_name: "Rosa Elena Padilla", review: "..."})
```
o aceptar `_id` del ítem en vez del índice, que es más estable. Con eso, mis nueve campos
habrían sido nueve líneas en un `batch` en lugar de bajar a PHP.

Segunda observación, esta a favor: la descripción **avisa** de que los repetidores se
sustituyen enteros. Si no lo dijera, yo habría mandado `[{text:"..."},{text:"..."}]` tan
tranquilo y habría borrado las fotos de los tres clientes sin enterarme. La advertencia me
ahorró un desastre silencioso. El problema no es que mienta: es que la limitación no debería
existir.

### G — Adivinar la clave de enlace de un widget de terceros

**Qué quería:** poner enlace a los 7 botones `jkit_button` de la portada (los CTA principales:
"Cotizar mi jardín", "Ver servicios"...). En el kit venían **sin enlace**: el HTML del front
era `<a href="">`.

**Qué pasó:** listé las 41 claves del widget y **no hay ninguna de enlace**, porque el kit
nunca lo puso. Así que no tenía de dónde deducirla. Probé `sg_content_link` con la forma
estándar de Elementor (`{url, is_external, nofollow}`) por analogía con `sg_content_label`.

**Resultado:** aceptada y guardada.

**Coste:** 1 llamada de reconocimiento. Acerté a la primera, pero por analogía, no porque nada
me lo dijera.

**Mi lectura:** **límite razonable, con un arreglo obvio disponible.** Ningún servidor MCP
puede documentar los widgets de todos los plugins de terceros del mundo. Pero este servidor
**ya tiene** `elementor_widget_schema`, y ahí está la respuesta: un esquema me habría dicho el
nombre y el tipo del control sin que yo tuviera que adivinar. Lo que falta es que las
descripciones de `elementor_element_update` y `elementor_element_add` **manden a consultarlo**:
"si no conoces las claves de ajuste de un widget, pídelas antes con `elementor_widget_schema`".
Yo tenía esa herramienta cargada desde el principio y no se me ocurrió usarla hasta después,
porque nada me apuntó a ella en el momento en que la necesitaba.

### ✅ y C — `seo_update`: buen error, buenos avisos, y una promesa de aprobación que no cumple

**Primer intento, sin Rank Math instalado:**
```
[not_supported] Rank Math no está activo en este sitio.
Comprueba con site_info qué hay instalado antes de usar estas herramientas.
```
**Error impecable**: dice qué falta, por qué falla y con qué herramienta comprobarlo. En dos
segundos supe qué hacer. Así deberían ser todos.

**Los avisos de cada escritura también valen:**
```
"avisos":["El título SEO tiene 72 caracteres y Google suele recortar a partir de 60.",
          "Sin palabra clave objetivo: Rank Math no puede puntuar el contenido."]
```
Esto me hizo ver algo que yo no habría mirado: **9 de los 24 títulos SEO que trae el documento
del cliente pasan de 60 caracteres** y Google los va a recortar. Un dato que sale gratis, en
el momento correcto, sin que lo pidiera.

**Lo que no cuadra.** La descripción dice:
> "Poner noindex en algo publicado, o cambiar la URL canónica, **necesita aprobación humana**:
> son cambios invisibles en la web cuyo efecto es que el tráfico deje de llegar."

Puse `index: "noindex"` en `/gracias/` (que es lo correcto para una página de gracias) y pasó
sin aprobación. Y el motivo que dio la propia herramienta **no es el modo de aprobaciones**:
```
"aviso":"... Se ejecutó sin pedir aprobación porque tiene vuelta atrás:
         history_restore con revision=214 la deshace."
```
O sea: la descripción dice "necesita aprobación humana", y el código dice "no hace falta
porque es reversible". **Son dos criterios distintos conviviendo en la misma herramienta.**
Es el tercer caso de este registro en que la descripción y el comportamiento no coinciden
(los otros: `elementor_template_conditions` y `option_update`), y ya no parece casualidad:
da la sensación de que las descripciones se escribieron describiendo la intención del diseño
y el código evolucionó después sin volver a ellas.

### 🔴 B — `seo_update` guarda bien y el sitio no emite nada (tercera vez el mismo patrón)

**Qué pasó:** apliqué title y meta descripción a las 24 páginas con `seo_update`. Todas
devolvieron éxito, con la URL, el título guardado y avisos útiles. Fui a comprobarlo al front:

```
<title>Jardines del Valle &#8211; Áreas verdes que se ven bien todo el año</title>
<meta name="description" content="">      <- no existía
```

El título del tema por defecto. **Ninguna de las 24 páginas emitía su SEO.** Los datos sí
estaban guardados:
```
get_post_meta(103,'rank_math_title')       -> "Servicios de jardinería en Honduras | ..."
get_post_meta(103,'rank_math_description') -> "Mantenimiento de jardines, paisajismo, ..."
get_option('rank_math_wizard_completed')   -> false      <- aquí está el problema
```

Rank Math recién instalado **no emite etiquetas hasta que se completa su asistente de
configuración**. `seo_update` escribe el metadato correcto en un plugin que todavía no está
sirviendo nada.

**Qué hice:** completar la configuración por PHP (`rank_math_wizard_completed`,
`registration_skip`, tipo y nombre de entidad). Tras eso, el front:
```
<title>Servicios de jardinería en Honduras | Jardines del Valle</title>
<meta name="description" content="Mantenimiento de jardines, paisajismo, poda, riego...">
```

**Coste:** 3 llamadas de diagnóstico + 1 de arreglo, después de 24 escrituras que yo ya había
dado por buenas.

**Mi lectura:** **es el tercer caso idéntico del mismo patrón, y el que confirma que es
sistémico, no casualidad.**

| Herramienta | Escribió bien | No hizo | Resultado visible |
|---|---|---|---|
| `elementor_template_apply` | `_elementor_data` | marcar la página como Elementor | página publicada sin estilos |
| `elementor_template_conditions` | `_elementor_conditions` | regenerar la caché de ubicaciones | cabecera que no sale |
| `seo_update` | `rank_math_title/description` | nada — el plugin no está configurado | 24 páginas sin SEO |

En los tres, **el dato queda perfecto y el efecto no ocurre, y la herramienta responde éxito.**
El tercero es el más venenoso de los tres porque el SEO **no se ve nunca mirando la web**: si
no se me ocurre leer el `<title>` del HTML, entrego 24 páginas sin metadatos y nadie se entera
hasta que alguien mira Search Console tres meses después.

Aquí ni siquiera pido que la herramienta arregle nada: **con que lo dijera bastaría.** Los 17
tools de SEO ya saben detectar que falta Rank Math —`seo_update` da un error excelente cuando
el plugin no está activo— así que comprobar `rank_math_wizard_completed` y devolver
`"aviso": "Rank Math está activo pero sin configurar: lo guardado no se emite todavía"` es
la misma comprobación una capa más adentro. La diferencia entre un dato guardado y un dato que
sirve es justo lo que este servidor no está mirando en ningún sitio.

### Nota de contenido, no de fricción: los títulos SEO del cliente

Los avisos de `seo_update` destaparon que **9 de los 24 títulos que trae el documento pasan de
60 caracteres** y Google los va a recortar. Acorté tres donde el recorte se comía la marca
(`/cobertura/`, `/planes-de-mantenimiento/`, `/servicios/jardines-verticales/`) y dejé el resto
tal como venía: son decisión del cliente, no mía. Los que siguen largos:
`/servicios/` (69), `/servicios/areas-verdes-empresas/` (68), `/servicios/grama-sintetica/` (68),
`/servicios/instalacion-de-grama-natural/` (72), `/servicios/diseno-de-jardines-paisajismo/` (64),
`/servicios/tala-de-arboles/` (64), `/servicios/sistemas-de-riego/` (62),
`/servicios/mantenimiento-de-jardines/` (61), `/servicios/fertilizacion-y-suelos/` (61),
`/servicios/limpieza-de-terrenos/` (61).

## Fase 7 — Logo

### A — No hay forma de subir un fichero a la biblioteca de medios

**Qué quería:** subir los cuatro logos de la marca (horizontal negro, horizontal blanco,
isotipo y versión apilada) que el cliente me pasó, y aplicarlos en cabecera, pie y favicon.

**La única herramienta de subida es `media_upload`:**
> "Descarga un fichero desde una **URL pública** y lo añade a la biblioteca de medios,
> generando los tamaños intermedios."

Su único parámetro obligatorio es `url`, con `http://` o `https://`. **No acepta bytes, ni
base64, ni una ruta del servidor.**

**Qué hice:** nada, de momento está bloqueado por otro motivo (los logos me llegaron dentro de
la conversación, no como ficheros en disco, así que tampoco tengo los bytes). Pero merece la
pena anotar el hueco aunque los tuviera.

**Mi lectura:** **hueco de diseño, y llamativo por lo que sí existe al lado.** El servidor
tiene `file_write`, que escribe ficheros dentro de `wp-content` — incluido `uploads/`. O sea:
**puedo dejar el fichero en el disco pero no puedo convertirlo en un adjunto de WordPress**,
que es lo que hace falta para que tenga ID, miniaturas y se pueda usar como logo, imagen
destacada o en un widget. Falta el puente: o `media_upload` acepta `contenido_base64` o
`ruta` además de `url`, o hay un `media_register(ruta)` que registre como adjunto algo que ya
está en `uploads/`.

Es un caso claro de "el servidor sabe hacer las dos mitades y no sabe unirlas", igual que
`elementor_template_import`, que sabe importar plantillas pero no leerlas de disco. El patrón
se repite: **todo lo que entra al sitio tiene que pasar por el contexto del modelo o por una
URL pública**, y los dos caminos fallan justo con lo que más pesa — kits, imágenes, medios.

### 🔬 El caso de las imágenes y los medios, entero

Este merece contarse completo porque es el hueco más raro que me he encontrado: no es que falte
una herramienta, es que **falta el camino de vuelta**.

**Lo que no se puede hacer, en orden:**

1. **Subir un fichero.** `media_upload` sólo acepta `url` pública. Ni bytes, ni base64, ni una
   ruta del servidor. Si el fichero está en mi máquina y no en un sitio público, no entra.
2. **Convertir en adjunto algo que ya está en el servidor.** `file_write` deja el fichero en
   `uploads/` sin problema, pero no hay nada que lo registre como adjunto de WordPress. Sin ID
   de adjunto no hay miniaturas, ni logo, ni imagen destacada, ni `site_icon`.
3. **Leer una imagen.** `file_read` es sólo para texto. Las 17 herramientas de medios
   (`media_list`, `media_get`, `media_update`...) devuelven **metadatos**: id, URL, mime, alt,
   fecha. **Ninguna devuelve la imagen.** Puedo saber que existe un fichero de 1983×793 px
   llamado `IMG_6665.png` y no tengo forma de saber qué hay dibujado dentro.

**Por qué importa aquí:** el cliente subió cuatro logos por wp-admin con nombres de cámara
—`IMG_6665`, `IMG_6666`, `IMG_6667`, `IMG_6668`— y yo tenía que colocar cada versión en su
sitio: la de texto negro en la cabecera, la de texto blanco en el pie, el isotipo en el
favicon. **Con las herramientas de medios eso es imposible**: los cuatro son "una imagen PNG".

**Lo que hice, en dos pasos.**

*Identificarlos sin verlos.* Por PHP, muestreando píxeles con GD:
```
354  1983x793   77% transparente · 18% negro ·  0% blanco · tinta a la derecha: 3839
356  1983x793   80% transparente ·  0% negro · 16% blanco · tinta a la derecha:   26
355  1254x1254   0% transparente · negro por tercios: 907 / 2698 / 2167
357  1254x1254   0% transparente · negro por tercios: 872 / 2440 / 3477
```
De ahí sale todo: 354 y 356 son la misma pieza horizontal en versión oscura y clara (la
"tinta a la derecha" de 26 en el 356 es el texto blanco, que mi filtro no cuenta como tinta).
Entre los dos cuadrados, el 357 tiene **un 60% más de negro en el tercio inferior**: ahí está
el rótulo "JARDINES DEL VALLE" debajo del dibujo. El 355 es el isotipo solo.

*Verlos de verdad.* Sí se puede, pero el único camino es **hacer pasar los bytes por mi
contexto**:
```
PHP: imagecopyresampled a 96px + imagejpeg + base64_encode
  -> el base64 viaja dentro del JSON de respuesta a mi contexto
  -> lo escribo a disco local con un heredoc y base64 -d
  -> lo abro con la herramienta Read
```
Funciona. Vi la 357 y es exactamente lo que predijo el análisis. **Coste: ~3 KB de base64 por
una miniatura de 96×96 px.** A tamaño original serían 1,3 MB, o sea unos 350.000 tokens por
imagen. Inviable para cualquier cosa que no sea un sello.

**Mi lectura.** Las dos mitades existen y no se tocan. El servidor sabe escribir ficheros en
`uploads/` y sabe listar la biblioteca de medios, pero no hay puente entre las dos, ni en un
sentido ni en el otro. Tres arreglos, por orden de lo que me habría servido:

- **`media_upload` que acepte `contenido_base64` o `ruta`** además de `url`. Es el que
  desbloquea el caso normal: el cliente te manda un logo y tú lo subes.
- **`media_thumbnail(media_id, ancho)`** que devuelva una miniatura pequeña en base64. Con
  128 px basta para reconocer un logo, una foto de producto o distinguir un "antes" de un
  "después", y cuesta unos pocos kilobytes. **Sin esto, un agente no puede comprobar lo que
  pone en una web.** Toda la maquetación va a ciegas sobre las imágenes.
- **`media_register(ruta)`** para adjuntar algo que ya está en el disco.

**Nota a favor, y buena:** cuando me equivoqué con los parámetros
(`media_update(post_id, alt_text, title)`), el error fue de manual:
```
Argumentos no reconocidos: post_id, alt_text, title.
Los válidos son: media_id, titulo, alt, leyenda, descripcion.
```
Dice qué está mal **y cuál es la lista correcta**. Lo arreglé en una llamada sin buscar nada.
Si todos los errores de este servidor fueran así, la mitad de este documento no existiría.

Y `batch` se comportó igual de bien al fallar: paró en el paso 2, dijo `"fallo_en": 2`, avisó
de que los 3 siguientes no se intentaron y de que los 2 anteriores se quedaron hechos, con su
revisión para deshacerlos. Exactamente lo que necesitas saber.

**Verificación final del logo, que también hubo que hacer a mano.** Puse la versión blanca en
el pie y me quedó la duda de si el fondo era oscuro de verdad — un logo blanco sobre fondo
claro es invisible y no lo detecta ningún test. No hay herramienta que conteste "de qué color
es el fondo detrás de este elemento", así que fui al CSS generado por Elementor:
```
.elementor-100 .elementor-element-fe2ce3e { background-color: var(--e-global-color-88e2e72) }
88e2e72 = #000000
```
Negro. El logo blanco es el correcto. Tres llamadas para responder algo que un humano resuelve
mirando la pantalla medio segundo.

## Fase 8 — Contacto con Contact Form 7

### ✅✅ `cf7_update` avisa de un fallo que no da error

Cambié los campos del formulario de `your-name/your-email/your-subject/your-message` a
`nombre/telefono/correo/ciudad/servicio/mensaje` y reescribí la plantilla del correo. Respuesta:

```
"aviso": "Las plantillas de correo usan [your-subject], [your-message], [your-email], y no hay
          ningún campo con ese nombre en el formulario. CF7 enviará el correo con la etiqueta
          literal dentro en vez del valor, y eso no da ningún error: simplemente llegan los
          avisos con corchetes. Corrige el correo o añade el campo."
```

Me faltaba el segundo correo (el acuse de recibo), que seguía apuntando a los campos viejos.
Lo corregí y el aviso desapareció.

**Esto es lo mejor que he visto en todo el servidor**, y es exactamente el problema que este
registro lleva documentando desde la fase 3: *el dato queda guardado y el efecto sale mal, sin
que nada falle*. Aquí, por una vez, **la herramienta lo comprueba y te lo dice**. Es la misma
clase de verificación que le falta a `elementor_template_apply` (¿la página está marcada como
Elementor?), a `elementor_template_conditions` (¿la caché se regeneró de verdad?) y a
`seo_update` (¿Rank Math está configurado?). Alguien pensó bien este trozo. Lo que pido en el
resto del documento ya existe aquí: **que la herramienta valide su propio efecto, no sólo su
escritura.**

### ✅ La lección de `elementor_widget_schema`, aplicada

En la fase 6 anoté que había adivinado `sg_content_link` por analogía porque nada me mandaba a
consultar el esquema. Esta vez, antes de tocar el widget `elementskit-contact-form7`, lo pedí:

```
elementor_widget_schema(widget:"elementskit-contact-form7", buscar:"form")
-> 506 controles. El selector de formulario es `ekit_contact_form7`, opciones: [11]
```

Acerté a la primera con las 9 claves de estilo y el selector, sin una sola prueba fallida. Y su
descripción dice la frase que hacía falta: *"Elementor ignora en silencio las claves que no
conoce, así que un ajuste mal nombrado se guarda y no hace nada"*.

**El problema no es la herramienta, es que no está enlazada desde donde se necesita.** Ninguna
descripción de `elementor_element_update` ni de `elementor_element_add` la menciona, y son
justo las dos desde las que uno llega necesitándola. Una línea —"si no conoces las claves de un
widget, pídelas antes con `elementor_widget_schema`"— convierte una herramienta que existe y
nadie encuentra en la que evita el problema.

**Pega menor:** la respuesta completa del esquema son 54.630 caracteres y **no cabe en una
respuesta**; el harness la volcó a un fichero y tuve que consultarla con `jq`. El parámetro
`buscar` existe precisamente para eso, pero con `buscar:"form"` seguían saliendo 322 de 506
controles, porque en este widget *todas* las claves llevan `form` en el nombre
(`ekit_contact_form_...`). Un `limite` o un `solo_claves:true` lo resolvería.

## Fase 9 — Todos los formularios a CF7

Cuatro formularios creados y colocados con el widget `elementskit-contact-form7`, que se estila
con las variables globales del kit:

| Dónde | Formulario | Campos |
|---|---|---|
| Contacto | `Contacto` (11) | nombre, teléfono, correo, ciudad, servicio, mensaje |
| Cotización | `Cotización` (375) | 12 campos, incluidas 5 subidas de foto |
| Portada | `Cita rápida` (393) | nombre, teléfono, correo, ciudad, fecha |
| Pie (todo el sitio) | `Newsletter` (394) | correo |

MetForm quedó sin uso y lo desactivé: no renderizaba nada y seguía cargando 4 JS
(`htm.js`, `index.js` ×2, `cute-alert.js`) en **todas** las páginas del sitio.

### A — No hay forma de crear un formulario de CF7

Hay `cf7_list`, `cf7_get` y `cf7_update`, pero **no `cf7_create`**. Los tres formularios nuevos
no tenían dónde nacer.

**Qué hice:** `content_create(post_type: "wpcf7_contact_form")` para crear el post vacío y luego
`cf7_update` para escribirle la plantilla. Funcionó a la primera.

**Mi lectura:** **hueco menor y con apaño limpio**, lo anoto por completitud. Dicho eso, el
apaño sólo funciona si ya sabes que CF7 guarda sus formularios en un tipo de contenido llamado
`wpcf7_contact_form` y que la plantilla vive en un metadato que `cf7_update` sabe escribir. Eso
no lo dice ninguna descripción: lo supe por conocer CF7, no por el servidor. Un `cf7_create`
—o una línea en `cf7_update` diciendo "si el formulario no existe, créalo antes con
`content_create` y `post_type: wpcf7_contact_form`"— lo resuelve.

### 🔴 B — `cf7_update` escribe, y el sitio sigue sirviendo la versión anterior

**Qué pasó.** El formulario del newsletter salía en el front con **la etiqueta literal en vez
del campo**:
```html
<form class="wpcf7-form init">
  ...
  <p>[email* correo placeholder "Su correo electrónico" autocomplete:email]</p>
  <input type="submit" value="Suscribirme" />
```
El `[submit]` sí se renderizó; el `[email*]` no.

**Mi primer diagnóstico fue equivocado.** Pensé que era orden de opciones —CF7 quiere las
opciones antes de los valores entrecomillados— y reescribí la etiqueta. **Siguió igual.**

**El diagnóstico bueno.** Fui a preguntar por los dos caminos a la vez:
```
cf7_get(394)                              -> "campos": ["correo"]     <- CF7 sí lo reconoce
do_shortcode('[contact-form-7 id="394"]') -> <input name="correo">    <- se renderiza bien
la misma página por HTTP                  -> [email* correo ...]      <- la etiqueta cruda
```
Y lo definitivo: **el HTML servido traía la plantilla con el orden de opciones ANTERIOR a mi
corrección.** No era un fallo de sintaxis, era una copia vieja. Un `wp_cache_flush()` y salió
bien a la primera.

**Coste:** 5 llamadas persiguiendo una hipótesis falsa + 2 de diagnóstico + 1 de arreglo. Y
estuve a punto de dejarlo como "cosa rara de CF7".

**Mi lectura.** Es **el cuarto caso del mismo patrón**, y tiene guasa porque le pasa justo a la
herramienta que elogié hace dos páginas por ser la única que valida su propio efecto:

| Herramienta | Escribió bien | No hizo |
|---|---|---|
| `elementor_template_apply` | `_elementor_data` | marcar la página como Elementor |
| `elementor_template_conditions` | `_elementor_conditions` | regenerar la caché de ubicaciones |
| `seo_update` | `rank_math_title` | (Rank Math sin configurar) |
| **`cf7_update`** | **la plantilla del formulario** | **invalidar la caché de objetos** |

`cf7_update` comprueba una cosa —que las etiquetas del correo existan como campos— y es
excelente en eso. Pero no comprueba la otra: **que lo que escribió sea lo que el visitante ve.**
Un `wp_cache_flush()` al final de la escritura, o al menos un
`"aviso": "si el sitio usa caché de objetos, el formulario viejo puede seguir sirviéndose"`,
habría evitado todo esto.

Y lo peor del caso es cómo se manifiesta: **el formulario no desaparece, se llena de basura
visible.** Un formulario de suscripción que en vez de una caja de correo enseña
`[email* correo placeholder "Su correo electrónico"]` en el pie de las 32 páginas del sitio.
Se ve, pero sólo si alguien mira; ningún test de los que he ido haciendo —HTTP 200, sin inglés,
sin enlaces vacíos, widgets renderizados— lo habría cazado. Lo cacé porque conté campos de
formulario, y eso lo hice por casualidad.

**Herramienta que echo de menos, otra vez la misma idea:** algo tipo `cache_flush()` expuesto,
o que las escrituras lo hagan solas. El servidor ya llama a
`\Elementor\Plugin::$instance->files_manager->clear_cache()` en algunos sitios; le falta hacer
lo propio con la caché de objetos de WordPress.

## Fase 10 — Las 13 fichas de servicio

Escritas y verificadas las trece: H1 propio, intro, secciones técnicas del documento, FAQ de
4–5 preguntas y CTA. Entre 418 y 586 palabras cada una, cero inglés, cero enlaces vacíos.

### 🔴 D — El coste de contexto obligó a escribir por PHP, como estaba previsto

En la fase 6 medí que `elementor_element_update` devuelve unas 30 veces lo que se le manda y
calculé ~1.500 escrituras para el sitio entero. Aquí tocó pagar esa factura: **13 páginas × 19
elementos = 247 escrituras.** Por la herramienta serían unas 250 respuestas de ~450 palabras:
del orden de **150.000 tokens sólo en acuses de recibo**, y el trabajo no cabe.

**Qué hice:** un aplicador en PHP que escribe por **ruta** —`0.0.0`, `1.1.4`, `1.0.2.2`— en vez
de por `element_id`, y guarda con `$doc->save(['elements' => $data])`, que es el pipeline
oficial de Elementor: crea revisión y regenera el CSS. Tres llamadas para las trece páginas.

La ruta, además, resultó ser **mejor identificador que el id** para este trabajo: las 13 fichas
son clones de la misma plantilla, así que los `element_id` son distintos en cada una pero la
ruta es idéntica. Un mismo mapa sirvió para las trece.

**Mi lectura:** esto no es un apaño por comodidad, es la consecuencia directa del problema
medido. Y sugiere dos cosas concretas para el servidor:

1. **`devolver: "minimo"` en `elementor_element_update`.** Ya lo pedí en la fase 6; aquí es
   donde se ve que sin eso no se puede maquetar un sitio entero.
2. **Direccionar por ruta, no sólo por id.** `elementor_find` ya devuelve el campo `ruta` de
   cada elemento, así que el servidor ya piensa en rutas — pero luego `elementor_element_update`
   sólo acepta `element_id`. Poder escribir por ruta convierte "aplicar el mismo contenido a 13
   clones de una plantilla" en un `batch` trivial.

### 🔍 Mi propio punto ciego: el pie llevaba 32 páginas en inglés

Esto no es fricción del servidor, es un error mío, y lo anoto porque el encargo pide decir
cuándo algo deja el sitio raro.

Mis verificaciones cortaban el HTML entre `data-elementor-type="wp-page"` y
`data-elementor-type="footer"` para revisar sólo el contenido de la página. Con eso vengo
diciendo "cero inglés" desde la fase 6. **El pie quedaba fuera del recorte por construcción**, y
llevaba desde el principio con:

```
Quick Links · Our Services · Contact Info · Subscribe To Our Weekly Newsletter
Garden Maintenance · Landscape Design · Lawn Care & Mowing
+1 (202) 555-0143 · info@yourdomain.com · Kazipur 6710, Sirajganj, BD
Copyright © 2026 Plantix By Roxtheme
```

Una dirección de Bangladesh y un teléfono de Washington en el pie de las 32 páginas del sitio.
Lo encontré de rebote, persiguiendo un "Landscape Design" que salía en una ficha de servicio y
que resultó venir del pie, no de la ficha.

**La lección, que sí va contra el servidor:** cuando lo que se comprueba es una plantilla del
theme builder, **no hay forma de preguntarle al servidor "¿dónde sale esto?"**. La cabecera y
el pie se dibujan en todas las páginas y no aparecen en ningún listado de contenido: no salen
en `content_search`, y `elementor_templates` los lista pero sin decir que su contenido está en
inglés. Una auditoría de "texto del sitio por plantilla, no por página" habría cazado esto en
la primera hora.

Corregido: pie y cabecera enteros en español, con los cinco datos de contacto reales, los
enlaces a las páginas que existen y el botón «Cotizar gratis» apuntando a `/cotizacion/`.

---

# Fase 11 — Cobertura, Planes y el hub de Proyectos

## E Leer la estructura de un repetidor cuesta el widget entero

Para reescribir los testimonios, el acordeón de los planes y las listas de características
necesitaba saber los nombres de las claves de cada fila del repetidor. No hay ninguna
herramienta que devuelva sólo eso.

**Llamada:** `elementor_element_get` sobre `elementskit-testimonial` (probado en fases
anteriores) devuelve las ~70 claves de estilo del widget además del repetidor. En esta fase
ya ni lo intenté: fui directo a `ingenio_execute_php` filtrando a mano.

**Respuesta:** el volcado de una sola fila del repetidor de testimonios ocupa 60 líneas de
JSON, de las cuales 5 son contenido (`client_name`, `designation`, `review`, `rating`,
`client_photo`) y 55 son ajustes de fondo, degradado y *ken burns* que nunca voy a tocar.

**Qué hice:** un dumper propio en PHP que recorre el documento y imprime sólo `path`,
`widgetType` y las claves de texto. Tres páginas completas caben en 60 líneas.

**Coste:** 4 llamadas de reconocimiento antes de poder escribir nada. Con una herramienta
tipo «dame el esquema de contenido de esta página» habría sido 1.

**Mi lectura:** límite razonable de diseño llevado demasiado lejos. `elementor_element_get`
hace lo que promete. Lo que falta es el modo «sólo contenido»: un `elementor_outline` que,
además de la jerarquía, traiga el texto de cada widget. La herramienta `elementor_outline`
existe y da la jerarquía; añadirle un parámetro `con_texto` costaría poco y ahorraría la
mitad de mis llamadas de lectura en todo el proyecto.

## G Insertar un elemento desplaza todas las rutas hermanas y nada te avisa

Las tres plantillas (Our Team, Pricing, Projects) no traen ningún hueco de texto entre el
H1 y la rejilla de tarjetas. El encargo sí tiene ahí dos o tres párrafos de intro. Inserté
un `text-editor` como primer hijo del contenedor de la sección.

**Llamada:** `array_unshift($x[1]['elements'][0]['elements'], $intro)` dentro de la misma
llamada PHP que aplicaba los textos por ruta.

**Respuesta:** guardado correcto. Pero en la llamada *siguiente*, para corregir el formato
de los precios, usé las rutas del mapa que había levantado antes de insertar:

```
'1.0.0.0.0.1' => ['ekit_heading_title' => 'Desde L 1,200 {{/al mes}}']
→ aplicados=0
```

Cero coincidencias, sin error. La rejilla se había movido de `1.0.0` a `1.0.1`.

**Qué hice:** repetir con `1.0.1.*`. `aplicados=3`.

**Coste:** 2 llamadas, una de ellas cara porque verifiqué con una expresión regular sobre
el HTML entero y me traje 40 KB de CSS y JSON-LD a la conversación. Ese trozo del coste es
culpa mía, no del plugin: `content_render` con `buscar` hace exactamente eso por 300 bytes.

**Mi lectura:** esto es inherente a direccionar por ruta, y el direccionamiento por ruta lo
inventé yo porque `elementor_element_update` devuelve treinta veces lo que se le manda
(anotado en la fase 6). Pero apunta a algo que sí es del plugin: el aplicador por ruta
devuelve `aplicados=0` y eso no es un error. Un `elementor_element_update` que reciba una
ruta inexistente debería fallar, no quedarse callado. Cuando escribes 36 ajustes de golpe,
la diferencia entre 36 y 35 es invisible si nadie la cuenta.

## C La sintaxis `{{/texto}}` de elementskit-heading no está documentada en ninguna parte

El widget `elementskit-heading` de las tarjetas de precio traía `$49 {{/Per Month}}`.

**Llamada:** `elementor_widget_schema` para `elementskit-heading` (fase 6) describe
`ekit_heading_title` como «título». Nada sobre llaves dobles.

**Qué hice:** deducirlo del contenido de demostración y probar. Renderiza
`<h3>Desde L 1,200 <span>/al mes</span></h3>`: lo de dentro de las llaves sale en un `span`
con estilo propio, **incluida la barra**. Mi primer intento, `A medida {{/cotización sin
costo}}`, salía en pantalla como «A medida /cotización sin costo», que no significa nada.
Lo dejé en `Cotización a medida` sin sufijo.

**Coste:** 1 llamada extra y un texto raro publicado durante unos minutos.

**Mi lectura:** no es culpa del plugin MCP, es de ElementsKit, que inventa una sintaxis de
plantilla dentro de un campo de texto plano. Pero sí es un hueco del servidor: cuando
`elementor_widget_schema` describe un campo de un widget de terceros con sintaxis propia,
lo único que puede salvarte es que la descripción lo diga. No lo dice. Sin el contenido de
demostración delante, esas llaves se publican tal cual.

## D Las ocho tarjetas de equipo reconvertidas en sedes: fotos y redes sociales a mano

La plantilla de Cobertura es *Our Team*: ocho tarjetas con retrato de persona de fondo y un
widget `social-icons` por tarjeta. Jardines del Valle no tiene ocho caras que poner ahí, y
un retrato con el rótulo «Tegucigalpa» debajo es peor que no poner nada.

**Qué hice:** cambiar el fondo de cada tarjeta (`background_image` en el contenedor
`1.0.0.{n}.0.0`) a ocho fotos de jardín que ya estaban en la mediateca del kit, y ocultar
los ocho `social-icons` con `hide_desktop` + `hide_tablet` + `hide_mobile`. Todo por PHP.

**Coste:** ninguna llamada extra respecto a lo que ya iba a hacer, porque lo metí en el
mismo aplicador por ruta. Pero con herramientas habrían sido 8 `elementor_element_update`
para los fondos y 8 más para ocultar, con su acuse de recibo cada una.

**Mi lectura:** límite razonable. Reutilizar una plantilla para algo que no es lo que la
plantilla dice es decisión mía, y ninguna herramienta puede adivinarla. Lo anoto porque es
el patrón de trabajo real con un kit comprado: casi ninguna sección se usa para lo que el
kit creía. La herramienta que falta no es «convertir equipo en sedes», es un
`elementor_element_update` que acepte varias rutas y varios ajustes en una sola llamada y
conteste con un número, no con el widget entero.

## Estado tras la fase 11

28 de 33 páginas con contenido real. Cobertura (465 palabras), Planes (676) y el hub de
Proyectos (366) verificados en el front: 200, cero inglés, los tres con su intro, sus
tarjetas, su bloque de «por qué» y los tres testimonios del encargo. Quedan FAQs, el
archivo del blog, el cuerpo de las tres legales y la de Gracias.

---

# Fase 12 — FAQs, legales, blog y el inglés que no se veía

## B Verificar por el HTML renderizado es ciego a lo que sólo se pinta cuando hay datos

Durante once fases verifiqué cada página descargándola y buscando palabras en inglés. En
esta fase hice por primera vez el barrido al revés: recorrer los **ajustes** de los
documentos de Elementor en lugar del HTML.

**Llamada:** un recorrido en PHP por los 38 documentos publicados (33 páginas + cabecera,
pie, entrada, archivo y 404), buscando inglés en cualquier ajuste de texto.

**Respuesta:** dos cosas que doce barridos de HTML no habían visto nunca.

1. `ekit_blog_posts_btn_text = "Learn more "` en la portada (103) y en la plantilla de
   archivo (131). No salía en el HTML porque el widget de entradas no pinta nada cuando no
   hay entradas, y el blog está vacío.
2. La plantilla de **entrada individual** (130) no tenía ni una sola etiqueta dinámica. El
   título, los cuatro párrafos, las dos imágenes y el carrusel eran el artículo de
   demostración del kit escrito a fuego: *«Top Sustainable Gardening Techniques For
   Eco-Friendly Homes»*. Cualquier artículo que se publicara habría salido con ese título y
   ese texto, no con el suyo.

**Qué hice:** traducir los botones, y reconstruir la sección del cuerpo de la 130:
`theme-post-featured-image` + `theme-post-content` en lugar de los seis widgets estáticos, y
una etiqueta dinámica `post-title` sobre el `heading` para conservar el estilo del kit.
Verificado creando una entrada de prueba, comprobando que sale su título y su texto, y
borrándola.

**Coste:** 5 llamadas. Pero el coste real es otro: si no llego a hacer este barrido, el
sitio se entrega con una plantilla de blog rota y nadie se entera hasta el primer artículo.

**Mi lectura:** fallo del plugin, y de los caros. `elementor_template_apply` copia el
contenido de demostración de una plantilla de entrada individual tal cual, incluidos el
título y el cuerpo del artículo de ejemplo, y contesta `{"accion":"contenido
sustituido","elementos":N}`. Una plantilla de tipo `single-post` sin una sola etiqueta
dinámica es, por definición, una plantilla que no funciona. El servidor sabe el tipo de
documento (`single-post`), sabe que no hay `__dynamic__` en ningún widget y podría decirlo
en el mismo resultado: «aplicada, pero esta plantilla de entrada no tiene ningún campo
dinámico: todas las entradas mostrarán el mismo texto». No dice nada.

Esto es el mismo patrón que llevo anotando desde la fase 2, una cuarta vez: **la
herramienta escribe el dato correcto, omite lo que hace que el dato sirva, y contesta que
todo fue bien.**

## A No hay forma de auditar el texto de un sitio a nivel de ajustes

Relacionado con lo anterior. `content_analyze` y `seo_audit` miran el contenido renderizado.
`content_search` busca en `post_content`, que en un sitio Elementor está vacío o duplicado.

**Qué quería hacer:** «dime todo el texto literal que hay en los documentos de Elementor de
este sitio, con su ruta, para poder revisarlo de un vistazo». Es la pregunta que se hace
cualquiera que hereda un sitio hecho con un kit.

**Qué hice:** escribirlo yo en PHP. Cuatro veces en el proyecto, cada vez un poco distinto.

**Coste:** difícil de contar. La primera versión que escribí en esta fase se me fue de las
manos: el patrón encajaba también con nombres de fichero de imagen y el resultado salió de
1,2 millones de caracteres, que el servidor cortó y volcó a fichero. Dos llamadas perdidas
por un error mío, pero un error que no habría cometido con una herramienta que sepa
distinguir un ajuste de texto de una URL.

**Mi lectura:** hueco claro. La herramienta que falta es `elementor_text_audit(post_ids,
buscar?)`: recorre los documentos, devuelve sólo los ajustes de texto visible con su ruta y
su widget, y opcionalmente filtra por una expresión. Con 161 herramientas y ocho de ellas
dedicadas a leer estructura de Elementor, que no exista la que lee el texto es raro.

## A No se puede cambiar un widget por otro

Las cinco páginas de FAQs, Gracias y las tres legales usan la misma plantilla: dos
acordeones de cinco preguntas. Para las legales un acordeón es mal sitio: el aviso de
privacidad y los términos se leen de corrido.

**Qué quería hacer:** convertir esos dos `elementskit-accordion` en dos `text-editor`
conservando su posición y su contenedor.

**Llamadas disponibles:** `elementor_element_remove` + `elementor_element_add`. Son dos
llamadas por widget, ocho en total para cuatro páginas, y el `add` necesita que le digas
dónde insertar por `element_id` del padre, que hay que ir a buscar antes.

**Qué hice:** sustituir el nodo entero en PHP conservando el `id` original, para no romper
el CSS generado que ya apunta a ese identificador.

**Coste:** 1 llamada en lugar de 12 aproximadas.

**Mi lectura:** límite razonable, pero con un hueco de diseño detrás. `elementor_element_update`
cambia ajustes; no hay nada que cambie el **tipo**. En un sitio hecho con kit, «esta sección
está bien colocada pero el widget no es el que necesito» es una operación constante. Un
`elementor_element_replace(post_id, ruta_o_id, widget_type, settings)` que conserve el `id`
sería la herramienta más usada del lote.

## D Todo lo demás de esta fase, en PHP

Sin herramienta propia y resuelto con `ingenio_execute_php`:

- Crear las seis categorías del blog (`wp_insert_term`). Hay `content_set_terms` para
  asignar términos a un contenido, pero nada para **crear** una taxonomía o un término.
- Poner el texto alternativo a 32 imágenes de la mediateca. `media_update` acepta `alt`,
  pero son 32 llamadas con su acuse de recibo. Lo hice con un `update_post_meta` en bucle.
- Repartir una imagen distinta a cada una de las 19 páginas de servicio y proyecto, y rotar
  las seis fotos del carrusel, para que no fueran las mismas seis en todas.
- Poner la etiqueta dinámica `post-title`. Existe `elementor_element_set_dynamic`, y es la
  herramienta correcta; no la usé porque ya estaba dentro de la llamada PHP que rehacía la
  sección entera. Lo anoto como uso mío, no como hueco.

**Mi lectura:** de estos cuatro, el único hueco real es el primero. No poder crear un
término desde el servidor, teniendo `content_set_terms` para asignarlo, es una asimetría
que obliga a bajar a PHP para algo que hace cualquier editor de WordPress.

## Lo que queda raro en el sitio, dicho explícitamente

**El blog está vacío.** `/blog/` responde 200 y pinta el H1, la miga y la intro, y después
no hay nada: cero entradas. El encargo pone los doce artículos en «pendientes para la
siguiente fase», así que no los he escrito. Pero el blog está en el menú principal y en el
pie, y hoy lleva a una página que se queda a medias. El widget `elementskit-blog-posts` no
tiene ajuste de «mensaje cuando no hay entradas», así que no se puede ni poner un aviso sin
tocar el widget. **Esto hay que resolverlo antes de que el sitio sea público**: o se
publican los primeros artículos, o se quita el blog del menú hasta que los haya.

**Las fotos son las del kit.** Las 19 páginas de servicio y proyecto llevan ahora una foto
principal distinta cada una y el carrusel rotado, pero siguen siendo fotos de banco de un
kit de jardinería genérico: no hay ni un jardín de Honduras ni una cuadrilla de Jardines del
Valle. Los textos alternativos que puse describen lo que la foto **representa** en su sitio,
no lo que la foto es. Con fotos reales hay que revisarlos.

**Los cuatro formularios envían a `hola@jardinesdelvalle.hn`,** que es un dominio que no
existe. En esta instalación de pruebas todo lo que se envíe va a rebotar. Es lo correcto
para el encargo y lo incorrecto para probar: si se quiere ver un envío completo, hay que
cambiar el destinatario de los cuatro formularios de Contact Form 7.

---

# Cierre: las cuatro preguntas del encargo

## 1. Las tres cosas que más bloquearon, en orden

### Primera: escribir el dato y omitir lo que hace que el dato sirva

Es el hallazgo del proyecto y no es una anécdota: lo vi **cinco veces** en cinco
herramientas distintas, siempre con la misma forma.

| Herramienta | Escribió bien | No hizo | Contestó |
|---|---|---|---|
| `elementor_template_apply` | `_elementor_data` (435 KB) | marcar la página como Elementor | `{"accion":"contenido sustituido","elementos":372}` |
| `elementor_template_conditions` | `_elementor_conditions` | regenerar la caché de ubicaciones | `{"regenerada":true}` |
| `seo_update` | `rank_math_title` y `description` en 24 páginas | nada (Rank Math sin asistente completado) | éxito, y el sitio no emitía una sola etiqueta |
| `cf7_update` | la plantilla del formulario | invalidar la caché de objetos | éxito, y el pie mostró el shortcode crudo en 32 páginas |
| `elementor_template_apply` (entrada) | la plantilla entera | avisar de que no tiene ni una etiqueta dinámica | `{"accion":"contenido sustituido"}` |

Las cinco veces el resultado fue el mismo: una página que existe, que responde 200 y que
está rota. Y las cinco veces lo descubrí **mirando el HTML del front**, nunca por la
respuesta de la herramienta.

Lo que cuesta esto no son las llamadas de la reparación, que son pocas. Lo que cuesta es que
destruye la confianza en el acuse de recibo. A partir de la fase 3 dejé de creerme cualquier
«éxito» y empecé a verificar cada escritura contra el HTML publicado. Eso multiplicó por dos
las llamadas de todo el proyecto.

El caso de `cf7_update` es el más instructivo porque es el único que **sí** valida su propio
efecto: detecta etiquetas de correo que apuntan a campos inexistentes y lo dice. O sea, el
plugin sabe hacer esto. Simplemente no lo hace en el resto de sitios donde hace falta.

### Segunda: `elementor_element_update` devuelve treinta veces lo que se le manda

Medido: cambiar tres textos de un widget `elementskit` gasta unas 1,200 palabras de
respuesta. Las 13 páginas de servicio necesitaban 247 escrituras de elemento. Sólo los
acuses de recibo habrían sido del orden de 150,000 tokens.

Con ese coste, usar las herramientas para el trabajo real era imposible. Construí un
aplicador por rutas en PHP que pasa por el `save()` oficial de Elementor y contesta un
número. A partir de ahí, **el 90% del contenido de este sitio se escribió sin usar ninguna
herramienta del servidor MCP.**

Esto no es un fallo técnico: la herramienta hace lo que promete. Es un fallo de economía que
convierte el resto del catálogo en decorado. Y arrastra un efecto de segundo orden que
anoté en la fase 11: al direccionar por ruta, insertar un elemento desplaza a todos sus
hermanos, y el aplicador contesta `aplicados=0` sin que eso sea un error.

### Tercera: no poder ver

Ni capturas de pantalla ni forma barata de mirar una imagen. Lo de las capturas es del
entorno, no del plugin: el proxy contesta 403 a un CONNECT hacia `mcp1.webs27.online:443`.
Lo anoto porque cambia cómo se trabaja, no para culpar a nadie.

Lo del plugin sí: para decidir cuál de los cuatro logos iba en la cabecera y cuál en el pie
tuve que **muestrear píxeles con GD** —porcentaje de transparencia, de negro, de blanco,
distribución de tinta por tercios verticales— y deducir que el fichero con un 60% más de
negro en el tercio inferior era la versión apilada con el texto debajo. Funcionó, y es una
manera absurda de mirar una imagen. Después comprobé que sí se pueden visualizar por
base64, pero sólo miniaturas: una imagen a tamaño completo son unos 350,000 tokens.

## 2. Qué herramienta eché de menos más veces

**`elementor_text_audit(post_ids, buscar?)`**: recorrer los documentos y devolver sólo los
ajustes de texto visible, con su ruta y su widget. Nada más.

La escribí a mano cuatro veces en PHP, con variaciones, porque cada vez necesitaba filtrar
distinto. Y su ausencia es la causa directa de los dos fallos más graves del proyecto:

- El **pie en inglés durante 32 páginas**, que encontré de rebote persiguiendo otra cosa,
  porque mi verificación cortaba el HTML justo antes del pie y lo excluía por construcción.
- La **plantilla de entrada con el artículo de demostración escrito a fuego**, que no salía
  en ningún barrido de HTML porque no había entradas que la pintaran.

Las dos son la misma pregunta sin responder: *¿qué texto hay en este sitio, exactamente?*
Con 161 herramientas y ocho dedicadas a leer la estructura de Elementor, que no exista la
que lee el texto es lo más raro del catálogo.

Segunda más echada de menos: **`elementor_element_replace`**, cambiar el tipo de un widget
conservando su posición y su `id`. En un sitio hecho con un kit comprado, «la sección está
bien puesta pero el widget no es el que necesito» pasa cada media hora.

## 3. Qué descripción reescribiría y cómo

La de **`elementor_template_apply`**, sin dudarlo. Es la herramienta con la que empieza
cualquier sitio hecho con kit, y su descripción omite las tres cosas que hay que saber.

Lo que falta en la descripción actual:

1. **El orden obligatorio.** `content_create` → `elementor_enable` → `elementor_template_apply`.
   Si se aplica antes de habilitar, se escriben cientos de kilobytes de `_elementor_data` en
   una página que `is_built_with_elementor()` sigue considerando no-Elementor, y el
   visitante recibe HTML desnudo. No está escrito en ninguna parte.
2. **Que se bloquea a sí misma.** Al aplicar se escribe también `post_content`, y la llamada
   a `elementor_enable` que viene después falla con `[security_gate] Esa página ya tiene
   contenido del editor de bloques`, refiriéndose a contenido que puso la propia
   herramienta dos llamadas antes. Hay que pasarle `force:true`. La descripción de
   `elementor_enable` debería nombrar este caso concreto.
3. **Que copia el contenido de demostración literal.** Al aplicar una plantilla de entrada
   individual, el título y el cuerpo del artículo de ejemplo del kit se quedan como texto
   fijo. La plantilla resultante no tiene ninguna etiqueta dinámica y todas las entradas
   saldrían iguales.

Propuesta de texto:

> Sustituye el contenido de una página por el de una plantilla de la biblioteca.
> **La página debe estar habilitada para Elementor antes** (`elementor_enable`): si no, se
> guardan los datos pero el visitante recibe la página sin maquetar. Al aplicar se escribe
> también `post_content`, así que si necesitas habilitar después tendrás que usar
> `force:true`.
> En plantillas del generador de temas (`single-post`, `archive`), el contenido de
> demostración se copia tal cual: revisa después si la plantilla necesita etiquetas
> dinámicas, porque esta herramienta no las añade.

Mención aparte para **`option_update`**, por lo contrario: su descripción avisa de que la
identidad del sitio está bloqueada «sin excepción», y me dejó cambiar `blogname` y
`blogdescription` sin la menor resistencia. Una descripción que promete una puerta que no
existe es peor que no prometer nada, porque te hace confiar en que algo te va a parar.

## 4. Qué hice con PHP que debería tener herramienta propia

Todo esto se resolvió con `ingenio_execute_php` porque no había otra vía. Ordenado por lo
que más me parece que falta:

| Lo que hice en PHP | Herramienta que falta |
|---|---|
| Aplicador de textos por ruta (el 90% del contenido del sitio) | `elementor_element_update` con varias rutas y ajustes en una llamada, que conteste un número |
| Leer todo el texto de un documento con su ruta | `elementor_text_audit` |
| Regenerar la caché de ubicaciones del generador de temas | que `elementor_template_conditions` lo haga de verdad, como promete |
| `wp_cache_flush()` después de casi cada escritura | que las herramientas de escritura invaliden su propia caché |
| Completar el asistente de Rank Math (`rank_math_wizard_completed`) | `seo_settings_update` debería poder dejar el plugin operativo, o al menos avisar de que no lo está |
| Cambiar un acordeón por un editor de texto (4 páginas legales) | `elementor_element_replace` |
| Rehacer la sección del cuerpo de la plantilla de entrada con widgets dinámicos | lo mismo |
| Crear las 6 categorías del blog | crear términos; existe `content_set_terms` para asignarlos pero no para crearlos |
| Poner el texto alternativo a 32 imágenes | `media_update` en lote |
| Asignar imagen distinta a 19 páginas y rotar los carruseles | lo mismo, en lote |
| `wp_update_post` para poner `post_parent` en las 13 páginas hijas | `content_update` no tiene parámetro `parent`; `wp_cli("post update --post_parent")` contesta `[not_supported]` |
| Identificar cuatro logos muestreando píxeles con GD | poder ver una imagen sin pagar 350,000 tokens |
| Comprobar que el logo blanco del pie iba sobre fondo negro, leyendo el CSS generado | `elementor_element_get` que devuelva el color efectivo resuelto, no la referencia `globals/colors?id=` |

De la lista, los dos que cambiarían más el trabajo diario son el primero y el segundo. Con
una escritura en lote barata y una lectura de texto, este sitio se habría hecho con las
herramientas del servidor en lugar de con PHP, que es justamente lo que veníamos a medir.

---

# Fase 13 — Mega menú de Servicios con Elementor Pro

Encargo: mega menú en Servicios, con ElementsKit o con Elementor Pro. Elegí el widget
`mega-menu` de Elementor Pro porque toda su configuración vive en `_elementor_data` y por
tanto se puede construir y verificar; el mega menú de ElementsKit se configura desde
Apariencia → Menús y guarda el contenido en un CPT propio (`elementskit_content`) con meta
que no está documentada en ninguna herramienta, y el encargo dice que no lea código.

## C El filtro de `elementor_widget_schema` no filtra, y esconde justo lo que hace falta

**Llamada:** `elementor_widget_schema(widget:"mega-menu", buscar:"menu")`

**Respuesta:** `Error: result (62,935 characters) exceeds maximum allowed tokens.` Volcado a
fichero. Dentro: `"total":675,"mostrados":382`. El filtro «menu» dejó pasar 382 de 675
controles, porque busca también en la **etiqueta** traducida y en este widget casi todas las
etiquetas llevan «menú».

Pero el problema de verdad es otro. Al leer el volcado, el control que necesitaba aparece
así y sólo así:

```json
{"ajuste":"menu_items","etiqueta":"Elementos del menú",
 "tipo":"nested-elements-repeater",
 "por_defecto":[{"item_title":"Elemento 1"},{"item_title":"Elemento 2"}]}
```

Un repetidor cuyas filas tienen campos, y la herramienta **no lista los campos**. De la
respuesta sólo se puede deducir que existe `item_title`, porque asoma en el valor por
defecto. Los tres que de verdad importan —`item_link`, `item_dropdown_content` y el
contrato de los hijos anidados— no salen.

**Qué hice:** bajar a la API de Elementor por PHP:

```php
$w = \Elementor\Plugin::$instance->widgets_manager->get_widget_types('mega-menu');
$c = $w->get_controls('menu_items');  // ['fields'] trae los campos de la fila
```

De ahí salieron `_id`, `item_title`, `item_link` (url), `item_dropdown_content` (switcher),
`item_icon`, `item_icon_active`, `element_id`. Y de `get_default_children_elements()`, por
reflexión porque el método es protegido, el contrato de los hijos: **un `container` por
cada fila del repetidor, en el mismo orden, en el array `elements` del widget.**

**Coste:** 4 llamadas, una de ellas reventada por tamaño.

**Mi lectura:** fallo del plugin, y con arreglo barato. La propia descripción de la
herramienta dice que existe «para no inventarse nombres de ajuste», que es exactamente lo
que me tocó hacer con los campos del repetidor. Dos cosas la arreglarían: que `buscar`
busque sólo en la clave salvo que se le pida lo contrario, y que un control de tipo
`repeater` o `nested-elements-repeater` devuelva sus campos anidados. Sin lo segundo, la
herramienta es incapaz de describir cualquier widget con repetidor, que en Elementor son casi
todos los interesantes.

## A No hay forma de crear un widget anidado

El `mega-menu` es un widget con dos mitades que tienen que ir sincronizadas: el repetidor
`menu_items` (8 filas) y el array `elements` del propio widget (8 contenedores, uno por
fila, en el mismo orden). Si se desincronizan, el menú se rompe.

**Qué quería hacer:** crear el widget con sus 8 items, y dentro del segundo, el panel del
mega menú: contenedor horizontal, tres columnas de enlaces a los 13 servicios y una cuarta
de llamada a la acción con fondo, titular, texto y botón. Veintitantos elementos.

**Llamadas disponibles:** `elementor_element_add` añade un widget suelto a un padre. No
conoce el contrato de hijos de un widget anidado, y aunque lo conociera, construir el panel
serían ~25 llamadas encadenadas en las que cada una necesita el `element_id` que devolvió la
anterior.

**Qué hice:** montar el árbol completo en PHP y sustituir el nodo del `ekit-nav-menu`
anterior de una vez. Una llamada.

**Coste:** ~25 llamadas ahorradas, pero también ~25 puntos donde la herramienta no habría
sabido qué hacer.

**Mi lectura:** hueco real, y el más caro del catálogo para Elementor moderno. Desde que
existen contenedores y elementos anidados, «montar una sección» es escribir un árbol, no
añadir widgets de uno en uno. Falta un `elementor_tree_insert(post_id, ruta, arbol_json)`
que acepte una rama entera y la valide antes de guardarla. Con eso, el 90% del trabajo que
hice en PHP durante todo este proyecto se podría haber hecho con herramientas.

## G Los identificadores de elemento tienen que ser hexadecimales

**Llamada:** guardé el widget con `'id' => 'megamenu1'`.

**Respuesta:** se guardó, la página renderizó bien, y el resultado trajo 31 avisos idénticos:

```
Deprecated en línea 230: Invalid characters passed for attempted conversion,
these have been ignored
```

**Qué hice:** cambiarlo por `a1b2c3d`. Los avisos desaparecieron.

**Coste:** 1 llamada.

**Mi lectura:** límite razonable de Elementor (genera los identificadores con `dechex`, así
que sólo admite `[0-9a-f]`), pero nada lo dice. Ni la descripción de `elementor_element_add`
ni la de `elementor_element_duplicate` mencionan el formato del identificador. Y el aviso
que sale no nombra ni el identificador ni el elemento: 31 líneas iguales que no dicen dónde
está el problema. Lo anoto porque es el tipo de cosa que se guarda mal, funciona, y aparece
seis meses después.

## G Los colores globales son identificadores opacos, y me equivoqué de color

Puse fondo a la columna de llamada a la acción con `globals/colors?id=73f6f6c`, que por el
sitio donde lo había visto antes daba por hecho que era un verde claro.

**Respuesta:** ninguna. Se guardó y se pintó. Al mirar el kit después:

```
73f6f6c = "Transparent #00000000"
fed1e35 = "Alt bg Light #EAF0DA"   ← el que quería
```

La columna quedó sin fondo y nadie se quejó, porque un fondo transparente no es un error,
es un fondo.

**Qué hice:** `elementor_get_kit` una vez, ver la tabla de once colores con su nombre, y
corregir. Verificado en el CSS generado: `background-color:var( --e-global-color-fed1e35 )`.

**Coste:** 2 llamadas. Culpa mía por no mirar la tabla antes.

**Mi lectura:** el error es mío, pero lo anoto porque la herramienta lo facilita.
`elementor_element_get` devuelve `"background_color": "globals/colors?id=73f6f6c"` y se queda
tan ancha. Un campo hermano con el valor resuelto y el nombre —`#00000000`, «Transparent»—
convertiría una referencia opaca en algo que se puede leer. Es el mismo problema que tuve en
la fase 9 comprobando si el logo blanco del pie caía sobre fondo negro: tuve que ir al CSS
generado a resolver la variable a mano.

## Resultado y una decisión que hay que saber

El mega menú funciona: 8 entradas de primer nivel, el panel de Servicios con los 13
servicios en tres columnas agrupadas (Mantenimiento, Diseño e instalación, Árboles y
sanidad) y una cuarta columna con llamada a la acción y botón a `/cotizacion/`. Panel blanco
con sombra y esquinas inferiores redondeadas, apertura al pasar el cursor, y botón
hamburguesa a partir de tableta. Verificado en el front: 23 enlaces, ninguno vacío.

**La decisión:** el menú ya no sale de Apariencia → Menús. El widget `mega-menu` de
Elementor Pro guarda sus entradas dentro de la cabecera, así que el menú de WordPress
«Principal» (21 items) se queda huérfano: sigue existiendo y no lo usa nadie. Para cambiar
el menú a partir de ahora hay que entrar a editar la plantilla de cabecera en Elementor.

Con ElementsKit se habría conservado Apariencia → Menús, pero el contenido del mega menú se
edita desde su propio constructor y se guarda en un CPT con meta no documentada: no había
forma de construirlo ni de verificarlo desde aquí sin leer el código del plugin, que el
encargo prohíbe. Si se prefiere el otro reparto, se revierte cambiando el widget de la
cabecera; el menú de WordPress sigue intacto.

---

# Fase 14 — El mega menú salió mal a la primera: tres causas

Capturas del cliente: el menú partido en dos líneas y el panel de Servicios a pantalla
completa, altísimo, con las cuatro columnas apiladas una debajo de otra en vez de en fila.
Tres causas independientes, y dos de ellas son el mismo patrón de siempre.

## F Un `select` acepta cualquier cadena y la escribe cruda en el CSS

El control `content_width` del widget `mega-menu` es un `select` con exactamente dos
valores: `full_width` y `fit_to_content`. Yo escribí `'full'`, por analogía con el control
del mismo nombre de los contenedores, donde `full` **sí** es válido.

**Llamada:** `$w['content_width'] = 'full';` dentro del guardado del documento.

**Respuesta:** ninguna. Se guardó, el documento validó, la página renderizó 200. Elementor
generó esto:

```css
.elementor-99 .elementor-element-a1b2c3d{--n-menu-dropdown-content-max-width:full;}
```

`full` no es una longitud CSS. El navegador descarta la declaración, la variable se queda sin
valor y el panel se estira a todo el ancho de la ventana. El selector del control es
literalmente `--n-menu-dropdown-content-max-width: {{VALUE}}`, con el valor interpolado tal
cual; los dos valores legítimos se traducen antes por diccionario (`full_width` → `initial`),
y cualquier otra cosa pasa de largo.

**Qué hice:** poner `full_width`. Comprobado en el CSS generado:
`--n-menu-dropdown-content-max-width:initial`.

**Coste:** 1 llamada de diagnóstico y 1 de corrección, más el rato de mirar las capturas.

**Mi lectura:** fallo del plugin MCP, y esta vez uno que se puede arreglar sin tocar
Elementor. El servidor **ya sabe** los valores válidos: `elementor_widget_schema` me los
devolvió cuando se los pedí, con su diccionario de opciones. `elementor_element_update`
tiene ese mismo esquema a mano y no lo usa para validar. Un ajuste de tipo `select` con un
valor que no está entre sus opciones es siempre un error del que escribe, y es detectable
en el momento. La propia descripción de `elementor_widget_schema` dice que existe porque
«Elementor ignora en silencio las claves que no conoce». Es peor que eso: los **valores**
que no conoce no los ignora, los publica.

Lo anoto como F y no como B porque encaja con la tercera pregunta del encargo: aquí no me
paró nada donde sí tocaba pararme.

## B La anchura de un contenedor se ignora en silencio si no está en modo «ancho completo»

Las cuatro columnas del panel llevaban `width: 22%`, `24%`, `20%` y `30%`. Salieron apiladas.

**Diagnóstico:** el CSS generado de cada columna no tenía **ninguna** declaración `--width`:

```css
.elementor-element-9a33e9b{--display:flex;--flex-direction:column;--gap:10px 10px;...}
```

mientras que un contenedor hermano de la cabecera, que yo no había tocado, sí la tenía:

```css
.elementor-element-c0675af{--width:60%;}
```

La diferencia: el de la cabecera trae `content_width: "full"` y los míos no traían
`content_width` en absoluto. En Elementor el control `width` de un contenedor depende de que
`content_width` sea `full`; en modo `boxed`, que es el de por defecto, manda `boxed_width` y
el `width` que escribas se guarda y no hace nada.

**Qué hice:** poner `content_width: 'full'` en las cuatro columnas. `--width:22%` apareció
al instante y las columnas se pusieron en fila.

**Coste:** 2 llamadas.

**Mi lectura:** el comportamiento es de Elementor y tiene su lógica, pero el resultado es
exactamente el patrón que llevo anotando desde la fase 2, ahora a nivel de control: **se
guarda un valor, no se emite nada, y nadie avisa.** Un ajuste con `condition` o `conditions`
que no se cumplen es un ajuste muerto, y el servidor puede saberlo: el esquema del control
trae esas condiciones. `elementor_element_update` podría contestar «he guardado `width`,
pero no tendrá efecto porque `content_width` no es `full`» en lugar de un acuse de recibo
limpio. Es la misma herramienta que ya valida etiquetas de correo en `cf7_update`.

## A El ajuste que hacía falta no tiene control, y hubo que ir por CSS propio

El menú se partía en dos líneas. La causa es `--n-menu-heading-wrap: wrap`, que el widget
pone por defecto. Busqué el control en el esquema con `elementor_widget_schema` filtrando por
`wrap`, `overflow` y `stretch`: **no existe**. La variable está, el control no.

**Qué hice:** el control `custom_css` de Elementor Pro, en el propio widget:

```css
selector{--n-menu-heading-wrap:nowrap;}
```

Comprobado en el CSS generado: la regla sale al final del fichero, con la misma
especificidad que la base, así que gana. Además bajé la separación entre entradas de 28 a
14 px y repartí la cabecera 17 / 66 / 17 en lugar de 20 / 60 / 20.

**Coste:** 3 llamadas contando la búsqueda en el esquema.

**Mi lectura:** límite razonable de Elementor, no del servidor. Pero deja una lección para
el catálogo: cuando el ajuste no existe, `custom_css` es la salida, y ninguna descripción de
las herramientas de Elementor lo menciona. Un `elementor_element_update` que al recibir una
clave desconocida contestara «ese ajuste no existe en este widget; si lo que quieres es una
regla CSS, el control es `custom_css`» ahorraría la búsqueda.

## Cómo quedó

Panel blanco a todo el ancho con el contenido limitado a 1240 px y centrado (`e-con-boxed`
con su `e-con-inner`), las cuatro columnas en fila con `flex-wrap: nowrap`, y el menú en una
sola línea. Verificado en el HTML publicado: 8 entradas, 0 enlaces vacíos, las cuatro
columnas con `e-con-full` y su `--width` emitido.

**Lo que aprendí de esta fase, que vale para todo el proyecto:** verifiqué el mega menú
contando enlaces y buscando texto, y pasó la verificación. Las tres cosas que estaban mal
eran de **maquetación**, y ninguna se ve contando elementos ni leyendo texto. Sin la captura
de pantalla del cliente, este menú se entrega roto. Es la contrapartida exacta de lo que
anoté en la fase 12: allí el HTML era ciego a los ajustes, aquí los ajustes y el HTML son
ciegos a cómo se ve. Con el proxy bloqueando las capturas desde este contenedor, la única
verificación visual del proyecto ha sido un humano mirando la pantalla.

---

# Fase 15 — Enlazar el logo a la portada

Cambio pequeño y sin sorpresas de herramienta, pero lo anoto porque dejó el sitio mal
durante un minuto y la regla del encargo es que eso se cuenta.

## D Recorrer widgets por tipo, otra vez en PHP

**Qué quería hacer:** que el logo de la cabecera lleve a la portada. Está en la plantilla de
cabecera, así que es un solo cambio para las 33 páginas.

**Qué hice:** `ingenio_execute_php` recorriendo los documentos 99 y 100, y en cada widget de
tipo `image` poniendo `link_to: 'custom'` y `link.url: home_url('/')`.

`elementor_element_update` habría servido aquí, y era la herramienta correcta: son dos
elementos y dos llamadas. Usé PHP por inercia del resto del proyecto. Lo anoto como uso mío,
no como hueco del catálogo.

**Mi lectura:** ninguna fricción atribuible al plugin en este cambio.

## Lo que sí salió mal: enlacé una imagen decorativa

Filtré por `widgetType === 'image'` y apliqué a todo lo que encajara. En la cabecera hay una
sola imagen y es el logo. En el pie hay **dos**: el logo blanco (`IMG_6666.png`) y
`Footer-01-1.webp`, que es un adorno de fondo del kit. Las enlacé las dos.

**Qué hice:** revertir la decorativa quitándole `link_to` y `link`, y verificar en el front
las tres por separado:

```
logo cabecera   -> https://mcp1.webs27.online/
logo pie        -> https://mcp1.webs27.online/
decorativa pie  -> sin enlace
```

**Coste:** 1 llamada de corrección. Estuvo mal publicado un minuto.

**Mi lectura:** error mío, no del plugin, y lo digo claro. Pero tiene la misma raíz que la
fase 14: **desde aquí no se puede saber qué es una imagen, sólo cómo se llama el fichero y
dónde está en el árbol.** «Logo» y «adorno de fondo» son el mismo `widgetType: image` con la
misma forma. En la fase 9 tuve que muestrear píxeles con GD para distinguir cuatro versiones
del mismo logo; aquí me bastaba con haber mirado las dos imágenes un segundo, y no puedo
mirarlas. Es el mismo hueco contado por tercera vez, y es el que más me ha costado en todo
el proyecto después de la economía de contexto.

Un dato a favor del plugin: los textos alternativos que puse en la fase 12 habrían bastado
para distinguirlas, si los hubiera consultado. `media_get` existe y los devuelve. No lo
pensé.

---

# Fase 16 — No puedo ver la página renderizada (el caso, entero)

**Esta entrada llega tarde y eso es en sí mismo un dato.** El asunto salió en la fase 4,
cuando se preguntó si se podían hacer capturas de pantalla. Lo investigué, contesté que no y
seguí trabajando. Quedó mencionado de pasada en dos sitios —dos frases en el cierre y un
párrafo en la fase 14— pero **nunca lo escribí como caso, con su llamada y su respuesta
literal**, que es justo lo que pide el encargo. El instinto de «rodearlo y seguir» me ganó
aquí, en la limitación más importante de todo el proyecto. Lo escribo ahora completo.

## A No hay forma de ver cómo se ve una página

**Qué quería hacer:** mirar la página. Una captura de pantalla, o cualquier cosa que me diga
que lo que acabo de maquetar se ve como debe verse.

**Lo que hay en el contenedor:** Chromium preinstalado y Playwright configurado para
encontrarlo (`PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers`). O sea que el navegador está.

**Llamada:**
```
curl -sS -o /dev/null -w "http=%{http_code}\n" --max-time 10 https://mcp1.webs27.online/
```

**Respuesta (comprobada de nuevo hoy, sigue igual):**
```
curl: (56) CONNECT tunnel failed, response 403
http=000
```

El proxy del entorno contesta 403 al CONNECT hacia `mcp1.webs27.online:443`. El navegador
está, pero no puede llegar al sitio. Cualquier ruta que pase por «abrir la URL» está cerrada.

**Qué hice:** verificar siempre por el HTML, pidiéndole al **sitio que se descargue a sí
mismo**:
```php
wp_remote_get(get_permalink($id))   // dentro de ingenio_execute_php
```
Esto sí funciona, porque la petición sale del servidor de WordPress, no de mi contenedor. Es
como he comprobado las 33 páginas: código 200, número de palabras, ausencia de inglés,
enlaces vacíos, elementos de Elementor presentes. Todo el proyecto se ha verificado así.

**Coste:** imposible de contar en llamadas, porque no es un coste de llamadas. Es un coste de
**clase de error**: hay una familia entera de fallos que este método no ve.

## Lo que esta ceguera dejó pasar, con nombre y apellidos

No es teórico. Tres cosas se publicaron mal y las tres pasaron mi verificación:

1. **El mega menú de la fase 14.** Conté 8 entradas, 23 enlaces, 0 vacíos. Pasó. Y estaba
   roto en tres sitios a la vez: el panel a pantalla completa, las cuatro columnas apiladas y
   el menú partido en dos líneas. **Lo cazó una captura de pantalla del cliente.**
2. **El pie en inglés en 32 páginas** (fase 10). Ahí el fallo fue mío —recortaba el HTML
   justo antes del pie— pero un solo vistazo a cualquier página lo habría enseñado.
3. **La imagen decorativa del pie enlazada por error** (fase 15). Dos imágenes, la misma
   forma en el árbol, ninguna manera de mirarlas.

El patrón es claro: **verificar por HTML detecta contenido y estructura; no detecta
maquetación.** Y en un sitio hecho con un kit comprado, donde casi ninguna sección se usa
para lo que el kit creía, la maquetación es justo donde está el riesgo.

## ¿Se podría rodear? Sí, y no compensa

Lo pensé y lo descarto con números, no por pereza:

- **Reconstruir la página en local y capturarla con el Chromium que tengo.** Haría falta
  traerme el HTML (que sí puedo) más todo el CSS, las fuentes y las imágenes, y todo eso vive
  en `mcp1.webs27.online`, que está bloqueado. Tendría que pasarlo por `ingenio_execute_php`
  en base64, igual que hice con la miniatura del logo en la fase 7. Sólo el CSS de la
  cabecera son 27 KB; la página entera con Elementor, ElementsKit y Jeg Kit anda por el
  megabyte. Son cientos de miles de tokens **por página**. Es la misma pared que con las
  imágenes.
- **Un servicio público de capturas.** Bloqueado por el mismo proxy, y además implicaría
  mandar la URL del sitio a un tercero.

Así que la respuesta honesta es la que pide la regla 2 del encargo: **esto no se puede con
las herramientas que tengo.** No hay heroicidad que lo disimule.

## Mi lectura

**El 403 es del entorno, no del plugin.** Es la política de red de este contenedor y hace
bien en ser restrictiva. No lo apunto para culpar al servidor MCP.

**Pero el servidor sí podría tapar el hueco, y no lo hace.** Tiene 161 herramientas, entre
ellas `site_performance`, `seo_audit`, `content_render` y `content_analyze`, todas del lado
del servidor. Le falta la que convierte a un agente en alguien que puede comprobar su
trabajo:

> **`page_screenshot(post_id, ancho, alto?)`** — que el servidor renderice la página y
> devuelva una imagen pequeña en base64. Con 600 px de ancho y calidad baja son unas pocas
> decenas de KB. No hace falta que sea bonita: hace falta poder ver que cuatro columnas están
> en fila y no apiladas.

El servidor está en el mismo sitio que WordPress, tiene PHP, y hay maneras (un navegador sin
cabeza si está disponible, o un servicio externo desde el servidor en lugar de desde mi
contenedor). Es la misma petición que hice en la fase 7 para `media_thumbnail`: **devolver
píxeles, no metadatos.** Las dos herramientas se parecen tanto que probablemente sean la
misma pieza de infraestructura.

Sin eso, el reparto de trabajo real de este proyecto ha sido: yo escribo y compruebo el
contenido, y **un humano mira la pantalla y me dice qué está torcido.** Funciona —el mega
menú se arregló en veinte minutos desde su captura— pero conviene decirlo tal cual, porque
es lo que de verdad hace falta para trabajar así, y no sale en ninguna descripción de
herramienta.

---

# Fase 17 — Verificación móvil: no se había hecho, y había dos fallos míos

Pregunta directa del cliente: *¿se hizo verificación de la versión móvil de este sitio?*

**No.** Ni una sola vez en 16 fases. Hasta ahora toda la verificación ha sido el HTML
publicado —código 200, palabras, inglés, enlaces vacíos, elementos de Elementor— y eso no
tiene tamaño de pantalla. Lo digo sin rodeos porque es exactamente el tipo de hueco que este
registro existe para medir, y llevaba 16 fases sin salir.

## Los dos fallos que encontré al mirarlo, y son míos

Los tres ajustes que metí en la fase 14 para arreglar el escritorio se aplicaban en **todos**
los tamaños. Al auditarlos en el CSS generado:

```
sin media query, o sea en móvil también:
  .elementor-element-a1b2c3d { --n-menu-heading-wrap: nowrap; }   <- del custom_css
  .elementor-element-05746ba { --flex-wrap: nowrap; }             <- el panel
```

**1. `--n-menu-heading-wrap: nowrap` en móvil.** El widget usa esa misma variable para las dos
cosas: en escritorio decide si la barra se parte en dos líneas, y en móvil, dentro del
desplegable de la hamburguesa, es lo que **apila las ocho entradas en vertical**. Al forzarla
a `nowrap` para arreglar el escritorio, dejé las ocho entradas comprimidas en una sola fila
dentro del desplegable móvil. Menú inservible en teléfono.

**2. `--flex-wrap: nowrap` en el panel de Servicios.** Las cuatro columnas del mega menú, que
en escritorio tienen que ir en fila, quedaban también en fila en móvil: cuatro columnas
aplastadas en 360 px.

**Qué hice:**
```php
// el nowrap, sólo escritorio
'custom_css' => '@media(min-width:1025px){selector{--n-menu-heading-wrap:nowrap;}}'
// el panel vuelve a envolver por debajo de escritorio
'flex_wrap' => 'nowrap', 'flex_wrap_tablet' => 'wrap', 'flex_wrap_mobile' => 'wrap'
// y las columnas: 22/24/20/30% escritorio, 50% tableta, 100% móvil
```
Comprobado en el CSS generado: el `nowrap` del menú vive ahora dentro de
`@media(min-width:1025px)`, el panel tiene `--flex-wrap:wrap` en `max-width:1024px` y en
`max-width:767px`, y las columnas `--width:100%` por debajo de 768.

**Coste:** 3 llamadas, y el fallo estuvo publicado desde la fase 14.

**Mi lectura:** error mío de cabo a rabo, y con una lección concreta: **el CSS propio de un
elemento (`custom_css`) no tiene variantes por dispositivo.** Los controles normales de
Elementor las traen —`flex_wrap_tablet`, `width_mobile`— pero lo que escribes a mano se
aplica en todas partes salvo que tú mismo pongas la media query. Cuando la salida a un ajuste
que no existe es escribir CSS a pelo (fase 14), te llevas también la responsabilidad de los
puntos de ruptura, y eso no lo advierte nada.

## Lo que una auditoría de datos sí ve, y lo que no

Recorrí los 38 documentos buscando problemas de móvil en los ajustes y en el CSS generado.

**Lo que salió, y era ruido:** doce contenedores con anchura fija grande —650 px, 700 px,
565 px, 520 px— sin variante móvil. Parecía desbordamiento seguro. No lo es: Elementor aplica

```css
.e-con{ max-width: min(100%, var(--width)) }
```

así que una anchura de 650 px en una pantalla de 360 se convierte en 360. Doce falsos
positivos. **Una captura de pantalla no me habría hecho perder ese tiempo.**

**Lo que salió y sí importa:** el botón «Cotizar gratis» de la cabecera está oculto en móvil
(`hide_mobile`, decisión del kit original, no mía). En teléfono la cabecera es logo +
hamburguesa y no hay ninguna llamada a la acción visible. Para un sitio cuyo argumento entero
es «mándenos fotos por WhatsApp», eso merece mirarse. No lo he cambiado porque no es un fallo,
es una decisión de diseño del kit y tocarla puede apretar la cabecera; lo dejo dicho.

**Y lo que no puedo ver de ninguna manera:** si el sitio **se ve bien** en un teléfono. He
auditado los ajustes y el CSS generado, que es una capa por debajo. Sé que las reglas dicen
lo correcto. No sé si el resultado se lee, si los textos caben, si algo se monta encima de
otra cosa, si los botones se pueden pulsar con el pulgar. **Para eso hace falta abrir la
página en un móvil, y eso es exactamente lo que la fase 16 dice que no puedo hacer.**

## Por qué esto sube la fase 16 de categoría

En la fase 16 dije que no ver el renderizado era la limitación más importante del proyecto.
Con esto se queda corto, y lo corrijo: **no es sólo que no pueda ver una página, es que no
puedo navegar el sitio ni cambiar de tamaño de pantalla.** Son dos cosas distintas y la
segunda es peor.

Una herramienta de captura del lado del servidor tendría que aceptar el ancho como parámetro:

> **`page_screenshot(post_id, ancho, alto?)`** — con `ancho=390` se ve el móvil, con
> `ancho=768` la tableta, con `ancho=1440` el escritorio. **Tres llamadas y unas decenas de
> KB por página**, contra la alternativa actual, que es que una persona abra el sitio en tres
> dispositivos.

Los dos fallos de esta fase habrían salido en la primera captura a 390 px. Los doce falsos
positivos no habrían existido. Y el botón oculto en móvil se habría visto de un vistazo en
lugar de salir de un `grep` por `hide_mobile`.

Corrijo también lo que escribí al final de la fase 14: dije que la única verificación visual
del proyecto había sido un humano mirando la pantalla. Es peor que eso: **ha sido un humano
mirando la pantalla de un escritorio.** La versión móvil de este sitio no la ha visto nadie
todavía, ni él ni yo.

---

# Fase 18 — El menú móvil, visto por fin

Capturas del cliente desde un iPhone. El menú desplegable se ve así: las ocho entradas
alineadas **a la derecha**, cada una sobre una franja blanca, y entre franja y franja **se ve
la página de fondo** —el titular del hero asomando a trozos entre las entradas del menú—.
Inservible, y feo de una manera que no admite discusión.

Esto es **después** de la corrección de la fase 17, que yo había dado por buena mirando el
CSS generado. Segundo aviso en dos fases de lo mismo: el CSS decía lo correcto y el resultado
era otro.

## Lo que estaba pasando, y lo que yo creía que pasaba

Mi primera hipótesis fue que las franjas eran los **siete contenedores vacíos** que el widget
obliga a crear (uno por entrada del menú, aunque sólo Servicios tenga desplegable). Lo
comprobé antes de tocar nada:

```php
substr_count($html, '8fdc8f9')  // contenedor vacío de "Inicio"
→ 0
```

Ninguno de los siete está en el DOM. Hipótesis descartada en una llamada, y menos mal, porque
iba a "arreglar" algo que no existía.

La causa real son dos cosas distintas que se suman:

1. **El panel desplegable no tiene fondo.** `.e-n-menu-wrapper` trae
   `background-color: transparent` del CSS del widget, y el único control de fondo que expone
   el catálogo, `content_background_color_color`, no pinta el panel: su selector es
   `... > .e-n-menu-content > .e-con`, o sea el **contenido** de cada desplegable, no el
   contenedor de la lista. Las entradas tienen fondo propio y los huecos entre ellas no. Por
   eso se ve la página entre línea y línea.
2. **`--n-menu-title-justify-content-mobile: flex-end`.** Herencia de haber puesto
   `item_position_horizontal: 'end'` para que en escritorio el menú vaya pegado a la derecha,
   junto al botón. En móvil, dentro de un panel a todo el ancho, eso deja los textos pegados
   al borde derecho.

**Qué hice:**

```php
'item_position_horizontal_tablet'  => 'start',   // y _mobile
'menu_item_title_space_between_mobile'  => 0,    // el hueco entre entradas
'menu_item_title_padding_mobile' => '15px 20px',
// y el fondo, que no tiene control, por CSS propio:
'@media(max-width:1024px){ selector .e-n-menu-wrapper{
    background-color: var(--e-global-color-f45b7f8);   // blanco del kit
    border-radius:0 0 14px 14px; box-shadow:0 14px 34px rgba(0,0,0,.14); }
  selector .e-n-menu-heading{row-gap:0;}
  selector .e-n-menu-item>.e-n-menu-title{border-bottom:1px solid var(--e-global-color-d61c747);}
}'
```

Comprobado en el CSS generado: en `max-width:1024px` y en `max-width:767px` ahora salen
`--n-menu-title-justify-content: initial`, `--n-menu-title-space-between: 0px` y
`--n-menu-title-padding: 15px 20px`, y el bloque propio con el fondo blanco.

**Coste:** 4 llamadas, más las dos capturas del cliente.

## A El control de fondo del desplegable no pinta el desplegable

Merece entrada propia porque es un hueco de verdad, no un error mío.

El widget expone un grupo entero de controles llamado **«Fondo del contenido desplegable»**
(`content_background_color_*`), con su color, su degradado, su imagen y hasta su presentación
de diapositivas con efecto Ken Burns. Su selector es:

```
:where( {{WRAPPER}} > .e-n-menu > .e-n-menu-wrapper > .e-n-menu-heading
        > .e-n-menu-item > .e-n-menu-content ) > .e-con
```

Es decir: pinta el **contenedor de contenido de cada entrada**, que en escritorio es el panel
del mega menú —y que yo ya tenía en blanco por otra vía— pero **no pinta nunca**
`.e-n-menu-wrapper`, que es la caja que envuelve la lista y la única que importa en móvil.

O sea: en el modo donde el widget más necesita un fondo sólido —desplegable a pantalla
completa sobre el contenido de la página— **no hay ningún control que lo ponga.** La única
salida es CSS propio, otra vez.

**Mi lectura:** es de Elementor, no del servidor MCP. Pero lo anoto porque completa un patrón
que ya llevo tres veces en este widget: `content_width` que acepta valores inválidos (fase
14), `--n-menu-heading-wrap` sin control (fase 14), y ahora un grupo de controles de fondo que
no cubre el elemento que hace falta. **En las tres, la salida fue escribir CSS a mano.** Un
widget cuyo uso real exige CSS propio tres veces en dos días no está terminado, y eso el
catálogo de herramientas no lo puede saber ni avisar.

## Lo que no puedo decir

**No sé si esto ha quedado bien.** Es la segunda corrección a ciegas del mismo menú: la de la
fase 17 la di por buena leyendo el CSS generado y estaba mal. Ahora he vuelto a leer el CSS
generado y vuelve a decir lo correcto. Es exactamente la misma evidencia que me falló hace
dos fases.

Lo digo tal cual en lugar de dar el trabajo por cerrado: hace falta otra captura del teléfono.
Y hace falta, en general, lo que pedí en la fase 16 con el ancho como parámetro, porque este
menú lleva ya tres rondas —escritorio roto, móvil roto, móvil roto otra vez— y las tres las ha
encontrado una persona mirando la pantalla, no yo.

---

# Fase 19 — La franja blanca de arriba en móvil

Captura del cliente: entre la barra del navegador y la tarjeta verde de la cabecera hay una
banda blanca que no pinta nada.

## No era un fallo de herramienta: era aritmética del kit que dejó de cuadrar

La cabecera **no tiene fondo**. Lo comprobé en el CSS generado y en los ajustes: el
contenedor de sección `ccc684d` no lleva `background_background`, y no hay ninguna regla que
pinte el `body` —ni en el CSS del kit (`post-9.css`, 12 KB), ni en el del tema, ni en ninguna
de las hojas de `uploads/elementor/css/`. Lo verde es la **tarjeta interior**, y el verde de
debajo es el hero de la portada. Lo de en medio es el fondo del documento: blanco.

Lo que hace el kit para que no se note es solaparlo en móvil:

```css
@media(max-width:767px){
  .elementor-element-ccc684d{ --min-height:90px; --margin-bottom:-90px; }
}
```

La sección mide 90 px y tira del contenido 90 px hacia arriba: se cancelan y el hero pasa por
detrás. Truco limpio **mientras la cabecera mida de verdad 90 px**.

Y ahí está el problema: **el logo de Jardines del Valle es mucho más alto que el del kit.**
El fichero es de 1983×793 y en móvil ocupaba el 75 % del ancho, así que la cabecera real
medía unos 150 px. El tirón de 90 px se quedaba 60 px corto, y esos 60 px de fondo del
documento son la banda blanca.

**Qué hice**, con números en lugar de a ojo, porque la relación de la imagen la sé:

```
1983 × 793  →  a 210 px de ancho son exactamente 84 px de alto
25 (padding superior de la sección) + 84 (logo) + 20 (padding de la tarjeta) = 129
```

- logo `width_mobile: 210px`
- sección `min_height_mobile: 130px` y `margin_mobile.bottom: -130px`

Comprobado en el CSS generado: `--min-height:130px` y `--margin-bottom:-130px` en
`max-width:767px`, y `.elementor-element-a64d74c img{width:210px}`. Los dos números se
cancelan y ya no queda hueco. De paso el logo deja de comerse tres cuartos de la pantalla.

**Coste:** 5 llamadas, tres de ellas de diagnóstico equivocado (busqué un fondo del `body`
que no existe, y una regla en el CSS del tema que tampoco).

## Mi lectura

Ninguna herramienta falló aquí, y es justo decirlo. Lo anoto por otra cosa:

**Es un fallo que nace de haber hecho bien una tarea anterior.** Poner el logo del cliente
—fase 7, con su muestreo de píxeles y todo— rompió una cuenta que el kit tenía calibrada para
su propio logo de demostración. Nada avisa de eso: el `margin-bottom: -90px` es un número
escrito a mano en una plantilla comprada, y no hay nada que diga «esto depende de que la
cabecera mida 90 px». No lo sabe Elementor, no lo sabe el servidor MCP y no lo sabía yo hasta
que se vio la franja.

En un sitio hecho con kit esto no es una rareza, es lo normal: **las plantillas están llenas
de números calibrados contra un contenido que tú vas a sustituir.** Un agente que trabaja a
ciegas los rompe sin enterarse. Y de nuevo, la única forma de enterarse es mirar.

Van tres fases seguidas —17, 18 y 19— en las que el fallo lo ha encontrado una captura de
pantalla de una persona. Ninguna la habría encontrado yo con el HTML, y la 19 ni siquiera con
el CSS, porque el CSS era correcto: lo que estaba mal era la relación entre un número del CSS
y el tamaño real de una imagen.

---

# Fase 20 — Plugin 1.8.0: qué se arregló y qué sólo cambió de sitio

Servidor reconectado y esquema nuevo confirmado antes de tocar nada.

## ✅ `elementor_element_update` con `cambios`: sí compensa, y por la razón correcta

Era el problema número dos del cierre y el que me empujó a escribir el 90 % del sitio en PHP.

**Llamada** (el arreglo del panel del menú móvil, dos elementos de la cabecera):

```json
{"post_id":99,"cambios":[
  {"element_id":"f14eacd","ajustes":{"custom_css":"@media(max-width:1024px){selector{position:relative;}}"}},
  {"element_id":"a1b2c3d","ajustes":{"custom_css":"@media(min-width:1025px){…}@media(max-width:1024px){…}"}}]}
```

**Respuesta, entera:**

```json
{"post_id":99,"aplicados":2,
 "elementos":[{"element_id":"f14eacd","claves":["custom_css"]},
              {"element_id":"a1b2c3d","claves":["custom_css"]}],
 "deshacer":{"revision":328,"como":"history_restore con revision=328 devuelve esto a como estaba."}}
```

Unos 200 bytes. Antes, **cambiar tres textos de un solo widget elementskit costaba unas 1.200
palabras de respuesta**.

Lo que importa no es el tamaño, es que **la respuesta ya no escala con el widget**: es
proporcional a las claves que cambias, no a lo que el widget tiene dentro. Ese era el defecto
de fondo, y está resuelto. Con esto, el trabajo de una página cabe en llamadas normales.
El `deshacer` con su revisión es un añadido que antes no estaba y que da confianza para
escribir en lote.

**Pero no quita todo el problema, y el trozo que queda es el que me costó más caro.**

`post_id` es uno solo, y cada entrada de `cambios` se identifica por `element_id`. El trabajo
que de verdad me obligó a bajar a PHP no era una página: eran **trece páginas clonadas de la
misma plantilla**. Y los identificadores no se repiten entre clones. La misma ruta, en cuatro
páginas hechas de la misma plantilla:

| ruta | 112 | 149 | 150 | 161 |
|---|---|---|---|---|
| `0.0.0` | eacb488 | df629ea | 1954468 | 3960303 |
| `1.1.1` | 7b96b1f | 08cebce | c803b11 | 2c99225 |
| `1.1.2` | c74354e | b177422 | 72d70da | b96645f |

Por eso inventé el direccionamiento por ruta: **la ruta sí es la misma en todos los clones.**
Con `cambios` tal como está, las 13 páginas de servicio serían una lectura para mapear
identificadores más 13 llamadas. Con el aplicador por rutas fue **una**.

**Veredicto:** para trabajo de una página, compensa y con holgura: la usaría siempre. Para
trabajo repetido sobre clones de plantilla, que en un sitio hecho con kit es la mitad del
encargo, sigue sin compensar. Dos arreglos posibles, por orden:

1. Que cada entrada de `cambios` pueda traer su propio `post_id`. Trece páginas, una llamada.
2. Que `element_id` admita como alternativa una `ruta` (`"1.1.2"`). Es lo que hace que un
   cambio sea describible una vez y aplicable a todos los clones.

Con cualquiera de las dos, el aplicador en PHP sobra. Sin ellas, lo sigo necesitando para el
caso que más pesa.

## ❌ `elementor_template_conditions`: cambió el falso positivo por un falso negativo

Esto es lo que preguntabas, y la respuesta es que **sólo movió el problema**, además en la
dirección que engaña más.

**Llamada** (reaplicar la condición que la cabecera ya tenía):

```json
{"post_id":99,"condiciones":["include/general"]}
```

**Respuesta:**

```json
{"id":99,"condiciones":["include/general"],
 "cache":"regenerada por Elementor Pro","regenerada":true,
 "confirmado":false,
 "efecto":"La condición está guardada pero Elementor Pro TODAVÍA NO la aplica en \"header\".
           Su caché sigue vieja: abre la plantilla en el editor y guarda, o vacía la caché de
           Elementor. Hasta entonces el visitante no verá esta plantilla."}
```

**La cabecera se está dibujando en todas las páginas del sitio.** Comprobado justo después,
por HTTP, en `/`, `/planes-de-mantenimiento/` y `/servicios/mantenimiento-de-jardines/`:
`data-elementor-type="header" data-elementor-id="99"` en las tres, y el pie también.

Y lo mismo con el pie (#100): `confirmado:false`, mismo texto, y el pie se dibuja igual.
O sea: **`confirmado` da false siempre**, al menos desde una llamada del servidor.

**La causa, con la comprobación hecha:**

```php
$cm->get_cache()->get_by_location('header')   → {"99":["include\/general"]}   ✅ correcto
$loc->get_documents_for_location('header')    → []                            ← el oráculo
```

`get_documents_for_location()` devuelve `[]` aunque la caché esté bien y aunque el sitio esté
dibujando la plantilla. Lo probé también **simulando una consulta principal de la portada**
(`WP_Query(['page_id'=>103])`, `is_main_query`, `the_post()`): sigue devolviendo `[]`. Ese
método no es un oráculo válido fuera de una petición de front de verdad, porque depende del
contexto de la petición, y una llamada MCP nunca lo es.

Es, con precisión, **el mismo método que usé yo en la fase 3 para diagnosticar el fallo
original**. Entonces me sirvió porque el dato estaba mal de verdad y el `[]` coincidía con la
realidad. Ahora que el dato está bien, el `[]` miente.

**Y la respuesta se contradice a sí misma:** `"cache":"regenerada por Elementor Pro"` y
`"regenerada":true` conviven en el mismo objeto con `"confirmado":false`. Si me creo lo
primero, está hecho; si me creo lo segundo, no. Un consumidor automático no puede decidir.

**Coste de este falso negativo:** me mandó a arreglar algo que no estaba roto. Gasté cuatro
llamadas en comprobar que el sitio estaba bien, y el `efecto` le pide a la persona que abra
el editor y guarde a mano, trabajo que no hace falta. **Un falso negativo es más caro que el
falso positivo anterior**: el falso positivo te deja el sitio roto y lo descubres mirando; el
falso negativo te hace romper lo que funciona.

**Cómo lo comprobaría yo**, por orden de fiabilidad:

1. Contra la caché de condiciones: `get_cache()->get_by_location($ubicacion)` y mirar si el
   `post_id` está dentro. En este caso da la respuesta correcta.
2. Mejor todavía y a prueba de contexto: una petición HTTP real a una página que deba
   cumplir la condición y buscar `data-elementor-type="<ubicacion>"` en el HTML. Es lo que
   hago yo y es lo único que contesta de verdad a la pregunta «¿lo ve el visitante?».

Y mientras tanto, si `confirmado` no se puede calcular con seguridad, es mejor no devolverlo
que devolverlo mal. La lección de todo este registro es que un dato afirmado con confianza y
equivocado cuesta más que un dato ausente.

## ✅ `option_update`: la descripción ya no miente

Anotado en el cierre como el caso de «una puerta que promete y no existe». La descripción
nueva dice: *«El título y la descripción del sitio NO están bloqueados: se pueden cambiar
desde aquí»*, y enumera lo que sí lo está. Cierra el caso.

## Lo que NO he podido comprobar en esta ronda, y lo digo en vez de suponerlo

- **`elementor_template_apply` (punto 2).** No hay ninguna plantilla que aplicar: las 33
  páginas están hechas. Aplicar una encima destruiría contenido real. Sin trabajo que lo
  justifique, no lo pruebo.
- **`cf7_update` (punto 4).** Los cuatro formularios están correctos y no hay cambio
  pendiente. Probarlo exigiría escribir y revertir.
- **`seo_update` (punto 5).** El aviso salta cuando el asistente de Rank Math está sin
  terminar, y lo terminé en la fase 5. Aquí ya no puede saltar, así que una llamada no
  probaría nada.

Los tres se prueban solos en el próximo encargo que los toque.

## El arreglo del menú que traías

El panel arrancaba a media altura de la cabecera y partía el logo. Causa:

```css
.elementor-widget-n-menu .e-n-menu{ position: relative }
.e-n-menu-wrapper{ top: 100% }
```

El panel se ancla al widget del hamburguesa, que está centrado verticalmente dentro de la
cabecera. `top:100%` de un icono centrado cae a media cabecera, encima del logo.

Lo arreglé **sin números mágicos**, que era la trampa de la fase 19: en móvil y tableta,
`.e-n-menu` pasa a `position: static` y el panel se ancla a la tarjeta de la cabecera, que
marqué `position: relative`. Así `top:100%` es el borde inferior de la cabecera, mida lo que
mida. Si mañana cambia el logo, sigue cuadrando.

Las dos escrituras fueron la llamada única a `cambios` de arriba.

**Sin verificar visualmente**, como siempre. Tercera corrección a ciegas de este menú.
