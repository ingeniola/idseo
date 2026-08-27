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

## 0.5. Acceso SSH y jailshell

Por defecto muchas cuentas cPanel tienen el shell deshabilitado. Si
`su - ingeniola` responde `Shell access is not enabled on your
account!`: **WHM → Manage Shell Access** → pon la cuenta `ingeniola`
en `/bin/bash` (o al menos `jailshell`).

Si te quedas en **jailshell** (verás el prompt como
`-jailshell: ...` en algunos mensajes de error), ojo con dos cosas
que causaron problemas reales en este despliegue:

- El jailshell puede **ocultar rutas que sí existen** en el servidor
  real, aunque el resto del sistema las vea — `/opt/cpanel/composer`
  puede dar "command not found" incluso si `Software → Composer`
  está instalado a nivel de WHM. No asumas que un `command not
  found` significa que no está instalado; puede ser una restricción
  de visibilidad del jail. Si pasa, instala Composer localmente en la
  propia carpeta de la app con el instalador oficial:

  ```bash
  cd /home/ingeniola/idseo
  php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
  php composer-setup.php
  php -r "unlink('composer-setup.php');"
  # queda composer.phar en esta carpeta; úsalo como:
  # php composer.phar install --no-dev --optimize-autoloader
  ```

- Si cloncas el repo (o corres cualquier comando) como `root` antes
  de cambiar a `ingeniola`, los archivos quedan `root:root` y
  `ingeniola` no puede escribir ahí. Arréglalo una vez, como root:

  ```bash
  chown -R ingeniola:ingeniola /home/ingeniola/idseo
  ```

## 1. Subdominio ya creado — servir `public/` vía symlink

Ya creaste el subdominio `idseo.ingenio.la` en la cuenta `ingeniola`.
cPanel (tema Jupiter) le puso el document root por defecto,
`/home/ingeniola/public_html/idseo.ingenio.la`, y **no deja editar esa
ruta a algo fuera de `public_html/`** ni desde "Create a New Domain"
ni desde "Manage the Domain" — el campo está anclado al prefijo
`/public_html/`.

El document root público tiene que apuntar específicamente a la
carpeta `public/` de Laravel, nunca a la carpeta que contiene el
código completo — si no, `.env`, `app/`, `database/` y todo lo demás
quedarían accesibles por HTTP. Como la UI no deja cambiar la ruta,
la solución es un **symlink**: la carpeta que cPanel ya apunta como
document root pasa a *ser* (vía enlace simbólico) la carpeta
`public/` de la app, sin tocar configuración de Apache ni el document
root declarado.

```bash
su - ingeniola

# Clona el código si todavía no existe /home/ingeniola/idseo:
git clone <url-del-repo> /home/ingeniola/idseo

# Quita la carpeta vacía que cPanel creó como placeholder...
rm -rf /home/ingeniola/public_html/idseo.ingenio.la

# ...y reemplázala por un symlink a la carpeta public/ de la app:
ln -s /home/ingeniola/idseo/public /home/ingeniola/public_html/idseo.ingenio.la
```

Verifica:

```bash
ls -la /home/ingeniola/public_html/ | grep idseo
```

Debería mostrar `idseo.ingenio.la -> /home/ingeniola/idseo/public`.

Deja el "Document Root" de cPanel tal como está — no hace falta
tocarlo, Apache sigue el symlink de forma transparente.

**Si al visitar el subdominio da 403 Forbidden** (poco común; pasa si
el servidor tiene `SymLinksIfOwnerMatch` u otra restricción de
symlinks activada más estricta de lo normal): avísame, hay una
alternativa con un `index.php` "passthrough" dentro de
`public_html/idseo.ingenio.la/` que funciona siempre, un poco más de
mantenimiento, que te doy si la necesitas.

## 2. PHP

- **WHM → MultiPHP Manager**: asigna PHP 8.3 u 8.4 específicamente al
  subdominio `idseo.ingenio.la` (no cambia lo que ya usa
  `agencia.ingenio.la`). **Nota real:** este `composer.lock` fue
  generado con PHP 8.4 (varios paquetes symfony/* exigen `php
  >=8.4.1`); si tu servidor tiene 8.3 y 8.4 disponibles, usa 8.4 para
  evitarte un conflicto de dependencias al hacer `composer install`.
- **WHM → MultiPHP INI Editor** → selecciona `idseo.ingenio.la` →
  pestaña de extensiones: activa `redis` y confirma el resto de la
  lista de arriba.
- Sube `memory_limit` a al menos 256M y `max_execution_time` a 120
  para ese subdominio.

**Importante — MultiPHP Manager solo cambia el PHP del handler
web/FPM, no el `php` de la línea de comandos (SSH/cron/Supervisor).**
Verifica con `php -v` después de asignar la versión: si sigue
mostrando la versión vieja, el CLI está resolviendo a otro binario.
En ese caso usa la ruta explícita de EA4 en **todos** los comandos de
este documento a partir de aquí (`artisan`, `composer`, el cron del
paso 9, el `command=` de Supervisor):

```bash
/opt/cpanel/ea-php84/root/usr/bin/php artisan ...
```

(Cambia `ea-php84` por tu versión real; confirma la ruta con
`ls /opt/cpanel/ | grep ea-php`.)

Además, las extensiones que actives en MultiPHP INI Editor **tampoco
alcanzan al CLI** — son dos configuraciones distintas. Si `php -m |
grep intl` (con el binario de EA4 de arriba) no muestra nada, o el
comando `dataforseo:sync-locations` falla con `Class "Redis" not
found`, instala el paquete de la extensión para esa versión de PHP
desde **WHM → EasyApache 4 → Customize** (no MultiPHP INI Editor):
busca y agrega `ea-php84-php-intl` y/o `ea-php84-php-redis` (ajusta
el número de versión), y espera a que termine el rebuild de EA4.
Vuelve a verificar con `php -m | grep -iE "intl|redis"`.

## 3. Código

Si ya clonaste el repo en el paso 1, sáltate el `git clone` de abajo.
Por SSH, como el usuario `ingeniola` (nunca como root para esto):

```bash
su - ingeniola
cd /home/ingeniola/idseo
git checkout main   # o la rama que vayas a desplegar

composer install --no-dev --optimize-autoloader
```

Si `composer`/`php` del sistema no resuelven a la versión 8.3/8.4 que
activaste para el subdominio, usa el binario explícito de EA4:

```bash
/opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/composer/bin/composer install --no-dev --optimize-autoloader
```

(Ajusta `ea-php84` a la versión real.)

Confirma que el symlink del paso 1 sigue apuntando bien
(`ls -la /home/ingeniola/public_html/ | grep idseo`) — **nunca** dejes
la app completa servida directo por web, expondría `.env`, `app/`, el
código fuente.

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

# OJO: el dashboard de DataForSEO muestra el login/password en texto
# plano y también, aparte, un blob en Base64 de "login:password" como
# formato alternativo para el header Authorization. DATAFORSEO_PASSWORD
# es el password EN TEXTO PLANO, nunca ese blob — pegarlo por error
# aquí (o en un curl -u "login:BLOB") da HTTP 401 / status_code 40100
# "You are not authorized" y se confunde fácil con el aviso de
# "Verify your account to start fetching data" del dashboard, que es
# un problema distinto.

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

(Recuerda usar el binario explícito de EA4 si el CLI no resuelve a
8.3/8.4 — ver paso 2.)

El catálogo completo de `serp/google/locations` son ~270,000 filas;
si tu cuenta tiene un `memory_limit` de CLI bajo (verifica con `php -i
| grep memory_limit` — el de MultiPHP INI Editor no aplica al CLI,
igual que las extensiones), el comando puede fallar o quedarse
colgado sin mensaje de error visible. Si pasa, corre con un límite
más alto solo para esta ejecución:

```bash
php -d memory_limit=512M artisan dataforseo:sync-locations
```

El comando también avisa si encuentra ubicaciones cuyo
`parent_code` no existe en el catálogo que DataForSEO mismo devolvió
(pasa con datos reales, es normal) — las guarda sin padre y te dice
cuántas fueron; no es un error.

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

Si tuviste que usar el binario explícito de EA4 en el paso 2 porque
el `php` del CLI no resolvía a 8.3/8.4, úsalo aquí también (cron
corre con su propio `PATH`, que puede no coincidir con el de tu
sesión SSH):

```
* * * * * cd /home/ingeniola/idseo && /opt/cpanel/ea-php84/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1
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

Igual que el cron: si el `php` del sistema no es el 8.3/8.4 que
activaste, cambia la línea `command=` para usar el binario explícito
de EA4, por ejemplo:

```ini
command=/opt/cpanel/ea-php84/root/usr/bin/php /home/ingeniola/idseo/artisan horizon
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start idseo-horizon
```

Verifica en `https://idseo.ingenio.la/admin/horizon` que los workers
aparecen activos.

## 10. Chrome/Node para Browsershot (reportes PDF)

Solo si vas a usar la generación de reportes. Este proyecto instala
`puppeteer-core` (no `puppeteer`) — ver `config/browsershot.php` y
`scripts/setup-puppeteer-shim.cjs` — para no depender de que cada
`npm install` descargue su propio Chromium; en su lugar usa un
Chrome/Chromium ya instalado en el servidor, apuntado por
`BROWSERSHOT_CHROME_PATH`.

**`puppeteer-core@25.x` exige Node.js >=22.12.0.** Si `node -v` te da
una versión menor, `npm install` va a instalar igual pero con un
warning `EBADENGINE` — no lo ignores, es un riesgo real de que
Browsershot falle en producción por una API de Node que no existe en
la versión vieja.

Como `root`:

```bash
dnf module list nodejs        # confirma que el stream 22 está disponible
dnf module reset -y nodejs
dnf module install -y nodejs:22
node -v                       # debe dar v22.x.x

dnf install -y chromium
which chromium-browser        # apunta el binario real, ej. /bin/chromium-browser
```

Como `ingeniola`, desde `/home/ingeniola/idseo` (confirma con `pwd`
antes de correr esto — un `cd` seguido de más comandos en el mismo
bloque a veces no persiste si el `su -` de arriba abrió una sesión
nueva a medio pegar):

```bash
cd /home/ingeniola/idseo
pwd   # debe decir /home/ingeniola/idseo

# puppeteer-core está en devDependencies: un npm install en modo
# producción lo saltaría, hay que forzar incluir dev deps
npm install --include=dev
```

Edita `.env` (confirma con `grep -n "^BROWSERSHOT_CHROME_PATH" .env`
que de verdad quedó escrito, no solo la línea vacía que trae
`.env.example`):

```env
BROWSERSHOT_CHROME_PATH=/bin/chromium-browser
```

```bash
php artisan config:cache

# prueba real, no solo que el comando no falle
php artisan tinker --execute="app(\App\Reports\PdfRenderer::class)->render('<h1>Prueba</h1>', '/tmp/test.pdf'); echo file_exists('/tmp/test.pdf') ? 'OK: '.filesize('/tmp/test.pdf').' bytes' : 'FALLO';"
file /tmp/test.pdf   # debe decir "PDF document"
rm -f /tmp/test.pdf
```

Si algún comando de este paso corrió por accidente como `root`
(pasa fácil si el bloque se pegó justo después de un `su - ingeniola`
que no tomó), `node_modules` queda con dueño equivocado y hay que
corregirlo una vez, como root:

```bash
chown -R ingeniola:ingeniola /home/ingeniola/idseo/node_modules
```

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
