<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Report;
use App\Reports\GenerateReportPdf;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReport implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $reportId,
    ) {}

    public function handle(GenerateReportPdf $generator): void
    {
        $report = Report::query()->find($this->reportId);

        if ($report === null) {
            return;
        }

        $generator->execute($report);
    }
}
