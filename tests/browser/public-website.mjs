import { chromium } from '@playwright/test';
import assert from 'node:assert/strict';
const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROME_PATH
        ? { executablePath: process.env.CHROME_PATH }
        : {}),
    args: ['--no-sandbox'],
});
const base = process.env.BASE_URL || 'http://127.0.0.1:8766';
const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
    locale: 'ro-RO',
});
const page = await context.newPage();
page.setDefaultTimeout(20000);
const errors = [];
page.on('pageerror', (e) => errors.push(e.message));
let analyticsRequests = 0;
await context.route(
    'https://static.cloudflareinsights.com/**',
    async (route) => {
        analyticsRequests++;
        await route.fulfill({
            contentType: 'application/javascript',
            body: 'window.fixtureAnalyticsLoaded=true;',
        });
    },
);
try {
    await page.goto(base + '/');
    await page.getByRole('heading', { name: 'Pontajul la zi.' }).waitFor();
    await page
        .getByRole('complementary', { name: 'Preferințe pentru cookie-uri' })
        .waitFor();
    assert.equal(analyticsRequests, 0);
    await page
        .getByRole('button', { name: 'Refuză opționalele', exact: true })
        .click();
    await page.reload();
    await page.getByRole('heading', { name: 'Pontajul la zi.' }).waitFor();
    assert.equal(analyticsRequests, 0);
    assert.equal(await page.locator('link[rel="canonical"]').count(), 1);
    assert.equal(
        await page.locator('link[rel="canonical"]').getAttribute('href'),
        'https://ipontaj.ro/',
    );
    assert.equal(
        await page.locator('script[type="application/ld+json"]').count(),
        1,
    );
    await page
        .getByRole('link', { name: 'Contact și reclamații', exact: true })
        .click();
    await page
        .getByRole('heading', { name: 'Trimite-ne un mesaj', exact: true })
        .waitFor();
    assert.equal(
        await page.locator('script[type="application/ld+json"]').count(),
        0,
    );
    assert.equal(
        await page.locator('link[rel="canonical"]').getAttribute('href'),
        'https://ipontaj.ro/contact',
    );
    await page.getByLabel('Nume', { exact: true }).fill('Fictiv');
    await page.getByLabel('Prenume', { exact: true }).fill('Ana');
    await page
        .getByLabel('Email pentru răspuns', { exact: true })
        .fill('contact-browser@example.test');
    await page
        .getByLabel('Subiect', { exact: true })
        .fill('Verificare fictivă de contact');
    await page
        .getByLabel('Mesaj', { exact: true })
        .fill('Acesta este un mesaj fictiv, fără date reale.');
    await page
        .getByRole('button', { name: 'Trimite mesajul', exact: true })
        .click();
    await page
        .getByText('Mesajul tău a fost înregistrat.', { exact: true })
        .waitFor();
    await page.screenshot({
        path: 'docs/screenshots/contact-desktop.png',
        fullPage: true,
    });
    await page.setViewportSize({ width: 390, height: 844 });
    assert.ok(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    );
    await page
        .getByRole('button', { name: 'Setări cookie-uri', exact: true })
        .first()
        .click();
    await page.getByRole('checkbox', { name: /Analiza utilizării/ }).check();
    await page
        .getByRole('button', { name: 'Salvează preferințele', exact: true })
        .click();
    await page.waitForFunction(() => window.fixtureAnalyticsLoaded === true);
    assert.equal(analyticsRequests, 1);
    await page
        .getByRole('link', { name: 'Intră în cont', exact: true })
        .click();
    await page.waitForURL('**/login');
    assert.equal(analyticsRequests, 1);
    assert.equal(
        await page.locator('script[data-optional-analytics]').count(),
        0,
    );
    await page.goto(base + '/contact');
    await page.waitForFunction(() => window.fixtureAnalyticsLoaded === true);
    assert.equal(analyticsRequests, 2);
    await page
        .getByRole('button', { name: 'Setări cookie-uri', exact: true })
        .first()
        .click();
    await Promise.all([
        page.waitForNavigation(),
        page
            .getByRole('button', { name: 'Refuză opționalele', exact: true })
            .click(),
    ]);
    await page
        .getByRole('button', { name: 'Setări cookie-uri', exact: true })
        .first()
        .waitFor();
    assert.equal(analyticsRequests, 2);
    await page
        .getByRole('button', { name: 'Setări cookie-uri', exact: true })
        .first()
        .click();
    await page.screenshot({
        path: 'docs/screenshots/cookie-preferences-mobile.png',
    });
    await page
        .getByRole('button', { name: 'Refuză opționalele', exact: true })
        .click();
    await page.goto(base + '/login');
    assert.equal(await page.locator('.legal-footer').count(), 0);
    assert.equal(
        await page.locator('meta[name="robots"]').getAttribute('content'),
        'noindex, nofollow',
    );
    await page.screenshot({
        path: 'docs/screenshots/login-mobile.png',
        fullPage: true,
    });
    await page.goto(base + '/register');
    assert.equal(await page.locator('.legal-footer').count(), 0);
    await page.screenshot({
        path: 'docs/screenshots/register-clean-mobile.png',
        fullPage: true,
    });
    await page.goto(base + '/');
    await page
        .getByRole('button', { name: 'Încearcă demo-ul', exact: true })
        .click();
    await page.waitForURL('**/dashboard');
    await page.setViewportSize({ width: 1440, height: 1000 });
    await page.locator('.legal-footer.compact').screenshot({
        path: 'docs/screenshots/authenticated-footer-desktop.png',
    });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.locator('.legal-footer.compact').screenshot({
        path: 'docs/screenshots/authenticated-footer-mobile.png',
    });
    assert.ok(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
        ),
    );
    assert.equal(analyticsRequests, 2);
    assert.deepEqual(errors, []);
    const optedInDemo = await browser.newPage({
        viewport: { width: 1280, height: 900 },
    });
    let demoBeacons = 0;
    await optedInDemo.route(
        'https://static.cloudflareinsights.com/**',
        async (route) => {
            demoBeacons++;
            await route.fulfill({
                contentType: 'application/javascript',
                body: 'window.fixtureAnalyticsLoaded=true;',
            });
        },
    );
    await optedInDemo.goto(base + '/');
    await optedInDemo
        .getByRole('button', { name: 'Acceptă toate', exact: true })
        .click();
    await optedInDemo.waitForFunction(
        () => window.fixtureAnalyticsLoaded === true,
    );
    await optedInDemo
        .getByRole('button', { name: 'Încearcă demo-ul', exact: true })
        .click();
    await optedInDemo.waitForURL('**/dashboard');
    assert.equal(
        await optedInDemo.locator('script[data-optional-analytics]').count(),
        0,
    );
    assert.equal(demoBeacons, 1);
    console.log(
        JSON.stringify({
            verified:
                'Contact, footer autentificat, login/register fără footer, refuz/acceptare/retragere cookie-uri, metadate după navigare Inertia, mobil',
            errors,
            analyticsRequests,
        }),
    );
} finally {
    await browser.close();
}
