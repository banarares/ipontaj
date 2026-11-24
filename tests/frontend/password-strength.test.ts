import { test } from 'node:test';
import assert from 'node:assert/strict';
import { estimatePassword } from '../../resources/js/lib/passwordStrength.ts';
await test('strength detects predictable passwords beyond character classes', () => {
    assert.equal(estimatePassword(''), 0);
    assert.ok(estimatePassword('aaaaaaaaaaaaaaa!1A') < 3);
    assert.ok(estimatePassword('Password123456!') < 3);
    assert.ok(
        estimatePassword('AnaPopescu2026!', [
            'Ana',
            'Popescu',
            'ana@example.test',
        ]) < 3,
    );
});
await test('a long, varied password receives a strong grade', () => {
    assert.ok(estimatePassword('Molid!Vapor7Nori$42') >= 3);
    assert.ok(estimatePassword('Vânt!Pădure7Ocean$52') >= 3);
});
