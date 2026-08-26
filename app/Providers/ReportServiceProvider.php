<?php

declare(strict_types=1);

namespace App\Providers;

use App\Reports\BrowsershotPdfRenderer;
use App\Reports\PdfRenderer;
use Illuminate\Support\ServiceProvider;

class ReportServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PdfRenderer::class, BrowsershotPdfRenderer::class);
    }
}
