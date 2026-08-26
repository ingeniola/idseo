<section>
    <h2>Tabla de posiciones</h2>

    @if ($data->positions->isEmpty())
        <p class="empty-state">Este proyecto no tiene keywords activas.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Keyword</th>
                    <th>Posición</th>
                    <th>URL</th>
                    <th>Volumen</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data->positions as $position)
                    <tr>
                        <td>{{ $position->keyword }}</td>
                        <td>{{ $position->positionEnd ?? '—' }}</td>
                        <td>{{ $position->url ?? '—' }}</td>
                        <td>{{ $position->searchVolume !== null ? number_format($position->searchVolume) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
