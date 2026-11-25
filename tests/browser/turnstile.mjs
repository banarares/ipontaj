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
const base = process.env.BASE_URL || 'http://127.0.0.1:8767';
const first = await browser.newPage({
    viewport: { width: 390, height: 844 },
    locale: 'ro-RO',
});
first.setDefaultTimeout(35000);
const errors = [];
first.on('pageerror', (e) => errors.push(e.message));
const email = `turnstile-${Date.now()}@example.test`,
    password = 'Molid!Vapor7Nori$42';
try {
    await first.goto(base + '/register');
    await first
        .getByRole('button', { name: 'Refuză opționalele', exact: true })
        .click();
    await first.getByLabel('Nume', { exact: true }).fill('Fictiv');
    await first.getByLabel('Prenume', { exact: true }).fill('Captcha');
    await first.getByLabel('Adresa de email', { exact: true }).fill(email);
    await first.getByLabel('Parola', { exact: true }).fill(password);
    await first.getByLabel('Confirmă parola', { exact: true }).fill(password);
    await first.getByRole('checkbox', { name: /Accept Termenii/ }).check();
    const register = first.getByRole('button', {
        name: 'Creează contul gratuit',
        exact: true,
    });
    await first.waitForFunction(
        () => {
            const b = [...document.querySelectorAll('button')].find(
                (x) => x.textContent.trim() === 'Creează contul gratuit',
            );
            return b && !b.disabled;
        },
        { timeout: 35000 },
    );
    await first.setViewportSize({ width: 320, height: 720 });
    await first.waitForFunction(
        () => document.documentElement.scrollWidth <= innerWidth,
    );
    await first.waitForFunction(() => {
        const button = [...document.querySelectorAll('button')].find(
            (item) => item.textContent.trim() === 'Creează contul gratuit',
        );
        return button && !button.disabled;
    });
    assert.ok(
        await first.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    );
    await first.screenshot({
        path: 'docs/screenshots/turnstile-register-mobile.png',
        fullPage: true,
    });
    await register.click();
    await confirmEmail(first, email, password);
    await first.waitForURL('**/onboarding');
    const second = await browser.newPage({
        viewport: { width: 1440, height: 1000 },
        locale: 'ro-RO',
    });
    second.on('pageerror', (e) => errors.push(e.message));
    second.setDefaultTimeout(35000);
    await second.goto(base + '/login');
    await second
        .getByRole('button', { name: 'Refuză opționalele', exact: true })
        .click();
    await second.getByLabel('Adresa de email', { exact: true }).fill(email);
    await second.getByLabel('Parola', { exact: true }).fill(password);
    await second.waitForFunction(
        () => {
            const b = [...document.querySelectorAll('button')].find(
                (x) => x.textContent.trim() === 'Intră în cont',
            );
            return b && !b.disabled;
        },
        { timeout: 35000 },
    );
    await second
        .getByRole('button', { name: 'Intră în cont', exact: true })
        .click();
    await second.waitForURL('**/onboarding');
    await second.goto(base + '/contact');
    await second.getByLabel('Nume', { exact: true }).fill('Fictiv');
    await second.getByLabel('Prenume', { exact: true }).fill('Captcha');
    await second
        .getByLabel('Email pentru răspuns', { exact: true })
        .fill(email);
    await second
        .getByLabel('Subiect', { exact: true })
        .fill('Test oficial Cloudflare');
    await second
        .getByLabel('Mesaj', { exact: true })
        .fill(
            'Mesaj fictiv pentru verificarea Turnstile cu chei publice de test.',
        );
    await second.waitForFunction(
        () => {
            const b = [...document.querySelectorAll('button')].find(
                (x) => x.textContent.trim() === 'Trimite mesajul',
            );
            return b && !b.disabled;
        },
        { timeout: 35000 },
    );
    await second
        .getByRole('button', { name: 'Trimite mesajul', exact: true })
        .click();
    await second
        .getByText('Mesajul tău a fost înregistrat.', { exact: true })
        .waitFor();
    assert.deepEqual(errors, []);
    console.log(
        JSON.stringify({
            verified:
                'Turnstile oficial test: înregistrare, login și contact; validare reală Siteverify, emailuri dezactivate',
            errors,
        }),
    );
} finally {
    await browser.close();
}
