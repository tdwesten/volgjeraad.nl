<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
Volg je raad
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
*Deze e-mail is automatisch met AI gegenereerd.*

Volg je raad — samenvattingen van gemeenteraadsvergaderingen

Een [open source](https://github.com/tdwesten/volgjeraad.nl) tool gemaakt door Thomas van der Westen — [Codesmiths.nl](https://codesmiths.nl)
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
