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
