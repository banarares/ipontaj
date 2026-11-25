<x-mail::layout>
<x-slot:header><x-mail::header :url="config('app.url')">iPontaj</x-mail::header></x-slot:header>
{!! $slot !!}

@isset($subcopy)<x-slot:subcopy><x-mail::subcopy>{!! $subcopy !!}</x-mail::subcopy></x-slot:subcopy>
@endisset
<x-slot:footer><x-mail::footer>© {{ now()->year }} iPontaj · Timp pentru ce contează.<br>Toate drepturile rezervate. [Contact]({{ rtrim(config('app.url'), '/') }}/contact)</x-mail::footer></x-slot:footer>
</x-mail::layout>
