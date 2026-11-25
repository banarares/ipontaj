import test from 'node:test';
import assert from 'node:assert/strict';
import {
    CONSENT_KEY,
    CONSENT_DAYS,
    cookieDomains,
    googleAnalyticsCookies,
    readCookieChoice,
    saveCookieChoice,
} from '../../resources/js/lib/cookieConsent.ts';
const storage = () => {
    const data = new Map<string, string>();
    return {
        getItem: (k: string) => data.get(k) ?? null,
        setItem: (k: string, v: string) => {
            data.set(k, v);
        },
    };
};
void test('cookie preferences default to no consent and reject invalid or stale choices', () => {
    const s = storage();
    assert.equal(readCookieChoice(s, 'v1', 1000), null);
    for (const raw of [
        '{bad',
        'null',
        '[]',
        JSON.stringify({
            version: 'v1',
            savedAt: 999,
            necessary: true,
            analytics: true,
            choice: 'rejected',
        }),
        JSON.stringify({
            version: 'v1',
            savedAt: 2000,
            necessary: true,
            analytics: true,
            choice: 'accepted',
        }),
    ]) {
        s.setItem(CONSENT_KEY, raw);
        assert.equal(readCookieChoice(s, 'v1', 1000), null);
    }
    saveCookieChoice(s, 'v1', 'accepted', true, 1000);
    assert.equal(readCookieChoice(s, 'v1', 1001)?.analytics, true);
    assert.equal(readCookieChoice(s, 'v2', 1001), null);
    assert.equal(
        readCookieChoice(s, 'v1', 1001 + CONSENT_DAYS * 86400000),
        null,
    );
});
void test('refusal overrides optional analytics and preserves strictly necessary preferences', () => {
    const s = storage();
    saveCookieChoice(s, 'v1', 'rejected', true, 1000);
    const c = readCookieChoice(s, 'v1', 1001);
    assert.equal(c?.analytics, false);
    assert.equal(c?.necessary, true);
    assert.equal(c?.choice, 'rejected');
    saveCookieChoice(s, 'v1', 'custom', false, 1002);
    assert.equal(readCookieChoice(s, 'v1', 1003)?.analytics, false);
});
void test('withdrawal finds only Google Analytics cookies on every parent domain', () => {
    assert.deepEqual(
        googleAnalyticsCookies(
            'XSRF-TOKEN=a; _ga=GA1.1.1; _ga_ABC123=GS1; _gid=x; _gat_UA-1=1; _gallery=keep; appearance=dark',
        ),
        ['_ga', '_ga_ABC123', '_gid', '_gat_UA-1'],
    );
    assert.deepEqual(googleAnalyticsCookies(''), []);
    assert.deepEqual(cookieDomains('www.ipontaj.ro'), [
        null,
        'www.ipontaj.ro',
        'ipontaj.ro',
    ]);
    assert.deepEqual(cookieDomains('localhost'), [null]);
});
