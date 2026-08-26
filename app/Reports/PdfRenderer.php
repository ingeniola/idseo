<?php

declare(strict_types=1);

namespace App\Reports;

/**
 * Frontera entre GenerateReportPdf y el motor real de PDF, para poder
 * probar la orquestación (armar datos, guardar el Report) sin invocar
 * un Chromium real en cada corrida de la suite de pruebas — el mismo
 * motivo por el que DataForSeoClient nunca se llama de verdad en los
 * tests (Http::fake() en su lugar). Aquí se hace bind de la
 * implementación real de Browsershot en AppServiceProvider.
 */
interface PdfRenderer
{
    public function render(string $html, string $absoluteOutputPath): void;
}
