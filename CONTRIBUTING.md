# Cum contribui la iPontaj

Mulțumim pentru timpul oferit proiectului. Sunt utile contribuțiile de cod, texte românești, testare, accesibilitate, documentație și cercetarea nevoilor echipelor mici. Citește mai întâi [README](README.md) și [regulile de securitate](SECURITY.md).

## Ramuri și propuneri

- Pornește din `develop` și creează o ramură scurtă, de exemplu `fix/day-editor-alignment` sau `feat/export-format`.
- Propune pull requestul către `develop`.
- `master` primește versiuni verificate din `develop`; nu propune acolo o funcție netestată.
- Pentru schimbări ample, descrie întâi problema, comportamentul dorit și impactul asupra datelor într-un issue.
- Nu rescrie istoricul ramurilor comune și nu folosi force push pe `develop` sau `master`.

Repositoryul poate fi privat; ai nevoie de acces pentru clonare și propuneri. Modificarea vizibilității sau publicarea serviciului este o decizie separată a proprietarului.

```bash
git switch develop
git pull --ff-only
git switch -c fix/descriere-scurta
```

Urmează instalarea din README, cu o bază locală și date fictive. Nu folosi baza, credentialele sau exporturile unui client pentru dezvoltare.

## Orientare în cod

| Zonă                                                             | Responsabilitate                                               |
| ---------------------------------------------------------------- | -------------------------------------------------------------- |
| `app/Services/AttendanceService.php`                             | Intervale, fusuri orare, confirmări și rezumatul lunii         |
| `app/Services/TeamService.php`                                   | Invitații și limite de persoane                                |
| `app/Services/CompanyContext.php`, `app/Policies/`               | Firma curentă și autorizarea                                   |
| `app/Services/CompanyRegistry.php`                               | Registrul ANAF, cache și limitare globală                      |
| `app/Services/ExcelService.php`                                  | Exportul pontajului, fără formule provenite din text nevalidat |
| `app/Support/PasswordPolicy.php`, `app/Rules/StrongPassword.php` | Reguli comune pentru setarea parolei                           |
| `resources/js/pages/Workspace.vue`                               | Ecranele principale ale echipei                                |
| `resources/js/components/DayEditor.vue`                          | Pontajul zilnic                                                |
| `resources/legal/`, `app/Services/Legal*`                        | Documente și dovezi contractuale                               |
| `tests/Feature/`, `tests/browser/`                               | Comportamente server și fluxuri reale în browser               |
| `docs/`                                                          | Operare, limite de verificare și lansare                       |

Păstrează Laravel/Inertia și componentele existente, fără a introduce un al doilea frontend sau un serviciu separat pentru o schimbare locală.

## Reguli importante

- Validează și autorizează pe server. Un `company_id` primit din browser nu dovedește dreptul de acces.
- Limitează interogările, joburile și exporturile la firma autorizată.
- Protejează cu tranzacții și blocare operațiunile concurente privind locurile sau lunile închise. SQLite și MySQL/PostgreSQL au comportamente diferite.
- Păstrează diferența dintre confirmarea angajatului și aprobarea proprietarului; corecturile cer motiv și audit.
- Nu completa automat ziua din program ca și cum ar fi o confirmare reală.
- Folosește nume și prenume separate; păstrează fusul orar și istoricul programelor.
- Nu trimite parole către servicii de evaluare externe și nu accepta scorul clientului ca validare.
- Nu adăuga trackere, marketing, încărcări externe sau noi furnizori fără evaluarea datelor și actualizarea documentelor necesare.
- Documentele juridice publicate sunt versionate; nu modifica retroactiv dovada unei acceptări.

## Verificări

Rulează verificările din README. Adaugă teste când modifici reguli de acces, izolare, tranzacții, calculul timpului, plăți sau un flux important. Pentru o corecție pur vizuală, verificarea în browser pe desktop și mobil este de obicei mai utilă decât un test care reproduce CSS-ul.

Testele ANAF și Stripe din suită folosesc răspunsuri simulate. Nu transforma o suită obișnuită într-un test care depinde de disponibilitatea unui serviciu extern sau care face încasări. Testele browser trebuie să folosească exclusiv o bază de test.

Înainte de commit:

```bash
git add <fisierele-modificate>
python3 scripts/check-secrets.py --staged
git diff --cached --check
git diff --cached --stat
```

Nu include `.env`, chei, tokenuri, cookie-uri, baze de date, loguri, backupuri, exporturi sau atașamente cu persoane reale. Exemplele de parole și acreditările din teste sunt fictive și limitate la test. Verifică separat capturile: nume/email fictive, fără meniuri sau notificări cu date reale. Datele publice de identificare ale operatorului, solicitate în paginile juridice, sunt o excepție intenționată de la exemplele fictive.

## Descrierea unui pull request

Explică problema și rezultatul final, motivul schimbării și cum ai verificat-o. Menționează efectul migrărilor și riscurile materiale. Pentru UI atașează capturi desktop/mobil cu date fictive. Pentru o integrare, precizează ce este simulat și ce a fost verificat extern.

Nu include istoricul conversației, loguri brute sau acreditări în descriere. Folosește [șablonul de PR](.github/pull_request_template.md).

## Zone bune pentru prime contribuții

- Revizuirea textelor și emailurilor în română.
- Navigare cu tastatura, cititoare de ecran și contrast.
- Verificare Safari/iOS și PWA pe dispozitive fizice.
- Documentarea instalării MySQL/MariaDB și verificarea migrațiilor pe un mediu de test.
- Formate de export noi, numai cu specificație și validare reală pe fișiere fictive.
- Interviuri cu utilizatori și materiale de prezentare care nu promit funcții sau conformități neverificate.

## Licență și finanțare

Contribuțiile la cod sunt propuse sub AGPL-3.0-only, fără transfer automat al drepturilor către o altă licență. Păstrează atribuirea și licențele materialelor terțe. După schimbarea dependențelor, regenerează inventarul cu `python3 scripts/update-third-party-notices.py`.

Susținerea financiară este explicată în [FUNDING.md](FUNDING.md). Contribuțiile financiare nu oferă automat dreptul de a schimba licența, de a accesa datele clienților sau de a evita regulile de securitate.
