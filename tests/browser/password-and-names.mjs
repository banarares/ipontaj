import { confirmEmail } from './support/verification.mjs';
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
await page.goto(base + '/register');
await page.getByLabel('Nume', { exact: true }).fill('Popescu');
await page.getByLabel('Prenume', { exact: true }).fill('Ana Maria');
await page
    .getByLabel('Adresa de email')
    .fill(`password-browser-${Date.now()}@example.test`);
await page.getByRole('checkbox', { name: /Accept Termenii/ }).check();
const verificationEmail = await page.getByLabel('Adresa de email').inputValue();
const password = page.getByLabel('Parola', { exact: true });
await password.fill('abc');
await page.getByRole('status').filter({ hasText: 'Foarte slabă' }).waitFor();
await password.fill('aaaaaaaaaaaaaaa!1A');
await page.getByRole('status').filter({ hasText: 'Slabă' }).waitFor();
await password.fill('Password123456!');
await page.getByRole('status').filter({ hasText: 'Acceptabilă' }).waitFor();
await page
    .getByLabel('Confirmă parola', { exact: true })
    .fill('Password123456!');
await page
    .getByRole('button', { name: 'Creează contul gratuit', exact: true })
    .click();
await page.getByRole('alert').filter({ hasText: 'previzibilă' }).waitFor();
assert.equal(await password.inputValue(), 'Password123456!');
await password.fill('Password!123456xyz');
await page
    .getByRole('status')
    .filter({ hasText: /^Puternică$/ })
    .waitFor();
await password.fill('Molid!Vapor7Nori$42');
await page
    .getByRole('status')
    .filter({ hasText: 'Foarte puternică' })
    .waitFor();
await page.getByRole('button', { name: 'Arată parola', exact: true }).click();
assert.equal(await password.getAttribute('type'), 'text');
await page.getByRole('button', { name: 'Ascunde parola', exact: true }).click();
await page
    .getByLabel('Confirmă parola', { exact: true })
    .fill('Molid!Vapor7Nori$42');
await page
    .getByRole('status')
    .filter({ hasText: 'Foarte puternică' })
    .waitFor();
await page.screenshot({
    path: 'docs/screenshots/register-password-desktop.png',
    fullPage: true,
});
await page.setViewportSize({ width: 390, height: 844 });
assert.ok(
    await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
    ),
    'Formularul nu trebuie să depășească lățimea telefonului.',
);
await page.screenshot({
    path: 'docs/screenshots/register-password-mobile.png',
    fullPage: true,
});
await page
    .getByRole('button', { name: 'Creează contul gratuit', exact: true })
    .click();
await confirmEmail(page, verificationEmail);
await page.waitForURL('**/onboarding');
await page.goto(base + '/settings/profile');
assert.equal(
    await page.getByLabel('Prenume', { exact: true }).inputValue(),
    'Ana Maria',
);
assert.equal(
    await page.getByLabel('Nume', { exact: true }).inputValue(),
    'Popescu',
);
await page.getByLabel('Prenume', { exact: true }).fill('Ana Ioana');
await page
    .getByRole('button', { name: 'Salvează datele', exact: true })
    .click();
await page.getByText('Datele au fost salvate.', { exact: true }).waitFor();
console.log(
    JSON.stringify({
        verified:
            'Câmpuri separate, grade reactive, refuz pe server al parolei previzibile, afișare/ascundere, înregistrare și editare profil',
        errors,
    }),
);
await browser.close();
if (errors.length) process.exitCode = 1;
