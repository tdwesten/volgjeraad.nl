<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Samenvatting klaar voor review</title>
</head>
<body style="font-family: sans-serif; font-size: 15px; color: #222; max-width: 600px; margin: 0 auto; padding: 24px;">
    <p>Hoi,</p>

    <p>
        De samenvatting van <strong>{{ $meeting->name }}</strong>
        @if($meeting->starts_at)
            ({{ $meeting->starts_at->format('d-m-Y') }})
        @endif
        staat klaar voor review.
    </p>

    <p>
        <a href="{{ $reviewUrl }}" style="color: #2563eb;">Ga naar de review-pagina</a>
    </p>

    <p>Na goedkeuring wordt de nieuwsbrief automatisch verstuurd naar de abonnees.</p>

    <p style="color: #888; font-size: 13px;">Dit bericht is automatisch gegenereerd door Volgjeraad.</p>
</body>
</html>
