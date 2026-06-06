<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>{{ $newsletter->subject }}</title>
</head>
<body style="font-family: sans-serif; max-width: 600px; margin: 0 auto; padding: 24px; color: #1a1a1a;">
    <p style="color: #6b7280; font-size: 13px; margin-bottom: 24px;">
        <a href="{{ $webUrl }}" style="color: #6b7280;">Bekijk deze e-mail in je browser &rarr;</a>
    </p>

    <h1 style="font-size: 22px; font-weight: 700; margin-bottom: 4px;">{{ $newsletter->subject }}</h1>

    @if($level->value === 'simple')
        <p style="font-size: 13px; color: #6b7280; margin-bottom: 24px;">
            Eenvoudige versie (B1-niveau)
        </p>
    @endif

    @if($newsletter->intro)
        <div style="margin-bottom: 32px; padding: 16px; background: #f9fafb; border-radius: 6px;">
            <p style="margin: 0; font-size: 15px;">{{ $newsletter->intro }}</p>
        </div>
    @endif

    @foreach($summaries as $summary)
        <div style="margin-bottom: 32px; padding-bottom: 32px; border-bottom: 1px solid #e5e7eb;">
            <h2 style="font-size: 17px; font-weight: 600; margin-bottom: 8px;">{{ $summary->title }}</h2>
            <div style="font-size: 14px; line-height: 1.6;">{!! Str::markdown($summary->body) !!}</div>
        </div>
    @endforeach

    <hr style="border: none; border-top: 1px solid #e5e7eb; margin: 32px 0;">

    <p style="color: #6b7280; font-size: 12px; margin-bottom: 8px;">
        Dit is een automatisch gegenereerde samenvatting door Volgjeraad. Controleer altijd de officiële bronnen.
    </p>

    <p style="font-size: 12px;">
        <a href="{{ $unsubscribeUrl }}" style="color: #6b7280;">Uitschrijven</a>
        &nbsp;&middot;&nbsp;
        <a href="{{ $municipalityUrl }}" style="color: #6b7280;">Naar gemeente-pagina</a>
    </p>
</body>
</html>
