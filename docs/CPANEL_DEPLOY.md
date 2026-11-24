# Pachetul privat pentru cPanel

Utilitarul local este `.local-tools/backup_for_cpanel.py`, adaptat pentru iPontaj după modelul Poiana Fermecată. Directorul `.local-tools/`, rezultatele din `backup/` și cache-urile Python sunt ignorate de Git. Acestea nu sunt incluse nici în arhiva publică a sursei AGPL. Scriptul privat nu este livrat prin repository; păstrează-l separat în mediul operatorului.

```bash
python3 .local-tools/backup_for_cpanel.py
```

Rulează din orice director. Sunt necesare PHP/Composer, Node 24.11+, dependențele frontend locale, Python 3 și utilitarele MySQL. Utilitarul folosește configurația Laravel locală, nu are parole scrise în cod și nu afișează acreditările bazei.

## Rezultate

Fiecare execuție reușită creează un director privat `backup/ipontaj-<data-ora>/` cu:

- `application.zip`: codul aplicației, buildul Vite și dependențele PHP de producție, instalate separat de vendorul de dezvoltare.
- `database.mysql.sql`: baza pregătită pentru import într-un MySQL nou.
- Backupul original complet al bazei: SQLite sau SQL nativ, potrivit sursei curente.
- `verification.json`: numere de rânduri și amprente pentru verificarea transferului.
- `SHA256SUMS.json` și instrucțiuni de instalare.

Fișierele sunt private; baza poate conține conturi, parole hash, date personale, invitații și istoric. Nu încărca acest director pe GitHub, într-un issue sau sub document root public. Nu trimite backupuri brute unui colaborator.

## Verificarea bazei

Pentru SQLite se creează o copie consistentă prin API-ul de backup SQLite, nu prin copierea unui fișier activ. Schema MySQL se construiește din migrările aplicației într-un server temporar, izolat de rețea. Transferul verifică tabelele, coloanele, rândurile, datele JSON și integritatea referințelor. SQL-ul este restaurat într-o a doua bază temporară și comparat cu exportul.

Pentru o sursă MySQL/MariaDB se folosește dumpul nativ, cu o configurare temporară privată pentru client; parola nu apare în argumentele procesului. Baza locală nu este modificată. Niciun server MySQL existent nu este oprit sau suprascris.

Backupul original păstrează baza completă. Exportul pentru deploy golește sesiunile, resetările de parolă, cache-ul și cozile de joburi/eșuări, ca să nu transfere autentificări locale sau să expedieze joburi vechi. Datele de activitate și evidențele de deduplicare relevante sunt păstrate. Setul curent poate conține conturi fictive de test; verifică-l înainte de lansare.

La eroare nu rămâne un pachet marcat ca finalizat. Diagnosticele sunt păstrate în directorul privat al utilitarului și nu trebuie publicate.

## Instalare pe găzduire

1. Creează o bază MySQL nouă în cPanel și importă `database.mysql.sql`. Nu importa peste o bază live existentă fără plan și backup verificat.
2. Extrage numai `application.zip` în directorul aplicației. Configurează document root al domeniului către subdirectorul `public/`.
3. Configurează `.env` separat, cu APP_ENV=production, APP_DEBUG=false, HTTPS, credențialele MySQL și SMTP. `.env` nu este în ZIP.
4. Dacă transferi conturi cu date criptate/2FA, păstrează în siguranță APP_KEY corespunzător bazei originale; nu genera o cheie nouă la întâmplare. Nu pune cheia în Git sau în documentație.
5. Rulează `php artisan package:discover`, `php artisan migrate --force` și `php artisan storage:link`, apoi pașii pentru cache, cozi și scheduler din OPERATIONS.md.
6. Verifică restaurarea, autentificarea, accesul între firme, intervalele, exportul, emailurile și webhookul de test.
7. Finalizează verificările din `php artisan pontaj:legal:check`. Existența ZIP-ului nu activează plăți și nu certifică pregătirea comercială/juridică.

Nu sunt incluse în arhiva aplicației: configurarea secretă, baze locale, loguri, sesiuni/cache, node_modules, codul utilitarului privat, istoricul Git sau directoarele de lucru ale agentului. Arhiva sursei corespunzătoare AGPL este inclusă separat în locația privată din care o servește ruta `/cod-sursa`.
