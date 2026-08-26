@php
    use App\Enums\ReportSection;
    use Illuminate\Support\Carbon;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Reporte SEO — {{ $data->project->name }}</title>
<style>
    * { box-sizing: border-box; }
    body {
        font-family: 'Helvetica Neue', Arial, sans-serif;
        color: #1f2937;
        margin: 0;
        padding: 0;
        font-size: 12px;
    }
    .page { padding: 32px 40px; }
    .header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 3px solid {{ $data->primaryColor }};
        padding-bottom: 16px;
        margin-bottom: 24px;
    }
    .header img { max-height: 48px; max-width: 220px; }
    .header h1 { font-size: 20px; margin: 0; color: {{ $data->primaryColor }}; }
    .header p { margin: 4px 0 0; color: #6b7280; }
    .header .period { text-align: right; }
    section { margin-bottom: 28px; page-break-inside: avoid; }
    section h2 {
        font-size: 15px;
        color: {{ $data->primaryColor }};
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 6px;
        margin-bottom: 12px;
    }
    .stats { display: flex; gap: 16px; flex-wrap: wrap; }
    .stat {
        flex: 1;
        min-width: 120px;
        background: #f9fafb;
        border-radius: 6px;
        padding: 12px;
    }
    .stat .label { color: #6b7280; font-size: 10px; text-transform: uppercase; }
    .stat .value { font-size: 18px; font-weight: bold; margin-top: 4px; }
    .stat .delta { font-size: 11px; margin-top: 2px; }
    .delta-up { color: #059669; }
    .delta-down { color: #dc2626; }
    .delta-flat { color: #6b7280; }
    table { width: 100%; border-collapse: collapse; }
    th, td { text-align: left; padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
    th { color: #6b7280; font-size: 10px; text-transform: uppercase; }
    .empty-state { color: #6b7280; font-style: italic; }
    footer { color: #9ca3af; font-size: 10px; text-align: center; margin-top: 32px; }
</style>
</head>
<body>
<div class="page">

    <div class="header">
        <div>
            @if ($data->logoDataUri)
                <img src="{{ $data->logoDataUri }}" alt="{{ $data->client->name }}">
            @else
                <h1>{{ $data->client->name }}</h1>
            @endif
            <p>{{ $data->project->name }} — {{ $data->project->domain }}</p>
        </div>
        <div class="period">
            <h1>Reporte SEO</h1>
            <p>{{ Carbon::parse($data->report->period_start)->translatedFormat('d \d\e F \d\e Y') }} — {{ Carbon::parse($data->report->period_end)->translatedFormat('d \d\e F \d\e Y') }}</p>
        </div>
    </div>

    @if ($data->hasSection(ReportSection::ExecutiveSummary))
        @include('reports.partials.executive-summary')
    @endif

    @if ($data->hasSection(ReportSection::VisibilityEvolution))
        @include('reports.partials.visibility-evolution')
    @endif

    @if ($data->hasSection(ReportSection::PositionsTable))
        @include('reports.partials.positions-table')
    @endif

    @if ($data->hasSection(ReportSection::TopGains))
        @include('reports.partials.keyword-list', ['title' => 'Top ganancias', 'positions' => $data->topGains, 'emptyMessage' => 'Ninguna keyword mejoró de posición en este período.'])
    @endif

    @if ($data->hasSection(ReportSection::TopLosses))
        @include('reports.partials.keyword-list', ['title' => 'Top pérdidas', 'positions' => $data->topLosses, 'emptyMessage' => 'Ninguna keyword perdió posiciones en este período.'])
    @endif

    @if ($data->hasSection(ReportSection::NewKeywordsInTop10))
        @include('reports.partials.new-top10', ['positions' => $data->newKeywordsInTop10])
    @endif

    <footer>
        Reporte generado el {{ Carbon::now()->translatedFormat('d \d\e F \d\e Y') }}
    </footer>

</div>
</body>
</html>
