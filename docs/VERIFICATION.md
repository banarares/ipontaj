# Raport de verificare — 25 noiembrie 2026

## Verificat local

- **177 teste PHP, 1366 aserțiuni: trecute**, atât pe SQLite, cât și pe un MySQL 8.0.46 temporar izolat. Ultima matrice GitHub a verificat commitul publicat anterior. Modificările locale curente nu au fost trimise în CI.
- PHPStan/Larastan, nivel 7: fără erori.
- Verificare TypeScript Vue: trecută.
- Testele frontend ale estimatorului de parolă: trecute.
- Browser: cele cinci grade ale parolei, respingerea unei parole previzibile pe server, afișarea/ascunderea și păstrarea datelor la eroare, înregistrarea și editarea numelui/prenumelui: trecute.
- Lint și formatare frontend: trecute.
- Laravel Pint: trecut.
- Build frontend: trecut. Dicționarele estimatorului de parolă generează un avertisment de dimensiune a bundle-ului; se încarcă separat, la prima tastare, fără a încărca tabloul de bord.
- `composer validate --no-check-publish`: trecut.
- Browser Chromium, desktop 1440 px și mobil 390 px: demo, confirmare, corectură, pontaj lunar, descărcare `.xlsx`, navigare mobilă și ieșire din demo, fără erori JavaScript.
- Browser, două sesiuni separate: înregistrare proprietar, configurare firmă, invitație, înregistrare angajat pe mobil, acceptare, confirmarea zilei și exportul proprietarului, fără erori JavaScript.
- Modal zilnic: două intervale verificate; coordonatele verticale ale câmpurilor început/sfârșit sunt identice în fiecare interval. Pe mobil se folosește o singură coloană.
- CIF/ANAF: teste pentru normalizare, autentificare, refuzul CNP-urilor și intrărilor nevalide, data în fusul București, cache, limitare globală și lock, timeout, răspunsuri incomplete sau pentru alt CIF, firmă negăsită, creare manuală, verificare exclusiv din cache-ul serverului, editări, firme existente, demo și exporturi.
- Browser, CIF pe desktop și mobil: completare denumire, sursă și dată, anularea confirmării după editare, ignorarea răspunsurilor întârziate, erori recuperabile, creare manuală și persistență în setări. Răspunsurile ANAF din acest test și capturile `company-lookup-*` sunt simulate cu firme fictive; testele PHP verifică separat salvarea confirmării pe server.

Paginile juridice și operatorul sunt verificate fără autentificare. Testele acoperă acceptarea explicită a versiunii curente, textul și hashul păstrate pe server, dovezi private și izolarea lor, acordul clientului profesional, configurarea unui consumator fără CIF, declarația de retragere confirmată, înregistrarea chiar la eșec SMTP, cereri GDPR cu termen calendaristic, rate limiting, registrul și soluționarea asistată, blocarea checkoutului neconfirmat în producție și arhiva sursei fără date runtime. Confirmarea contractuală Stripe verifică clientul/sesiunea, deduplicarea și atașarea condițiilor exacte. Pentru checkoutul consumatorului se verifică cererea expresă de începere anticipată.

Browserul a verificat pictograma SAL oficială servită local, paginile pe mobil fără depășire orizontală, retragerea în doi pași și dovada semnată, acceptarea nebifată inițial, înregistrarea și configurarea consumatorului și descărcarea dovezii termenilor. Au fost repetate cu succes și fluxurile de parole, CIF, proprietar–angajat și pontaj/demo. Capturile sunt `legal-desktop.png`, `legal-mobile.png`, `withdrawal-mobile.png`, alături de cele existente.

Testul simultan al invitațiilor a expus intermitent o conversie concurentă a unui lock de citire în SQLite. Invitația obține acum intenția de scriere înaintea numărării locurilor, fără modificarea valorii updated_at; timeoutul SQLite este de 5 secunde. Verificarea reală cu două procese a trecut în trei repetări consecutive și apoi în suita completă. MySQL/PostgreSQL păstrează verificarea FOR UPDATE. Suita completă a fost executată separat pe un MySQL 8.0.46 temporar; Matricea GitHub Actions a trecut ulterior pe SQLite, MySQL și PostgreSQL; [rezultatul verificării](https://github.com/banarares/ipontaj/actions/runs/45377364276) identifică exact commitul testat.

Capturile reale sunt în `docs/screenshots/`. Testele browser sunt în `tests/browser/`; nu folosesc date de producție.

Testele PHP acoperă izolarea firmelor, rolurile, invitațiile și regenerarea/revocarea lor, limitele și reactivările, o cursă reală între două procese pentru ultimul loc pe SQLite, turele peste miezul nopții, ambele schimbări ale orei, pauzele și suprapunerile, corecturile și auditul, blocarea/redeschiderea lunii, programele istorice, Excelul și formulele malițioase, mementourile duplicate, demo-ul și curățarea lui.

Pentru facturare se verifică local checkoutul și portalul cu un client Stripe simulat, modurile test/live, evenimente semnate fictiv, webhookuri duplicate, izolarea abonamentelor și refuzul configurării incomplete sau amestecate. Pentru contabil se verifică deduplicarea expedierii, linkurile semnate, expirarea și invalidarea lor după redeschiderea/reaprobarea lunii, inclusiv în aceeași secundă.

## ANAF — verificat extern

Endpointul public v9 a răspuns cu succes unei interogări de citire pe 24 noiembrie 2026. O verificare suplimentară prin `CompanyRegistry` a returnat `found`, CIF-ul cerut, denumirea și data verificării, folosind cache în memorie. Răspunsul real a confirmat structura `found[].date_generale` cu CUI, dată și denumire, fără câmpul rădăcină `cod` prezentat în unele exemple din documentație. Nu s-a salvat firma reală în baza aplicației sau în fixture-uri. Disponibilitatea externă poate varia; testele automate nu depind de ANAF.

## Stripe test — verificat extern

Cheile de test au fost furnizate local de proprietar. `STRIPE_TEAM_PRICE`, `STRIPE_PORTAL_CONFIGURATION` și `STRIPE_WEBHOOK_SECRET` au fost configurate fără afișarea secretelor. Verificarea cu API-ul Stripe și listenerul local a trecut pentru:

- checkout cu totalul 4.900 bani, moneda RON și preț inclusiv;
- crearea unei sesiuni de portal;
- o plată fictivă Stripe și activarea abonamentului local numai după webhook;
- retrimiterea evenimentului semnat fără abonament sau eveniment duplicat;
- anularea abonamentului și actualizarea drepturilor prin webhook.

Clientul Stripe fictiv și firma locală folosite la verificare au fost eliminate. Raportul local fără secrete este în `.runtime/stripe-verification.json`, ignorat de Git. Acesta este un test API; completarea manuală a checkoutului găzduit se poate încerca din Abonament, cu listenerul pornit. Nu s-au făcut încasări live și nu s-a verificat emiterea de facturi fiscale.

## Limite și verificări externe rămase

- Codul acceptă acum Stripe test/live, după activarea explicită și verificările tehnice. Nu s-a făcut o plată reală în verificările locale; recepția efectivă a webhookului public și emailurilor trebuie verificată pe găzduire. Procesul RO e-Factura este asumat manual de operator. Vezi BILLING.md.
- PostgreSQL este configurabil și există o matrice CI SQLite/PostgreSQL/MySQL, însă mediul local nu are extensia `pdo_pgsql` sau un server PostgreSQL. Suita pe MySQL-ul efectiv al resellerului rămâne de executat înainte de producție. Verificarea PostgreSQL din GitHub Actions a trecut. Rezultatele workflowurilor pentru commitul curent se consultă în fila Actions a repositoryului; raportul de față consemnează verificările locale.
- SMTP real, livrabilitatea și expedierea către un contabil real nu au fost verificate. Local, mailerul scrie în log; testele folosesc notificări simulate.
- Instalarea PWA pe dispozitive fizice, Safari/iOS și alte browsere rămâne de verificat. Nu există pontaj offline.
- Nu există încă facturare fiscală românească, flux automat de ștergere a firmei sau calcul/aprobare de drepturi salariale. Fluxul de ștergere este asistat de operator, documentat în OPERATIONS.md.
- Unele componente avansate de securitate și emailurile standard ale frameworkului necesită o revizuire suplimentară a traducerii. Ecranele principale sunt în română.
- Backupul/restaurarea și monitorizarea producției sunt documentate, fără exercițiu pe infrastructură reală.
- Actualizarea repositoryului este separată de deploy. Publicarea codului pe ramurile `develop` și `master` nu instalează aplicația pe găzduire și nu activează încasările.

Decizii confirmate: **49 lei/lună, preț final; operator neînregistrat în scopuri de TVA**, **AGPL-3.0-only**, Stripe configurat și verificat în test.

## Pregătirea juridică — limite externe

Datele PFA-ului, contactul, domeniul, contul reseller și absența înregistrării TVA sunt confirmate de titular. Pictograma SAL a fost descărcată din pagina publică ANPC, verificată vizual și păstrată nemodificată, afișată proporțional în 250×50 px. Inventarul conține 480 de dependențe fixate, cu notificările de licență originale disponibile; arhiva publică de sursă include doar fișierele permise, directoarele goale necesare instalării și configurarea npm fără acreditări locale.

Contractul/DPA reseller, locațiile și retenția efectivă a copiilor, acordurile Stripe/transferurile, CAEN, facturarea și e-Factura, retenția operațională, SMTP real, exercițiul de restaurare/ștergere și fluxul contractual integral cu checkout găzduit nu sunt declarate finalizate. `pontaj:legal:check` le raportează în continuare; acesta este rezultatul așteptat al verificării unei lansări încă neconfirmate, nu o certificare juridică. Documentele sunt în starea de pregătire pentru lansare. Vezi [LEGAL_LAUNCH.md](LEGAL_LAUNCH.md), [CYBERFOLKS_REQUEST.md](CYBERFOLKS_REQUEST.md) și [GDPR_OPERATIONS.md](GDPR_OPERATIONS.md). Solicitarea către furnizor este pregătită, dar nu a fost trimisă. Nu s-a făcut deploy și nu s-au activat plăți reale.

## Corecții și pregătirea publicării

Patru teste de regresie au reprodus înaintea corecției salvarea într-o pauză de inactivitate și încărcarea lunii greșite din linkurile zilnice. Salvarea orelor și absențelor verifică acum aceleași perioade istorice folosite la planificare, inclusiv pentru proprietar. Limitele perioadelor sunt inclusive. Ziua explicită determină luna, înregistrările și închiderea încărcată; în lipsa zilei, o lună istorică selectează prima sa zi. Testele verifică și izolarea înregistrărilor angajatului. Interfața explică inactivitatea și ascunde comenzile de înregistrare.

Footerul grupează linkurile ANPC, SAL, Consumer Redress în UE și ANSPDCP cu iconuri Lucide de aceeași dimensiune, în rânduri egale. Pictograma oficială SAL rămâne nemodificată, servită local în 250×50 px. Sunt legături informative către autorități și resurse, fără afirmații de aprobare sau certificare. Verificarea browser măsoară alinierea iconurilor, textelor, cardurilor și pictogramei SAL la 1440, 390 și 320 px și verifică URL-ul european și absența depășirii orizontale.

Utilitarul privat cPanel a creat un snapshot SQLite consistent, a construit schema din migrările Laravel într-un MySQL temporar și a verificat conținutul celor 25 de tabele după transfer și restaurare. Au fost verificate separat exportul nativ MySQL și restaurarea sa, pe toate cele 25 de tabele, inclusiv metadatele de migrare. Originalul rămâne complet; numai copia pentru deploy golește sesiunile, cache-ul, resetările și cozile vechi. Arhiva aplicației include buildul și vendorul PHP fără dependențe de dezvoltare. Configurația secretă, scripturile locale și rezultatele sunt excluse din sursa distribuită. Nu este un exercițiu pe infrastructura resellerului.

Scanarea istoricului existent a acoperit 8 commituri, 4 arbori și 135 de bloburi distincte, fără potriviri pentru tiparele de acreditări verificate. Scannerul pentru publicare verifică separat căile private, conținutul indexului Git și eventualele valori configurate local, fără a afișa valorile. Cinci teste automate cu date sintetice verifică aceste controale. Auditurile Composer și npm nu au raportat vulnerabilități cunoscute la data verificării.

## Texte pentru publicare

Versiunea juridică `2026-11-24.2` elimină notele temporare din textele publice și adaugă GitHub la pagina Licență, alături de arhiva locală a sursei. Testele verifică păstrarea integrală a dovezilor versiunii precedente, refuzul cheilor Stripe live și diferențierea checkoutului demonstrativ. Bannerul public a fost eliminat independent de verificările de pregătire a infrastructurii, care rămân active.

## Cloudflare, contact, cookie-uri și SEO — modificări locale

Testele noi acoperă mesajele de contact criptate și păstrarea lor la eșecul notificării, honeypot și rate limiting, validarea Turnstile pe cele trei POST-uri, hostname/acțiune, reutilizarea tokenului, timeout, configurare incompletă și refuzul cheilor dummy în producție. Proxy-ul acceptă antetele numai de la intervalele Cloudflare configurate și nu acceptă hostul redirecționat.

În browser, pe o bază separată cu emailuri dezactivate, au trecut contactul, footerul pe desktop/mobil, absența lui pe login/register, refuzul și persistența preferințelor, acceptarea și retragerea analizei și lipsa beaconului în cont. Tranziția către login și intrarea în demo cu analiză acceptată închid documentul public înainte de încărcarea ecranelor private. Widgetul Cloudflare real și Siteverify au fost verificate cu cheile publice oficiale de test pentru înregistrare, autentificare și contact, inclusiv adaptarea la 320 px. Cheile clientului nu au fost configurate sau afișate.

Fluxul complet de pontaj a fost repetat fără alegerea inițială a cookie-urilor: confirmare, corectură, export Excel, modal pe desktop/mobil și ieșire din demo, fără erori JavaScript. Meniul lateral rezervă spațiul bannerului; comenzile de salvare și creare a contului rămân accesibile. Câmpurile început/sfârșit sunt în continuare aliniate în ambele intervale.

SEO are conținut HTML inițial pe pagina principală, canonical, metadate sociale și JSON-LD fără ratinguri inventate, sitemap limitat la paginile publice și noindex pentru cont. Metadatele se schimbă corect la navigarea Inertia. Configurarea locală rămâne neindexabilă implicit. Nu s-au făcut verificări de proprietate DNS, înregistrare Search Console sau cereri de indexare.

Documentele active sunt `2026-11-24.3`; versiunile precedente sunt păstrate. Instrucțiunile de operare sunt în [CLOUDFLARE_SEO.md](CLOUDFLARE_SEO.md). La cererea titularului, aceste modificări rămân locale, fără commit sau push; rezultatele CI ale commiturilor anterioare nu reprezintă o verificare a lor.

## Colegi fără cont, pontaj istoric și footer

Opt teste noi verifică asumarea explicită pentru colegii fără email, absența unui cont/invitații fictive, izolarea pe firme și drepturile proprietarului, limita comună cu invitațiile, mementourile oprite, numele în Excel/JSON și aprobarea responsabilului fără a atribui confirmarea angajatului. Corectarea primei zile necesită confirmare și motiv, este auditată și se anulează integral dacă intervalele sunt invalide; nu deschide pauze de inactivitate, luni aprobate sau zile viitoare. Testul cu procese concurente verifică acum și cursa dintre o invitație și un coleg fără cont pentru ultimul loc.

Browserul a verificat formularul fără email pe mobil, salvarea și reîncărcarea pontajului, extinderea explicită a datei de început și footerul discret cu date legale extensibile, fără erori JavaScript. Între tabelul istoricului și buton sunt 22 px. Fluxul demo, corectura, exportul și navigarea mobilă au fost repetate cu succes. Capturile sunt `history-spacing-desktop.png`, `past-day-confirmation.png`, `managed-member-mobile.png` și `authenticated-footer-*`.

Migrarea locală a fost aplicată după un backup SQLite consistent privat. La instalarea pe găzduire trebuie aplicate migrările din release; nu se copiază baza fictivă a testelor.

## Configurația juridică și cPanel PHP 8.3

Exemplele cron folosesc executabilul confirmat `/usr/local/bin/ea-php83`. Ghidul LEGAL_ENV distinge datele factuale de confirmările operatorului. Factura confirmă Cyber_Folks SRL, CIF RO33424916; cele 13 copii JetBackup nu dovedesc o durată de 13 zile. ANAF v9 a returnat CAEN principal 6210 pentru CIF 33388821, fără verificarea tuturor activităților autorizate sau a încadrării SaaS. Cheile Stripe live și resursele Billing nu confirmă transmiterea RO e-Factura. Nu au fost declarate finalizate aceste demersuri.

Titularul a aprobat 30 de zile pentru logurile aplicației. Comanda nouă previzualizează implicit; aplicarea cere logger zilnic și durată pozitivă, limitează ștergerea la fișierele zilnice locale expirate și păstrează ziua-limită, alte fișiere, symlinkuri și logul single anterior. Schedulerul o execută la 03:20 UTC dacă politica este configurată. Patru teste acoperă previzualizarea, limitele de vârstă, refuzul configurației incomplete, izolarea căilor și execuția repetată fără bază de date. Confirmarea planului general de retenție rămâne separată.

În producție, înregistrarea gratuită și configurarea firmei nu mai primesc 503 exclusiv din cauza checklistului comercial. Un test verifică întregul flux fără a modifica declarațiile neconfirmate; checkoutul cu facturare activată păstrează blocarea, iar restricția cheilor Stripe live rămâne. Suita completă a trecut pe SQLite și MySQL temporar, câte 162 teste și 1213 aserțiuni; Pint, PHPStan nivel 7, lint și formatare au trecut. Nu s-a repetat verificarea browserului pentru aceste modificări backend/documentație. Nu s-au făcut commit, push, deploy, încasări sau trimiteri de emailuri reale.

## Confirmare email, notificări, iPhone și Stripe live — 25 noiembrie

Înregistrarea nu mai lasă o sesiune autentificată înainte de confirmarea emailului. Middlewareul acoperă și sesiunile existente, autentificarea, 2FA/passkeys și schimbarea emailului. Linkul semnat poate fi deschis fără autentificare, inclusiv pe alt browser; verifică semnătura, termenul și hashul adresei curente, fără a autentifica utilizatorul. Verificarea și schimbarea adresei sunt serializate la scriere. Retrimiterea folosește doar contul dovedit de sesiunea limitată și este throttled. Destinația invitației se păstrează prin înregistrare, verificare și autentificare.

Confirmarea, recuperarea parolei și toate mesajele de notificare folosesc logo-ul iPontaj, antetul comun și footer în română. Contactul este randat în aceeași prezentare și caracterele speciale din conținut sunt afișate ca text. Testul cu coadă database reală a procesat confirmarea, contactul și invitația prin transport array, cu zero joburi rămase/eșuate. Nu s-au trimis emailuri reale. Comanda de diagnostic raportează numai configurarea și contoarele, fără parole sau payloaduri; workerul cron are lock și heartbeat.

Notificările interne persistă separat de audit, sunt izolate pe destinatar/apartenențe active și se anulează cu tranzacția dacă operația eșuează. Marcarea este idempotentă; deschiderea verifică destinatarul și selectează firma corectă. Clopoțelul afișează necititele și ultimele 30, cu actualizare periodică și după navigare. Nu există integrare Firebase sau push. Migrarea locală a fost aplicată după backup SQLite consistent privat; baza live nu a fost accesată.

Chromium desktop și WebKit 27.2 cu profil iPhone 13 au verificat înregistrarea cu confirmare, notificările, marcarea tuturor ca citite, încadrarea câmpurilor native de dată/oră și salvarea pontajului, fără erori JavaScript. Capturile sunt private în `.runtime/day-editor-*` și `.runtime/notifications-*`. WebKit folosește biblioteci extrase în directorul privat, fără instalare de sistem. Testul nu înlocuiește verificarea pe un iPhone fizic, în modul standalone. Fluxurile browser pentru colegi fără email, pontaj istoric și proprietar–invitație–angajat cu confirmarea ambelor adrese au trecut; spațierea istoricului este 22 px.

Plata nu mai este limitată la test. În producție, un client Stripe simulat a verificat checkoutul live, fără a modifica declarațiile operatorului și fără activare locală înainte de webhook. Prețul greșit este refuzat înaintea creării clientului/contractului. Cheile amestecate, webhookul lipsă, semnătura falsă și evenimentele din modul greșit sunt respinse. Dovada contractuală păstrează modul real. Titularul a confirmat dreptul de a vinde și a asumat transmiterea manuală RO e-Factura; aplicația nu o transmite automat. Checklistul rămâne raport separat de blocajele tehnice. Nu s-au creat clienți, abonamente sau încasări reale în această verificare.

Documentele active sunt `2026-11-25.1`; versiunile precedente rămân intacte. Scannerul permite numai override-uri Blade legitime în `resources/views/vendor`, continuă să verifice conținutul lor și refuză arborii de dependențe. Opt teste Python cu date sintetice au trecut. Actualizarea este pregătită pentru încărcare manuală pe cPanel; nu s-au făcut commit, push sau deploy.

Verificarea finală a trecut pe SQLite și MySQL 8.0.46 temporar: **177 teste, 1366 aserțiuni** pe fiecare. PHPStan nivel 7, Pint, TypeScript, lint/formatare frontend și buildul au trecut. Avertismentul existent privind dimensiunea dicționarelor parolei rămâne; acestea sunt încărcate separat.
