# Despliegue en WHM/cPanel — idseo.ingenio.la

Servidor dedicado propio, acceso root vía WHM, que también aloja
cuentas cPanel de clientes. La sección 12 del SPEC pide un servidor
completamente separado; al quedarte en el mismo servidor, esto mitiga
el riesgo compartido con las herramientas que sí tienes disponibles
por ser dueño del server (cuenta cPanel propia y aislada, límites de
recursos LVE, respaldo a almacenamiento externo). Sigue siendo el
mismo kernel/red que los sitios de clientes — no es equivalente a un
VPS separado, pero es razonable con estas mitigaciones.

Cuenta cPanel: `idseo` · Dominio: `idseo.ingenio.la` · Ruta:
`/home/idseo/idseo`.

## Requisitos del servidor

No es una máquina nueva — es lo que necesita **existir además de** lo
que WHM/cPanel ya trae, en el servidor que ya tienes.

**Hardware** (sobre lo que ya usan WHM + los sitios de clientes):
- **RAM**: +2 GB libres de margen como mínimo (Redis + Horizon +
  el pool PHP-FPM de esta cuenta). Si el servidor ya anda ajustado de
  memoria con los sitios actuales, súmale RAM antes de instalar esto,
  no lo comparas después de que empiece a fallar.
- **CPU**: 2 vCPU libres razonable; los crawls de auditoría on-page y
  las llamadas Live pueden generar ráfagas de uso — de ahí la
  recomendación de LVE Manager más abajo, para que esas ráfagas no le
  quiten CPU a un sitio de cliente.
- **Disco**: SSD, 20 GB libres para empezar. La tabla `rankings` y los
  snapshots de SERP crecen rápido con el uso (riesgo #3 del SPEC) —
  vigilar esto no es un paso único de instalación, es continuo.

**Software** (lo que hay que agregar al servidor; todo lo demás ya
viene con WHM):
- **PHP 8.3 o 8.4** con extensiones: `pdo_mysql`, `redis`, `bcmath`,
  `gd`, `intl`, `zip`, `mbstring`, `xml`, `curl`, `sodium`, `fileinfo`
  — todas estándar en EA4, se activan desde WHM, no se instalan
  aparte.
- **MySQL/MariaDB** — ya viene con WHM.
- **Redis** — no viene con cPanel, se instala aparte (paso 5).
- **Supervisor** — no viene con cPanel, se instala aparte (paso 10),
  necesario para mantener Horizon corriendo 24/7.
- **Composer** — muchos WHM ya lo traen en `Software → Composer` /
  `/opt/cpanel/composer/bin/composer`; si no, se instala manual.
- **Git** — normalmente ya está.
- **Node.js + Chrome/Chromium headless** — solo si vas a usar la
  generación de reportes en PDF (Browsershot, paso 11).
- **AutoSSL** — ya viene con cPanel, solo hay que activarlo (paso 12).

## 0. Crear y aislar la cuenta

1. **WHM → Create a New Account**: dominio `idseo.ingenio.la`,
   usuario `idseo`. Cuenta dedicada solo a esto — nunca reutilices la
   de un cliente. Te da un usuario Linux propio (`/home/idseo`) y, con
   PHP-FPM (default en EA4), un pool PHP-FPM propio.
2. Si tienes **CloudLinux con LVE Manager**: **WHM → LVE Manager** →
   ponle límites explícitos de CPU/memoria a la cuenta `idseo`. Es lo
   que evita que un crawl grande le robe recursos a un sitio de
   cliente en la misma caja física.

## 1. PHP

- **WHM → MultiPHP Manager**: asigna PHP 8.3 u 8.4 al dominio `idseo.ingenio.la`.
- **WHM → MultiPHP INI Editor** (o `Select PHP Version` dentro del
  cPanel de la cuenta) → pestaña de extensiones: activa `redis` y
  confirma que el resto de la lista de arriba está activo.
- Sube `memory_limit` a al menos 256M y `max_execution_time` a 120.

## 2. Código

Por SSH, como el usuario de la cuenta (nunca como root para esto):

```bash
su - idseo
git clone <url-del-repo> idseo
cd idseo
git checkout main   # o la rama que vayas a desplegar

composer install --no-dev --optimize-autoloader
```

Si `composer`/`php` del sistema no resuelven a la versión 8.3/8.4 que
activaste para la cuenta, usa el binario explícito de EA4:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/composer/bin/composer install --no-dev --optimize-autoloader
```

(Ajusta `ea-php84` a la versión real.)

## 3. Document root apuntando a `public/`, no a `public_html/`

Laravel sirve desde `public/`; cPanel sirve desde `public_html/` por
defecto — **nunca** dejes la app suelta dentro de `public_html/`
(expondría `.env`, `app/`, el código fuente completo).

**WHM → Domains → idseo.ingenio.la → Document Root** → apúntalo a
`/home/idseo/idseo/public`. (O `cPanel → Domains → Manage → Document
Root` desde dentro de la cuenta si WHM no te deja hacerlo directo.)

## 4. Base de datos

**WHM → MySQL Database Wizard** (o `MySQL® Databases` dentro del
cPanel de la cuenta): crea la base, un usuario, y dale todos los
privilegios sobre esa base. cPanel prefija los nombres con el usuario
de la cuenta — normalmente quedan como `idseo_idseo` (base) e
`idseo_idseo` (usuario), o similar; usa lo que WHM te muestre.

## 5. Redis

```bash
# AlmaLinux/CloudLinux (lo más común en WHM):
dnf install -y redis
systemctl enable --now redis
```

Un solo Redis del sistema es suficiente. Déjalo escuchando solo en
`127.0.0.1` — nunca expuesto a la red pública.

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
DB_DATABASE=idseo_idseo
DB_USERNAME=idseo_idseo
DB_PASSWORD=<la real>

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

DATAFORSEO_LOGIN=<tu login real>
DATAFORSEO_PASSWORD=<tu password real>
DATAFORSEO_WEBHOOK_TOKEN=<genera uno largo y random, ej: openssl rand -hex 32>

# Sección 9 del SPEC: nunca "local" en producción, sobre todo aquí,
# que comparte disco físico con los sitios de clientes. Usa S3,
# Wasabi, Backblaze B2, o similar.
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
chown -R idseo:idseo storage bootstrap/cache
```

## 9. Scheduler (cron)

**WHM → Cron Jobs** (o `crontab -e` como el usuario `idseo`):

```
* * * * * cd /home/idseo/idseo && php artisan schedule:run >> /dev/null 2>&1
```

Verifica con `php artisan schedule:list` — todas las tareas ya traen
`->sentryMonitor()`, así que si el cron deja de correr, Sentry Crons
avisa; no dependas solo de mirarlo a ojo.

## 10. Cola persistente (Horizon) vía Supervisor

```bash
dnf install -y supervisor
systemctl enable --now supervisord
```

`/etc/supervisord.d/idseo-horizon.ini`:

```ini
[program:idseo-horizon]
process_name=%(program_name)s
command=php /home/idseo/idseo/artisan horizon
autostart=true
autorestart=true
user=idseo
redirect_stderr=true
stdout_logfile=/home/idseo/idseo/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start idseo-horizon
```

Verifica en `https://idseo.ingenio.la/admin/horizon` que los workers
aparecen activos.

## 11. Chrome/Node para Browsershot (reportes PDF)

Solo si vas a usar la generación de reportes: **WHM → Node.js
Selector**, más Chrome/Chromium headless a nivel de sistema, y apunta
`BROWSERSHOT_CHROME_PATH` en `.env` al binario.

## 12. HTTPS

**WHM → SSL/TLS Status** o **AutoSSL** → actívalo para
`idseo.ingenio.la`. Obligatorio por sección 12 del SPEC, y necesario
para que el webhook de postback (paso 13) sea alcanzable.

## 13. Verificar que el webhook es alcanzable desde internet

DataForSEO llama de vuelta a
`https://idseo.ingenio.la/webhooks/dataforseo/{serp|onpage|reviews}?token=...`
para entregar resultados de tareas Standard (rank tracking, auditoría
on-page, reseñas). Confirma que responde desde **fuera** de tu red, no
solo desde el propio servidor, antes de dar por buena la instalación —
un firewall o WAF mal configurado aquí deja las tareas Standard
atoradas en `pending` (el job `ReconcilePendingTasks` las recupera
cada hora, pero eso es un síntoma de que el webhook no sirve, no una
solución).

## 14. Prueba de humo final

- [ ] `https://idseo.ingenio.la/admin/login` carga y el login funciona.
- [ ] Horizon (`/admin/horizon` o `php artisan horizon:status`) muestra el worker corriendo.
- [ ] `php artisan schedule:list` muestra las tareas con su próxima hora.
- [ ] Un crawl de auditoría on-page de prueba en un proyecto real termina y aparece en la pestaña.
- [ ] `php artisan backup:run` corre limpio y sube el zip al disco externo (no local).
- [ ] Sigues **RESTORE.md** al menos una vez contra ese backup real, no solo el smoke test.
- [ ] `SENTRY_LARAVEL_DSN` real — dispara un error de prueba y confirma que llega a Sentry.
