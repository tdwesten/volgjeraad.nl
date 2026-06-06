<x-mail::message>
<x-mail::panel>
Automatisch samengevat door AI. Controleer altijd de bronnen voor officiële informatie.
</x-mail::panel>

[Bekijk deze e-mail in je browser →]({{ $webUrl }})

# {{ $newsletter->subject }}

@if($level->value === 'simple')
*Eenvoudige versie (B1-niveau)*

@endif
@if($newsletter->intro)
{{ $newsletter->intro }}

@endif
@foreach($summaries as $summary)
## {{ $summary->title }}

{!! $summary->body !!}

---

@endforeach

*Controleer voor beslissingen altijd de officiële bronnen.*

[Uitschrijven]({{ $unsubscribeUrl }}) · [Naar gemeente-pagina]({{ $municipalityUrl }})
</x-mail::message>
