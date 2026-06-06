<x-mail::message>
# Bevestig je aanmelding

Bedankt voor je aanmelding bij Volgjeraad voor **{{ $municipalityName }}**.

Klik op de knop hieronder om je aanmelding te bevestigen:

<x-mail::button :url="$confirmUrl">
Aanmelding bevestigen
</x-mail::button>

Of kopieer deze link in je browser:
{{ $confirmUrl }}

*Je ontvangt dit e-mailbericht omdat iemand zich heeft aangemeld met dit e-mailadres via Volgjeraad. Als je je niet hebt aangemeld, kun je dit bericht negeren.*
</x-mail::message>
