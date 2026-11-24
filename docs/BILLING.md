# Stripe pentru iPontaj

Planul Echipă: până la 15 persoane, **49 lei/lună, preț final; operator neînregistrat în scopuri de TVA**. În workspace este configurat modul **test**; încasările live sunt blocate în cod.

## Configurarea realizată

Proprietarul a furnizat local `STRIPE_KEY` și `STRIPE_SECRET`. Au fost create/reutilizate în Stripe test produsul iPontaj Echipă, prețul recurent lunar de 4.900 bani RON cu `tax_behavior=inclusive` și o configurație de portal pentru gestionarea datelor, plății, facturilor și anulării la sfârșitul perioadei.

În `.env` sunt setate, fără a fi publicate:

```dotenv
BILLING_ENABLED=true
STRIPE_KEY=pk_test_...
STRIPE_SECRET=sk_test_...
STRIPE_TEAM_PRICE=price_...
STRIPE_PORTAL_CONFIGURATION=bpc_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

Acestea sunt exemple de formă; nu înlocui valorile deja configurate cu exemplele. Nu comite `.env`.

Conform [documentației Stripe despre prețuri inclusive](https://docs.stripe.com/tax/products-prices-tax-codes-tax-behavior), comportamentul inclusiv păstrează suma finală a prețului. Stabilirea prețului nu configurează singură obligațiile, cota aplicabilă sau facturarea fiscală a operatorului.

## Pornirea în fiecare sesiune locală

Aplicația trebuie să ruleze pe portul ales, implicit 8000. În alt terminal, păstrează pornit:

```bash
php artisan pontaj:stripe:listen --forward-to=http://127.0.0.1:8000/stripe/webhook
```

Comanda folosește cheia de test prin mediul procesului, pornește Stripe CLI, salvează automat secretul listenerului în `.env` și îl maschează în ieșire. Acceptă doar o destinație locală. Stripe CLI este disponibil în acest workspace la `.runtime/stripe/stripe`; la o instalare nouă poate fi instalat în PATH după [ghidul oficial](https://docs.stripe.com/cli/install).

Fără listenerul local sau un endpoint public funcțional, plata poate apărea în Stripe fără actualizarea imediată a planului în iPontaj. Nu activa planul manual din URL-ul de succes. Sursa confirmării sunt webhookurile semnate.

Deschide **Abonament** în aplicație și încearcă checkoutul de test. Portalul este disponibil după crearea clientului Stripe; un abonament activ este gestionat din portal. Pentru plata interactivă, folosește numai datele din [documentația Stripe de testare](https://docs.stripe.com/testing).

## Comenzi repetabile

```bash
php artisan pontaj:stripe:setup
php artisan pontaj:stripe:listen
```

`setup` verifică și reutilizează prețul identificat prin lookup key; nu creează alt preț la fiecare rulare. Verifică suma, moneda, perioada, caracterul inclusiv și modul test. Păstrează limitele planului din `config/ipontaj.php` în acord cu produsul configurat.

Pentru o verificare externă automată, cu listenerul deja pornit:

```bash
php artisan pontaj:stripe:verify
```

Această comandă creează explicit un client, o sesiune checkout și un abonament **fictive în Stripe test**, cu o metodă de plată de test. Verifică totalul de 49 lei, portalul, plata fictivă, activarea locală prin webhook, deduplicarea evenimentului și anularea. Expiră checkoutul și șterge clientul și firma locală la final. În Stripe rămân evenimentele și documentele de test asociate plății. Raportul local fără secrete este `.runtime/stripe-verification.json`.

Comanda a fost executată cu succes pe 24 noiembrie 2026. Testele automate obișnuite folosesc separat un client simulat și chei fictive; nu accesează cheile operatorului din `.env`.

## Limite și planuri

Cu `BILLING_ENABLED=true`, planul Gratuit permite o firmă și 5 persoane, iar Echipă 15 persoane. Invitațiile active rezervă locuri. Drepturile se calculează din abonamentul Cashier valid al firmei. Anularea sau eșecul plății nu șterge istoricul și nu blochează exporturile; adăugarea și reactivarea persoanelor rămân supuse limitei.

Pentru instalare proprie fără Stripe:

```dotenv
BILLING_ENABLED=false
SELF_HOSTED_SEAT_LIMIT=15
```

## Înaintea încasărilor live

Checkoutul refuză cheile `sk_live_`. Activarea live necesită o schimbare revizuită, după stabilirea entității vânzătoare, datelor și statutului fiscal, taxelor pentru prețul final de 49 lei, procesului de facturare în România, regulilor comerciale și contului de încasare.

Configurează HTTPS, webhookul public și evenimentele cerute de versiunea Cashier instalată. [Cashier documentează înregistrarea webhookurilor și verificarea semnăturii](https://laravel.com/docs/13.x/billing#handling-stripe-webhooks). Verifică endpointul public, anularea, plata nereușită, limitele și emailul către contabil înainte de lansare. Nu presupune că setarea TVA inclus stabilește automat regimul fiscal; acesta se validează cu contabilul operatorului.

## Informare și consumatori

Titularul a confirmat la 24 noiembrie 2026 că PFA-ul nu este înregistrat în scopuri de TVA. Prețul public este **49 lei/lună, preț final**, fără a declara o cotă TVA de 0%. Prețul Stripe de test existent are tehnic `tax_behavior=inclusive`; checkoutul ține `automatic_tax` dezactivat și nu adaugă TVA. Comportamentul tehnic al prețului nu dovedește statutul fiscal și nu înlocuiește factura românească.

Checkoutul cere acceptarea versiunii curente a condițiilor. Pentru spațiile de tip consumator, începerea anticipată este cerută expres, fără o renunțare generală la retragere. O opțiune de începere după 14 zile se gestionează prin contact înaintea comenzii; nu se simulează un trial cu acces imediat drept amânare a prestării.

`checkout.session.completed`, semnat, cu plată confirmată și client/sesiune potrivite, generează o singură confirmare contractuală cu textele acceptate atașate. Testele automate verifică logica și atașamentul; livrarea SMTP reală și momentul efectiv al accesului în întregul flux trebuie verificate înainte de live. Formularele de retragere nu rambursează automat: operatorul identifică solicitarea, oprește plățile și efectuează rambursarea legal datorată, în termen. Vezi [LEGAL_LAUNCH.md](LEGAL_LAUNCH.md).
