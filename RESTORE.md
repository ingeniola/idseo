# Restauración de respaldos

Sección 12 del SPEC: *"Respaldos automáticos diarios de MySQL a
almacenamiento externo, con **restauración probada**. La base de
datos es el activo; un respaldo que nunca se restauró no es un
respaldo."*

`spatie/laravel-backup` (ya instalado y programado en `routes/console.php`)
deliberadamente **no** trae un comando `backup:restore` — restaurar es una
operación rara, manual y de alto riesgo que no conviene automatizar del
todo. Esto documenta el procedimiento exacto para hacerlo a mano.

**Corre este procedimiento al menos una vez antes de dar acceso a
clientes en producción**, y de nuevo cada vez que cambie de forma
importante el esquema de respaldo (disco, compresión, etc.). Un
respaldo que nunca se restauró no es un respaldo — es una promesa sin
verificar.

## 1. Ubicar el respaldo

```bash
php artisan backup:list
```

Lista los respaldos disponibles en el disco configurado
(`BACKUP_DISK` en `.env`) con su tamaño y fecha. Cada respaldo es un
único archivo `.zip`.

Descárgalo (o cópialo) a un servidor **distinto** al de producción —
nunca restaures sobre la base de datos viva sin antes tener el zip a
mano fuera de esa base de datos.

## 2. Descomprimir y ubicar el dump

```bash
unzip nombre-del-respaldo.zip -d /tmp/restore-test
```

El dump de la base de datos queda en:

```
/tmp/restore-test/db-dumps/mysql-<nombre_de_la_base><timestamp>.sql
```

(`mysql-` porque `DB_CONNECTION=mysql` en producción; el nombre exacto
de la base viene de `DB_DATABASE`). El resto del zip contiene
`storage/app/*` — logos de clientes y reportes ya generados, sección 9
del SPEC.

## 3. Restaurar contra una base de datos de prueba (nunca la de producción directamente)

```bash
mysql -u root -p -e "CREATE DATABASE idseo_restore_test;"
mysql -u root -p idseo_restore_test < /tmp/restore-test/db-dumps/mysql-idseo*.sql
```

## 4. Verificar que la restauración es real, no solo que el comando no falló

```bash
mysql -u root -p idseo_restore_test -e "
  SELECT COUNT(*) AS clients FROM clients;
  SELECT COUNT(*) AS projects FROM projects;
  SELECT COUNT(*) AS rankings FROM rankings;
  SELECT MAX(created_at) AS ultimo_registro FROM cost_ledger;
"
```

Compara los conteos contra lo que esperas ver en producción a esa
fecha. Si `ultimo_registro` es mucho más viejo que la hora del
respaldo, el dump no capturó lo que debía.

Para una verificación más completa (opcional pero recomendado la
primera vez): apunta una copia de la app a `idseo_restore_test` en un
`.env` de prueba y entra al panel — confirma que un proyecto real
carga con sus keywords y rankings.

## 5. Restaurar los archivos de `storage/app`

```bash
cp -r /tmp/restore-test/storage/app/* /ruta/real/storage/app/
```

Solo si de verdad estás restaurando producción (no aplica al ensayo
del paso 3-4).

## 6. Limpieza

```bash
mysql -u root -p -e "DROP DATABASE idseo_restore_test;"
rm -rf /tmp/restore-test
```

## Notas

- Este documento **no fue ejecutado contra MySQL real** al escribirlo
  — el entorno de desarrollo donde se generó no tenía MySQL ni el
  cliente `sqlite3` disponibles para ensayarlo end-to-end. Los pasos
  y la sintaxis (`mysqldump`/`mysql`, estructura del zip de
  `spatie/laravel-backup`) están verificados contra el código fuente
  del paquete (`vendor/spatie/laravel-backup/src/Tasks/Backup/BackupJob.php`
  y `vendor/spatie/db-dumper`), no inventados — pero el primer
  ensayo real en tu servidor sigue siendo el que cuenta.
- Repetir este ensayo periódicamente (trimestral es razonable) no
  solo una vez: un cambio futuro en el esquema, en `BACKUP_DISK`, o en
  las credenciales de MySQL puede romper el respaldo sin que
  `backup:monitor` lo note si el respaldo "corre" pero queda vacío o
  corrupto.
