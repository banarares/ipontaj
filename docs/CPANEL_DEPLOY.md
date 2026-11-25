# Pachetul privat pentru cPanel

Utilitarul local este `.local-tools/backup_for_cpanel.py`, adaptat pentru iPontaj după modelul Poiana Fermecată. Directorul `.local-tools/`, rezultatele din `backup/` și cache-urile Python sunt ignorate de Git. Acestea nu sunt incluse nici în arhiva publică a sursei AGPL. Scriptul privat nu este livrat prin repository; păstrează-l separat în mediul operatorului.

```bash
python3 .local-tools/backup_for_cpanel.py
```

Implicit se pregătește o **instalare inițială cu bază goală**, fără utilizatori sau date demo. Pentru actualizarea unui site deja live folosește numai codul:

```bash
python3 .local-tools/backup_for_cpanel.py --database-mode=none
```

Transferul intenționat al datelor locale necesită `--database-mode=data`; prezența conturilor de test/demo oprește exportul dacă nu există și opțiunea explicită `--allow-test-data`.

Rulează din orice director. Sunt necesare PHP/Composer, Node 24.11+, dependențele frontend locale, Python 3 și utilitarele MySQL. Utilitarul folosește configurația Laravel locală, nu are parole scrise în cod și nu afișează acreditările bazei.

## Rezultate

Fiecare execuție reușită creează un director privat `backup/ipontaj-<data-ora>/` cu:

- `application.zip`: codul aplicației, buildul Vite și dependențele PHP de producție, instalate separat de vendorul de dezvoltare.
- `database.schema.mysql.sql`: schema goală pentru instalarea inițială; în modul `data`, fișierul este `database.data.mysql.sql` și include datele sursei. Modul `none` nu produce SQL.
- Backupul original complet al bazei, SQLite sau SQL nativ, numai în modurile `schema` și `data`. Modul `none` nu citește baza și nu produce backupul ei.
- `verification.json`: numere de rânduri și amprente pentru verificarea transferului.
- `SHA256SUMS.json` și instrucțiuni de instalare.

Fișierele sunt private; baza poate conține conturi, parole hash, date personale, invitații și istoric. Nu încărca acest director pe GitHub, într-un issue sau sub document root public. Nu trimite backupuri brute unui colaborator.

## Verificarea bazei

Pentru SQLite se creează o copie consistentă prin API-ul de backup SQLite, nu prin copierea unui fișier activ. Schema MySQL se construiește din migrările aplicației într-un server temporar, izolat de rețea. Transferul verifică tabelele, coloanele, rândurile, datele JSON și integritatea referințelor. SQL-ul este restaurat într-o a doua bază temporară și comparat cu exportul.

Pentru o sursă MySQL/MariaDB se folosește dumpul nativ, cu o configurare temporară privată pentru client; parola nu apare în argumentele procesului. Baza locală nu este modificată. Niciun server MySQL existent nu este oprit sau suprascris.

Backupul original păstrează baza completă. În modul `schema`, SQL-ul de instalare nu conține conturi sau date de activitate. În modul `data`, exportul golește sesiunile, resetările de parolă, cache-ul și cozile de joburi/eșuări, păstrând datele de activitate și evidențele de deduplicare relevante. Verifică raportul privind datele fictive înainte de orice transfer intenționat.

La eroare nu rămâne un pachet marcat ca finalizat. Diagnosticele sunt păstrate în directorul privat al utilitarului și nu trebuie publicate.

## Instalare pe găzduire

1. Pentru instalare inițială creează o bază MySQL nouă în cPanel și importă `database.schema.mysql.sql`. Pentru actualizare live folosește modul `none` și păstrează baza existentă. Niciun SQL din pachet nu se importă peste baza live.
2. Extrage numai `application.zip` în directorul aplicației. Configurează document root al domeniului către subdirectorul `public/`.
3. Configurează `.env` separat, cu APP_ENV=production, APP_DEBUG=false, HTTPS, credențialele MySQL și SMTP. `.env` nu este în ZIP.
4. Dacă transferi conturi cu date criptate/2FA, păstrează în siguranță APP_KEY corespunzător bazei originale; nu genera o cheie nouă la întâmplare. Nu pune cheia în Git sau în documentație.
5. Rulează `php artisan package:discover`, `php artisan migrate --force` și `php artisan storage:link`, apoi pașii pentru cache, cozi și scheduler din OPERATIONS.md.
6. Verifică restaurarea, autentificarea, accesul între firme, intervalele, exportul, emailurile și webhookul de test.
7. Finalizează verificările din `php artisan pontaj:legal:check`. Existența ZIP-ului nu activează plăți și nu certifică pregătirea comercială/juridică.

Nu sunt incluse în arhiva aplicației: configurarea secretă, baze locale, loguri, sesiuni/cache, node_modules, codul utilitarului privat, istoricul Git sau directoarele de lucru ale agentului. Arhiva sursei corespunzătoare AGPL este inclusă separat în locația privată din care o servește ruta `/cod-sursa`.

## cPanel fără shell: PHP 8.3 și cron

Pentru contul verificat, executabilul CLI este `/usr/local/bin/ea-php83`. În exemplele de mai jos înlocuiește `UTILIZATOR` și calea aplicației cu valorile contului. Rapoartele rămân în afara `public_html`.

După modificarea `.env`, rulează o singură dată prin cron, apoi elimină jobul:

```bash
/usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan config:cache --no-ansi > /home/UTILIZATOR/ipontaj-config.log 2>&1
```

Verificarea declarativă a demersurilor, tot printr-un cron temporar:

```bash
/usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan pontaj:legal:check --no-ansi > /home/UTILIZATOR/ipontaj-legal-check.log 2>&1
```

În cPanel setează frecvența la fiecare minut pentru scheduler. În câmpul de comandă se pune numai comanda, fără cele cinci asteriscuri:

```cron
* * * * * /usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan schedule:run > /dev/null 2>&1
```

Pentru coada `database`, în lipsa unui worker persistent, poate fi folosit un cron pe minut:

```cron
* * * * * /usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan pontaj:queue:work > /home/UTILIZATOR/ipontaj-queue.log 2>&1
```

Workerul folosește un lock de 180 de secunde în cache-ul comun, se oprește când coada este goală și are limita de 50 de secunde, 3 încercări și timeout de 45 de secunde per job. Timeoutul trebuie să fie mai mic decât `retry_after` din configurarea cozii. SMTP are timeout de 15 secunde. Nu configura în paralel și vechiul cron `queue:work`. Verifică rezultatele în loguri și procesarea unui job controlat. Raportul juridic nu trimite emailuri și nu înlocuiește această verificare.

Valorile `LEGAL_*`, sursele și dovezile necesare sunt explicate în [LEGAL_ENV.md](LEGAL_ENV.md). Checklistul este un raport pentru operator, separat de blocajele tehnice. Checkoutul verifică activarea explicită, cheile din același mod, secretul webhookului, prețul și acceptarea condițiilor. Nu modifica flagurile pentru a simula contracte sau teste neefectuate.

După încărcarea versiunii din 25 noiembrie, aplică migrarea pentru notificări (`php artisan migrate --force`) înainte de a redeschide accesul. Curăță view-urile compilate cu `php artisan view:clear` și refă configurația cu `config:cache`. Păstrează `APP_KEY` și baza live existente. Nu importa baza locală de test.

Pentru contact/invitații/confirmări sunt necesare **ambele cronuri pe minut**: workerul procesează emailurile; schedulerul lansează mementourile și curățările. Diagnosticul fără expediere sau secrete se rulează temporar:

```bash
/usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan pontaj:delivery:status --no-ansi > /home/UTILIZATOR/ipontaj-delivery-status.log 2>&1
```

Raportul arată numărul joburilor în așteptare/eșuate, ultima execuție a workerului și prezența configurării SMTP. Nu arată parole, destinatari privați sau conținutul joburilor. Dacă există eșuări vechi, repară mai întâi cauza SMTP și verifică joburile înainte de `queue:retry`; joburile deja eșuate nu sunt reluate doar prin pornirea workerului. Pentru portul 465 folosește `MAIL_MAILER=smtp` și `MAIL_SCHEME=smtps`, cu numele SMTP și acreditările oferite de furnizor. Raportul nu dovedește primirea efectivă în inbox.
