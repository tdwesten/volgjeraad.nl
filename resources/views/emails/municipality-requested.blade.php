<x-mail::message>
# Nieuwe gemeente-aanvraag

Iemand wil **Volg je raad** graag voor de volgende gemeente:

**{{ $municipalityName }}**

@if($requesterEmail)
Contact (optioneel opgegeven): {{ $requesterEmail }}
@else
Er is geen contact-e-mailadres opgegeven.
@endif

Bekijk of deze gemeente toegevoegd kan worden.
</x-mail::message>
