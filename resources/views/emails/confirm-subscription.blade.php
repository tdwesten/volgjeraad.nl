<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Bevestig je aanmelding</title>
</head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1a1a1a;">
    <h1 style="font-size: 20px; font-weight: 600;">Bevestig je aanmelding</h1>

    <p>Bedankt voor je aanmelding bij Volgjeraad voor <strong>{{ $municipalityName }}</strong>.</p>

    <p>Klik op de knop hieronder om je aanmelding te bevestigen:</p>

    <p style="margin: 32px 0;">
        <a href="{{ $confirmUrl }}"
           style="background-color: #18181b; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 6px; font-weight: 500;">
            Aanmelding bevestigen
        </a>
    </p>

    <p style="color: #6b7280; font-size: 14px;">
        Of kopieer deze link in je browser:<br>
        <a href="{{ $confirmUrl }}" style="color: #6b7280;">{{ $confirmUrl }}</a>
    </p>

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 32px 0;">

    <p style="color: #6b7280; font-size: 12px;">
        Je ontvangt dit e-mailbericht omdat iemand zich heeft aangemeld met dit e-mailadres via Volgjeraad.
        Als je je niet hebt aangemeld, kun je dit bericht negeren.
    </p>
</body>
</html>
