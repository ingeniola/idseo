<?php

declare(strict_types=1);

namespace App\Reports;

use Spatie\Browsershot\Browsershot;

final class BrowsershotPdfRenderer implements PdfRenderer
{
    public function render(string $html, string $absoluteOutputPath): void
    {
        $browsershot = Browsershot::html($html)
            ->noSandbox()
            ->format('A4')
            ->showBackground()
            ->timeout((int) config('browsershot.timeout'));

        $chromePath = config('browsershot.chrome_path');

        if (filled($chromePath)) {
            $browsershot->setChromePath((string) $chromePath);
        }

        $browsershot->save($absoluteOutputPath);
    }
}
