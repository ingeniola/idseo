@component('mail::message')
# Alertas de ranking — {{ $projectName }}

Se detectaron {{ $alerts->count() }} alerta(s) hoy:

@component('mail::table')
| Keyword | Alerta | Antes | Ahora |
| :------ | :----- | :---: | :---: |
@foreach ($alerts as $alert)
| {{ $alert->keyword->keyword }} | {{ $alert->type->getLabel() }} | {{ $alert->previous_position ?? '—' }} | {{ $alert->current_position ?? '—' }} |
@endforeach
@endcomponent

Revise el proyecto en el panel para más detalle.

Saludos.
@endcomponent
