# Especificación técnica — Plataforma SEO interna (Ingenio)

> **Cómo usar este documento:** pégalo completo en Claude Code como contexto inicial del proyecto, o guárdalo como `SPEC.md` en la raíz del repo y arranca la sesión con: *"Lee SPEC.md. Vamos a construir esto por fases. Empezamos por la Fase 1, sección por sección. No escribas código de fases posteriores. Antes de cada módulo, propón el plan y espera mi confirmación."*

---

## 0. Contexto del proyecto

Somos una agencia de marketing digital con ~14 años de operación y más de 600 proyectos web/SEO ejecutados, con base en Honduras y clientes en Centroamérica y el mercado latinoamericano en general.

**Problema:** las licencias de suites SEO (Semrush, Ahrefs) escalan por asiento y por proyecto, no por uso real. Con nuestro volumen de clientes, el costo por licencia se vuelve el rubro dominante del servicio SEO, y además la cobertura de datos de esas suites en mercados centroamericanos es débil.

**Solución:** construir una plataforma propia que consuma datos SEO directamente de **DataForSEO** (modelo pago-por-uso), los almacene históricamente en nuestra infraestructura, y los presente en un panel multi-cliente con reportes de marca blanca.

**Objetivo de negocio del MVP:** reemplazar el flujo mensual de reportería de posiciones para clientes, que hoy es lo que más consume licencias y horas manuales.

**No-objetivo:** construir un competidor de Semrush. No vamos a vender esto como SaaS público (al menos no en esta fase). Es una herramienta interna con portal de cliente.

---

## 1. Stack técnico

Elegido para alinearse con el conocimiento existente del equipo (PHP, MySQL, entorno LAMP) y minimizar el costo de mantenimiento.

| Capa | Tecnología | Notas |
|---|---|---|
| Lenguaje | PHP 8.3+ | Tipado estricto (`declare(strict_types=1)`) en todo el código |
| Framework | Laravel 12 (o la última LTS estable) | Verificar versión actual al iniciar |
| Base de datos | MySQL 8.0 / MariaDB 10.11+ | InnoDB, `utf8mb4_unicode_ci` |
| Cache + colas | Redis 7 | Obligatorio, no usar driver `database` para colas |
| Gestor de colas | Laravel Horizon | Panel de monitoreo de jobs |
| Frontend | Livewire 3 + Alpine.js + Tailwind CSS | Evita el salto a SPA; menos superficie de mantenimiento |
| Panel admin | Filament 3 (opcional, evaluar) | Acelera CRUD interno; **no** usar para el portal de cliente |
| Gráficas | Chart.js o ApexCharts | Vía CDN o npm, sin build pesado |
| PDF | Spatie Browsershot (Puppeteer) o DomPDF | Browsershot da mejor fidelidad para reportes con gráficas |
| Cliente HTTP | `Illuminate\Support\Facades\Http` (Guzzle) | No usar cURL crudo |
| Tests | Pest PHP | Feature tests obligatorios en la capa DataForSEO |
| Análisis estático | Larastan (PHPStan nivel 6+) | En CI |
| Formato | Laravel Pint | En CI |

**Requisitos de despliegue:** el servidor será **independiente** del VPS de producción con WHM/cPanel que aloja los sitios de clientes. No compartir infraestructura. Ver sección 12.

---

## 2. Arquitectura general

```
┌─────────────────────────────────────────────────────────┐
│  UI (Livewire)                                          │
│  ├── Panel interno (equipo Ingenio)                     │
│  └── Portal de cliente (solo lectura, marca blanca)     │
├─────────────────────────────────────────────────────────┤
│  Capa de Aplicación                                     │
│  ├── Actions (una clase = una operación de negocio)     │
│  ├── Jobs en cola (todo lo que toca API externa)        │
│  └── Scheduler (Laravel Task Scheduling)                │
├─────────────────────────────────────────────────────────┤
│  Capa de Integración — DataForSEO                       │
│  ├── DataForSeoClient (HTTP, auth, reintentos)          │
│  ├── Endpoints tipados por servicio                     │
│  ├── CostLedger (contabilidad de gasto por request)     │
│  └── ResponseCache (evita pagar dos veces lo mismo)     │
├─────────────────────────────────────────────────────────┤
│  Persistencia                                           │
│  ├── MySQL (histórico, el activo más valioso)           │
│  └── Redis (cache caliente + colas)                     │
└─────────────────────────────────────────────────────────┘
```

**Principio rector #1:** cada llamada a DataForSEO cuesta dinero real. La arquitectura entera debe estar diseñada alrededor de *no repetir llamadas innecesarias*. Toda respuesta se persiste íntegra antes de procesarse.

**Principio rector #2:** DataForSEO no guarda nuestro historial. El valor acumulado de la plataforma **es nuestra base de datos**. El esquema y los respaldos son críticos, no accesorios.

---

## 3. Integración con DataForSEO

### 3.1 Autenticación

- HTTP Basic Auth. Credenciales = `login:password` codificadas en base64.
- Base URL: `https://api.dataforseo.com/v3/`
- Guardar en `.env` como `DATAFORSEO_LOGIN` y `DATAFORSEO_PASSWORD`. **Nunca** en el repositorio.
- Encriptar en base de datos si alguna vez se soportan credenciales por tenant.

> ⚠️ **Instrucción para Claude Code:** los nombres exactos de endpoints, parámetros y estructura de respuesta deben verificarse contra `https://docs.dataforseo.com/v3/` antes de implementar cada módulo. La documentación es la fuente de verdad; lo que sigue en este spec es la arquitectura, no la firma exacta de la API. No inventes campos.

### 3.2 Modos de ejecución

DataForSEO ofrece tres modos con costos distintos. Implementar los tres y elegir por caso de uso:

1. **Standard (task_post → task_ready → task_get)** — el más barato. Asíncrono, resultados en minutos u horas. **Este es el modo por defecto para todo el rank tracking programado.**
2. **Live** — respuesta inmediata en un solo request. Más caro. Usar solo para consultas interactivas que dispara un usuario en pantalla.
3. **Priority** — Standard con prioridad elevada. Costo intermedio.

**Regla de negocio:** el modo Live debe requerir confirmación explícita en la UI mostrando el costo estimado. Nada en el sistema debe poder disparar Live de forma automática o en bucle.

### 3.3 Recepción de resultados: postback vs polling

Implementar **postback** (DataForSEO hace POST del resultado a nuestra URL cuando está listo) en lugar de hacer polling contra `tasks_ready`. Es más barato en requests y más simple operativamente.

- Endpoint: `POST /webhooks/dataforseo/{tipo}`
- Seguridad: token secreto en la query string + validación de que el `task_id` existe en nuestra base y está en estado `pending`.
- El webhook debe ser **idempotente**: si llega dos veces el mismo `task_id`, no duplicar registros.
- El webhook **no procesa**: guarda el payload crudo y despacha un job. Debe responder 200 en menos de 500ms.
- Implementar también un job de reconciliación (`ReconcilePendingTasks`) que corra cada hora y recupere vía `tasks_ready` cualquier tarea que lleve más de N horas en `pending` (los postbacks se pierden).

### 3.4 Manejo de errores

- `status_code` 20000 = éxito. Todo lo demás requiere manejo explícito.
- Distinguir errores **transitorios** (5xxxx, timeouts, 429) de **permanentes** (4xxxx: parámetros inválidos, ubicación inexistente).
- Transitorios: reintento con backoff exponencial (3 intentos: 1s, 5s, 25s).
- Permanentes: no reintentar. Marcar la tarea como `failed`, guardar el mensaje, notificar.
- Registrar **todo** request y response en una tabla `dataforseo_requests` (ver esquema). Sin excepción. Es la única forma de auditar gasto y depurar.

### 3.5 Control de costos — módulo crítico

Este es el módulo que hay que construir bien desde el día uno, no después.

- Cada respuesta de DataForSEO incluye un campo `cost`. Persistirlo siempre.
- Tabla `cost_ledger` con: fecha, tipo de endpoint, proyecto asociado, cliente asociado, costo, request_id.
- **Presupuestos configurables** a tres niveles: global mensual, por cliente, por proyecto.
- **Circuit breaker:** si el gasto del mes supera el umbral configurado, se detiene la creación de nuevas tareas y se notifica. Debe requerir acción humana para reanudar.
- Dashboard de costos: gasto del mes, proyección a fin de mes, top 10 proyectos por consumo, costo promedio por reporte generado.
- Antes de disparar cualquier lote grande (ej. refrescar 5,000 keywords), la UI muestra **estimación de costo** y pide confirmación.
- Consultar el saldo de la cuenta vía el endpoint de `user_data` una vez al día y alertar bajo un umbral.

### 3.6 Cache

- Cache de respuestas en Redis con TTL por tipo de dato:
  - Volumen de búsqueda: 30 días (Google actualiza mensualmente)
  - Datos de dominio / rank overview: 7 días
  - SERP: **no cachear** — el propósito es capturar el cambio
  - Backlinks: 7 días
  - Auditoría on-page: no aplica (bajo demanda)
- Clave de cache = hash de (endpoint + parámetros normalizados y ordenados).
- Nunca servir cache para datos que el usuario está pidiendo explícitamente refrescar.

### 3.7 Límites de la API

- Verificar en la documentación el límite de llamadas por minuto y el número máximo de tareas por POST (el POST acepta arreglos: **agrupar tareas siempre**, no enviar una por request).
- Implementar rate limiting propio en el cliente HTTP con Redis, por debajo del límite oficial.
- Agrupar en lotes: al programar 1,000 keywords, enviar N requests con el máximo de tareas permitido por request, no 1,000 requests.

---

## 4. Modelo de datos

Esquema propuesto. Ajustar nombres a convención Laravel (plural, snake_case).

### Núcleo

**`users`** — estándar Laravel + `role` (admin, manager, analyst, client), `client_id` nullable.

**`clients`** — id, name, slug, logo_path, primary_color, contact_email, is_active, monthly_budget_cap, timestamps.
> El branding vive aquí: es lo que alimenta los reportes de marca blanca.

**`projects`** — id, client_id, name, domain, target_type (domain/subdomain/url), default_location_code, default_language_code, search_engine (google/bing), tracking_frequency (daily/weekly/monthly), is_active, timestamps.

**`locations`** — espejo local del catálogo de ubicaciones de DataForSEO. Se sincroniza con un comando artisan (`dataforseo:sync-locations`), no se consulta en vivo. Campos: location_code, location_name, location_name_canonical, country_iso_code, location_type, parent_code.
> Importante para nosotros: Honduras, Guatemala, El Salvador, Nicaragua, Costa Rica, Panamá, y ciudades principales. Verificar qué granularidad soporta DataForSEO para cada uno **antes** de prometerle SEO local a un cliente.

**`languages`** — espejo del catálogo de idiomas.

### Rank tracking

**`keywords`** — id, project_id, keyword, location_code, language_code, tags (json), search_volume, cpc, competition, volume_updated_at, is_active, timestamps.
> Índice único compuesto en (project_id, keyword, location_code, language_code).

**`rankings`** — id, keyword_id, checked_at (date), position, previous_position, url, serp_features (json), estimated_traffic, is_featured_snippet, is_local_pack, timestamps.
> **Tabla de mayor crecimiento del sistema.** Con 100 clientes × 50 keywords × diario = 150k filas/mes. Índices en (keyword_id, checked_at). Planificar particionamiento por rango de fecha o política de archivado desde el diseño inicial.

**`serp_snapshots`** — id, keyword_id, captured_at, raw_response (json/longtext o ruta a S3), top_results (json).
> Guardar el SERP completo permite análisis retrospectivo de competidores sin volver a pagar. Evaluar comprimir o mover a almacenamiento de objetos si crece.

**`serp_competitors`** — id, project_id, domain, keywords_overlap, avg_position, visibility_score, calculated_at.
> Derivado de los snapshots, calculado localmente. Costo cero.

### Investigación de keywords

**`keyword_research_sessions`** — id, project_id, user_id, seed_keyword, source_endpoint, cost, created_at.

**`keyword_ideas`** — id, session_id, keyword, search_volume, cpc, competition, difficulty, intent, is_selected.
> `is_selected` permite promover ideas a `keywords` para seguimiento.

### Backlinks

**`backlink_summaries`** — id, project_id, captured_at, total_backlinks, referring_domains, referring_ips, domain_rank, broken_backlinks, raw (json).

**`backlinks`** — id, project_id, source_url, source_domain, target_url, anchor, dofollow, first_seen, last_seen, is_lost, domain_rank.

**`referring_domains`** — id, project_id, domain, backlinks_count, first_seen, domain_rank, is_lost.

### Auditoría técnica

**`site_audits`** — id, project_id, task_id, status, started_at, completed_at, pages_crawled, onpage_score, cost.

**`audit_issues`** — id, audit_id, url, issue_type, severity (critical/warning/notice), message, details (json).
> El catálogo de tipos de issue debe vivir en un enum PHP con etiquetas en español, no en strings sueltos.

### Infraestructura DataForSEO

**`dataforseo_tasks`** — id, task_id (el de DataForSEO), endpoint, taskable_type + taskable_id (polimórfico), status (pending/completed/failed), payload_sent (json), payload_received (longtext), cost, posted_at, completed_at, error_message, retry_count.

**`dataforseo_requests`** — log de auditoría de cada llamada HTTP: método, URL, duración_ms, http_status, api_status_code, cost, created_at.

**`cost_ledger`** — id, date, client_id, project_id, endpoint_group, cost, task_reference.

### Reportes

**`report_templates`** — id, client_id (nullable = plantilla global), name, sections (json), branding_overrides (json).

**`reports`** — id, project_id, template_id, period_start, period_end, status, file_path, generated_by, sent_at, created_at.

---

## 5. Módulos funcionales

### Fase 1 — MVP (construir solo esto primero)

**5.1 Gestión de clientes y proyectos**
- CRUD de clientes con branding (logo, color primario).
- CRUD de proyectos vinculados a cliente.
- Asignación de usuarios internos a proyectos.

**5.2 Gestión de keywords**
- Alta individual y por importación CSV/pegado masivo.
- Asignación de ubicación e idioma por keyword (no solo por proyecto — un cliente hondureño puede querer rankear en Honduras y en Guatemala).
- Etiquetado libre para agrupar (marca, transaccional, informacional, por servicio, por sucursal).
- Enriquecimiento de volumen de búsqueda bajo demanda (endpoint de Keywords Data), con confirmación de costo.

**5.3 Rank tracking**
- Job programado según la frecuencia del proyecto.
- Agrupación de tareas en lotes por eficiencia.
- Recepción por postback, procesamiento en cola.
- Detección de SERP features: featured snippet, local pack, People Also Ask, imágenes, video, AI Overview.
- Cálculo de posición anterior y delta.
- Vista de tabla: keyword, posición actual, cambio, URL rankeando, volumen, features.
- Filtros por etiqueta, por rango de posición, por movimiento.
- Gráfica de evolución por keyword y de visibilidad agregada del proyecto.

**5.4 Reportería de marca blanca**
- Generación de PDF con logo y color del cliente.
- Secciones: resumen ejecutivo, evolución de visibilidad, tabla de posiciones, top ganancias, top pérdidas, keywords nuevas en top 10.
- Comparativa contra período anterior.
- Generación manual y programada (mensual automática).
- Envío por correo al contacto del cliente.

**5.5 Portal de cliente**
- Login separado, acceso solo a sus proyectos, solo lectura.
- Vista simplificada: no mostrar costos, no mostrar nada de DataForSEO, no mencionar la fuente de datos.
- Descarga de reportes históricos.

**5.6 Control de costos** (ver 3.5 — no es opcional en el MVP)

### Fase 2

- Análisis de competidores derivado de los SERP snapshots.
- Investigación de keywords (DataForSEO Labs).
- Alertas: caída de más de N posiciones, salida del top 10, entrada al top 3, pérdida de featured snippet.
- Integración con Google Search Console (OAuth propio, datos gratis).

### Fase 3

- Backlinks: perfil, dominios referentes, enlaces perdidos, comparativa contra competidores.
- Auditoría técnica on-page.
- Monitoreo de reseñas y Google Business Profile (relevante para nuestros clientes de SEO local).
- Monitoreo de menciones en LLMs (GEO) — endpoint de AI Optimization.

---

## 6. Jobs y programación

| Job | Frecuencia | Descripción |
|---|---|---|
| `ScheduleRankTrackingTasks` | Diario 02:00 | Recorre proyectos activos, agrupa keywords según frecuencia, publica tareas en lote |
| `ProcessDataForSeoPostback` | Por evento | Procesa el payload recibido, escribe rankings y snapshot |
| `ReconcilePendingTasks` | Cada hora | Recupera tareas huérfanas vía tasks_ready |
| `CalculateProjectVisibility` | Diario 06:00 | Métricas derivadas, sin costo de API |
| `DetectRankingAlerts` | Diario 06:30 | Evalúa reglas de alerta y notifica |
| `GenerateScheduledReports` | Día 1 de cada mes | Genera y envía reportes mensuales |
| `SyncAccountBalance` | Diario | Consulta saldo y alerta si está bajo |
| `ArchiveOldSerpSnapshots` | Semanal | Comprime o mueve snapshots de más de N meses |

Colas separadas por prioridad: `high` (interactivo), `default` (procesamiento), `low` (reportes, archivado). Configurar Horizon con supervisores distintos por cola.

---

## 7. Convenciones de código

- `declare(strict_types=1)` en todos los archivos.
- Una clase `Action` por operación de negocio (`App\Actions\Rankings\ScheduleKeywordCheck`). Nada de lógica de negocio en controladores ni en modelos.
- DTOs para todo lo que entra y sale de DataForSEO (`spatie/laravel-data`). **Nunca** pasar arreglos asociativos crudos entre capas.
- Enums de PHP 8.1 para estados, severidades, tipos de endpoint. Nada de strings mágicos.
- Form Requests para validación.
- Nombres de tablas, columnas, clases, variables y comentarios en **inglés**. Textos de interfaz en **español**, vía archivos de traducción (`lang/es/`).
- Migraciones con `down()` funcional siempre.
- Nada de `env()` fuera de archivos de configuración.

---

## 8. Tests

Prioridad de cobertura, en este orden:

1. **Capa DataForSEO** — mockear respuestas con `Http::fake()`. Cubrir: éxito, error transitorio con reintento, error permanente, respuesta malformada, timeout. **Ningún test debe pegarle a la API real.**
2. **Cálculo de costos** — que el ledger sume correcto y que el circuit breaker corte.
3. **Idempotencia del webhook** — mismo payload dos veces = un solo registro.
4. **Procesamiento de rankings** — que un payload de SERP real (fixture guardado) produzca las filas esperadas.
5. **Generación de reportes** — que el PDF se genere con el branding correcto del cliente.

Guardar fixtures reales de respuestas de DataForSEO en `tests/Fixtures/dataforseo/`. Capturarlos una vez con la cuenta real y reutilizarlos siempre.

---

## 9. Seguridad

- Credenciales de DataForSEO solo en `.env`, nunca en base de datos sin encriptar, nunca en el repo.
- El portal de cliente y el panel interno deben tener **separación estricta de autorización** (Policies de Laravel). Un usuario cliente no debe poder acceder a rutas internas ni ver datos de otros clientes bajo ninguna combinación de parámetros.
- Rate limiting en las rutas que disparan llamadas de pago.
- Webhook de DataForSEO protegido por token y validación de origen.
- Logs sin credenciales ni PII.
- 2FA para usuarios internos.

---

## 10. Interfaz — lineamientos

- Español de Latinoamérica en toda la interfaz.
- Densidad de información alta en el panel interno (somos operadores, no visitantes); interfaz limpia y simple en el portal de cliente.
- Cada acción que cueste dinero debe mostrar el costo estimado **antes** de ejecutarse. Sin excepción.
- Estados de carga explícitos: los datos llegan de forma asíncrona, la UI debe comunicar "en proceso" con honestidad, no fingir instantaneidad.
- Vacío ≠ error: distinguir "aún no hay datos" de "la consulta falló".

---

## 11. Fases de implementación para Claude Code

Trabajar estrictamente en este orden. No adelantar.

1. **Andamiaje** — proyecto Laravel, Docker Compose (php-fpm, nginx, mysql, redis), CI con Pint + Larastan + Pest.
2. **Capa DataForSEO** — cliente HTTP, autenticación, reintentos, logging, DTOs, tests con `Http::fake()`. **Sin tocar la API real todavía.**
3. **Módulo de costos** — ledger, presupuestos, circuit breaker, dashboard.
4. **Modelo de datos** — migraciones completas de Fase 1, modelos, factories, seeders.
5. **Sincronización de catálogos** — comando artisan para ubicaciones e idiomas. *(Primera llamada real a la API.)*
6. **Clientes y proyectos** — CRUD interno.
7. **Keywords** — CRUD, importación masiva, enriquecimiento de volumen.
8. **Rank tracking** — jobs, postback, procesamiento, reconciliación.
9. **Visualización** — tablas, filtros, gráficas.
10. **Reportes** — plantillas, PDF, envío programado.
11. **Portal de cliente** — auth, policies, vistas.
12. **Endurecimiento** — 2FA, rate limits, auditoría de autorización, respaldos.

Al terminar cada fase: correr la suite completa, correr Larastan, y hacer commit con mensaje descriptivo. No avanzar con tests en rojo.

---

## 12. Despliegue

- Servidor **completamente separado** del VPS de producción con WHM/cPanel que aloja sitios de clientes. Sin excepciones.
- VPS con Docker (Hetzner, DigitalOcean o Vultr — 4GB RAM es suficiente para empezar), o Laravel Forge si se prefiere gestión asistida.
- Respaldos automáticos diarios de MySQL a almacenamiento externo, con **restauración probada**. La base de datos es el activo; un respaldo que nunca se restauró no es un respaldo.
- Monitoreo: Horizon para colas, Sentry o Flare para excepciones, alerta si el scheduler deja de correr.
- HTTPS obligatorio. Certificado gestionado.

---

## 13. Riesgos conocidos — leer antes de empezar

1. **Este spec asume que el dato de DataForSEO ya fue validado** contra keywords reales de clientes en Honduras y Centroamérica. Si esa validación no se ha hecho, hacerla **antes** de escribir una línea de código. Construir la plataforma y después descubrir que la cobertura local es insuficiente sería el error más caro posible.
2. **Los términos de servicio de DataForSEO son un contrato aparte de la licencia del software.** Revisar qué permiten respecto a mostrar sus datos a terceros bajo nuestra marca antes de exponer el portal de cliente.
3. **El crecimiento de la tabla `rankings` y de los snapshots es el principal riesgo técnico a 12 meses.** Definir política de retención y archivado desde el diseño, no cuando el disco se llene.
4. **Esto es un producto, no un script.** Requiere alguien dedicado a mantenerlo. El ahorro en licencias es real, pero no es gratis: se paga en horas de ingeniería.
5. **Existe OpenSEO** (MIT, alternativa open source a Semrush/Ahrefs sobre DataForSEO, en TypeScript). Antes de construir desde cero en PHP, vale la pena evaluar si un fork resuelve el 80% del problema. La razón válida para elegir PHP es la capacidad de mantenimiento del equipo, no la novedad — pero es una decisión que conviene tomar con los ojos abiertos.
