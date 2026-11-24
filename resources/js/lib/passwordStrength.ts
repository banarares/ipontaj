import { ZxcvbnFactory } from '@zxcvbn-ts/core';
import {
    adjacencyGraphs,
    dictionary as common,
} from '@zxcvbn-ts/language-common';
import { dictionary as english } from '@zxcvbn-ts/language-en';
import { dictionary as romanian, translations } from '@zxcvbn-ts/language-ro';

const estimator = new ZxcvbnFactory({
    graphs: adjacencyGraphs,
    dictionary: { ...common, ...english, ...romanian },
    translations,
});
const commonInputs = [
    'ipontaj',
    'parola',
    'parolamea',
    'parolasecreta',
    'bineaivenit',
    'romania',
];
export function estimatePassword(
    password: string,
    inputs: string[] = [],
): number {
    if (!password) return 0;
    const words = inputs.flatMap((input) => [
        input,
        ...input
            .split(/[\s@._-]+/u)
            .filter((word) => Array.from(word).length >= 3),
    ]);
    const score = estimator.check(password, [...commonInputs, ...words]).score;
    if (/(.)\1{4}/u.test(password.toLocaleLowerCase('ro-RO')))
        return Math.min(score, 1);
    const [first, last] = inputs;
    if (
        first &&
        last &&
        !first.includes('@') &&
        !last.includes('@') &&
        Array.from(first).length >= 3 &&
        Array.from(last).length >= 3
    ) {
        const normalize = (text: string) =>
            text.replace(/[^\p{L}\p{N}]/gu, '').toLocaleLowerCase('ro-RO');
        const value = normalize(password),
            a = normalize(first),
            b = normalize(last);
        if (value.includes(a + b) || value.includes(b + a))
            return Math.min(score, 1);
    }
    return score;
}
