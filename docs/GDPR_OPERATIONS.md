# Proceduri interne de protecție a datelor — proiect pentru aprobare

Aceste proceduri trebuie completate cu persoanele autorizate, locațiile și duratele contractuale și apoi aplicate. Existența fișierului nu înseamnă că un audit, exercițiu de restaurare sau ștergere a avut loc.

## Registrul activităților

Păstrează separat rolul PFA-ului de operator pentru conturi, suport, facturare și securitate și rolul de împuternicit pentru pontajul clienților. Pentru fiecare activitate documentează scopul, persoanele/datele, temeiul operatorului, destinatarii, transferurile, retenția, măsurile și persoana responsabilă. Nu presupune că excepția pentru organizații cu mai puțin de 250 de angajați elimină obligațiile în cazul unor prelucrări regulate sau cu date speciale.

Inventar inițial: users/autentificare, memberships și perioade istorice, invitations, schedules, time_entries și intervals, month_closures, audit_events, legal_acceptances, legal_requests, abonamente/evenimente Stripe, sesiuni, notificări/cozi, loguri și backupuri. Codul CM și observațiile pot dezvălui sănătatea: minimizează, limitează accesul, documentează instrucțiunile clientului și analiza art. 9. Nu colecta CNP sau copii medicale.

## Planul de retenție de aprobat

| Categorie                    | Regula propusă / decizia necesară                                                                                                                           |
| ---------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Cont și date operaționale    | Pe durata serviciului; export și ștergere/anonimizare asistată în cel mult 30 zile după închiderea verificată, cu excepții justificate                      |
| Pontaj și audit              | Durata instruită de client, în raport cu obligațiile sale; nu șterge auditul necesar separându-l de istoricul relevant                                      |
| Invitații                    | Valabilitate 7 zile; aprobă separat termenul de eliminare a evidențelor expirate/revocate                                                                   |
| Acceptări și cereri juridice | Păstrează strict dovada necesară pentru termenul aplicabil drepturilor/obligațiilor; stabilește durata cu responsabilul juridic și apoi șterge/anonimizează |
| Documente fiscale            | Termenele legale aplicabile confirmate de contabil; păstrează documentele originale necesare, inclusiv cele e-Factura                                       |
| Loguri de aplicație          | Alege o durată proporțională, configurează canal daily și rotația, verifică să nu conțină parole, tokenuri, exporturi sau mesaje excesive                   |
| Loguri ale furnizorului      | Confirmare contractuală distinctă; rotația Laravel nu șterge logurile web/SMTP ale gazdei                                                                   |
| Cozi și notificări           | Curățare controlată a joburilor eșuate/expirate și evidențelor de deduplicare, păstrând funcționarea retryului                                              |
| Backupuri                    | Durata exactă din contractul reseller și o procedură de expirare; confirmarea publică generală nu este suficientă                                           |
| Demo                         | Curățare după 24h prin comanda programată; verifică schedulerul                                                                                             |

Până la aprobarea și aplicarea planului, păstrează LEGAL_RETENTION_APPROVED=false. Nu activa o ștergere distructivă generală doar pentru a completa această listă.

## Cereri și ștergere asistată

Verifică zilnic registrul `pontaj:legal:requests`. Confirmă identitatea proporțional, identifică cine este operator, inventariază datele și destinatarii și răspunde în termen. Pentru acces, exclude datele altor persoane și secretele de securitate; un export global al firmei nu este automat răspunsul corect pentru un angajat.

La închidere, verifică dreptul solicitantului, notifică și obține instrucțiunile operatorului client, exportă datele, separă obligațiile de păstrare și stabilește ștergerea/anonimizarea. Nu șterge automat o persoană care are încă apartenențe sau obligații de evidență. Revocă sesiunile, linkurile, invitațiile și accesul atunci când este cazul. Consemnează categoriile, temeiul unei excepții, data, responsabilul și rezultatul. La restaurare reaplică instrucțiunile de ștergere înainte de redeschiderea serviciului.

## Incidente

Limitează accesul afectat și păstrează dovezile minimale; nu suprascrie logurile utile. Documentează momentul luării la cunoștință, datele/persoanele, riscul, destinatarii, măsurile și comunicările. Când PFA-ul este împuternicit, informează clientul fără întârzieri nejustificate și oferă actualizări. Când este operator, evaluează notificarea autorității în cel mult 72h de la luarea la cunoștință și informarea persoanelor dacă există risc ridicat; documentează și decizia justificată de a nu notifica. Nu expedia mesaje sau notificări către autorități fără validarea faptelor și autorizarea persoanei responsabile.

## Acces și verificare periodică

Folosește conturi administrative individuale, MFA pentru reseller/cPanel/email/Stripe, privilegii minime și revocare la schimbarea personalului. Separă mediile și datele de test. Verifică actualizările, patchurile, joburile și configurarea de producție. Efectuează o restaurare controlată, un exercițiu de cerere GDPR și unul de retragere/rambursare înainte de live; documentează rezultatul și repetă după modificări relevante.
