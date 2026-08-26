@component('mail::message')
# Su reporte SEO ya está disponible

Adjuntamos el reporte de **{{ $projectName }}** correspondiente al período del {{ \Illuminate\Support\Carbon::parse($periodStart)->translatedFormat('d \d\e F') }} al {{ \Illuminate\Support\Carbon::parse($periodEnd)->translatedFormat('d \d\e F \d\e Y') }}.

Si tiene alguna pregunta sobre los resultados, no dude en contactarnos.

Saludos.
@endcomponent
