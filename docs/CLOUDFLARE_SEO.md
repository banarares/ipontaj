# Cloudflare, formulare publice, cookie-uri și indexare

Toate integrările externe sunt dezactivate implicit. Lucrul local și planul fără plăți nu necesită un cont Cloudflare. Nu salva chei reale în Git, issue-uri sau capturi.

## Proxy Cloudflare

Pe domeniul găzduit, configurează certificatul HTTPS al originului și modul **Full (strict)**. După activarea proxy-ului pentru domeniu:

```dotenv
APP_URL=https://ipontaj.ro
CLOUDFLARE_ENABLED=true
SESSION_SECURE_COOKIE=true
```

Aplicația acceptă IP-ul și protocolul din antetele Forwarded numai când conexiunea vine de la o rețea Cloudflare de încredere. Nu acceptă `X-Forwarded-Host`. Lista implicită IPv4/IPv6 a fost verificată la 24 noiembrie 2026; verific-o periodic la [Cloudflare IP ranges](https://www.cloudflare.com/ips/). `CLOUDFLARE_TRUSTED_PROXIES` permite o listă explicită separată prin virgule; evită `*` și intervalele generale. Dacă găzduirea introduce încă un proxy, confirmă adresele și comportamentul acestuia înainte de a-l considera de încredere.

Păstrează regulile de cache compatibile cu `Cache-Control: no-store, private`. Nu activa „Cache Everything” peste cont, formulare, invitații, pontaje, exporturi sau linkuri semnate. Nu expune baza ori backupurile în document root.

## Turnstile pe login, register și contact

Creează un widget **Managed** în Cloudflare Turnstile și permite domeniile folosite efectiv. Completează numai `.env` pe instalarea ta:

```dotenv
TURNSTILE_ENABLED=true
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=
TURNSTILE_HOSTNAMES=ipontaj.ro,www.ipontaj.ro
```

Site key este publică; secret key rămâne pe server. Funcția nu depinde de activarea proxy-ului Cloudflare. Când `TURNSTILE_ENABLED=false`, widgetul și verificarea serverului sunt dezactivate. Cu flagul activ, lipsa cheilor sau un răspuns nereușit blochează trimiterea; nu există trecere automată la verificare exclusiv în browser.

Serverul verifică succesul, hostname-ul, acțiunea (`login`, `register`, `contact`) și reutilizarea tokenului, iar endpointurile au limitare de frecvență. Parola și mesajul nu sunt trimise prin Siteverify; apelul conține secretul, tokenul și IP-ul stabilit de server. Widgetul se reîncarcă după o încercare, expirare sau eroare și este eliminat la părăsirea paginii. CSP permite domeniile Cloudflare doar când funcția este activă.

Pentru dezvoltare poți folosi [cheile publice oficiale de test](https://developers.cloudflare.com/turnstile/troubleshooting/testing/). Acestea nu protejează o instalare publică și sunt refuzate în producție. Răspunsurile dummy nu includ acțiunea reală; verificarea strictă hostname/acțiune este testată separat cu răspunsuri simulate, iar cheia de test este acceptată exclusiv în `local`/`testing`.

După modificarea `.env`, rulează pe găzduire `php artisan config:cache` și `php artisan queue:restart`. Nu copia cache-ul de configurare de pe calculatorul local.

## Contact și email

Pagina `/contact` include formular, email, telefon și informațiile operatorului. Mesajele sunt salvate criptat în baza aplicației înaintea notificării către operator. Păstrează `APP_KEY` pentru a le putea citi după transfer/restaurare. Sunt necesare SMTP valid și un worker pentru coadă. Un eșec al emailului nu șterge mesajul.

```bash
php artisan migrate --force
php artisan queue:work --tries=3 --timeout=90
php artisan pontaj:contact
php artisan pontaj:contact --show=REFERINTA
php artisan pontaj:contact --resolve=REFERINTA
```

Registrul este disponibil numai din consola operatorului; nu există o adresă publică pentru citirea mesajelor. `--resolve` marchează soluționarea și nu trimite răspuns automat. Nu transmite conținutul registrului în issue-uri publice. Cererile GDPR și retragerile păstrează formularele dedicate.

## Preferințe cookie-uri

Bannerul are acceptare, refuz și alegere personalizată, fără selectarea implicită a analizei. Cookie-urile necesare și protecția Turnstile funcționează independent de analiza opțională. Preferința locală include versiunea informării și data și expiră după 180 de zile. Modificarea versiunii ori activarea unei categorii indisponibile anterior solicită din nou alegerea. „Setări cookie-uri” permite retragerea acordului.

Nu este instalat Google Analytics și nu sunt incluși pixeli publicitari. Opțional, poți configura Cloudflare Web Analytics:

```dotenv
CLOUDFLARE_WEB_ANALYTICS_TOKEN=
```

Tokenul beacon este public, dar configurarea rămâne în `.env`. Folosește integrarea manuală și **dezactivează injectarea automată a beaconului din panoul Cloudflare**; o inserare făcută independent la edge nu poate fi controlată de bannerul aplicației.

Beaconul se încarcă numai după acord, pe prezentarea publică și contact, fără navigarea SPA a furnizorului. La părăsirea documentului public cu analiză activă, navigarea GET face o încărcare completă, iar intrarea în demo folosește un POST nativ, pentru a termina ascultătorii înainte de deschiderea contului. Nu încărcăm analiza în ecranele autentificate, login/register, invitații sau recuperarea parolei. Retragerea acordului reîncarcă pagina și păstrează categoria refuzată. Dacă stocarea browserului nu funcționează, analiza rămâne oprită.

Revizuiește acordurile Cloudflare, transferurile și informarea efectivă înaintea activării pe date reale. Documentele active sunt `2026-11-24.3`; versiunile precedente și dovezile deja acceptate sunt păstrate.

## Pregătire Google

Pe domeniul live, după verificarea accesului public și a condițiilor de lansare:

```dotenv
APP_URL=https://ipontaj.ro
SEO_CANONICAL_URL=https://ipontaj.ro
SEO_INDEXABLE=true
GOOGLE_SITE_VERIFICATION=
```

Lasă `SEO_INDEXABLE=false` pe localhost, staging și copii de test. Acest flag produce `noindex` și `Disallow: /`; nu este o metodă de protejare a datelor private. Accesul în cont este controlat separat prin autentificare și autorizare.

Pagina principală are titlu, descriere, canonical fără parametri de campanie, Open Graph, Twitter Card, imagine socială PNG 1200×630 și JSON-LD pentru Organization, WebSite și WebApplication. Conținutul de bază este prezent și în HTML-ul inițial, înainte de pornirea Vue, cu aceleași informații publice despre serviciu și prețuri. Metadatele se actualizează la navigarea Inertia. Nu sunt inventate recenzii sau ratinguri și nu este garantată afișarea unui rich result.

`/sitemap.xml` include doar pagina principală și contactul. Conturile, formularele de autentificare, pontajele și descărcările private au antet `X-Robots-Tag: noindex, nofollow`. `robots.txt` este generat de Laravel; nu pune un fișier static în `public/robots.txt` care să ascundă ruta.

### Dacă robots blochează tot și sitemap-ul este gol

Acesta este comportamentul configurat pentru `SEO_INDEXABLE=false`, inclusiv în producție. Adăugarea proprietății în Search Console nu modifică `.env` sau configurația Laravel. Pe instalarea live setează cele trei valori din exemplul de mai sus, fără a înlocui cheile ori restul fișierului `.env`. `SEO_CANONICAL_URL` trebuie să fie URL-ul simplu, fără sintaxa de link Markdown, paranteze sau ghilimele suplimentare.

Pe cPanel, fără shell, adaugă temporar următorul cron și elimină-l după rularea reușită:

```bash
/usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan config:cache --no-ansi > /home/UTILIZATOR/ipontaj-config-cache.log 2>&1
```

După încărcarea codului pentru noile rute `llms.txt`/`llm.txt`, curăță și eventualul cache de rute printr-un cron temporar separat:

```bash
/usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan route:clear --no-ansi > /home/UTILIZATOR/ipontaj-route-clear.log 2>&1
```

Nu este necesar `cache:clear` sau ștergerea întregului cache al aplicației pentru această actualizare.

Pentru diagnostic ulterior, comanda următoare nu modifică setări, nu accesează baza și nu face cereri de rețea:

```bash
/usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan pontaj:seo:status --no-ansi > /home/UTILIZATOR/ipontaj-seo-status.log 2>&1
```

Raportul trebuie să arate `indexing_enabled: true`, canonical `https://ipontaj.ro`, două URL-uri în `sitemap_urls` și lista `public_files_shadowing_routes` goală. Nu afișează chei sau valoarea tokenului Google. Dacă apar fișiere statice cu aceleași nume în document root, păstrează o copie privată și elimină numai acele fișiere vechi care ocolesc rutele Laravel. Nu crea un `robots.txt` static cu `Allow: /` drept înlocuitor pentru activarea configurației: pagina ar putea rămâne `noindex`.

Verifică public, fără autentificare: `/robots.txt` trebuie să conțină `Allow: /` și URL-ul sitemapului, `/sitemap.xml` cele două pagini, iar pagina principală să aibă meta robots `index, follow, max-image-preview:large` și să nu trimită `X-Robots-Tag: noindex`. Dacă un CDN livrează încă răspunsul anterior, golește cache-ul numai pentru URL-urile afectate și verifică regulile de cache; răspunsurile Laravel sunt `no-store, private`. După confirmarea răspunsurilor actuale, retrimite sitemap-ul și folosește URL Inspection în Search Console. Google poate păstra temporar informațiile din accesările anterioare.

### Informații pentru asistenți AI

`/llms.txt` oferă un rezumat Markdown al serviciului, limitele funcționale și linkuri către pagini/documente publice și codul sursă. `/llm.txt` redirecționează permanent către numele plural. Pagina principală și contactul îl indică prin `rel="describedby"`. Conținutul se generează din `resources/seo/llms.md` cu domeniul canonical și emailul public al operatorului; nu include contul autentificat, firme sau linkuri individuale semnate. Un fișier static cu același nume ar ocoli această generare.

Este disponibil și cu indexarea oprită, ca orice informație publică accesată direct; `robots.txt` rămâne blocant pe staging. Fișierul folosește [propunerea llms.txt](https://llmstxt.org/), nu este un mecanism de autorizare și nu garantează preluarea de către un furnizor AI. Google arată că [nu sunt necesare fișiere AI speciale pentru AI Overviews sau AI Mode](https://developers.google.com/search/docs/appearance/ai-features). Activarea indexării, accesul crawlerelor și conținutul public rămân verificări separate.

1. Adaugă domeniul în [Google Search Console](https://search.google.com/search-console/).
2. Pentru proprietatea de domeniu, confirmă proprietatea prin înregistrarea TXT indicată de Google, adăugată în DNS Cloudflare. Pentru o proprietate URL-prefix poți folosi metoda meta-tag, completând numai valoarea în `GOOGLE_SITE_VERIFICATION`.
3. După verificare, trimite `https://ipontaj.ro/sitemap.xml`.
4. Folosește URL Inspection pe `https://ipontaj.ro/`, verifică pagina publicată și cere indexarea.
5. Verifică [datele structurate](https://search.google.com/test/rich-results) și urmărește raportul Pages. Publicarea sitemapului sau cererea de indexare nu garantează includerea ori poziția în rezultate.

Înregistrarea efectivă în Search Console și modificările DNS sunt pași ai titularului domeniului. Codul nu trimite automat cereri de indexare și nu modifică un cont Cloudflare.

## Verificări

```bash
php vendor/bin/phpunit --no-progress
npm run test:cookies
npm run types:check
npm run check
npm run build
```

`tests/browser/public-website.mjs` verifică refuzul, persistența, acceptarea și retragerea, blocarea beaconului pe cont și metadatele după navigare. Rulează cu token de analiză fictiv într-un mediu izolat; scriptul de test interceptează beaconul. `tests/browser/turnstile.mjs` folosește un server de test cu cheile oficiale dummy, emailuri dezactivate și bază separată. Nu rula testele de înregistrare/contact pe producție.
