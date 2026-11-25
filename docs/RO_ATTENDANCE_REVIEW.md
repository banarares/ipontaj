# Pontajul și tipurile de timp în România

Verificare funcțională la 25 noiembrie 2026, pe baza codului local și a surselor oficiale de mai jos. Rezultatul: iPontaj păstrează intervale efective și patru categorii de absență, dar **nu acoperă încă toate regimurile de muncă, pauzele și concediile din România**. Exportul nou îmbunătățește prelucrarea contabilă; nu extinde catalogul de absențe și nu calculează salarii.

## Ce oferă aplicația acum

`AttendanceService::ABSENCES` este un catalog fix: CO, CM, CFP și A. O înregistrare zilnică are fie intervale de lucru/pauză, fie o absență pentru întreaga zi. Nu se poate combina o învoire de două ore cu muncă în aceeași zi și nu există cereri de concediu pentru viitor.

Programul poate fi comun sau individual, cu istoric, zile ale săptămânii și o pereche început/sfârșit. Intervalele efective pot fi multiple, inclusiv peste miezul nopții, cu fus orar și offset explicit. Toate pauzele introduse sunt scăzute din lucru. Programul planificat nu scade pauze și nu exclude automat sărbătorile legale. Aprobarea responsabilului și confirmarea angajatului sunt distincte.

## Cerințe relevante și lipsuri

În tabel, referința juridică identifică regimul relevant; evaluarea și propunerile din ultimele coloane sunt concluzii despre implementarea iPontaj.

| Domeniu și temei                                                                                                                                                                   | Implementarea actuală                                                  | Extindere necesară                                                                 |
| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- | ---------------------------------------------------------------------------------- |
| Evidență zilnică cu început/sfârșit: [Codul muncii, art. 119](https://legislatie.just.ro/Public/DetaliiDocument/128647).                                                           | Intervalele exacte sunt în detalii. Foaia contabilă prezintă ore nete. | Păstrarea ambelor niveluri; documentarea regulilor firmei.                         |
| Normă, program inegal și timp parțial: [art. 112–118, 103–107](https://legislatie.just.ro/Public/DetaliiDocument/128647).                                                          | Program simplu pe zile, fără normă contractuală distinctă.             | Normă cu istoric, ture alternative și reguli pentru categorii de personal.         |
| Pauzele pot fi incluse prin CCM/regulament intern: [art. 134](https://legislatie.just.ro/Public/DetaliiDocument/128647).                                                           | Orice pauză scade minutele nete.                                       | Pauză inclusă/exclusă, politică cu dată de aplicare și program net comparabil.     |
| Muncă suplimentară și compensare: [art. 120–124](https://legislatie.just.ro/Public/DetaliiDocument/128647).                                                                        | Diferență lunară față de program, fără clasificare juridică.           | Solicitare/acord, aprobare și evidența compensării distincte.                      |
| Muncă de noapte, repaus, sărbători: [art. 125–128, 135–142](https://legislatie.just.ro/Public/DetaliiDocument/128647).                                                             | Fără totaluri tematice, calendar legal sau verificarea repausului.     | Împărțire pe zile civile, calendar aplicabil persoanei, avertizări și compensări.  |
| Odihnă și concedii suplimentare: [art. 144–151](https://legislatie.just.ro/Public/DetaliiDocument/128647).                                                                         | CO pe zi întreagă, fără sold.                                          | Drept anual, report, cerere/aprobare și reguli de consum.                          |
| Evenimente familiale, îngrijitor, urgențe familiale: [art. 152–152²](https://legislatie.just.ro/Public/DetaliiDocument/128647).                                                    | Numai A generic.                                                       | Categorii distincte; recuperare pentru urgențe; fără amestec cu CO.                |
| Fără plată și formare profesională: [art. 153–158](https://legislatie.just.ro/Public/DetaliiDocument/128647).                                                                      | CFP generic; formarea nu este identificată.                            | Separarea motivului, duratei și tratamentului aplicabil.                           |
| Concediu paternal: [Legea 210/1999](https://legislatie.just.ro/Public/DetaliiDocument/258132).                                                                                     | Nu există categorie dedicată.                                          | Evidență și total propriu, separat de CM/CO.                                       |
| Concedii medicale, maternitate, risc maternal, îngrijirea copilului bolnav/pacientului oncologic: [OUG 158/2005, art. 2](https://legislatie.just.ro/Public/DetaliiDocument/66305). | Un singur CM.                                                          | Subcategorii și unități potrivite; fără calcul automat al indemnizației în pontaj. |
| Creșterea copilului: [OUG 111/2010](https://legislatie.just.ro/Public/DetaliiDocument/124331).                                                                                     | Fără categorie sau flux dedicat.                                       | Perioade de absență și program coerent cu situația contractuală.                   |
| Telemuncă și evidența orelor: [Legea 81/2018, art. 5](https://legislatie.just.ro/Public/DetaliiDocument/252866).                                                                   | Poate înregistra intervale, fără marcaj telemuncă.                     | Context al muncii separat de absențe; nu consumă concediu.                         |

Acesta este un inventar al lipsurilor importante pentru produs, nu o listă exhaustivă a tuturor drepturilor prevăzute de legi speciale, contracte colective și regimuri sectoriale. Literele CO/CM/CFP/A sunt convențiile aplicației; nu reprezintă un nomenclator universal impus pentru orice pontaj sau import contabil.

## Model propus pentru extindere

1. **Pauze și program:** separăm timpul de prezență de timpul de muncă; o pauză inclusă nu reduce netul, iar una exclusă îl reduce. Politica firmei se versionează, fără recalcularea tacită a lunilor aprobate.
2. **Absențe configurabile:** catalog cu denumire/cod, categorie, unitate (ore/zile), necesitatea aprobării, data aplicării și legătura cu regula firmei. Adăugăm categorii dedicate pentru cazurile din tabel. O etichetă „plătit” nu calculează singură indemnizația. Configurația nu trebuie să permită eliminarea drepturilor legale.
3. **Zi mixtă:** învoire/absență parțială și muncă, fără suprapuneri; programări viitoare separate de orele efectiv prestate; solduri separate de înregistrările zilnice.
4. **Clasificarea orelor:** normă contractuală, calendar, ture și împărțire la miezul nopții, inclusiv între luni. Dimensiunile noapte/sărbătoare/suplimentar pot coincide și nu trebuie modelate ca opțiuni reciproc exclusive. O sâmbătă lucrată sau diferența lunară pozitivă nu declanșează singure clasificarea ca ore suplimentare.
5. **Contabilitate și acces:** totaluri distincte și explicații pentru fiecare unitate; păstrăm versiunea regulilor folosite, autorul și aprobarea. Extinderea catalogului nu necesită afișarea diagnosticului sau a actelor medicale colegilor.

Ordinea recomandată de implementare este cea de mai sus. Etapele 1–4 sunt **de făcut**, nu funcții activate de actualizarea exportului.

## Limitele exportului livrat

- „Pentru contabil” afișează ore zecimale numerice, totaluri confirmate/aprobate, ore de verificat, zile lucrate și absențe aprobate distincte. Absențele neaprobate rămân marcate și nu măresc totalurile aprobate.
- Totalurile de absențe numără **înregistrări zilnice aprobate**, inclusiv dacă responsabilul a introdus o absență într-o zi fără program. Nu sunt solduri legale, zile calendaristice indemnizabile sau calcul de salariu. Aplicația nu validează încă eligibilitatea și consumul fiecărui concediu.
- Ziua și luna sunt cele ale începutului turei. O tură care trece în luna următoare nu este încă împărțită automat; intervalele exacte rămân în detalii.
- „Centralizator” păstrează minutele zilnice. În „Intervale detaliate”, netul zilei apare o singură dată; fiecare interval are separat minute cu semn, pozitiv pentru lucru și negativ pentru pauză.
- Exportul nu este stat de plată, declarație D112 sau format de import certificat pentru o aplicație contabilă. Alegerea regulilor aplicabile fiecărei firme și verificarea drepturilor rămân distincte de confirmarea tehnică a pontajului.
