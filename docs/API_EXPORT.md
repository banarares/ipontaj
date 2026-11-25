# Exportul datelor pentru transfer

Proprietarul autorizat al spațiului poate descărca `GET /company/data`, răspuns JSON UTF-8, `Content-Disposition: attachment`. Endpointul folosește sesiunea autentificată și spațiul curent; nu există acces prin simpla cunoaștere a CIF-ului. Nu transmite cookie-ul de sesiune altui furnizor. Descarcă fișierul și transferă-l printr-un canal autorizat sau solicită asistența operatorului.

Structura: `generated_at`, `company`, `members` (cu datele de identificare ale persoanelor necesare), `schedules`, `entries` cu `intervals`, `closures`, `audit`. Datele și identificatorii permit corelarea între secțiuni; intervalele păstrează reprezentarea temporală și fusul firmei necesare interpretării. Datele de confirmare și aprobare sunt distincte. Înregistrările pot conține date personale, inclusiv absențe, și nu trebuie publicate sau folosite drept fixture-uri reale.

Un coleg fără cont are `managed: true` și `user_id: null`, cu nume/prenume în apartenență și un profil `user` doar pentru afișare, fără ID sau email. Acesta nu reprezintă un cont de autentificare. `managed_confirmed_by` și `managed_confirmed_at` păstrează asumarea proprietarului. Pontajele sale au `confirmed_by: null`; aprobarea este atribuită responsabilului prin `approved_by`. Nu interpreta aprobarea drept confirmare a angajatului.

Parolele, autentificarea multifactor, tokenurile de recuperare și identificatorii Stripe ascunși ai firmei nu sunt incluși. Dovada contractuală individuală se descarcă separat de titularul acceptării din Contul meu. Un export de firmă nu trebuie oferit automat unui angajat care solicită acces doar la datele sale.

Pentru centralizator și intervale lizibile: `GET /exports?month=YYYY-MM`. Se livrează XLSX, cu statut provizoriu/aprobat, cu trei foi:

- **Pentru contabil**, deschisă implicit: ore zilnice și totaluri numerice în ore zecimale (`7,50 h = 7 h 30 min`), ore confirmate/aprobate separat de orele de verificat, zile lucrate, CO/CM/CFP/alte absențe aprobate, zile lipsă și înregistrări neconfirmate. Statutul lunii apare în antet și pe fiecare rând. Totalurile echipei folosesc persoane-zile, nu zile calendaristice distincte. Numele și antetul rămân fixate la derulare; există filtru și setare de tipărire A3 orizontal.
- **Centralizator**: formatul anterior, cu valori zilnice în minute și totaluri în ore.
- **Intervale detaliate**: început/sfârșit cu offset de fus orar, confirmări, aprobări și observații. „Minute nete zi (prima linie)” este numeric și apare o singură dată pentru fiecare zi, evitând dublarea la însumare. „Minute interval (+lucru / −pauză)” permite verificarea fiecărui interval.

În prima foaie, `?` înseamnă zi programată scadentă fără înregistrare, `—` zi fără program, iar o celulă goală zi viitoare programată. Înregistrările care necesită verificare sunt colorate și au explicații în comentariul celulei. Absențele neaprobate sunt afișate prin cod, dar nu sunt incluse în totalurile aprobate. Zilele înregistrate neconfirmate nu au nici confirmarea angajatului, nici aprobarea responsabilului; aprobarea unui coleg fără cont nu este prezentată drept confirmare a angajatului.

Calculele păstrează precizia minutelor; afișarea orelor la două zecimale nu rotunjește datele sursă. Turele aparțin zilei/lunii de început, fără împărțire automată la miezul nopții. Totalurile de absențe reprezintă înregistrări zilnice aprobate, nu solduri sau zile indemnizabile. Diferența față de program nu determină automat ore suplimentare. Vezi [analiza tipurilor de timp și concediu din România](RO_ATTENDANCE_REVIEW.md) pentru limitele actuale.

Formatul este pentru verificare și prelucrare, fără promisiune de import automat într-un produs terț. Numele, observațiile și codurile sunt scrise ca text explicit, fără interpretarea lor ca formule Excel.

Pentru tranziție asistată sau date exportabile care nu sunt suficiente în formatul standard, contactează office@ipontaj.ro. Documentează cererea, întinderea, destinatarul, calendarul, verificarea integrității și recuperarea ulterioară. Nu șterge sursa înainte de confirmarea transferului sau de instrucțiunea legală de ștergere. Condițiile Data Act și drepturile GDPR se verifică distinct; codul sursă este accesibil la `/cod-sursa`, cu instrucțiunile de instalare.
