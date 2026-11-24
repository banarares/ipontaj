export const CONSENT_KEY = 'ipontaj:cookie-preferences';
export const CONSENT_DAYS = 180;
export type CookieChoice = {
    version: string;
    savedAt: number;
    necessary: true;
    analytics: boolean;
    choice: 'accepted' | 'rejected' | 'custom';
};
type StorageLike = Pick<Storage, 'getItem' | 'setItem'>;
export function readCookieChoice(
    storage: StorageLike,
    version: string,
    now = Date.now(),
): CookieChoice | null {
    try {
        const value: unknown = JSON.parse(
            storage.getItem(CONSENT_KEY) || 'null',
        );
        if (!value || typeof value !== 'object') return null;
        const v = value as Record<string, unknown>;
        if (
            v.version !== version ||
            v.necessary !== true ||
            typeof v.analytics !== 'boolean' ||
            !['accepted', 'rejected', 'custom'].includes(String(v.choice)) ||
            typeof v.savedAt !== 'number' ||
            !Number.isFinite(v.savedAt) ||
            v.savedAt > now ||
            now - v.savedAt > CONSENT_DAYS * 86400000
        )
            return null;
        if (v.choice === 'rejected' && v.analytics) return null;
        return v as CookieChoice;
    } catch {
        return null;
    }
}
export function saveCookieChoice(
    storage: StorageLike,
    version: string,
    choice: CookieChoice['choice'],
    analytics: boolean,
    now = Date.now(),
): CookieChoice {
    const value: CookieChoice = {
        version,
        savedAt: now,
        necessary: true,
        analytics: choice === 'rejected' ? false : analytics,
        choice,
    };
    storage.setItem(CONSENT_KEY, JSON.stringify(value));
    return value;
}
