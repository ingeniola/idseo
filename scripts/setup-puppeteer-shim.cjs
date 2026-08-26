// spatie/browsershot (vendor/spatie/browsershot/bin/browser.cjs) does
// a hard `require('puppeteer')`. Este proyecto instala
// `puppeteer-core` en vez de `puppeteer` (ver package.json) para no
// depender de la descarga de Chromium que trae `puppeteer` en cada
// `npm install` — BROWSERSHOT_CHROME_PATH (ver config/browsershot.php)
// apunta al binario de Chrome/Chromium que ya esté instalado en la
// máquina. Este script crea un módulo `puppeteer` local que reexporta
// `puppeteer-core`, para que Browsershot no necesite ningún cambio de
// código. Si alguna vez se instala el paquete `puppeteer` real (por
// ejemplo, en un servidor que prefiera dejar que Chromium se descargue
// solo), esa carpeta real tiene prioridad y este script no hace nada.
const fs = require('fs');
const path = require('path');

const shimDir = path.join(__dirname, '..', 'node_modules', 'puppeteer');

if (fs.existsSync(shimDir)) {
    process.exit(0);
}

fs.mkdirSync(shimDir, { recursive: true });

fs.writeFileSync(
    path.join(shimDir, 'package.json'),
    JSON.stringify({
        name: 'puppeteer',
        version: '0.0.0-shim',
        private: true,
        main: 'index.js',
    }, null, 2) + '\n',
);

fs.writeFileSync(
    path.join(shimDir, 'index.js'),
    "module.exports = require('puppeteer-core');\n",
);
