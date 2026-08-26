<section>
    <h2>Keywords nuevas en top 10</h2>

    @if ($positions->isEmpty())
        <p class="empty-state">Ninguna keyword entró al top 10 en este período.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Keyword</th>
                    <th>Posición anterior</th>
                    <th>Posición actual</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($positions as $position)
                    <tr>
                        <td>{{ $position->keyword }}</td>
                        <td>{{ $position->positionStart ?? 'Sin dato / fuera del top 10' }}</td>
                        <td>{{ $position->positionEnd }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
