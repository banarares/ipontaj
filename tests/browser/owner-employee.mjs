import { confirmEmail } from './support/verification.mjs';
import { chromium } from '@playwright/test';
const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROME_PATH
        ? { executablePath: process.env.CHROME_PATH }
        : {}),
    args: ['--no-sandbox'],
});
const base = process.env.BASE_URL || 'http://127.0.0.1:8765';
const owner = await browser.newPage({
    viewport: { width: 1440, height: 1000 },
    locale: 'ro-RO',
});
owner.setDefaultTimeout(15000);
const suffix = Date.now();
const ownerEmail = `owner-${suffix}@example.test`,
    employeeEmail = `employee-${suffix}@example.test`;
const password = 'Testare-iPontaj-2026!';
const errors = [];
owner.on('pageerror', (e) => errors.push(e.message));
async function register(page, name, email) {
    await page.goto(base + '/register');
    const [firstName, ...lastNames] = name.split(' ');
    await page.getByLabel('Prenume', { exact: true }).fill(firstName);
    await page.getByLabel('Nume', { exact: true }).fill(lastNames.join(' '));
    await page.getByLabel('Adresa de email').fill(email);
    await page.getByLabel(/^Parola/).fill(password);
    await page.getByLabel('Confirmă parola', { exact: true }).fill(password);
    await page.getByRole('checkbox', { name: /Accept Termenii/ }).check();
    await page
        .getByRole('button', { name: 'Creează contul gratuit', exact: true })
        .click();
}
await register(owner, 'Proprietar Fictiv', ownerEmail);
await confirmEmail(owner, ownerEmail, password);
await owner.waitForURL('**/onboarding');
await owner.getByLabel('CIF', { exact: true }).fill('1234');
await owner.getByLabel('Numele firmei').fill('Echipa de test browser');
await owner
    .getByRole('checkbox', { name: /Sunt autorizat să reprezint firma/ })
    .check();
await owner.getByText('Confirm programul propus.', { exact: false }).click();
await owner.getByRole('button', { name: 'Creează firma și continuă' }).click();
await owner.waitForURL('**/dashboard');
await owner.goto(base + '/team');
await owner
    .getByRole('button', { name: 'Invită un coleg', exact: true })
    .first()
    .click();
await owner
    .getByRole('dialog')
    .getByLabel('Prenume', { exact: true })
    .fill('Angajat');
await owner
    .getByRole('dialog')
    .getByLabel('Nume', { exact: true })
    .fill('Fictiv');
await owner
    .getByRole('dialog')
    .getByLabel('Adresa de email')
    .fill(employeeEmail);
await owner
    .getByRole('button', { name: 'Creează invitația', exact: true })
    .click();
const invitation = await owner
    .getByLabel('Link individual de invitație')
    .inputValue();
const employee = await browser.newPage({
    viewport: { width: 390, height: 844 },
    locale: 'ro-RO',
});
employee.setDefaultTimeout(15000);
employee.on('pageerror', (e) => errors.push(e.message));
await employee.goto(invitation);
await employee
    .getByRole('link', { name: 'Creează contul tău', exact: true })
    .click();
await employee.getByLabel('Nume', { exact: true }).fill('Fictiv');
await employee.getByLabel('Prenume', { exact: true }).fill('Angajat');
await employee.getByLabel('Adresa de email').fill(employeeEmail);
await employee.getByLabel(/^Parola/).fill(password);
await employee.getByLabel('Confirmă parola', { exact: true }).fill(password);
await employee.getByRole('checkbox', { name: /Accept Termenii/ }).check();
await employee.getByRole('button', { name: 'Creează contul gratuit' }).click();
await confirmEmail(employee, employeeEmail, password);
await employee.waitForURL('**/invite/**');
await employee.getByRole('button', { name: 'Acceptă invitația' }).click();
await employee.waitForURL('**/today');
await employee
    .getByRole('button', { name: 'Confirmă ziua', exact: true })
    .click();
await employee.getByRole('heading', { name: '8 h', exact: true }).waitFor();
await employee.screenshot({
    path: 'docs/screenshots/employee-mobile.png',
    fullPage: true,
});
await owner.goto(base + '/month');
await owner
    .locator('.monthly-table tbody th')
    .filter({ hasText: 'Angajat Fictiv' })
    .waitFor();
const download = owner.waitForEvent('download');
await owner.getByRole('link', { name: 'Exportă Excel' }).click();
await download;
console.log(
    JSON.stringify({
        flow: 'Înregistrare proprietar, configurare firmă, invitație, înregistrare angajat pe mobil, acceptare, confirmare și Excel',
        errors,
        ownerEmail,
        employeeEmail,
    }),
);
await browser.close();
if (errors.length) process.exitCode = 1;
