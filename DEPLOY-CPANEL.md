# Despliegue en WHM/cPanel — idseo.ingenio.la

Servidor dedicado propio, acceso root vía WHM. `idseo.ingenio.la` se
instala como **subdominio dentro de la cuenta cPanel existente
`ingeniola`** (la misma que ya aloja `agencia.ingenio.la`) — no una
cuenta nueva. Como `agencia.ingenio.la` es propiedad de ustedes y no
de un cliente externo, compartir cuenta con ella es razonable; la
preocupación de la sección 12 del SPEC ("servidor completamente
separado") es sobre todo con sitios de **clientes** de terceros, que
siguen en otras cuentas cPanel de este mismo servidor físico — para
esos, la mitigación sigue siendo LVE Manager a nivel de cuenta (ver
abajo) y no depende de esto.

Cuenta cPanel: `ingeniola` · Subdominio: `idseo.ingenio.la` · Ruta de
la app: `/home/ingeniola/idseo` · Document root del subdominio:
`/home/ingeniola/idseo/public`.

## Requisitos del servidor

No es una máquina nueva — es lo que necesita **existir además de** lo
que WHM/cPanel ya trae, en el servidor que ya tienes.

**Hardware** (sobre lo que ya usan WHM + `agencia.ingenio.la` + los
sitios de clientes en otras cuentas):
- **RAM**: +2 GB libres de margen como mínimo (Redis + Horizon + el
  pool PHP-FPM de este subdominio). Si el servidor ya anda ajustado de
  memoria, súmale RAM antes de instalar esto, no después de que
  empiece a fallar.
- **CPU**: 2 vCPU libres razonable; los crawls de auditoría on-page y
  las llamadas Live pueden generar ráfagas de uso.
- **Disco**: SSD, 20 GB libres para empezar. La tabla `rankings` y los
  snapshots de SERP crecen rápido con el uso (riesgo #3 del SPEC) —
  vigilar esto es continuo, no un paso único de instalación.

**Software** (lo que hay que agregar al servidor; todo lo demás ya
viene con WHM):
- **PHP 8.3 o 8.4** con extensiones: `pdo_mysql`, `redis`, `bcmath`,
  `gd`, `intl`, `zip`, `mbstring`, `xml`, `curl`, `sodium`, `fileinfo`
  — todas estándar en EA4, se activan desde WHM, no se instalan
  aparte. cPanel permite fijar la versión de PHP por subdominio, así
  que `idseo.ingenio.la` puede usar 8.3/8.4 aunque
  `agencia.ingenio.la` use otra.
- **MySQL/MariaDB** — ya viene con WHM.
- **Redis** — no viene con cPanel, se instala aparte (paso 4).
- **Supervisor** — no viene con cPanel, se instala aparte (paso 9),
  necesario para mantener Horizon corriendo 24/7.
- **Composer** — muchos WHM ya lo traen en `Software → Composer` /
  `/opt/cpanel/composer/bin/composer`; si no, se instala manual.
- **Git** — normalmente ya está.
- **Node.js + Chrome/Chromium headless** — solo si vas a usar la
  generación de reportes en PDF (Browsershot, paso 10).
- **AutoSSL** — ya viene con cPanel, solo hay que activarlo (paso 11).

## 0. Límite de recursos de la cuenta (si aplica)

Si tienes **CloudLinux con LVE Manager**: **WHM → LVE Manager** →
revisa el límite de CPU/memoria de la cuenta `ingeniola`. Un crawl de
auditoría on-page grande corriendo bajo `idseo.ingenio.la` consume
recursos de la misma cuenta que `agencia.ingenio.la` — si el límite
actual de la cuenta es justo para el sitio de agencia solo, súbelo
antes de meter esta app, para que un crawl pesado no le tumbe
disponibilidad al sitio de agencia.

## 1. Crear el subdominio

**cPanel (de la cuenta `ingeniola`) → Domains → Create A New Domain**:

- **Domain**: `idseo.ingenio.la`
- **Document Root**: cPanel va a proponer algo como
  `idseo.ingenio.la` dentro de `public_html/` — **cámbialo** a
  `idseo/public` (o créalo así y ajusta en el paso 3 si no te deja
  escribir una ruta fuera de `public_html/` en este formulario).

## 2. PHP

- **WHM → MultiPHP Manager**: asigna PHP 8.3 u 8.4 específicamente al
  subdominio `idseo.ingenio.la` (no cambia lo que ya usa
  `agencia.ingenio.la`).
- **WHM → MultiPHP INI Editor** → selecciona `idseo.ingenio.la` →
  pestaña de extensiones: activa `redis` y confirma el resto de la
  lista de arriba.
- Sube `memory_limit` a al menos 256M y `max_execution_time` a 120
  para ese subdominio.

## 3. Código

Por SSH, como el usuario `ingeniola` (nunca como root para esto):

```bash
su - ingeniola
git clone <url-del-repo> idseo
cd idseo
git checkout main   # o la rama que vayas a desplegar

composer install --no-dev --optimize-autoloader
```

Si `composer`/`php` del sistema no resuelven a la versión 8.3/8.4 que
activaste para el subdominio, usa el binario explícito de EA4:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/composer/bin/composer install --no-dev --optimize-autoloader
```

(Ajusta `ea-php84` a la versión real.)

Confirma que el document root del subdominio (paso 1) terminó
apuntando a `/home/ingeniola/idseo/public` y no a
`/home/ingeniola/idseo` ni a `public_html/idseo` — **nunca** dejes la
app completa como document root, expondría `.env`, `app/`, el código
fuente. Si el asistente de cPanel no te dejó escribir esa ruta
directo: **WHM → Domains → idseo.ingenio.la → Document Root** te deja
corregirla después de creado el subdominio.

## 4. Base de datos

**WHM → MySQL Database Wizard** (o `MySQL® Databases` dentro del
cPanel de la cuenta `ingeniola`): crea una base y un usuario
*específicos para esta app* — no reutilices la base de
`agencia.ingenio.la`. cPanel prefija los nombres con el usuario de la
cuenta, normalmente quedan como `ingeniola_idseo` (base) e
`ingeniola_idseo` (usuario); usa lo que WHM te muestre.

## 5. Redis

```bash
dnf install -y redis
systemctl enable --now redis
```

Un solo Redis del sistema es suficiente (esta es la única app que lo
usa). Déjalo escuchando solo en `127.0.0.1` — nunca expuesto a la red
pública.

## 6. `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` con los valores reales:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://idseo.ingenio.la

DB_HOST=localhost
DB_DATABASE=ingeniola_idseo
DB_USERNAME=ingeniola_idseo
DB_PASSWORD=<la real>

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

DATAFORSEO_LOGIN=<tu login real>
DATAFORSEO_PASSWORD=<tu password real>
DATAFORSEO_WEBHOOK_TOKEN=<genera uno largo y random, ej: openssl rand -hex 32>

# Sección 9 del SPEC: nunca "local" en producción — este servidor
# también aloja sitios de clientes en otras cuentas. Usa S3, Wasabi,
# Backblaze B2, o similar.
BACKUP_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_BUCKET=...
AWS_DEFAULT_REGION=...

SENTRY_LARAVEL_DSN=<tu DSN de sentry.io>
```

Rellena también las estimaciones de costo (`DATAFORSEO_*_COST_*`) con
los precios reales de tu cuenta antes de que el equipo empiece a
disparar acciones Live/Standard a demanda — sin esto la UI avisa "sin
estimación configurada" pero deja seguir igual.

## 7. Migraciones y catálogo inicial

```bash
php artisan migrate --force
php artisan dataforseo:sync-locations
```

**Antes de prometerle SEO local a un cliente en Honduras, Guatemala,
El Salvador, Nicaragua, Costa Rica o Panamá**: revisa a mano en la
tabla `locations` qué granularidad (`location_type`) trae DataForSEO
para cada país — riesgo #1 del SPEC.

## 8. Cachés de producción y storage

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache

chmod -R 775 storage bootstrap/cache
chown -R ingeniola:ingeniola storage bootstrap/cache
```

## 9. Scheduler (cron) y Horizon vía Supervisor

**Scheduler** — **WHM → Cron Jobs** (o `crontab -e` como `ingeniola`):

```
* * * * * cd /home/ingeniola/idseo && php artisan schedule:run >> /dev/null 2>&1
```

Si `agencia.ingenio.la` u otra app de la cuenta ya tiene su propio
cron, agrega esta línea al mismo crontab del usuario `ingeniola` —
no hay conflicto, cada línea es independiente.

Verifica con `php artisan schedule:list` — todas las tareas ya traen
`->sentryMonitor()`, así que si el cron deja de correr, Sentry Crons
avisa; no dependas solo de mirarlo a ojo.

**Horizon** (cola persistente, necesita un proceso 24/7 — cPanel no lo
hace por sí solo):

```bash
dnf install -y supervisor
systemctl enable --now supervisord
```

`/etc/supervisord.d/idseo-horizon.ini`:

```ini
[program:idseo-horizon]
process_name=%(program_name)s
command=php /home/ingeniola/idseo/artisan horizon
autostart=true
autorestart=true
user=ingeniola
redirect_stderr=true
stdout_logfile=/home/ingeniola/idseo/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start idseo-horizon
```

Verifica en `https://idseo.ingenio.la/admin/horizon` que los workers
aparecen activos.

## 10. Chrome/Node para Browsershot (reportes PDF)

Solo si vas a usar la generación de reportes: **WHM → Node.js
Selector**, más Chrome/Chromium headless a nivel de sistema, y apunta
`BROWSERSHOT_CHROME_PATH` en `.env` al binario.

## 11. HTTPS

**WHM → SSL/TLS Status** o **AutoSSL** → actívalo específicamente para
`idseo.ingenio.la` (un subdominio nuevo necesita su propio
certificado, no hereda el de `agencia.ingenio.la` automáticamente en
todos los casos — confírmalo). Obligatorio por sección 12 del SPEC, y
necesario para que el webhook de postback (paso 12) sea alcanzable.

## 12. Verificar que el webhook es alcanzable desde internet

DataForSEO llama de vuelta a
`https://idseo.ingenio.la/webhooks/dataforseo/{serp|onpage|reviews}?token=...`
para entregar resultados de tareas Standard (rank tracking, auditoría
on-page, reseñas). Confirma que responde desde **fuera** de tu red, no
solo desde el propio servidor, antes de dar por buena la instalación —
un firewall o WAF mal configurado aquí deja las tareas Standard
atoradas en `pending` (el job `ReconcilePendingTasks` las recupera
cada hora, pero eso es un síntoma de que el webhook no sirve, no una
solución).

## 13. Prueba de humo final

- [ ] `https://idseo.ingenio.la/admin/login` carga y el login funciona.
- [ ] `agencia.ingenio.la` sigue funcionando normal (nada se rompió al compartir cuenta).
- [ ] Horizon (`/admin/horizon` o `php artisan horizon:status`) muestra el worker corriendo.
- [ ] `php artisan schedule:list` muestra las tareas con su próxima hora.
- [ ] Un crawl de auditoría on-page de prueba en un proyecto real termina y aparece en la pestaña.
- [ ] `php artisan backup:run` corre limpio y sube el zip al disco externo (no local).
- [ ] Sigues **RESTORE.md** al menos una vez contra ese backup real, no solo el smoke test.
- [ ] `SENTRY_LARAVEL_DSN` real — dispara un error de prueba y confirma que llega a Sentry.
