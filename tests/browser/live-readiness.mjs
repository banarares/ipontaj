import { chromium, webkit, devices } from '@playwright/test';
import assert from 'node:assert/strict';
import { confirmEmail } from './support/verification.mjs';
const base = process.env.BASE_URL || 'http://127.0.0.1:8773';
assert.ok(['localhost', '127.0.0.1'].includes(new URL(base).hostname));
const modes = [
    {
        name: 'chromium',
        engine: chromium,
        device: { viewport: { width: 1440, height: 1000 }, locale: 'ro-RO' },
    },
    {
        name: 'webkit-iphone',
        engine: webkit,
        device: { ...devices['iPhone 13'], locale: 'ro-RO' },
    },
];
for (const { name, engine, device } of modes.filter(
    (mode) => !process.env.BROWSER || mode.name === process.env.BROWSER,
)) {
    const browser = await engine.launch({
        headless: true,
        ...(name === 'chromium' && process.env.CHROME_PATH
            ? {
                  executablePath: process.env.CHROME_PATH,
                  args: ['--no-sandbox'],
              }
            : {}),
    });
    const page = await browser.newPage(device);
    page.setDefaultTimeout(25000);
    const errors = [];
    page.on('pageerror', (error) => errors.push(error.message));
    try {
        await page.goto(base + '/register');
        const refuse = page.getByRole('button', {
            name: 'Refuză opționalele',
            exact: true,
        });
        if (await refuse.count()) await refuse.click();
        const email = `readiness-${name}-${Date.now()}@example.test`;
        await page.getByLabel('Nume', { exact: true }).fill('Fictiv');
        await page.getByLabel('Prenume', { exact: true }).fill('Responsabil');
        await page.getByLabel('Adresa de email').fill(email);
        await page
            .getByLabel('Parola', { exact: true })
            .fill('Molid!Vapor7Nori$42');
        await page
            .getByLabel('Confirmă parola', { exact: true })
            .fill('Molid!Vapor7Nori$42');
        await page.getByRole('checkbox', { name: /Accept Termenii/ }).check();
        await page
            .getByRole('button', {
                name: 'Creează contul gratuit',
                exact: true,
            })
            .click();
        await page.waitForURL('**/email/verify');
        assert.equal(await page.locator('.workspace').count(), 0);
        await page
            .getByRole('heading', { name: 'Confirmă adresa de email' })
            .waitFor();
        await confirmEmail(page, email);
        await page.waitForURL('**/onboarding');
        await page
            .getByLabel('Folosesc iPontaj', { exact: true })
            .selectOption('consumer');
        await page
            .getByLabel('Numele spațiului', { exact: true })
            .fill('Echipă fictivă browser');
        await page
            .getByRole('checkbox', { name: /Confirm programul propus/ })
            .check();
        await page
            .getByRole('button', {
                name: 'Creează firma și continuă',
                exact: true,
            })
            .click();
        await page.waitForURL('**/dashboard');
        await page.locator('.notification-count').waitFor();
        await page.locator('.notification-menu summary').click();
        await page.getByText('Firma a fost creată', { exact: true }).waitFor();
        const panel = await page.locator('.notification-panel').boundingBox();
        assert.ok(
            panel.x >= 0 &&
                panel.x + panel.width <= page.viewportSize().width + 1,
        );
        await page
            .getByRole('button', { name: 'Marchează toate ca citite' })
            .click();
        await page
            .locator('.notification-count')
            .waitFor({ state: 'detached' });
        await page.keyboard.press('Escape');
        await page.goto(base + '/today');
        await page
            .getByRole('button', { name: 'Modifică orele', exact: true })
            .click();
        const dialog = page.getByRole('dialog');
        const inputs = dialog.locator('input[type="datetime-local"]');
        assert.equal(await inputs.count(), 2);
        const dimensions = await inputs.evaluateAll((fields) =>
            fields.map((field) => {
                const box = field.getBoundingClientRect();
                const parent = field
                    .closest('.interval-editor')
                    .getBoundingClientRect();
                return {
                    left: box.left,
                    right: box.right,
                    parentLeft: parent.left,
                    parentRight: parent.right,
                    width: box.width,
                    value: field.value,
                };
            }),
        );
        for (const field of dimensions) {
            assert.ok(
                field.left >= field.parentLeft &&
                    field.right <= field.parentRight + 1,
                JSON.stringify(field),
            );
            assert.ok(field.width > 100);
        }
        assert.ok(
            await dialog.evaluate(
                (node) => node.scrollWidth <= node.clientWidth + 1,
            ),
        );
        await dialog.screenshot({ path: `.runtime/day-editor-${name}.png` });
        await dialog
            .getByRole('button', { name: 'Salvează ziua', exact: true })
            .click();
        await dialog.waitFor({ state: 'detached' });
        await page.locator('.notification-menu summary').click();
        await page
            .locator('.notification-panel')
            .getByText('Zi înregistrată', { exact: true })
            .waitFor();
        await page
            .locator('.notification-panel')
            .screenshot({ path: `.runtime/notifications-${name}.png` });
        assert.deepEqual(errors, []);
        console.log(
            JSON.stringify({
                browser: name,
                registrationRequiresVerification: true,
                notificationsReadAndPersist: true,
                dateInputsContained: true,
                saveSucceeded: true,
                jsErrors: errors,
            }),
        );
    } finally {
        await browser.close();
    }
}
