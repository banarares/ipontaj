# Lansarea juridică și operațională iPontaj

Stare la 24 noiembrie 2026: titularul a publicat instanța iPontaj.ro, iar resursele de configurare Stripe live au fost create. Încasările live rămân blocate în codul curent. Publicarea și existența resurselor nu confirmă contractele și testele operaționale. Acest dosar este un instrument de lucru, nu un certificat de conformitate. Documentele se verifică în raport cu situația reală a PFA-ului.

## Date confirmate de titular

- BANA RARES-NICOLAE PERSOANA FIZICA AUTORIZATA, CIF **33388821**, fără prefix RO; titularul a confirmat că nu este înregistrat în scopuri de TVA.
- ONRC **F2014000987292**; Str. Națiunii nr. 65, Ploiești, Prahova, România.
- Domeniu: **ipontaj.ro**; contact: **office@ipontaj.ro**, **0735863399**.
- cyber_Folks, cont reseller; bază de date MySQL; email și backupuri zilnice la furnizor.
- Serviciul va fi oferit și consumatorilor. Prețul planului Echipă: **49 lei/lună, preț final**. Mențiunea anterioară „TVA inclus” a fost corectată; nu folosim artificial o cotă de 0%.

## Implementat în aplicație

| Componentă                    | Implementare                                                                                                                                    |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| Identificarea furnizorului    | Footer și casetă pe paginile juridice; denumire, CIF, ONRC, sediu, email, telefon                                                               |
| Documente                     | Termeni, confidențialitate, cookie-uri, DPA, furnizori, abonamente/anulare, retragere, cereri GDPR, contact, securitate/accesibilitate, licență |
| Informare înainte de contract | Preț final și reînnoire lunară; anulare și retragere distincte; condiții de utilizare                                                           |
| Acceptări                     | Bife nebifate inițial; validare server; versiune, text, hash, context și dată păstrate; dovezi private descărcabile                             |
| Datele angajaților            | DPA separat pentru clienții profesionali; informare fără consimțământ general impus angajatului                                                 |
| Consumatori                   | Configurare personală fără CIF; începere anticipată solicitată expres la checkout, fără renunțare generală la retragere                         |
| Retragere online              | Link permanent, formular fără autentificare, verificare și confirmare distinctă, înregistrare înaintea emailului, dovadă descărcabilă           |
| Cereri GDPR                   | Formular, confirmare, registru și termen calculat calendaristic; datele nu se divulgă unui solicitant neverificat                               |
| Confirmare abonament          | La evenimentul Stripe semnat de checkout plătit, condiții exacte atașate emailului; verificare client/sesiune și deduplicare                    |
| ANPC                          | Link vizibil ANPC și pictogramă SAL oficială actuală, servită local; fără pictograma SOL închisă                                                |
| Cookie-uri                    | Preferințe de acceptare/refuz și categorii; analiza opțională configurată pornește numai cu acord, independent de funcțiile necesare            |
| Securitate                    | Antete de protecție, CSP și HSTS în producție, fără a declara certificări inexistente                                                           |
| AGPL                          | Arhivă de sursă din directoare permise; fără .env, baze de date, storage sau runtime; inventar și notificări de licență pentru dependențe       |
| Verificarea lansării          | `pontaj:legal:check`; checkoutul cu facturare activată verifică demersurile, independent de contul gratuit                                      |

## Ce trebuie finalizat în afara aplicației

| Demers                | Cine / dovada necesară                                                                                                                                                                      |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Activitate autorizată | Titular + ONRC/contabil: verificarea activității efectiv autorizate și a codurilor CAEN Rev.3 aplicabile serviciului; nu deducem autorizarea din numele PFA-ului                            |
| Regim fiscal          | Contabil: confirmarea statutului TVA, evidenței veniturilor, tratamentului comisioanelor Stripe și eventualelor operațiuni externe                                                          |
| Facturare             | Alegerea și testarea emiterii facturii românești și a transmiterii în RO e-Factura unde este obligatorie; documentele Stripe nu sunt considerate suficiente automat                         |
| cyber_Folks           | Contractul reseller, acord art. 28, entitatea contractuală, subîmputerniciți, locații, backup și email, inclusiv filtrare și suport                                                         |
| Stripe                | Entitatea contractuală, DPA, transferuri/garanții și obligațiile proprii de operator; datele comerciale ale PFA-ului în contul Stripe                                                       |
| Retenție              | Plan aprobat și aplicat pentru pontaj, conturi, audit, acceptări, solicitări, loguri, cozi și backupuri; fără durate universale inventate                                                   |
| Operațiuni GDPR       | Registrele de prelucrare, analiza rolurilor/temeiurilor, minimizarea datelor medicale, analiza necesității DPIA și DPO potrivit situației efective, personal autorizat și confidențialitate |
| Ștergere / incidente  | Exercițiu de export, ștergere/anonimizare și restaurare; reaplicarea ștergerilor după restaurare; procedură de incident și contacte                                                         |
| Email                 | SMTP real, expeditor autorizat office@ipontaj.ro, SPF/DKIM/DMARC și verificarea livrării/atașamentelor către căsuțe reale de test controlate de titular                                     |
| Consumatori           | Parcurs complet cu checkout găzduit, informații înaintea plății, confirmare pe suport durabil, anulare, retragere și rambursare; evidență și personal care procesează cererile la timp      |
| Infrastructură        | HTTPS, document root public/, secrete protejate, administrare cu MFA, cron/cozi, actualizări și monitorizare; test pe MySQL-ul efectiv                                                      |
| Accesibilitate        | Evaluarea Legii 232/2022 pentru serviciul concret și eventualei excepții de microîntreprindere; nu pretindem că simpla existență a unei pagini dovedește conformitatea                      |

Solicitările și rambursările se soluționează de operator; un formular funcțional nu înlocuiește executarea obligației. Nu activăm încasările live sau publicarea ca efect al adăugării acestor pagini.

## Verificarea publică a contului reseller

Surse consultate:

- [Oferta reseller cyber_Folks](https://cyberfolks.ro/reseller-hosting/): backup incremental inclus.
- [Parametri de securitate](https://cyberfolks.ro/parametrii-de-securitate/): tabelul reseller indică până la 28 de zile disponibilitate a copiilor și locații Cluj-Napoca/București.
- [Termeni generali](https://cyberfolks.ro/termeni-si-conditii/): backup zilnic shared/reseller, minimum 5 zile și limite de garanție a integrității.
- [Politica de confidențialitate](https://cyberfolks.ro/politica-de-confidentialitate/): acces tehnic necesar, furnizori și posibile transferuri.

Pagina cu parametri a fost disponibilă în rezultatele indexate, dar adresa a răspuns și cu 404 în verificarea directă; de aceea valoarea de 28 de zile nu este introdusă în configurare ca durată contractuală. Aceste pagini nu dovedesc acceptarea unui DPA pentru contul concret. Nu am accesat contul privat reseller și nu am setat o retenție garantată doar pe baza reclamei. O solicitare gata de trimis de titular este în [CYBERFOLKS_REQUEST.md](CYBERFOLKS_REQUEST.md). Nu a fost expediată.

## Configurarea și verificarea lansării

Variabilele `LEGAL_*` sunt în `.env.example`, iar datele publice confirmate în `config/legal.php`. Completează locațiile și duratele numai din documentele contului. Bifele de verificare sunt declarații ale operatorului despre demersuri efectuate, nu înlocuitori pentru aceste demersuri.

```bash
php artisan migrate --force
python3 scripts/update-third-party-notices.py
php artisan pontaj:source:build
php artisan pontaj:legal:check
```

Arhiva de sursă se regenerează după ultima modificare și la fiecare release. Textul deja acceptat rămâne în evidența de acceptare. La schimbarea condițiilor, creează un nou director `resources/legal/<versiune>/`, actualizează versiunea/data și notifică utilizatorii după regulile aplicabile; nu înlocui tăcut o versiune publicată.

În producție, conturile existente fără acceptările versiunii curente ajung la `/acorduri`; demo-ul este exceptat. Exportul și drepturile persoanelor trebuie în continuare facilitate, inclusiv prin contact, fără a condiționa o cerere GDPR de acceptarea unui nou contract.

## Procesarea retragerilor și cererilor privind datele

```bash
php artisan pontaj:legal:requests
php artisan pontaj:legal:requests --show=<referinta>
php artisan pontaj:legal:requests --resolve=<referinta> --resolution='Soluția deja comunicată solicitantului'
```

Registrul conține date personale și este destinat administratorului autorizat, nu unui endpoint public. Ultima comandă consemnează soluția; nu trimite email, nu anulează abonamentul și nu efectuează rambursarea.

Pentru retragere: păstrează momentul declarației, identifică proporțional contractul, oprește reînnoirea și aplică încetarea/rambursarea cerute de lege. Nu solicita motiv, document de identitate automat sau număr Stripe obligatoriu. Urmărește termenul legal de rambursare; termenul din registru este un reper, nu o concluzie automată de eligibilitate.

Pentru GDPR: confirmă primirea, identifică rolul PFA/client și identitatea, verifică datele și obligațiile de păstrare, răspunde în maximum o lună sau comunică justificarea unei prelungiri în prima lună. Soluționarea se documentează și se comunică printr-un canal sigur. Nu șterge automat pontajul angajatorului pe baza emailului unei persoane neverificate.

## Pregătirea găzduirii reseller

Aplicația necesită PHP 8.3+ și extensiile Laravel/PhpSpreadsheet, inclusiv pdo_mysql, bcmath, mbstring, gd, zip și XML. Node 24 este necesar la build, care poate fi efectuat local; nu presupunem că un proces Vite permanent este disponibil pe reseller.

Configurează domeniul cu document root către `public/`, fără expunerea .env, storage, vendor sau arhivelor private. Folosește MySQL 8+ compatibil cu versiunea Laravel, un utilizator dedicat, charset utf8mb4 și conturi administrative individuale. Nu schimba baza locală SQLite doar pentru pregătirea deployului.

Configurează cron pentru `schedule:run` la fiecare minut. Dacă furnizorul nu permite un worker permanent, verifică un cron care rulează `queue:work --stop-when-empty --max-time=50`, fără suprapuneri, și limitele pachetului. Protejează joburile și căsuța de contact, monitorizează joburile eșuate și verifică zilnic registrul cererilor. Nu utiliza permisiuni 777.

SMTP real și configurarea fiscală se fac cu acreditări introduse local în mediul de găzduire, nu în conversație sau în repository. Verifică HTTPS, APP_URL, APP_ENV=production, APP_DEBUG=false, SESSION_SECURE_COOKIE=true și cache comun persistent. Nu publica fișierul `public/hot`. CI include MySQL, însă trecerea unui workflow neexecutat nu este afirmată ca rezultat local.

## Surse oficiale pentru revizuire

- [Legea 365/2002, art. 5](https://legislatie.just.ro/Public/DetaliiDocumentAfis/288595): identificare și informații comerciale.
- [OUG 34/2014, inclusiv art. 11^1](https://legislatie.just.ro/Public/DetaliiDocument/158913): informare, retragere și funcția online în forma actualizată în 2026.
- [GDPR, publicat de ANSPDCP](https://www.dataprotection.ro/servlet/ViewDocument?id=1262): informare, roluri, contracte și securitate; [întrebări frecvente ANSPDCP](https://www.dataprotection.ro/index.jsp?lang=ro&page=IntrebariFrecvente1) pentru drepturi și termene.
- [ANPC, comunicat SAL din 19 mai 2026](https://anpc.ro/comunicat-sal/); pagina publică actuală accesată în browser: [SAL](https://www.anpc.ro/public/sal), [pictograma oficială](https://www.anpc.ro/download/sal/SAL-PICTOGRAMA.png). Originalul PNG este păstrat local și afișat proporțional într-o casetă de 250×50 px.
- [Comisia Europeană: închiderea SOL](https://consumer-redress.ec.europa.eu/site-relocation_en): platforma veche este închisă din 20 iulie 2025.
- [ANAF: OUG 89/2025](https://static.anaf.ro/static/10/Anaf/legislatie/OUG_89_2025.pdf): inclusiv modificări de termene RO e-Factura aplicabile în 2026; contabilul verifică obligațiile concrete înainte de live.

Nu au fost depuse declarații fiscale, cereri ONRC, notificări către autorități sau semnate contracte în numele titularului. Acestea necesită documentele și autorizările corespunzătoare.

Pentru schimbarea furnizorului, verificați aplicabilitatea și implementarea capitolului VI din [Data Act — explicațiile Comisiei](https://digital-strategy.ec.europa.eu/en/factpages/data-act-explained) și [regulamentul](https://eur-lex.europa.eu/legal-content/EN/TXT/PDF/?uri=CELEX%3A32023R2854). Termenii includ transferul, calendarul, recuperarea și lipsa taxei de transfer; înainte de lansare, testați tranziția efectivă și documentați suportul și formatul exportabil, fără a presupune că simplul buton Excel rezolvă toate obligațiile.

Înainte de publicare, verifică și dreptul de utilizare a domeniului, disponibilitatea denumirii comerciale în raport cu mărcile existente și proveniența materialelor grafice. Înregistrarea unei mărci nu este prezentată ca o condiție universală pentru orice lansare.

Footerul grupează legăturile ANPC, SAL, Consumer Redress în UE și ANSPDCP folosind iconuri de navigare din aceeași familie, cu dimensiuni și aliniere comune. Pictograma oficială SAL rămâne nemodificată și separată de aceste iconuri. Materialele instituționale păstrate în proiect au sursele atribuite în THIRD_PARTY_NOTICES.md. Cerința din [Ordinul ANPC 505/2026, art. I pct. 2](https://legislatie.just.ro/Public/DetaliiDocument/314012) este linkul vizibil către ANPC; pictograma SAL are propriul regim. Nu am identificat în obligațiile de informare GDPR un sigiliu obligatoriu ANSPDCP sau o certificare obținută prin afișarea siglei.

Linkul suplimentar către [Consumer Redress in the EU](https://consumer-redress.ec.europa.eu/select-language?destination=/node/1) este o resursă informativă actuală, disponibilă în mai multe limbi. Nu îl prezentăm ca vechea platformă SOL și nu deducem o obligație de afișare a unui nou sigiliu din existența portalului. [Comisia Europeană](https://consumer-redress.ec.europa.eu/site-relocation_en) confirmă închiderea SOL la 20 iulie 2025, iar [comunicatul ANPC privind Ordinul 270/2026](https://anpc.ro/comunicat-sal/) explică eliminarea referințelor la SOL și păstrarea pictogramei SAL de 250×50 px.

## Textele publice și versiunea documentelor

Versiunea activă a documentelor este `2026-11-24.2`. Formulările temporare de lansare au fost eliminate din paginile pentru clienți, din prezentarea prețurilor și din pagina abonamentului. Informațiile despre furnizori și retenție folosesc numai valori efective; cercetarea orientativă, checklistul operațional și lipsurile contractuale rămân documentate în acest dosar intern. Reducerea textelor publice nu confirmă îndeplinirea obligațiilor și nu modifică verificările din `pontaj:legal:check`, configurarea Stripe sau `LEGAL_*`.

Documentele `2026-11-24.1` sunt păstrate nemodificate; dovezile semnate/descărcabile conțin în continuare textul și hashul acceptat atunci. O nouă acceptare folosește versiunea curentă, fără rescrierea dovezilor vechi. Pagina Licență indică repositoryul GitHub al proiectului și păstrează arhiva sursei versiunii instalate. Publicarea repositoryului este o acțiune separată a proprietarului.

Pentru operare rămân necesare confirmarea contractuală a locației și retenției pentru pachetul efectiv, acordurile furnizorilor, planul de retenție aplicat și evaluarea obligațiilor de accesibilitate din Legea nr. 232/2022. Aceste aspecte nu sunt declarate finalizate prin schimbarea textelor.

## Cloudflare și formularele publice

Versiunea activă este `2026-11-24.3`. Include contactul salvat criptat și informarea despre serviciile Cloudflare configurabile, proxy, Turnstile și analiza publică opțională. Verifică acordul aplicabil contului și transferurile înainte de activarea serviciilor; nu este presupusă o localizare exclusiv în SEE. Bannerul separă măsurile necesare de analiza opțională, iar refuzul nu împiedică autentificarea. Ghidul de configurare este [CLOUDFLARE_SEO.md](CLOUDFLARE_SEO.md). Versiunile anterioare nu au fost rescrise.

## Actualizare 25 noiembrie 2026

Versiunea activă este `2026-11-27.1`, care extinde analiza opțională la toate paginile publice și descrie evenimentele și cele trei conversii din cont (creare cont, începere plată, abonare confirmată) trimise în Google Analytics cu acord (cookies, furnizori, confidențialitate). Versiunea `2026-11-25.2` a adăugat Google Analytics prin Google Tag Manager. Versiunea `2026-11-25.1` a explicat notificările interne și confirmarea emailului. Versiunile anterioare rămân intacte pentru dovezile existente. Titularul a confirmat dreptul de a comercializa serviciul ca PFA și a asumat transmiterea manuală RO e-Factura folosind datele Stripe. Acest lucru nu este o integrare automată de raportare fiscală.

Limitarea tehnică la chei Stripe test a fost eliminată. Activarea explicită, potrivirea modului cheilor/webhookurilor, prețul exact și condițiile acceptate sunt verificate la checkout; checklistul declarațiilor rămâne un raport pentru operator. Referințele istorice din acest dosar la blocarea exclusivă a plăților live descriu versiunile precedente. Nu s-au bifat automat acordurile sau procedurile neconfirmate.
