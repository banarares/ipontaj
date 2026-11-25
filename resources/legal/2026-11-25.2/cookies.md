## Cookie-uri și tehnologii similare

Cookie-urile strict necesare asigură autentificarea, securitatea și preferințele pe care le alegi. Ele rămân active pentru funcționarea serviciului. Fonturile și iconurile interfeței sunt găzduite de aplicație.

Din banner poți accepta toate categoriile disponibile, refuza analiza opțională sau salva o alegere personalizată. Acceptarea și refuzul sunt la fel de accesibile. Poți redeschide oricând „Setări cookie-uri”, inclusiv după autentificare. Alegerea nu condiționează folosirea contului și nu reprezintă înscriere la marketing.

| Element                                         | Scop                                                       | Durată / control                                                                                |
| ----------------------------------------------- | ---------------------------------------------------------- | ----------------------------------------------------------------------------------------------- |
| Cookie de sesiune iPontaj, cu nume configurabil | Autentificare și securitatea sesiunii                      | Conform configurării sesiunii; durata curentă este afișată în caseta tehnică                    |
| XSRF-TOKEN                                      | Protecție împotriva trimiterilor neautorizate de formulare | Corelat cu sesiunea                                                                             |
| remember_web_*                                  | Menținerea autentificării dacă alegi „Ține-mă minte”       | Până la deconectare, expirare sau revocare; durată afișată în caseta tehnică                    |
| appearance, sidebar_state                       | Preferințe explicite privind afișarea                      | Cookie de aspect: până la un an; localStorage: până la ștergere/modificare; meniu lateral: până la 7 zile, când funcția este folosită |
| sessionStorage / istoricul Inertia criptat      | Navigare și revenirea în ecrane în sesiunea curentă        | Controlat de browser și sesiune; curățat la trecerea la pagini neautentificate                  |
| Cache API / service worker                      | Pagina publică offline și iconurile aplicației             | Până la actualizarea aplicației sau ștergerea datelor site-ului                                 |
| `_ga`, `_ga_<ID>` (Google Analytics)            | Statistici agregate de vizitare, numai cu acordul tău      | Până la 2 ani, conform configurării Google Analytics; șterse de aplicație la retragerea acordului |

Nu păstrăm în cache-ul PWA răspunsuri autentificate, pontaje sau fișiere Excel. Accesul offline la pontaj nu este oferit.

## Preferința ta

Salvăm local în browser, sub cheia `ipontaj:cookie-preferences`, versiunea informării, momentul alegerii și categoriile permise. Preferința este valabilă 180 de zile și este cerută din nou dacă se schimbă versiunea sau disponibilitatea analizei. Dacă browserul nu permite salvarea, analiza rămâne oprită. Nu deducem acordul din navigare, derulare sau lipsa unei acțiuni.

## Cloudflare, Google și analiza opțională

Dacă este activat, Cloudflare Turnstile verifică solicitările de autentificare, înregistrare și contact pentru a limita abuzurile. Este o măsură de securitate distinctă de analiza opțională și nu este dezactivată prin refuzul acesteia. Cloudflare poate prelucra IP-ul, caracteristici ale browserului și datele tehnice necesare verificării. Consultă [informarea Cloudflare](https://www.cloudflare.com/privacypolicy/) și [anexa Turnstile](https://www.cloudflare.com/turnstile-privacy-policy/).

Analiza opțională poate folosi Cloudflare Web Analytics și/sau Google Analytics, încărcat prin Google Tag Manager; serviciile active sunt afișate în banner și în caseta tehnică. Analiza se încarcă numai pe paginile publice de prezentare și contact, dacă este disponibilă în această instalare și ai acceptat categoria. Nu încărcăm analiza în cont, în pontaj, pe paginile de autentificare, invitații sau recuperare a parolei. Refuzul o ține oprită, iar retragerea acordului șterge cookie-urile Google Analytics ale site-ului și reîncarcă pagina pentru a opri și ascultătorii de evenimente ai serviciilor. Funcțiile de pontaj nu depind de această analiză.

Google Analytics primește date despre pagina vizitată, sursa vizitei, dispozitiv, browser, locația aproximativă și un identificator aleator salvat în cookie-urile `_ga`. Semnalăm serviciului că ai acceptat doar statistici: stocarea și datele în scop publicitar și personalizarea reclamelor rămân refuzate. Nu folosim pixeli publicitari, remarketing, hărți sau clipuri încorporate. Detalii despre furnizor și transferuri găsești la [Furnizori și transferuri](/furnizori) și în [informarea Google](https://business.safety.google/privacy/).

## Servicii externe și control

Checkoutul și portalul Stripe se deschid pe infrastructura Stripe și pot utiliza propriile tehnologii de securitate și plată. Consultă informarea Stripe înainte de folosire; acest lucru nu activează analiză ori marketing în iPontaj.

Poți șterge cookie-urile și datele site-ului din browser; este posibil să fii deconectat și să pierzi preferințele locale. Nu ștergi prin aceasta datele de pe server. Pentru ștergerea datelor contului folosește [solicitarea privind datele](/solicitari-date).
