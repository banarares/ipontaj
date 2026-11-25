import { confirmEmail } from './support/verification.mjs';
import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';

const base = process.env.BASE_URL || 'http://127.0.0.1:8768';
assert.ok(
    ['127.0.0.1', 'localhost'].includes(new URL(base).hostname),
    'Use an isolated local test database and disabled email.',
);
const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROME_PATH
        ? { executablePath: process.env.CHROME_PATH }
        : {}),
    args: ['--no-sandbox'],
});
const page = await browser.newPage({
    viewport: { width: 1440, height: 1000 },
    locale: 'ro-RO',
});
page.setDefaultTimeout(20000);
const errors = [];
page.on('pageerror', (error) => errors.push(error.message));
try {
    await page.goto(base + '/register');
    await page
        .getByRole('button', { name: 'Refuză opționalele', exact: true })
        .click();
    await page.getByLabel('Nume', { exact: true }).fill('Fictiv');
    await page.getByLabel('Prenume', { exact: true }).fill('Responsabil');
    await page
        .getByLabel('Adresa de email')
        .fill(`managed-browser-${Date.now()}@example.test`);
    await page
        .getByLabel('Parola', { exact: true })
        .fill('Molid!Vapor7Nori$42');
    await page
        .getByLabel('Confirmă parola', { exact: true })
        .fill('Molid!Vapor7Nori$42');
    await page.getByRole('checkbox', { name: /Accept Termenii/ }).check();
    const verificationEmail = await page
        .getByLabel('Adresa de email')
        .inputValue();
    await page
        .getByRole('button', { name: 'Creează contul gratuit', exact: true })
        .click();
    await confirmEmail(page, verificationEmail);
    await page.waitForURL('**/onboarding');
    await page
        .getByLabel('Folosesc iPontaj', { exact: true })
        .selectOption('consumer');
    await page
        .getByLabel('Numele spațiului', { exact: true })
        .fill('Echipă fictivă fără email');
    await page
        .getByRole('checkbox', { name: /Confirm programul propus/ })
        .check();
    await page
        .getByRole('button', { name: 'Creează firma și continuă', exact: true })
        .click();
    await page.waitForURL('**/dashboard');

    await page.goto(base + '/history');
    const gap = await page
        .locator('.history-day-link')
        .evaluate(
            (link) =>
                link.getBoundingClientRect().top -
                link.previousElementSibling.getBoundingClientRect().bottom,
        );
    assert.ok(gap >= 20, `History spacing: ${gap}`);
    const footer = page.locator('.legal-footer.compact');
    assert.ok((await footer.boundingBox()).height < 110);
    assert.equal(
        await footer.locator('.legal-footer-identity').isVisible(),
        false,
    );
    await footer.screenshot({
        path: 'docs/screenshots/authenticated-footer-desktop.png',
    });
    await footer
        .getByText('Informații legale și documente', { exact: true })
        .click();
    assert.equal(
        await footer.locator('.legal-footer-identity').isVisible(),
        true,
    );
    await footer
        .getByText('Informații legale și documente', { exact: true })
        .click();
    await page.screenshot({
        path: 'docs/screenshots/history-spacing-desktop.png',
        fullPage: true,
    });

    await page.goto(base + '/today');
    const today = await page
        .getByLabel('Alege ziua', { exact: true })
        .inputValue();
    const start = new Date(today.slice(0, 7) + '-01T12:00:00Z');
    if (start.toISOString().slice(0, 10) === today)
        start.setUTCMonth(start.getUTCMonth() - 1);
    const early = start.toISOString().slice(0, 10);
    await page.goto(base + '/today?date=' + early);
    await page
        .getByRole('button', { name: 'Modifică orele', exact: true })
        .click();
    const day = page.getByRole('dialog');
    await day
        .getByRole('checkbox', { name: /Confirm că persoana era activă/ })
        .waitFor();
    assert.equal(
        await day.locator('form').evaluate((form) => form.checkValidity()),
        false,
    );
    await day
        .getByRole('checkbox', { name: /Confirm că persoana era activă/ })
        .check();
    await day
        .getByLabel('Motivul corecturii', { exact: true })
        .fill('Persoana lucra deja înainte de crearea contului.');
    await day.screenshot({
        path: 'docs/screenshots/past-day-confirmation.png',
    });
    await day
        .getByRole('button', { name: 'Salvează ziua', exact: true })
        .click();
    await day.waitFor({ state: 'hidden' });
    await page.getByRole('heading', { name: '8 h', exact: true }).waitFor();

    await page.goto(base + '/team');
    await page
        .getByRole('button', { name: 'Invită un coleg', exact: true })
        .first()
        .click();
    const invite = page.getByRole('dialog');
    await invite.getByLabel('Nume', { exact: true }).fill('Colegul Fictiv');
    await invite.getByLabel('Prenume', { exact: true }).fill('Ion');
    await invite
        .getByRole('checkbox', {
            name: 'Colegul nu are adresă de email',
            exact: true,
        })
        .check();
    assert.equal(await invite.locator('input[type="email"]').count(), 0);
    await invite.getByLabel('Pontaj începând cu', { exact: true }).fill(early);
    assert.equal(
        await invite.locator('form').evaluate((form) => form.checkValidity()),
        false,
    );
    await invite
        .getByRole('checkbox', { name: /Confirm că acest coleg nu are email/ })
        .check();
    await page.setViewportSize({ width: 390, height: 844 });
    await invite.screenshot({
        path: 'docs/screenshots/managed-member-mobile.png',
    });
    await invite
        .getByRole('button', { name: 'Adaugă colegul', exact: true })
        .click();
    await invite.waitFor({ state: 'hidden' });
    await page.getByText('Ion Colegul Fictiv', { exact: true }).waitFor();
    await page
        .getByText('Fără cont · pontaj de responsabil', { exact: true })
        .waitFor();

    await page.goto(base + '/month?month=' + early.slice(0, 7));
    const row = page
        .locator('.monthly-table tbody tr')
        .filter({ hasText: 'Ion Colegul Fictiv' });
    await row.getByRole('button').first().click();
    await day
        .getByText(/Coleg fără cont\. Înregistrezi și aprobi orele/)
        .waitFor();
    assert.equal(
        await day.getByLabel('Motivul corecturii', { exact: true }).count(),
        0,
    );
    await day
        .getByRole('button', { name: 'Salvează ziua', exact: true })
        .click();
    await day.waitFor({ state: 'hidden' });
    assert.equal(
        (await row.getByRole('button').first().textContent()).trim(),
        '8',
    );
    await page.reload();
    assert.equal(
        (await row.getByRole('button').first().textContent()).trim(),
        '8',
    );
    await page.goto(base + '/history');
    assert.ok((await footer.boundingBox()).height < 170);
    await footer.screenshot({
        path: 'docs/screenshots/authenticated-footer-mobile.png',
    });
    assert.ok(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    );
    assert.deepEqual(errors, []);
    console.log(
        JSON.stringify({
            verified:
                'Footer discret, spațiere istoric, început istoric confirmat, coleg fără cont și pontaj salvat pe mobil',
            historyGap: gap,
            errors,
        }),
    );
} finally {
    await browser.close();
}
