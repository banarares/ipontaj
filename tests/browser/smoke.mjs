import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROME_PATH
        ? { executablePath: process.env.CHROME_PATH }
        : {}),
    args: ['--no-sandbox'],
});
const base = process.env.BASE_URL || 'http://127.0.0.1:8765';
const page = await browser.newPage({
    viewport: { width: 1440, height: 1100 },
    locale: 'ro-RO',
});
page.setDefaultTimeout(12000);
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
console.log('landing');
await page.goto(base);
await page.getByRole('heading', { name: 'Pontajul la zi.' }).waitFor();
await page.screenshot({
    path: 'docs/screenshots/landing-desktop.png',
    fullPage: true,
});
await page
    .getByRole('button', { name: 'Încearcă demo-ul', exact: true })
    .click();
await page.waitForURL('**/dashboard');
await page.getByRole('heading', { name: 'Bună, Andrei!' }).waitFor();
await page.screenshot({
    path: 'docs/screenshots/dashboard-desktop.png',
    fullPage: true,
});
console.log('today');
await page.getByRole('link', { name: 'Ziua mea', exact: true }).click();
await page.getByRole('button', { name: 'Confirmă ziua', exact: true }).click();
await page.getByText('Ziua a fost înregistrată.', { exact: true }).waitFor();
await page.getByRole('heading', { name: '8 h', exact: true }).waitFor();
console.log('edit');
await page.getByRole('button', { name: 'Modifică orele', exact: true }).click();
await page.getByRole('dialog').waitFor();
// A visitor can use the demo before making a cookie choice.
await page
    .getByRole('complementary', { name: 'Preferințe pentru cookie-uri' })
    .waitFor();
await page
    .getByRole('button', { name: 'Salvează ziua', exact: true })
    .click({ trial: true });
await page.getByRole('button', { name: 'Adaugă interval sau pauză' }).click();
const alignment = await page
    .locator('.interval-editor .two-columns')
    .evaluateAll((groups) =>
        groups.map((group) => {
            const inputs = group.querySelectorAll(
                'input[type="datetime-local"]',
            );
            return {
                start: inputs[0].getBoundingClientRect().top,
                end: inputs[1].getBoundingClientRect().top,
            };
        }),
    );
if (
    alignment.length !== 2 ||
    alignment.some((row) => Math.abs(row.start - row.end) > 1)
)
    throw new Error(
        'Câmpurile intervalelor nu sunt aliniate: ' + JSON.stringify(alignment),
    );
await page.screenshot({ path: 'docs/screenshots/day-modal-desktop.png' });
await page.getByRole('button', { name: 'Elimină', exact: true }).last().click();
console.log('Aliniere început/sfârșit:', JSON.stringify(alignment));

await page.getByLabel('Motivul corecturii').fill('Verificare browser demo');
await page.getByRole('button', { name: 'Salvează ziua', exact: true }).click();
await page.getByRole('dialog').waitFor({ state: 'hidden' });
const selectedDay = await page.getByLabel('Alege ziua').inputValue();
const previousMonth = new Date(selectedDay.slice(0, 7) + '-01T12:00:00Z');
previousMonth.setUTCMonth(previousMonth.getUTCMonth() - 1);
const oldDay = previousMonth.toISOString().slice(0, 10);
await page.goto(base + '/today?date=' + oldDay);
await page.getByText('Zi în afara perioadei active', { exact: true }).waitFor();
assert.equal(await page.getByLabel('Alege ziua').inputValue(), oldDay);
assert.equal(
    await page
        .getByRole('button', { name: 'Confirmă ziua', exact: true })
        .count(),
    0,
);
assert.equal(
    await page
        .getByRole('button', { name: 'Modifică orele', exact: true })
        .count(),
    1,
);
await page.getByRole('button', { name: 'Modifică orele', exact: true }).click();
assert.equal(
    await page
        .getByRole('checkbox', { name: /Confirm că persoana era activă/ })
        .isChecked(),
    false,
);
await page.getByRole('button', { name: 'Renunță', exact: true }).click();
await page.goto(base + '/today');
console.log('month');
await page.locator('nav a[href="/month"]').click();
await page
    .getByRole('heading', { name: 'Pontaj lunar', exact: true })
    .waitFor();
const dl = page.waitForEvent('download');
await page.getByRole('link', { name: 'Exportă Excel' }).click();
const download = await dl;
if (!download.suggestedFilename().endsWith('.xlsx'))
    throw new Error('Missing xlsx');
await page.setViewportSize({ width: 390, height: 844 });
await page.goto(base + '/dashboard');
await page.getByRole('heading', { name: 'Bună, Andrei!' }).waitFor();
await page.screenshot({
    path: 'docs/screenshots/dashboard-mobile.png',
    fullPage: true,
});
await page.getByRole('button', { name: 'Deschide meniul' }).click();
await page.getByRole('link', { name: 'Ziua mea', exact: true }).click();
await page.screenshot({
    path: 'docs/screenshots/today-mobile.png',
    fullPage: true,
});
await page.getByRole('button', { name: 'Modifică orele', exact: true }).click();
await page.getByRole('dialog').waitFor();
await page
    .getByRole('button', { name: 'Salvează ziua', exact: true })
    .click({ trial: true });
await page.screenshot({ path: 'docs/screenshots/day-modal-mobile.png' });
await page.getByRole('button', { name: 'Renunță', exact: true }).click();
await page.getByRole('button', { name: 'Deschide meniul' }).click();
await page
    .getByRole('button', { name: 'Creează contul tău', exact: true })
    .click();
await page.waitForURL('**/register');
console.log(
    JSON.stringify({
        pages: 'Landing, demo, dashboard, confirmare, editare, pontaj lunar, export, mobil, ieșire demo',
        errors,
    }),
);
await browser.close();
if (errors.length) process.exitCode = 1;
