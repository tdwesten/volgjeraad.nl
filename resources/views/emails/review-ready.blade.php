<x-mail::message>
Hoi,

De samenvatting van **{{ $meeting->name }}**@if($meeting->starts_at) ({{ $meeting->starts_at->format('d-m-Y') }})@endif staat klaar voor review.

<x-mail::button :url="$reviewUrl">
Ga naar de review-pagina
</x-mail::button>

Na goedkeuring wordt de nieuwsbrief automatisch verstuurd naar de abonnees.
</x-mail::message>
