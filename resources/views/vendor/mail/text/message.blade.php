<x-mail::layout>
<x-slot:header><x-mail::header :url="config('app.url')">iPontaj · Timp pentru ce contează.</x-mail::header></x-slot:header>
{{ $slot }}

@isset($subcopy)<x-slot:subcopy><x-mail::subcopy>{{ $subcopy }}</x-mail::subcopy></x-slot:subcopy>
@endisset
<x-slot:footer><x-mail::footer>© {{ now()->year }} iPontaj. Toate drepturile rezervate.</x-mail::footer></x-slot:footer>
</x-mail::layout>
