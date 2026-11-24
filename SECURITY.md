# Raportarea problemelor de securitate

Nu publica tokenuri, date personale sau pași de exploatare într-un issue deschis.

Folosește funcția privată **Report a vulnerability** din GitHub, dacă este activată, sau scrie la **office@ipontaj.ro**, cu subiectul „Securitate iPontaj”. Nu deschide un issue public pentru o vulnerabilitate necorectată. Confirmarea primirii și pașii următori se stabilesc direct; nu declarăm un program de recompense sau un timp de răspuns garantat.

Include versiunea/commitul, impactul, condițiile și pașii de reproducere pe date fictive. Pentru probleme de izolare, descrie rolurile și firmele implicate, fără date reale. Nu trimite parole sau chei API.

Zone importante pentru revizuire: acces între firme, invitații, limite concurente, blocarea lunii, exporturi, linkuri semnate, procesarea Stripe și cache-ul după logout.

Cheile se păstrează în configurarea mediului. În caz de expunere, operatorul revocă/rotește cheia afectată și analizează logurile. Rotirea `APP_KEY` invalidează linkurile semnate și poate afecta datele criptate; se face cu un plan de recuperare.

## Reguli de parolă

`App\Support\PasswordPolicy` este sursa pragurilor comune pentru backend și interfață. Configurația curentă cere minimum 10 caractere, litere mari și mici, o cifră, un simbol și un scor de cel puțin 3/4. Validarea suplimentară respinge repetițiile lungi și combinațiile cu numele persoanei. Toate cele trei fluxuri folosesc aceeași regulă de validare; un scor trimis de client nu este acceptat ca dovadă.

Bcrypt este păstrat pentru compatibilitatea conturilor existente. La setarea unei parole noi este verificată limita de 72 de octeți UTF-8 înainte de hash, pentru a evita trunchierea. Testele automate forțează cheile Stripe goale, independent de `.env`, astfel încât testele unitare să nu folosească accesul configurat de operator. Testarea externă Stripe se rulează separat și acceptă numai chei de test.
