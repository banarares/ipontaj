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
    viewport: { width: 1440, height: 1100 },
    locale: 'ro-RO',
});
page.setDefaultTimeout(20000);
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
try {
    await page.goto(base + '/register');
    await page.getByLabel('Prenume', { exact: true }).fill('Ana');
    await page.getByLabel('Nume', { exact: true }).fill('Fictiv');
    await page
        .getByLabel('Adresa de email')
        .fill(`company-browser-${Date.now()}@example.test`);
    await page
        .getByLabel('Parola', { exact: true })
        .fill('Molid!Vapor7Nori$42');
    await page.getByRole('checkbox', { name: /Accept Termenii/ }).check();
    await page
        .getByLabel('Confirmă parola', { exact: true })
        .fill('Molid!Vapor7Nori$42');
    await page
        .getByRole('button', { name: 'Creează contul gratuit', exact: true })
        .click();
    await page.waitForURL('**/onboarding');
    let delayed;
    let release;
    const started = new Promise((resolve) => {
        delayed = resolve;
    });
    const gate = new Promise((resolve) => {
        release = resolve;
    });
    // Browser-only fictitious responses. PHP tests cover the real server cache and verification.
    await page.route('**/company-lookup?*', async (route) => {
        const cif = new URL(route.request().url()).searchParams
            .get('cif')
            .replace(/\s|ro/giu, '');
        if (cif === '7777') {
            delayed();
            await gate;
            await route
                .fulfill({
                    json: {
                        status: 'found',
                        cif: '7777',
                        name: 'Răspuns vechi fictiv',
                        verified_at: new Date().toISOString(),
                    },
                })
                .catch(() => {});
        } else if (cif === '5678') {
            await route.fulfill({
                status: 404,
                json: {
                    status: 'not_found',
                    cif,
                    message:
                        'Nu am găsit firma la ANAF. Verifică CIF-ul sau completează denumirea manual.',
                },
            });
        } else if (cif === '9999') {
            await route.fulfill({
                status: 503,
                json: {
                    status: 'unavailable',
                    cif,
                    message:
                        'ANAF nu răspunde acum. Poți încerca din nou sau completa denumirea manual.',
                },
            });
        } else {
            await route.fulfill({
                json: {
                    status: 'found',
                    cif,
                    name: 'Atelier Fictiv SRL',
                    verified_at: new Date().toISOString(),
                },
            });
        }
    });
    const cif = page.getByLabel('CIF', { exact: true });
    const name = page.getByLabel('Numele firmei', { exact: true });
    const search = page.getByRole('button', {
        name: 'Caută firma',
        exact: true,
    });
    async function assertAligned() {
        const inputBox = await cif.boundingBox();
        const buttonBox = await search.boundingBox();
        assert.ok(
            Math.abs(inputBox.y - buttonBox.y) < 1,
            'CIF-ul și butonul de căutare trebuie aliniate.',
        );
    }
    await assertAligned();
    await cif.fill('ro 1234');
    await search.click();
    await page.getByText('Preluat din ANAF', { exact: false }).waitFor();
    assert.equal(await cif.inputValue(), '1234');
    assert.equal(await name.inputValue(), 'Atelier Fictiv SRL');
    await name.fill('Atelier Fictiv editat');
    await page
        .getByText('Datele nu sunt confirmate de ANAF.', { exact: false })
        .waitFor();
    await cif.fill('7777');
    await search.click();
    await started;
    await page.getByRole('button', { name: 'Se caută…' }).waitFor();
    await cif.fill('5678');
    await search.click();
    await page
        .getByRole('status')
        .filter({ hasText: 'Nu am găsit firma' })
        .waitFor();
    release();
    assert.equal(await cif.inputValue(), '5678');
    assert.equal(await name.inputValue(), 'Atelier Fictiv editat');
    await cif.fill('9999');
    await search.click();
    await page
        .getByRole('status')
        .filter({ hasText: 'ANAF nu răspunde' })
        .waitFor();
    await cif.fill('1234');
    await search.click();
    await page.getByText('Preluat din ANAF', { exact: false }).waitFor();
    await page.screenshot({
        path: 'docs/screenshots/company-lookup-desktop.png',
        fullPage: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await assertAligned();
    assert.ok(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
        'Căutarea trebuie să încapă pe mobil.',
    );
    await page.screenshot({
        path: 'docs/screenshots/company-lookup-mobile.png',
        fullPage: true,
    });
    await name.fill('Atelier Manual Fictiv SRL');
    await page
        .getByRole('checkbox', { name: /Sunt autorizat să reprezint firma/ })
        .check();
    await page.getByText('Confirm programul propus.', { exact: false }).click();
    await page
        .getByRole('button', { name: 'Creează firma și continuă' })
        .click();
    await page.waitForURL('**/dashboard');
    await page.goto(base + '/settings');
    assert.equal(await page.getByLabel(/^CIF/).inputValue(), '1234');
    assert.equal(await name.inputValue(), 'Atelier Manual Fictiv SRL');
    await page
        .getByText('Datele nu sunt confirmate de ANAF.', { exact: false })
        .waitFor();
    await page.getByLabel(/^CIF/).fill('5678');
    await name.fill('Atelier Modificat Fictiv SRL');
    await page
        .getByRole('button', { name: 'Salvează setările', exact: true })
        .click();
    await page
        .getByText('Setările au fost salvate.', { exact: true })
        .waitFor();
    await page.reload();
    assert.equal(await page.getByLabel(/^CIF/).inputValue(), '5678');
    assert.equal(await name.inputValue(), 'Atelier Modificat Fictiv SRL');
    assert.deepEqual(errors, []);
    console.log(
        JSON.stringify({
            verified:
                'Căutare CIF, completare nume, sursă ANAF, editare, răspuns întârziat, firmă negăsită, indisponibilitate, creare manuală, salvare setări, mobil fără depășire',
            errors,
        }),
    );
} finally {
    await browser.close();
}
