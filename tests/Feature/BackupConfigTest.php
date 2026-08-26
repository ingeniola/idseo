<?php

declare(strict_types=1);

/**
 * No ejecuta backup:run de verdad en el suite (seria fragil contra el
 * sqlite en memoria de las pruebas) — solo verifica que la configuracion
 * quedo con el alcance decidido en la seccion 9 del SPEC: storage/app
 * (no base_path() completo) y un disco configurable via BACKUP_DISK.
 */
test('el respaldo incluye solo storage/app, no toda la aplicacion', function () {
    expect(config('backup.backup.source.files.include'))->toBe([storage_path('app')]);
});

test('el respaldo usa el disco configurado por BACKUP_DISK', function () {
    expect(config('backup.backup.destination.disks'))->toBe([env('BACKUP_DISK', 'local')])
        ->and(config('backup.monitor_backups.0.disks'))->toBe([env('BACKUP_DISK', 'local')]);
});

test('el email de notificacion de respaldos siempre resuelve a un valor valido', function () {
    expect(config('backup.notifications.mail.to'))->not->toBeEmpty();
});
