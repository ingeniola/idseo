<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Binario de Chrome/Chromium
    |--------------------------------------------------------------------------
    |
    | Browsershot (spatie/browsershot) requiere el paquete npm
    | `puppeteer` para hablar con Chrome. Este proyecto instala en su
    | lugar `puppeteer-core` (ver package.json y
    | scripts/setup-puppeteer-shim.cjs) para no depender de la descarga
    | de Chromium que trae `puppeteer` en cada `npm install` — hay que
    | apuntar explícitamente a un binario de Chrome/Chromium ya
    | instalado en la máquina. Vacío = dejar que Browsershot use su
    | resolución por defecto (solo funciona si en algún momento se
    | instala el paquete `puppeteer` completo en vez de este shim).
    |
    */

    'chrome_path' => env('BROWSERSHOT_CHROME_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | Segundos antes de que Browsershot aborte la generación del PDF.
    | Un reporte con muchas keywords puede tardar más que el timeout
    | por defecto de Browsershot (30s).
    |
    */

    'timeout' => (int) env('BROWSERSHOT_TIMEOUT', 60),

];
