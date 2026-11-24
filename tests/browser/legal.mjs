import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROME_PATH
        ? { executablePath: process.env.CHROME_PATH }
        : {}),
    args: ['--no-sandbox'],
});
const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
const page = await browser.newPage({
    viewport: { width: 1440, height: 1050 },
    locale: 'ro-RO',
});
page.setDefaultTimeout(20000);
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
try {
    await page.goto(base + '/');
    await page
        .getByRole('link', { name: 'ANPC · Protecția consumatorilor' })
        .waitFor();
    const sal = page.getByRole('img', {
        name: 'ANPC — Soluționarea alternativă a litigiilor',
    });
    await sal.waitFor();
    assert.equal(
        await sal.evaluate((img) => img.complete && img.naturalWidth > 0),
        true,
    );
    assert.equal(await sal.getAttribute('width'), '250');
    assert.equal(
        await sal.locator('..').getAttribute('href'),
        'https://reclamatiisal.anpc.ro',
    );
    for (const name of [
        'ANPC · Protecția consumatorilor',
        'ANSPDCP · Protecția datelor',
    ]) {
        const logo = page
            .getByRole('link', { name, exact: true })
            .locator('img');
        assert.equal(
            await logo.evaluate((img) => img.complete && img.naturalWidth > 0),
            true,
        );
        assert.ok((await logo.getAttribute('src')).startsWith('/legal/'));
    }
    await page
        .locator('.legal-footer')
        .screenshot({ path: 'docs/screenshots/footer-desktop.png' });
    await page.setViewportSize({ width: 390, height: 844 });
    await page
        .locator('.legal-footer')
        .screenshot({ path: 'docs/screenshots/footer-mobile.png' });
    await page.setViewportSize({ width: 1440, height: 1050 });
    await page
        .getByRole('link', { name: 'Confidențialitate', exact: true })
        .click();
    await page
        .getByRole('heading', { name: 'Confidențialitate', exact: true })
        .waitFor();
    await page.screenshot({ path: 'docs/screenshots/legal-desktop.png' });
    await page.setViewportSize({ width: 390, height: 844 });
    assert.ok(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
        'Documentele trebuie să încapă pe mobil.',
    );
    await page.screenshot({ path: 'docs/screenshots/legal-mobile.png' });
    await page.goto(base + '/retragere');
    await page.getByLabel('Nume', { exact: true }).fill('Fictiv');
    await page.getByLabel('Prenume', { exact: true }).fill('Consumator');
    await page
        .getByLabel('Email pentru confirmare')
        .fill(`withdrawal-${Date.now()}@example.test`);
    await page
        .getByLabel('Contractul din care te retragi')
        .fill('Comandă fictivă de verificare, fără plată');
    await page.getByRole('button', { name: 'Verifică solicitarea' }).click();
    assert.equal(
        await page.getByLabel('Nume', { exact: true }).getAttribute('readonly'),
        '',
    );
    await page.getByRole('button', { name: 'Corectează datele' }).click();
    await page
        .getByLabel('Prenume', { exact: true })
        .fill('Consumator Exemplu');
    await page.getByRole('button', { name: 'Verifică solicitarea' }).click();
    await page
        .getByRole('button', { name: 'Confirmă retragerea', exact: true })
        .click();
    await page
        .getByText('Solicitarea a fost înregistrată.', { exact: true })
        .waitFor();
    const receiptUrl = await page
        .getByRole('link', { name: 'Descarcă dovada primirii' })
        .getAttribute('href');
    const receipt = await page.request.get(receiptUrl);
    assert.equal(receipt.status(), 200);
    assert.equal(receipt.headers()['cache-control'], 'no-store, private');
    assert.match(await receipt.text(), /Consumator Exemplu Fictiv/);
    await page.screenshot({
        path: 'docs/screenshots/withdrawal-mobile.png',
        fullPage: true,
    });
    await page.goto(base + '/register');
    await page.getByLabel('Nume', { exact: true }).fill('Fictiv');
    await page.getByLabel('Prenume', { exact: true }).fill('Consumator');
    await page
        .getByLabel('Adresa de email')
        .fill(`consumer-${Date.now()}@example.test`);
    await page
        .getByLabel('Parola', { exact: true })
        .fill('Molid!Vapor7Nori$42');
    await page
        .getByLabel('Confirmă parola', { exact: true })
        .fill('Molid!Vapor7Nori$42');
    const consent = page.getByRole('checkbox', { name: /Accept Termenii/ });
    assert.equal(await consent.isChecked(), false);
    await consent.check();
    await page
        .getByRole('button', { name: 'Creează contul gratuit', exact: true })
        .click();
    await page.waitForURL('**/onboarding');
    await page
        .getByLabel('Folosesc iPontaj', { exact: true })
        .selectOption('consumer');
    assert.equal(await page.locator('#onboarding-company-cif').count(), 0);
    await page
        .getByLabel('Numele spațiului', { exact: true })
        .fill('Spațiu personal fictiv');
    await page.getByText('Confirm programul propus.', { exact: false }).click();
    await page
        .getByRole('button', { name: 'Creează firma și continuă' })
        .click();
    await page.waitForURL('**/dashboard');
    await page.goto(base + '/settings/profile');
    const agreement = page.getByRole('link', { name: /Termenii contului/ });
    await agreement.waitFor();
    const accepted = await page.request.get(
        base + (await agreement.getAttribute('href')),
    );
    assert.equal(accepted.status(), 200);
    assert.match(await accepted.text(), /SHA-256/);
    await page.goto(base + '/settings');
    assert.equal(
        await page.getByLabel('Numele spațiului', { exact: true }).inputValue(),
        'Spațiu personal fictiv',
    );
    assert.equal(await page.locator('#settings-company-cif').count(), 0);
    assert.deepEqual(errors, []);
    console.log(
        JSON.stringify({
            verified:
                'Pagini juridice, operator, pictogramă oficială SAL, mobil, retragere în doi pași și dovadă privată, acceptare nebifată inițial, consumator fără CIF, dovezi contractuale',
            errors,
        }),
    );
} finally {
    await browser.close();
}
