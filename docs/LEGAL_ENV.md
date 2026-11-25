# Configurarea declarațiilor LEGAL_*

Surse verificate la 24 noiembrie 2026. Câmpurile descriu infrastructura și acțiuni efectuate de operator; codul nu certifică aceste acțiuni. Un produs Stripe creat, o pagină publicată sau un test local nu confirmă automat contracte ori funcționarea pe găzduire.

## Câmpuri factuale

| Variabilă                     | Valoarea și dovada necesară                                                                                                                                                                                                         |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `LEGAL_HOSTING_ENTITY`        | Denumirea din contractul/factura reseller. Termenii publici numesc `CYBER_FOLKS SRL`; verifică documentul contului înainte de copiere.                                                                                              |
| `LEGAL_DATA_LOCATION`         | Locațiile confirmate pentru aplicație, MySQL, email și backupuri. Politica publică indică găzduire în România și backupuri în spațiul european; nu promite că toate copiile rămân în România.                                       |
| `LEGAL_BACKUP_RETENTION_DAYS` | Durata efectivă confirmată pentru pachet. Termenii shared/reseller menționează minimum 5 zile, nu o retenție exactă universală de 5 sau 28 de zile. Istoricul JetBackup poate fi mai scurt decât politica, dacă serviciul este nou. |
| `LEGAL_LOG_RETENTION_DAYS`    | Durata aprobată pentru logurile aplicației. Nu se deduce din backupuri și nu șterge logurile web/SMTP ale gazdei. `0` înseamnă neconfigurat, nu ștergere imediată.                                                                  |

Surse: [termenii cyber_Folks, inclusiv secțiunea Backup](https://cyberfolks.ro/termeni-si-conditii/), [politica de confidențialitate](https://cyberfolks.ro/politica-de-confidentialitate/). Aceste pagini descriu oferta generală. Pentru contul concret folosește [solicitarea pregătită](CYBERFOLKS_REQUEST.md).

Titularul a confirmat din factură Cyber_Folks SRL, CIF RO33424916. Cele 13 copii afișate în JetBackup sunt un număr de copii, nu 13 zile de retenție. Geolocalizarea IP-ului nu confirmă locația fizică a aplicației, a emailului sau a copiilor. Durata efectivă și locațiile contului rămân de confirmat cu furnizorul.

## Când poate deveni true fiecare confirmare

| Variabilă                       | Acțiunea și dovada păstrată privat                                                                                                                                                                                                                                                             |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `LEGAL_HOSTING_DPA_CONFIRMED`   | Acordul aplicabil resellerului este acceptat și verificat, inclusiv subîmputerniciții și transferurile. Păstrezi versiunea și confirmarea furnizorului; politica generală nu dovedește singură acest pas.                                                                                      |
| `LEGAL_STRIPE_DPA_CONFIRMED`    | Verifici termenii contului, DPA, transferurile și subîmputerniciții; păstrezi versiunea/data. DPA face parte din acordul Stripe; nu presupunem că trebuie întotdeauna semnat separat. Crearea webhookului nu dovedește revizuirea.                                                             |
| `LEGAL_CAEN_CONFIRMED`          | Documentele PFA și verificarea cu ONRC/contabilul confirmă autorizarea activității efectiv oferite. Păstrezi codurile și documentul; nu deduci autorizarea din numele PFA-ului.                                                                                                                |
| `LEGAL_INVOICING_CONFIRMED`     | Contabilul confirmă și testezi emiterea, numerotarea, evidența, comisioanele, corecțiile și RO e-Factura unde se aplică. Procesul poate fi extern iPontaj; factura Stripe nu îl confirmă automat.                                                                                              |
| `LEGAL_RETENTION_APPROVED`      | Aprobi și aplici planul pentru conturi, pontaj/audit, invitații, acceptări/cereri, loguri, cozi și backupuri, cu responsabil și excepții. O singură valoare numerică nu aplică planul complet.                                                                                                 |
| `LEGAL_OPERATIONS_CONFIRMED`    | Procedurile și exercițiile de acces/export, ștergere/anonimizare, incident și restaurare sunt efectuate pe infrastructura folosită, cu rezultat și dată. Testul MySQL local nu este o restaurare pe cPanel.                                                                                    |
| `LEGAL_MAIL_DELIVERY_CONFIRMED` | Primești efectiv emailuri controlate de înregistrare/recuperare și confirmare contractuală, cu atașamentele așteptate și rezultate SPF/DKIM/DMARC verificate. DNS prezent nu demonstrează livrarea.                                                                                            |
| `LEGAL_CONSUMER_FLOW_CONFIRMED` | Verifici informarea, prețul/reînnoirea, cererea de începere anticipată, checkoutul găzduit, webhookul, confirmarea contractuală, anularea, retragerea și procedura de rambursare. Păstrezi dovezi din test și verificarea procedurii de pe găzduire; nu inițiezi plăți reale doar pentru bifă. |
| `LEGAL_PUBLICATION_APPROVED`    | Titularul aprobă explicit documentele și configurația versiunii publicate, după verificarea demersurilor. Păstrezi data/versiunea; domeniul accesibil nu dovedește singur aprobarea.                                                                                                           |

Stripe explică integrarea DPA și a acordului de transfer în [FAQ DPA](https://stripe.com/legal/dpa/faqs). Consultă și [DPA](https://stripe.com/legal/dpa), [transferurile](https://stripe.com/legal/dta), [termenii aplicabili](https://stripe.com/en-ro/legal/ssa) și [subîmputerniciții](https://stripe.com/legal/service-providers). Nu descriem datele Stripe ca fiind exclusiv în SEE.

Pentru CAEN folosește documentele proprii și [clasificarea ONRC Rev.3](https://www.onrc.ro/documente/anunturi/CAEN-Rev.3_structura-completa.pdf). Editarea software și software-ul la comandă au denumiri distincte; lista nu stabilește singură codul aplicabil iPontaj.

Interogarea [serviciului oficial ANAF v9](https://static.anaf.ro/static/10/Anaf/Informatii_R/Servicii_web/servicii_sincron.html), pentru CIF 33388821 la 24 noiembrie 2026, a returnat CAEN principal **6210**, activități de realizare a software-ului la comandă. Acest răspuns nu enumeră toate activitățile autorizate și nu confirmă singur încadrarea serviciului SaaS. La 25 noiembrie, titularul a confirmat explicit dreptul PFA-ului de a vinde serviciul; `LEGAL_CAEN_CONFIRMED=true` poate consemna această declarație. Interogarea ANAF rămâne distinctă de documentele de autorizare.

[Documentația Stripe pentru e-invoicing](https://docs.stripe.com/invoicing/e-invoicing) precizează că Stripe Invoicing nu generează și nu transmite singur fișierele de facturare electronică conforme. iPontaj nu include momentan transmitere RO e-Factura. Configurarea cheilor live nu confirmă acest proces; stabilește cu contabilul emiterea și transmiterea printr-o integrare sau procedură externă verificată înainte de `LEGAL_INVOICING_CONFIRMED=true`.

Planul intern este în [GDPR_OPERATIONS.md](GDPR_OPERATIONS.md). Principiile de stocare, obligațiile contractuale și securitatea se aplică situației reale; vezi [GDPR, art. 5, 28 și 32](https://eur-lex.europa.eu/eli/reg/2016/679/oj/eng). Titularul a aprobat 30 de zile pentru logurile aplicației. Este o alegere operațională, nu o durată legală universală și nu aprobarea întregului plan de retenție.

## Aplicarea retenției logurilor

Configurația aprobată pentru logurile aplicației:

```dotenv
LOG_CHANNEL=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=30
LEGAL_LOG_RETENTION_DAYS=30
LEGAL_RETENTION_APPROVED=false
```

În versiunea instalată, `LOG_DAILY_DAYS` controlează numărul maxim de fișiere zilnice Monolog. Nu garantează singur expirarea după vârstă, mai ales la trafic redus. `LEGAL_LOG_RETENTION_DAYS` singur nu modifică loggerul.

Comanda `php artisan pontaj:logs:prune` previzualizează numărul fișierelor expirate, fără a le citi sau șterge. Opțiunea `--apply` aplică politica numai dacă `LOG_CHANNEL=daily` și durata este pozitivă. Sunt eligibile doar fișierele zilnice ale loggerului configurat din `storage/logs`, cu o dată validă strict anterioară zilei-limită; ziua-limită, ziua curentă, alte loguri și legăturile simbolice sunt păstrate. Comanda nu accesează baza de date.

Schedulerul execută zilnic curățarea la **03:20 UTC**, fusul aplicației, dacă aceste condiții sunt îndeplinite. Fișierele expirate se elimină la următoarea execuție reușită. Pe cPanel este necesar cronul `schedule:run` în fiecare minut. După instalarea actualizării și refacerea cache-ului, previzualizează separat:

```bash
/usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan pontaj:logs:prune --no-ansi > /home/UTILIZATOR/ipontaj-log-retention.log 2>&1
```

Vechiul `laravel.log` nu este șters automat: comanda îl semnalează pentru revizuire și aplicarea separată a politicii. Păstrează privat dovezile necesare unui incident conform procedurii aprobate. Logurile web/SMTP ale furnizorului și backupurile au reguli distincte; curățarea locală nu le șterge.

## Comenzi cPanel

Executabilul confirmat de titular: `/usr/local/bin/ea-php83`. După editarea privată a `.env`, rulează temporar prin cron:

```bash
/usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan config:cache --no-ansi > /home/UTILIZATOR/ipontaj-config.log 2>&1
/usr/local/bin/ea-php83 /home/UTILIZATOR/public_html/artisan pontaj:legal:check --no-ansi > /home/UTILIZATOR/ipontaj-legal-check.log 2>&1
```

Sunt două comenzi separate; înlocuiește `UTILIZATOR`, execută-le în ordine și dezactivează cronurile temporare. Rapoartele rămân în afara document root. Schedulerul și coada sunt descrise în [ghidul cPanel](CPANEL_DEPLOY.md).

În versiunea din 25 noiembrie, `pontaj:legal:check` rămâne raportul declarațiilor operatorului. Nu blochează tehnic contul sau checkoutul. Plata acceptă atât test, cât și live, după activarea explicită `BILLING_ENABLED=true` și verificarea tehnică a cheilor, webhookului și prețului. Declarațiile neconfirmate nu sunt transformate automat în `true`. Titularul a asumat transmiterea manuală RO e-Factura din datele Stripe; aplicația nu face acea transmitere. Încărcarea codului pe găzduire este separată de editarea `.env`.

Pentru fiecare confirmare păstrează privat: variabila, valoarea, data, persoana responsabilă, referința documentului/testului și data revizuirii. Nu pune contracte, facturi, chei sau loguri brute în repository.
