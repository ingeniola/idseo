# Despliegue en un servidor dedicado WHM/cPanel (root)

Esto asume lo que confirmaste: un servidor **dedicado** tuyo, con
**acceso root** vía WHM, que también aloja cuentas cPanel de clientes.
La sección 12 del SPEC pide un servidor completamente separado; al
quedarte en el mismo servidor, esto mitiga el riesgo compartido con
las herramientas que sí tienes disponibles por ser dueño del server
(cuenta cPanel propia y aislada, límites de recursos LVE, respaldo a
almacenamiento externo). Sigue siendo el mismo kernel/red que los
sitios de clientes — no es equivalente a un VPS separado, pero es
razonable con estas mitigaciones.

## 0. Aislar la cuenta antes de instalar nada

1. **WHM → Create a New Account**: crea una cuenta cPanel dedicada
   solo a esto (ej. usuario `ingenioseo`), nunca reutilices la cuenta
   de un cliente. Esto te da un usuario Linux propio, `/home/ingenioseo`
   propio, y (con PHP-FPM, el default en EA4) un pool PHP-FPM propio.
2. Si tienes **CloudLinux con LVE Manager** instalado (común en WHM
   dedicado): **WHM → LVE Manager** → ponle límites explícitos de CPU
   y memoria a la cuenta `ingenioseo`. Esto es lo que evita que un
   crawl de auditoría on-page grande le robe recursos a un sitio de
   cliente — sin esto, un runaway job en la misma caja física sí puede
   afectarlos.

## 1. PHP

La app requiere **PHP ^8.3** (probado con 8.4) y estas extensiones
(todas estándar en EA4, ninguna exótica): `pdo_mysql`, `redis`,
`bcmath`, `gd`, `intl`, `zip`, `mbstring`, `xml`, `curl`, `sodium`,
`fileinfo`.

- **WHM → MultiPHP Manager**: asigna PHP 8.3 o 8.4 a la cuenta/dominio.
- **WHM → MultiPHP INI Editor** (o `Select PHP Version` dentro del
  propio cPanel de la cuenta) → pestaña de extensiones: activa
  `redis` si no está ya, y las demás de la lista de arriba.
- Sube `memory_limit` a al menos 256M y `max_execution_time` a 120
  (los crawls on-page y las llamadas Live pueden tardar).

## 2. Código

Por SSH, como el usuario de la cuenta (nunca como root para esto):

```bash
su - ingenioseo
git clone <url-del-repo> idseo
cd idseo
git checkout main   # o la rama que vayas a desplegar

# El PHP del sistema puede no ser el 8.3/8.4 de la cuenta — usa el
# binario de EA4 explícito para composer/artisan si `php -v` no
# coincide con lo que configuraste arriba:
/opt/cpanel/ea-php84/root/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
```

(Ajusta `ea-php84` a la versión real que activaste. Si `php` ya
resuelve a la versión correcta para ese usuario, `composer install
--no-dev --optimize-autoloader` a secas basta.)

## 3. Document root apuntando a `public/`, no a `public_html/`

Laravel sirve desde `public/`, cPanel sirve desde `public_html/` por
defecto — **nunca** copies la app dentro de `public_html/` directo
(expondría `.env`, `app/`, todo el código fuente).

- **WHM → Domains → (el dominio/subdominio) → Document Root**: apúntalo
  a `/home/ingenioseo/idseo/public`.
- O usa **cPanel → Domains → Manage → Document Root** desde dentro de
  la cuenta si WHM no te deja hacerlo directo.

Usa un subdominio dedicado (ej. `seo.tuagencia.com`), no un dominio de
cliente.

## 4. Base de datos

**WHM → MySQL Database Wizard** (o dentro del cPanel de la cuenta:
`MySQL® Databases`): crea la base, un usuario, y dale todos los
privilegios sobre esa base. Anota `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD`
— en cPanel suelen ir prefijados con el usuario de la cuenta
(`ingenioseo_idseo`, etc.).

## 5. Redis

No viene con cPanel — se instala a nivel de sistema (tienes root):

```bash
# AlmaLinux/CloudLinux (lo más común en WHM):
dnf install -y redis
systemctl enable --now redis
```

Un solo Redis del sistema es suficiente (esta es la única app que lo
usa). Déjalo escuchando en `127.0.0.1` únicamente — nunca lo expongas
a la red pública.

## 6. `.env`

```bash
cp .env.example .env
php artisan key:generate
```

Edita `.env` con los valores reales. Como mínimo, cambia esto respecto
al `.env.example`:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://seo.tuagencia.com

DB_HOST=localhost
DB_DATABASE=ingenioseo_idseo
DB_USERNAME=ingenioseo_idseo
DB_PASSWORD=<la real>

SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

DATAFORSEO_LOGIN=<tu login real>
DATAFORSEO_PASSWORD=<tu password real>
DATAFORSEO_WEBHOOK_TOKEN=<genera uno largo y random, ej: openssl rand -hex 32>

# Sección 9 del SPEC: nunca "local" en producción — sobre todo aquí,
# que comparte disco físico con los sitios de clientes. Un respaldo
# que solo vive en el mismo disco no protege contra una falla de ese
# disco. Usa S3, Wasabi, Backblaze B2, o similar.
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
chown -R ingenioseo:ingenioseo storage bootstrap/cache
```

## 9. Scheduler (cron)

**WHM → Cron Jobs** (o `crontab -e` como el usuario de la cuenta):

```
* * * * * cd /home/ingenioseo/idseo && php artisan schedule:run >> /dev/null 2>&1
```

Verifica que corrió: `php artisan schedule:list` desde la cuenta te
muestra la próxima hora de cada tarea (todas ya traen `->sentryMonitor()`
— si el cron deja de correr, Sentry Crons avisa, no dependas solo de
mirarlo a ojo).

## 10. Cola persistente (Horizon) vía Supervisor

Horizon necesita un proceso corriendo 24/7 — cPanel no lo hace por sí
solo. Con root, usa Supervisor:

```bash
dnf install -y supervisor
systemctl enable --now supervisord
```

`/etc/supervisord.d/idseo-horizon.ini`:

```ini
[program:idseo-horizon]
process_name=%(program_name)s
command=php /home/ingenioseo/idseo/artisan horizon
autostart=true
autorestart=true
user=ingenioseo
redirect_stderr=true
stdout_logfile=/home/ingenioseo/idseo/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start idseo-horizon
```

Verifica en `https://seo.tuagencia.com/admin/horizon` (o la ruta que
tenga configurada) que los workers aparecen activos.

## 11. Chrome/Node para Browsershot (generación de reportes PDF)

Si vas a usar la generación de reportes (Browsershot), necesita
Chrome/Chromium headless + Node.js instalados a nivel de sistema —
**WHM → Node.js Selector**, o instala Chrome vía el gestor de paquetes
del sistema, y apunta `BROWSERSHOT_CHROME_PATH` en `.env` al binario.

## 12. HTTPS

**WHM → SSL/TLS Status** o **AutoSSL**: actívalo para el subdominio.
Sección 12 del SPEC: HTTPS obligatorio, sin excepciones — el webhook
de postback (paso 13) además necesita ser alcanzable por HTTPS público
para que DataForSEO pueda llamarlo.

## 13. Verificar que el webhook es alcanzable desde internet

DataForSEO llama de vuelta a
`https://seo.tuagencia.com/webhooks/dataforseo/{serp|onpage|reviews}?token=...`
para entregar resultados de tareas Standard (rank tracking, auditoría
on-page, reseñas). Confirma que ese subdominio responde desde fuera de
tu red (no solo desde el propio servidor) antes de dar por buena la
instalación — un firewall o un WAF mal configurado aquí deja las
tareas Standard atoradas en `pending` para siempre (el job
`ReconcilePendingTasks` las recupera cada hora, pero es un síntoma de
que el webhook no sirve, no una solución).

## 14. Prueba de humo final

- [ ] `https://seo.tuagencia.com/admin/login` carga y el login funciona.
- [ ] `php artisan horizon:status` (o el dashboard) muestra el worker corriendo.
- [ ] `php artisan schedule:list` muestra las tareas con su próxima hora.
- [ ] Un crawl de auditoría on-page de prueba en un proyecto real termina y aparece en la pestaña.
- [ ] `php artisan backup:run` corre limpio y sube el zip al disco externo (no local).
- [ ] Sigue **RESTORE.md** al menos una vez contra ese backup real, no solo el smoke test.
- [ ] `SENTRY_LARAVEL_DSN` real — dispara un error de prueba y confirma que llega a Sentry.
