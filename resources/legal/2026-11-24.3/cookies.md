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

Nu păstrăm în cache-ul PWA răspunsuri autentificate, pontaje sau fișiere Excel. Accesul offline la pontaj nu este oferit.

## Preferința ta

Salvăm local în browser, sub cheia `ipontaj:cookie-preferences`, versiunea informării, momentul alegerii și categoriile permise. Preferința este valabilă 180 de zile și este cerută din nou dacă se schimbă versiunea sau disponibilitatea analizei. Dacă browserul nu permite salvarea, analiza rămâne oprită. Nu deducem acordul din navigare, derulare sau lipsa unei acțiuni.

## Cloudflare și analiza opțională

Dacă este activat, Cloudflare Turnstile verifică solicitările de autentificare, înregistrare și contact pentru a limita abuzurile. Este o măsură de securitate distinctă de analiza opțională și nu este dezactivată prin refuzul acesteia. Cloudflare poate prelucra IP-ul, caracteristici ale browserului și datele tehnice necesare verificării. Consultă [informarea Cloudflare](https://www.cloudflare.com/privacypolicy/) și [anexa Turnstile](https://www.cloudflare.com/turnstile-privacy-policy/).

Cloudflare Web Analytics se încarcă numai pe paginile publice de prezentare și contact, dacă analiza este disponibilă în această instalare și ai acceptat categoria. Nu încărcăm analiza în cont, în pontaj, pe paginile de autentificare, invitații sau recuperare a parolei. Refuzul o ține oprită, iar retragerea acordului reîncarcă pagina pentru a opri și ascultătorii de evenimente ai serviciului. Funcțiile de pontaj nu depind de această analiză. Nu încărcăm Google Analytics, pixeli de publicitate, hărți sau clipuri încorporate.

## Servicii externe și control

Checkoutul și portalul Stripe se deschid pe infrastructura Stripe și pot utiliza propriile tehnologii de securitate și plată. Consultă informarea Stripe înainte de folosire; acest lucru nu activează analiză ori marketing în iPontaj.

Poți șterge cookie-urile și datele site-ului din browser; este posibil să fii deconectat și să pierzi preferințele locale. Nu ștergi prin aceasta datele de pe server. Pentru ștergerea datelor contului folosește [solicitarea privind datele](/solicitari-date).
