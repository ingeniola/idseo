@php
    $chart = $data->visibilityChart;
@endphp
<section>
    <h2>Evolución de visibilidad</h2>

    @if ($data->visibilityEvolution->isEmpty())
        <p class="empty-state">No hay snapshots de visibilidad dentro de este período.</p>
    @else
        <svg width="{{ $chart['width'] }}" height="{{ $chart['height'] }}" viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}">
            <polyline
                points="{{ $chart['polyline'] }}"
                fill="none"
                stroke="{{ $data->primaryColor }}"
                stroke-width="2"
            />
            @foreach ($chart['dots'] as $dot)
                <circle cx="{{ $dot['x'] }}" cy="{{ $dot['y'] }}" r="3" fill="{{ $data->primaryColor }}" />
            @endforeach
            @foreach ($chart['labels'] as $label)
                <text x="{{ $label['x'] }}" y="{{ $chart['height'] - 8 }}" font-size="9" fill="#6b7280" text-anchor="middle">{{ $label['label'] }}</text>
            @endforeach
        </svg>
    @endif
</section>
