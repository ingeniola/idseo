<section>
    <h2>{{ $title }}</h2>

    @if ($positions->isEmpty())
        <p class="empty-state">{{ $emptyMessage }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Keyword</th>
                    <th>Antes</th>
                    <th>Ahora</th>
                    <th>Cambio</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($positions as $position)
                    @php $delta = $position->delta(); @endphp
                    <tr>
                        <td>{{ $position->keyword }}</td>
                        <td>{{ $position->positionStart ?? '—' }}</td>
                        <td>{{ $position->positionEnd ?? '—' }}</td>
                        <td class="{{ $delta > 0 ? 'delta-up' : 'delta-down' }}">
                            {{ $delta > 0 ? '▲' : '▼' }} {{ abs($delta) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
