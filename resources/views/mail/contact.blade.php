<x-mail::message>
# Mesaj nou în iPontaj

Referință: {{ $reference }}

De la: {{ $data['first_name'] }} {{ $data['last_name'] }} &lt;{{ $data['email'] }}&gt;

Subiect: {{ $data['subject'] }}

<div style="white-space:pre-wrap">{{ $data['message'] }}</div>

Poți răspunde direct acestui email pentru a contacta expeditorul.
</x-mail::message>
