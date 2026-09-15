# Altorre Maquinaria — encargo de contenido

> **Todos los datos de este documento son ficticios.** Empresa, personas, teléfonos,
> direcciones, tarifas y testimonios están inventados para montar el sitio de demostración
> de mcp2.webs27.online con el kit RentForge. Cualquier parecido con una empresa real es
> casualidad.
>
> **Este documento es la fuente única.** Si un dato aparece aquí, se escribe así en el sitio
> y no se inventa otra versión. (En la fase 9 aparecieron dos direcciones distintas para la
> misma oficina precisamente por no tener esta regla.)

## La empresa

**Altorre Maquinaria, S.L.** · altorremaquinaria.es
Alquiler de maquinaria de obra pública y construcción, con y sin operador.

Fundada en **2009** en Zaragoza por **Jesús Bandrés**, jefe de taller de una constructora que
se cansó de esperar máquinas que no llegaban, y **Nuria Olivé**, que llevaba la administración.
Empezaron con dos retroexcavadoras y una góndola de segunda mano.

Hoy, 2026: **17 años**, **54 personas**, **9 mecánicos en plantilla**, **6 transportistas con
góndola propia**, **214 máquinas** en flota, **+1.900 obras servidas**.

**Sedes** (direcciones fijadas, no inventar otras):

| Sede | Dirección |
|---|---|
| Zaragoza (central) | Plataforma Logística PLAZA, calle Alaún 24, 50197 Zaragoza |
| Pamplona | Polígono Landaben, calle F nave 12 |
| Lleida | Polígono El Segre, calle 401 nave 7 |
| Teruel | Base móvil |

**Cobertura:** Aragón, Navarra, La Rioja, Cataluña occidental, Soria y Guadalajara.

**Contacto:** +34 976 500 180 · WhatsApp +34 640 22 18 90 · alquiler@altorremaquinaria.es ·
averías: averias@altorremaquinaria.es
**Horario:** L–V de 7:00 a 19:00 · sábados de 8:00 a 13:00 · entregas a obra desde las 6:30.

**Lema:** «La máquina, en obra y funcionando.»

**Los cuatro compromisos** (se repiten por todo el sitio, siempre con estos números):
1. Presupuesto cerrado en **menos de 2 horas laborables**.
2. Entrega en **24 horas** en Zaragoza, Pamplona y Lleida; **48 horas** en el resto.
3. Asistencia en obra en **menos de 4 horas**.
4. **Máquina de sustitución** si la avería pasa de 8 horas, sin coste.

## Tono

Directo y de oficio. Se habla de usted. Quien lee es un jefe de obra o un autónomo con una
máquina parada, no un comprador de servicios. Nada de «soluciones integrales» ni «partner
estratégico». Frases cortas. Números concretos. Se usan las palabras del sector: góndola,
implemento, cazo, martillo hidráulico, horómetro, ITV de maquinaria, marcado CE, obra civil,
movimiento de tierras, a pie de obra.

## Identidad

| | |
|---|---|
| Primario (fondos oscuros) | `#1F2328` |
| Acento | `#F4B400` |
| Texto | `#6B6B6B` |
| Crema (fondos de sección) | `#FEF7E5` |
| Titulares | Anton |
| Texto corrido | Inter |
| Logotipo | adjunto **476** — icono «A» de celosía en ámbar, «ALTORRE» en blanco, «MAQUINARIA» en ámbar. Va en cabecera, pie y panel de menú móvil |

**Pendiente:** favicon. El sitio no tiene (`site_icon` = 0). Hace falta un cuadrado de
512×512 con sólo la «A» sobre `#1F2328`.

## Las ocho familias de máquina

Cada una es una ficha en `/maquinaria/{slug}/`.

| Familia | slug | id | Desde | Nota de venta |
|---|---|---|---|---|
| Excavadoras de cadenas | `excavadoras` | 202 | 240 €/día | De 1,8 t a 25 t. Martillo y cazo de limpieza incluidos en tarifa semanal. |
| Bulldozers | `bulldozers` | 203 | 480 €/día | D5 y D6. Para desmonte y explanación de parcelas grandes. |
| Cargadoras de ruedas | `cargadoras` | 204 | 290 €/día | De 1,5 a 3,5 m³. Acopio, carga de camión y áridos. |
| Retroexcavadoras mixtas | `retroexcavadoras` | 205 | 180 €/día | La máquina de todo. Zanja, carga y desplazamiento por carretera. |
| Grúas | `gruas` | 206 | 620 €/día | Autopropulsadas de 25 a 60 t. Siempre con operador y plan de izado. |
| Carretillas elevadoras | `carretillas` | 207 | 70 €/día | Diésel y todoterreno, de 2,5 a 7 t. Obra, nave y logística. |
| Motoniveladoras | `motoniveladoras` | 208 | 420 €/día | Caminos, viales y refino de explanada. Con GPS de nivelación. |
| Compactadoras y rodillos | `compactadoras` | 209 | 110 €/día | Tándem, monocilíndrico y bandeja. Zanja, vial y urbanización. |

**Estructura de cada ficha, tal y como está montada:** titular y precio de entrada · «En
resumen» con dos etiquetas (tipo de tracción y rango) · «Qué va incluido» · «Para qué sirve
de verdad» · «Ideal para» · «Qué se cobra aparte» · los números de la casa · cómo funciona ·
tres entradas del blog · llamada final.

> El encargo original pedía además **«una obra real donde se usó»** en cada ficha. **No está
> escrito.** Es lo único del encargo que quedó fuera, y haría falta una historia por familia.

## Cómo funciona (tres pasos)

1. **Cuéntenos la obra, no la máquina.** Metros cúbicos, acceso, plazo. Si nos dice qué tiene
   que hacer, le decimos qué máquina le sale más barata.
2. **Presupuesto cerrado en menos de 2 horas.** Con transporte, seguro y horas incluidas. Lo
   que le decimos es lo que paga.
3. **La llevamos y la recogemos.** Góndola propia. Entrega a pie de obra desde las 6:30.

En el sitio se presenta en cuatro pasos, porque el molde del kit trae cuatro: se añadió
**«A trabajar»** al final, con el compromiso de la máquina de sustitución.

## Tarifas

Tres modalidades. Precio por día sin IVA, transporte aparte según distancia.

- **Por días** — tarifa diaria, mínimo un día, 8 horas de horómetro.
- **Por semanas** — 20 % menos que la suma de días. 40 horas de horómetro. *La más pedida.*
- **Larga duración (mes o más)** — 35 % menos, mantenimiento periódico y cambios de aceite
  incluidos, y máquina de sustitución garantizada.

**Lo que entra en todas las tarifas:** seguro de la máquina · revisión, marcado CE e ITV al
día · asistencia en obra en menos de 4 horas · máquina de sustitución si la avería pasa de 8.

**Lo que no entra:** combustible, operador (salvo grúas, que siempre lo llevan), transporte
fuera del radio de 30 km de la sede, implementos especiales y las horas de horómetro que
pasen del cupo. Todo eso va desglosado en el presupuesto, nunca de sorpresa.

## Sectores

Construcción y edificación · Obra civil y viales · Movimiento de tierras · Demolición y
reciclaje · Energías renovables (parques eólicos y plantas solares) · Industria y logística ·
Agrícola y ganadero · Administraciones públicas y ayuntamientos.

> El molde del kit sólo traía **siete** casillas. Se duplicó una en portada y otra en
> Nosotros para que cupieran las ocho.

## Equipo

- **Jesús Bandrés** — Socio fundador y director técnico. 30 años en taller. Es quien decide
  qué máquina le mandamos cuando la que pide no es la que necesita.
- **Nuria Olivé** — Socia fundadora y directora de administración. Lleva los contratos, los
  seguros y las licitaciones.
- **Iván Castejón** — Jefe de taller. Nueve mecánicos a su cargo y el compromiso de las
  4 horas de asistencia.
- **Marta Sanz** — Responsable de alquiler y planificación. La que le coge el teléfono y la
  que sabe si hay máquina libre el martes.

## Testimonios

Los tres primeros son los del encargo; los tres últimos se añadieron para llenar el carrusel,
que muestra seis. Van en portada, Tarifas, Nosotros, Reservar y Opiniones.

> «Pedí una excavadora de 20 toneladas y Jesús me dijo que con una de 14 y un martillo hacía
> el mismo trabajo por la mitad. Tenía razón. Eso no lo hace quien sólo quiere alquilar.»
> **Carlos Membrado** — Jefe de obra, Construcciones Bardenas (Tudela)

> «Se nos rompió el rodillo un jueves a las seis de la tarde con el hormigón pedido para el
> viernes. A las nueve de la noche teníamos otro en la obra. Llevamos seis años con ellos.»
> **Silvia Ferrer** — Gerente, Excavaciones Ferrer e Hijos (Fraga)

> «Trabajamos con plazos de parque eólico, que no perdonan. En tres campañas no hemos tenido
> una sola máquina parada más de medio día.»
> **Óscar Lahoz** — Responsable de mantenimiento, Parque Eólico La Muela

> «Pedimos una motoniveladora con GPS un martes por la tarde y el miércoles a las siete
> estaba en el vial. Eso es todo lo que le pido a quien me alquila.»
> **Ana Belén Used** — Jefa de producción, Viales del Ebro (Zaragoza)

> «Soy uno solo con una retro. Me tratan igual que a una constructora de doscientos. El
> presupuesto me llega en una hora y sin letra pequeña.»
> **Fernando Aínsa** — Autónomo de movimiento de tierras (Barbastro)

> «El rodillo se averió a media compactación. A las tres horas y media había un mecánico en
> la obra. No perdimos el día.»
> **Rubén Latorre** — Jefe de obra, Urbanizaciones Cinca (Monzón)

## Estructura del sitio

| Página | id | URL | Plantilla del kit |
|---|---|---|---|
| Inicio | 189 | `/` | Home |
| Maquinaria | 190 | `/maquinaria/` | Services |
| 8 fichas de familia | 202–209 | `/maquinaria/{slug}/` | Services Detail |
| Tarifas | 191 | `/tarifas/` | Pricing |
| Reservar máquina | 192 | `/reservar/` | Book Equipment |
| Nosotros | 193 | `/nosotros/` | About |
| Equipo | 194 | `/equipo/` | Team Detail |
| Opiniones | 195 | `/opiniones/` | Testimonials |
| Blog | 196 | `/blog/` | Blog (es la página de entradas) |
| Contacto | 197 | `/contacto/` | Contact |
| Aviso legal | 198 | `/aviso-legal/` | — |
| Política de privacidad | 199 | `/politica-de-privacidad/` | — |
| Política de cookies | 200 | `/politica-de-cookies/` | — |
| Condiciones de alquiler | 201 | `/condiciones-de-alquiler/` | — |

**Plantillas:** cabecera 161 · pie 163 · error 404 185 · kit de estilos 17.

**Menú «Principal»** (id 32): 16 elementos, con las ocho fichas colgando de Maquinaria.

**Lo que ya había en el sitio y qué se hizo con ello:**

- **WooCommerce** (Tienda 36, Carrito 37, Finalizar compra 38, Mi cuenta 39): **se quedan
  publicadas y funcionando, pero fuera del menú.** Borrarlas rompe WooCommerce, que está
  activo. «Mi cuenta» se enlaza desde la barra superior como «Área de cliente».
- **Páginas de prueba del plugin** (22 y 24), entrada de prueba (69), «Hello world» (1),
  «Privacy Policy» (3) y la política de devoluciones (40): **a la papelera**.

## Formularios (Contact Form 7)

| Formulario | id | Campos | Dónde está |
|---|---|---|---|
| Contacto | 57 | nombre, empresa, teléfono, correo, mensaje | — |
| Pedir presupuesto | 246 | nombre, empresa, teléfono, correo, población, familia, fecha, duración, operador, trabajo | Reservar (192) y Contacto (197) |
| Reserva rápida | 247 | teléfono, qué necesita | Portada (189) |

## Blog

Categorías: **Guías de máquina** · **Normativa y seguridad** · **Mantenimiento** ·
**Obra y terreno**.

Cuatro artículos escritos, uno por categoría:

| Artículo | id | Categoría |
|---|---|---|
| Qué excavadora pedir según los metros cúbicos que tenga que mover | 295 | Guías de máquina |
| Qué papeles tiene que llevar la máquina que llega a su obra | 296 | Normativa y seguridad |
| El horómetro no es el cuentakilómetros: cómo se cuentan las horas de un alquiler | 297 | Mantenimiento |
| Yesos, gravas y arcillas: cómo el terreno del valle del Ebro decide la máquina | 298 | Obra y terreno |

El listado de las tres últimas entradas sale en portada, Tarifas, Opiniones y las ocho fichas.

## SEO

Título, meta descripción y palabras clave escritos para las **21 páginas y las 4 entradas**.
Rank Math emite las etiquetas, el schema y el `og:`. El grafo de conocimiento está como
`HomeAndConstructionBusiness` con el nombre, la dirección, el teléfono, el correo, el
logotipo y el horario reales de la ficha.

## Antes de publicar esto de verdad, hay que sustituir

Todo lo de este bloque es inventado y está escrito en el sitio:

| Dato | Valor ficticio | Dónde aparece |
|---|---|---|
| NIF | `B-99123456` | aviso legal, política de privacidad |
| Registro mercantil | tomo 3.412, folio 118, hoja Z-71.905 | aviso legal |
| Direcciones de las tres sedes | ver arriba | contacto, pie, legales, schema |
| Teléfonos y correos | 976 500 180 · 640 22 18 90 · las dos direcciones de correo | todo el sitio |
| Perfiles sociales | LinkedIn `company/altorre-maquinaria` · Facebook `altorremaquinaria` · YouTube `@altorremaquinaria` · WhatsApp `wa.me/34640221890` | cabecera y pie |
| Testimonios y clientes | los seis nombres y sus empresas | cinco páginas |
| Nombres de los logotipos de cliente | Construcciones Bardenas · Excavaciones Ferrer e Hijos · Parque Eólico La Muela · Viales del Ebro · Urbanizaciones Cinca · Áridos del Gállego · Ayuntamiento de La Almunia | el globo `title=` de los 28 carruseles |
| Logotipos de cliente | los del kit, marcas inventadas | portada, Maquinaria, Tarifas, Reservar, Nosotros, fichas |
| Tarifas | los ocho precios «desde» | Tarifas y las ocho fichas |
| Cifras de la casa | 214 máquinas, 54 personas, 9 mecánicos, 6 góndolas, 1.900 obras | portada, Maquinaria, Nosotros, fichas |
| Fotografías | las del kit RentForge, ya alojadas en el sitio | todo el sitio |

Las **fotografías** merecen mención aparte: se descargaron del servidor del autor del kit y
ahora son locales, pero **son fotos de banco del kit, no de la empresa**. La licencia de uso
va con el kit; si el sitio deja de ser una demostración, hay que comprobarla o sustituirlas.
