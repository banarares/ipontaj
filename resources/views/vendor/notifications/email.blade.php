<x-mail::message>
# {{ $greeting ?: 'Bună!' }}


@foreach ($introLines as $line)
{{ $line }}


@endforeach

@isset($actionText)
<x-mail::button :url="$actionUrl" color="success">{{ $actionText }}</x-mail::button>

@endisset

@foreach ($outroLines as $line)
{{ $line }}


@endforeach
{{ $salutation ?: 'Echipa iPontaj' }}


@isset($actionText)
<x-slot:subcopy>Dacă butonul „{{ $actionText }}” nu funcționează, copiază acest link în browser: <span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span></x-slot:subcopy>

@endisset
</x-mail::message>
