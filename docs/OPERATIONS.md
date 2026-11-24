# Operare, backup și date

## Găzduire

O singură aplicație Laravel, cu rădăcina web în `public/`. Frontendul compilat este în `public/build/`. Folosește PHP 8.3+ cu `pdo_pgsql`, PostgreSQL, HTTPS, un proces de coadă supravegheat și schedulerul Laravel. Node 24 este necesar la build.

Exemplu de configurare, cu valori proprii în secret manager:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://adresa-aleasa-de-operator
APP_LOCALE=ro
DB_CONNECTION=pgsql
DB_HOST=adresa-bazei
DB_PORT=5432
DB_DATABASE=ipontaj
DB_USERNAME=ipontaj
DB_PASSWORD=
DB_SSLMODE=require
SESSION_SECURE_COOKIE=true
SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
BILLING_ENABLED=false
SELF_HOSTED_SEAT_LIMIT=15
```

Păstrează `APP_KEY`. Configurează SMTP și un expeditor autorizat; `MAIL_MAILER=log` nu livrează emailuri. Nu expune `.env`, logurile sau baza de date prin serverul web. Configurează proxyurile de încredere pentru mediul ales, fără a accepta arbitrar antete de la clienți.

Pași de release după aprobarea destinației:

```bash
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan config:cache
php artisan view:cache
php artisan queue:restart
```

Repornește procesul de coadă la fiecare release. Rulează permanent `php artisan queue:work --tries=3 --timeout=90`, printr-un manager de procese. Schedulerul necesită un cron pe minut:

```cron
* * * * * cd /cale/catre/ipontaj && php artisan schedule:run >> /dev/null 2>&1
```

`pontaj:remind` rulează la 15 minute și verifică fusul firmei, preferințele și starea zilei înainte de notificare. `pontaj:clean-demo` rulează orar. Verifică `php artisan queue:failed` și logurile. Programările nu funcționează fără cron sau `schedule:work`.

Deduplicarea aplicației previne retrimiterea obișnuită a aceluiași memento/export la retry. Un proces oprit după acceptarea emailului de către SMTP, înainte de salvarea confirmării locale, poate totuși produce o retrimitere; livrarea SMTP nu participă la tranzacția bazei. Testarea cu furnizorul real și monitorizarea rămân necesare.

## Backup și restaurare

Operatorul stabilește frecvența, retenția tehnică, locul criptat și obiectivele de recuperare. Păstrează separat baza PostgreSQL, fișierele private necesare și configurarea secretă, inclusiv `APP_KEY`. Nu pune backupurile în repository sau în `public/`.

Exemplu de backup PostgreSQL, folosind o conexiune configurată securizat prin mecanismele libpq:

```bash
pg_dump --format=custom --file=/cale-privata/ipontaj.dump ipontaj
```

Restaurează întâi într-o bază separată și verifică accesul și exporturile:

```bash
pg_restore --no-owner --dbname=ipontaj_restore /cale-privata/ipontaj.dump
```

Nu suprascrie producția fără autorizare și un backup verificat. După restaurare, verifică migrările, firmele, membrii, intervalele, auditul și un export Excel. Reconcilierea cu Stripe și cu emailurile deja expediate trebuie analizată înainte de pornirea cozilor restaurate.

Pentru SQLite local, oprește scrierile sau folosește mecanismul de backup SQLite; nu copia la întâmplare doar fișierul principal în timpul unei tranzacții. Restaurarea reală și testele pe infrastructura de producție nu au fost executate în acest workspace.

## Export și retenție

Proprietarul poate descărca Excelul lunar și, din Setări, exportul JSON al firmei: firmă, membri, programe, intervale, închideri și audit. Parolele și identificatorii Stripe nu sunt incluși în exportul de firmă. Accesul este verificat pe server.

Demo-ul se șterge după 24 de ore prin scheduler. Datele reale și auditul nu sunt șterse automat în această versiune. Operatorul stabilește și documentează perioadele de păstrare a pontajului, auditului, logurilor, invitațiilor expirate, joburilor și backupurilor, împreună cu persoanele responsabile. Nu se presupune o durată legală universală.

Logurile pot conține emailuri în dezvoltare. Restricționează accesul și configurează rotația/expirarea lor. Tokenurile din linkuri și URL-urile semnate nu trebuie copiate în rapoarte publice.

## Ștergerea contului și a firmei

Un utilizator fără apartenențe poate șterge definitiv contul din Contul meu, cu parola curentă. Un cont cu apartenențe intră într-un proces asistat de operator, pentru a evita ștergerea accidentală a pontajelor și a istoricului firmei.

La o cerere de ștergere:

1. Verifică identitatea solicitantului și dreptul de a solicita ștergerea firmei sau a datelor persoanei.
2. Explică exact datele vizate: profil, apartenențe, invitații, intervale, programe, aprobări și audit, plus ce rămâne temporar în backupuri.
3. Clarifică obligațiile de păstrare și datele care trebuie păstrate sau anonimizate. Exportă datele pe care operatorul este autorizat să le furnizeze.
4. Oprește notificările, revocă accesul și linkurile necesare, apoi execută ștergerea/anonimizarea autorizată și consemnează operațiunea.
5. Aplică politica stabilită și backupurilor; explică momentul expirării copiilor.

Nu există încă un flux automat de ștergere definitivă a unei firme cu pontaj. Nu șterge direct un utilizator din baza de date pentru a ocoli acest proces: relațiile au ștergeri în cascadă care pot elimina istoricul.

## PWA și publicare

Manifestul și iconurile sunt publice. Service workerul păstrează numai pagina offline și iconurile iPontaj; nu cache-uiește HTML autentificat, răspunsuri Inertia sau exporturi. Răspunsurile aplicației au `Cache-Control: no-store, private`; istoricul Inertia este criptat și cheile sunt curățate la trecerea la o pagină neautentificată.

Instalarea PWA pe dispozitive reale, în special Safari/iOS, trebuie verificată după configurarea HTTPS. Telefonul are nevoie de internet pentru a vedea și salva pontajul.

Licența AGPL-3.0-only este confirmată. Înaintea publicării unui serviciu prin rețea, operatorul trebuie să asigure accesul la sursa corespunzătoare versiunii oferite, conform LICENSE, și să stabilească legătura publică spre sursă. Nu s-a efectuat deploy sau publicare pe GitHub.
