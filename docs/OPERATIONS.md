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

Pentru contul cPanel al operatorului, executabilul confirmat este `/usr/local/bin/ea-php83`. Exemplele complete de cron, inclusiv workerul limitat în timp și rapoartele private, sunt în [CPANEL_DEPLOY.md](CPANEL_DEPLOY.md).

`pontaj:remind` rulează la 15 minute și verifică fusul firmei, preferințele și starea zilei înainte de notificare. `pontaj:clean-demo` rulează la 10 minute. Verifică `php artisan queue:failed` și logurile. Programările nu funcționează fără cron sau `schedule:work`.

## Durata și volumul demo-urilor

Un demo creează un singur utilizator temporar, cu parolă aleatorie și fără adresă reală de email. Ceilalți trei colegi sunt profiluri de pontaj fără cont. Repetarea pornirii în aceeași sesiune o reutilizează; vizitatorii diferiți au firme separate, fără un cont demo comun.

```dotenv
DEMO_SESSION_MINUTES=60
DEMO_MAX_WORKSPACES=20
```

Durata curge de la creare, fără prelungire la fiecare cerere. Configurarea este limitată la 5–1440 minute și 1–200 spații. La ieșirea din demo sau deconectare se șterg firma fictivă, pontajele și utilizatorul temporar. O sesiune expirată nu mai poate modifica pontajul; vizitatorul poate porni alta. Închiderea browserului nu produce un eveniment sigur de ieșire, astfel că demo-urile abandonate sunt curățate de scheduler.

Pornirea unui demo curăță și datele expirate înainte de verificarea capacității, astfel încât acumularea nu depinde exclusiv de cron. O limită atinsă afișează un mesaj fără a mai crea rânduri. Crearea și curățarea folosesc același lock atomic din cache; în producție folosește un cache persistent și comun tuturor proceselor/nodurilor (`database` sau Redis), fără fallback la `array`/`null`. Nu șterge cache-ul în timpul pornirii demo-urilor. Testele pot folosi `array`; acesta nu coordonează procese diferite.

Pentru inspecție și curățare manuală:

```bash
php artisan pontaj:clean-demo --dry-run
php artisan pontaj:clean-demo
```

Comanda acoperă și demo-urile vechi cu patru conturi, utilizatorii demo orfani expirați și sesiunile lor din baza de date. Selectează explicit `is_demo`; nu identifică un cont doar după nume sau email. Păstrează firmele reale, firmele marcate demo care au membri reali, CIF, client Stripe, abonamente sau acceptări contractuale și utilizatorii care mai au apartenențe ori acceptări. Datele protejate neobișnuite necesită revizuire manuală; nu sunt șterse pentru a elibera capacitatea. Limita blochează alocări noi, nu evacuează vizitatori activi când este micșorată.

Nu se modifică baza live printr-un export SQL local. La actualizare păstrează utilizatorii și firmele reale, aplică noul cod și rulează numai comanda de curățare a demo-urilor. Un lock ocupat poate amâna curățarea la deconectare; expirarea și schedulerul o vor relua.

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

Colegii fără email sunt adăugați numai de proprietar, după confirmarea responsabilității și alegerea primei zile de pontaj. Nu primesc cont, parole sau mementouri. Numele/prenumele și dovada asumării se păstrează în apartenență; proprietarul înregistrează și aprobă orele, cu audit și motiv obligatoriu la corectarea lor. Profilurile inactive eliberează locul, iar reactivarea verifică din nou limita. Conversia ulterioară a unui profil fără cont într-un cont cu email nu este automatizată.

Prima zi poate fi corectată spre trecut de proprietar chiar din editorul zilnic, cu confirmare și motiv. Se extinde numai primul interval de activitate; pauzele ulterioare rămân protejate. Dacă schimbarea ar afecta luni deja aprobate, acestea trebuie redeschise. Nu modifica direct `joined_on` sau intervalele în baza de date pentru a ocoli auditul.

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

### Notificări și email — 25 noiembrie

Notificările interne sunt persistate în `user_notifications`, cu destinatar și firmă, fără texte de pontaj, parole sau tokenuri. Citirea și marcarea sunt limitate la destinatar și apartenențele active. Datele se șterg în cascadă la ștergerea contului/firmei și intră în exportul asistat al datelor persoanei. Ultimele 30 sunt afișate; această limită nu este o politică de ștergere a istoricului. Nu există integrare Firebase/push.

Autentificarea sau înregistrarea fără email confirmat încheie sesiunea și duce la pagina de confirmare. Linkul semnat poate fi deschis fără autentificare și nu creează sesiune; utilizatorul intră cu parola după confirmare. Sesiunile existente ale conturilor neconfirmate sunt închise. Modificarea emailului cere reconfirmare. Verifică separat livrarea reală, dosarul Spam și antetele SPF/DKIM/DMARC.

Folosește `pontaj:delivery:status` pentru raportul fără secrete și `pontaj:queue:work` în cron pe minut. Acesta procesează coada independent de scheduler; `schedule:run` nu înlocuiește workerul. Instrucțiunile pentru cPanel fără shell sunt în CPANEL_DEPLOY.md.
