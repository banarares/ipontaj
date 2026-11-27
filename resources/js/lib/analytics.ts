export type AnalyticsEvent = {
    id?: string;
    event: string;
    params?: Record<string, string | number | boolean>;
    ecommerce?: Record<string, unknown>;
};

type GtmPush = Record<string, unknown> & {
    eventCallback?: () => void;
    eventTimeout?: number;
};

declare global {
    interface Window {
        dataLayer?: unknown[];
    }
}

// GTM keeps every pushed key in its data model, so each event clears the others.
const PARAMETERS = [
    'cta_location',
    'cta_destination',
    'section',
    'form_name',
    'method',
    'file_name',
] as const;
const DELIVERED_KEY = 'ipontaj:analytics-delivered';

export const TEAM_PLAN = {
    currency: 'RON',
    value: 49,
    items: [
        {
            item_id: 'team',
            item_name: 'iPontaj Echipă',
            price: 49,
            quantity: 1,
        },
    ],
};

export function eventPayload(event: AnalyticsEvent): Record<string, unknown> {
    const payload: Record<string, unknown> = Object.fromEntries(
        PARAMETERS.map((key) => [key, undefined]),
    );
    for (const [key, value] of Object.entries(event.params ?? {}))
        if ((PARAMETERS as readonly string[]).includes(key))
            payload[key] = value;
    if (event.ecommerce) payload.ecommerce = event.ecommerce;
    payload.event = event.event;

    return payload;
}

export function undeliveredEvents(
    events: AnalyticsEvent[],
    storage: Pick<Storage, 'getItem'>,
): AnalyticsEvent[] {
    let delivered: unknown = [];
    try {
        delivered = JSON.parse(storage.getItem(DELIVERED_KEY) || '[]');
    } catch {
        delivered = [];
    }
    const seen = new Set(Array.isArray(delivered) ? delivered : []);

    return events.filter((event) => !event.id || !seen.has(event.id));
}

export function markDelivered(
    events: AnalyticsEvent[],
    storage: Pick<Storage, 'getItem' | 'setItem'>,
): void {
    const ids = events.map((event) => event.id).filter(Boolean);
    if (!ids.length) return;
    let previous: unknown = [];
    try {
        previous = JSON.parse(storage.getItem(DELIVERED_KEY) || '[]');
    } catch {
        previous = [];
    }
    storage.setItem(
        DELIVERED_KEY,
        JSON.stringify(
            [...(Array.isArray(previous) ? previous : []), ...ids].slice(-50),
        ),
    );
}

type Mode = 'off' | 'page' | 'conversion';
let mode: Mode = 'off';
let gtmId: string | null = null;
let consented = false;

export function configureAnalytics(options: {
    gtmId: string | null;
    consented: boolean;
}): void {
    gtmId = options.gtmId;
    consented = options.consented;
}

export function analyticsMode(): Mode {
    return mode;
}

export function appendScript(
    src: string,
    configure: (script: HTMLScriptElement) => void,
): void {
    const script = document.createElement('script');
    configure(script);
    script.src = src;
    const nonce = document.querySelector<HTMLMetaElement>(
        'meta[name="csp-nonce"]',
    )?.content;
    if (nonce) script.nonce = nonce;
    document.head.appendChild(script);
}

function gtag(...args: unknown[]) {
    void args;
    // GTM recognises gtag commands only as an Arguments object, not as an array.
    // eslint-disable-next-line prefer-rest-params
    window.dataLayer?.push(arguments);
}

export function loadGoogleTagManager(scope: Exclude<Mode, 'off'>): boolean {
    if (!gtmId || !consented) return false;
    if (mode !== 'off') return true;
    window.dataLayer = window.dataLayer || [];
    // Consent Mode v2: the visitor accepted statistics only, never advertising.
    gtag('consent', 'default', {
        analytics_storage: 'granted',
        ad_storage: 'denied',
        ad_user_data: 'denied',
        ad_personalization: 'denied',
    });
    window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
    appendScript(
        'https://www.googletagmanager.com/gtm.js?id=' +
            encodeURIComponent(gtmId),
        (script) => {
            script.dataset.optionalAnalytics = 'google-tag-manager';
            script.async = true;
        },
    );
    mode = scope;

    return true;
}

function push(event: AnalyticsEvent, callback?: () => void): void {
    if (event.ecommerce) window.dataLayer?.push({ ecommerce: null });
    const payload: GtmPush = eventPayload(event);
    if (callback) {
        payload.eventCallback = callback;
        payload.eventTimeout = 2000;
    }
    window.dataLayer?.push(payload);
}

/** Interaction on a public page; ignored unless analytics already runs there. */
export function track(
    event: string,
    params: AnalyticsEvent['params'] = {},
): void {
    if (mode === 'page') push({ event, params });
}

/**
 * Sends events and resolves once GTM has handled them (or after a timeout), so the
 * caller can navigate away safely. Inside the account GTM is loaded only for this.
 */
export function deliver(events: AnalyticsEvent[]): Promise<boolean> {
    if (!events.length || !loadGoogleTagManager('conversion'))
        return Promise.resolve(false);

    return new Promise((resolve) => {
        let pending = events.length;
        const finish = () => {
            pending = 0;
            resolve(true);
        };
        const timer = window.setTimeout(finish, 2500);
        for (const event of events)
            push(event, () => {
                if (pending > 0 && --pending === 0) {
                    window.clearTimeout(timer);
                    finish();
                }
            });
    });
}

/** Like track(), but resolves when the event is handed over, before leaving the page. */
export function trackBeforeLeaving(
    event: string,
    params: AnalyticsEvent['params'] = {},
): Promise<boolean> {
    return mode === 'page'
        ? deliver([{ event, params }])
        : Promise.resolve(false);
}
