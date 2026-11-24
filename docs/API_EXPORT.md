# Exportul datelor pentru transfer

Proprietarul autorizat al spațiului poate descărca `GET /company/data`, răspuns JSON UTF-8, `Content-Disposition: attachment`. Endpointul folosește sesiunea autentificată și spațiul curent; nu există acces prin simpla cunoaștere a CIF-ului. Nu transmite cookie-ul de sesiune altui furnizor. Descarcă fișierul și transferă-l printr-un canal autorizat sau solicită asistența operatorului.

Structura: `generated_at`, `company`, `members` (cu datele de identificare ale persoanelor necesare), `schedules`, `entries` cu `intervals`, `closures`, `audit`. Datele și identificatorii permit corelarea între secțiuni; intervalele păstrează reprezentarea temporală și fusul firmei necesare interpretării. Datele de confirmare și aprobare sunt distincte. Înregistrările pot conține date personale, inclusiv absențe, și nu trebuie publicate sau folosite drept fixture-uri reale.

Un coleg fără cont are `managed: true` și `user_id: null`, cu nume/prenume în apartenență și un profil `user` doar pentru afișare, fără ID sau email. Acesta nu reprezintă un cont de autentificare. `managed_confirmed_by` și `managed_confirmed_at` păstrează asumarea proprietarului. Pontajele sale au `confirmed_by: null`; aprobarea este atribuită responsabilului prin `approved_by`. Nu interpreta aprobarea drept confirmare a angajatului.

Parolele, autentificarea multifactor, tokenurile de recuperare și identificatorii Stripe ascunși ai firmei nu sunt incluși. Dovada contractuală individuală se descarcă separat de titularul acceptării din Contul meu. Un export de firmă nu trebuie oferit automat unui angajat care solicită acces doar la datele sale.

Pentru centralizator și intervale lizibile: `GET /exports?month=YYYY-MM`. Se livrează XLSX, cu statut provizoriu/aprobat. Formatul este pentru verificare și prelucrare, fără promisiune de import automat într-un produs terț.

Pentru tranziție asistată sau date exportabile care nu sunt suficiente în formatul standard, contactează office@ipontaj.ro. Documentează cererea, întinderea, destinatarul, calendarul, verificarea integrității și recuperarea ulterioară. Nu șterge sursa înainte de confirmarea transferului sau de instrucțiunea legală de ștergere. Condițiile Data Act și drepturile GDPR se verifică distinct; codul sursă este accesibil la `/cod-sursa`, cu instrucțiunile de instalare.
