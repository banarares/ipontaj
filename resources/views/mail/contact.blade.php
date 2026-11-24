<!doctype html><html lang="ro"><body>
<h1>Mesaj nou în iPontaj</h1>
<p>Referință: {{ $reference }}</p>
<p>De la: {{ $data['first_name'] }} {{ $data['last_name'] }} &lt;{{ $data['email'] }}&gt;</p>
<p>Subiect: {{ $data['subject'] }}</p>
<hr>
<p style="white-space: pre-wrap">{{ $data['message'] }}</p>
</body></html>
