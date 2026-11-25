import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';

export async function confirmEmail(
    page,
    email,
    password = 'Molid!Vapor7Nori$42',
) {
    await page.waitForURL('**/email/verify');
    const base = new URL(page.url()).origin;
    assert.ok(['127.0.0.1', 'localhost'].includes(new URL(base).hostname));
    const root = fileURLToPath(new URL('../../../', import.meta.url));
    const link = execFileSync(
        'php',
        ['tests/browser/support/verification-link.php', email],
        {
            cwd: root,
            env: {
                ...process.env,
                APP_ENV: 'testing',
                APP_URL: base,
                DB_CONNECTION: 'sqlite',
                DB_DATABASE: root + '.runtime/public-web.sqlite',
                DB_URL: '',
                MAIL_MAILER: 'array',
                QUEUE_CONNECTION: 'sync',
                CACHE_STORE: 'array',
            },
            encoding: 'utf8',
        },
    ).trim();
    await page.goto(link);
    await page.waitForURL('**/login');
    await page.getByLabel('Adresa de email', { exact: true }).fill(email);
    await page.getByLabel('Parola', { exact: true }).fill(password);
    const button = page.getByRole('button', {
        name: 'Intră în cont',
        exact: true,
    });
    await button.waitFor();
    await page.waitForFunction(() => {
        const login = [...document.querySelectorAll('button')].find(
            (b) => b.textContent.trim() === 'Intră în cont',
        );
        return login && !login.disabled;
    });
    await button.click();
}
