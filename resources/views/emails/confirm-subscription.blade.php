<x-mail::message>
# Bevestig je aanmelding

Bedankt voor je aanmelding bij **Volg je raad** voor **{{ $municipalityName }}**.

Klik op de knop hieronder om je aanmelding te bevestigen:

<x-mail::button :url="$confirmUrl">
Aanmelding bevestigen
</x-mail::button>

Of kopieer deze link in je browser:
{{ $confirmUrl }}

*Je ontvangt dit e-mailbericht omdat iemand zich heeft aangemeld met dit e-mailadres via Volg je raad. Als je je niet hebt aangemeld, kun je dit bericht negeren.*

<x-mail::subcopy>
Volg je raad is nog in ontwikkeling (beta): functies en samenvattingen kunnen nog veranderen of fouten bevatten. Controleer bij twijfel altijd de officiële bronnen.
</x-mail::subcopy>
</x-mail::message>
