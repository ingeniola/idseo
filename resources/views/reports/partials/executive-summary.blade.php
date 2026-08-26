@php
    $delta = $data->currentMetrics->visibilityScoreDeltaFrom($data->previousMetrics);
    $deltaClass = $delta === null ? 'delta-flat' : ($delta > 0 ? 'delta-up' : ($delta < 0 ? 'delta-down' : 'delta-flat'));
@endphp
<section>
    <h2>Resumen ejecutivo</h2>

    @if ($data->currentMetrics->visibilityScore === null)
        <p class="empty-state">Todavía no hay suficientes datos de visibilidad para este proyecto.</p>
    @else
        <div class="stats">
            <div class="stat">
                <div class="label">Visibilidad</div>
                <div class="value">{{ number_format($data->currentMetrics->visibilityScore, 1) }}</div>
                <div class="delta {{ $deltaClass }}">
                    @if ($delta === null)
                        Sin dato del período anterior
                    @else
                        {{ $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '—') }} {{ number_format(abs($delta), 1) }} vs. período anterior
                    @endif
                </div>
            </div>
            <div class="stat">
                <div class="label">Keywords rastreadas</div>
                <div class="value">{{ $data->currentMetrics->trackedKeywordsCount }}</div>
            </div>
            <div class="stat">
                <div class="label">En top 3</div>
                <div class="value">{{ $data->currentMetrics->keywordsInTop3 }}</div>
            </div>
            <div class="stat">
                <div class="label">En top 10</div>
                <div class="value">{{ $data->currentMetrics->keywordsInTop10 }}</div>
            </div>
            <div class="stat">
                <div class="label">Posición promedio</div>
                <div class="value">{{ $data->currentMetrics->averagePosition !== null ? number_format($data->currentMetrics->averagePosition, 1) : '—' }}</div>
            </div>
        </div>
    @endif
</section>
