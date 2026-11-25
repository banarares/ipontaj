<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import {
    cookieDomains,
    googleAnalyticsCookies,
    readCookieChoice,
    saveCookieChoice,
    type CookieChoice,
} from '@/lib/cookieConsent';
declare global {
    interface Window {
        dataLayer?: unknown[];
    }
}
const settings = computed(() => usePage().props.cookieSettings);
const available = computed(
    () => !!settings.value.analyticsToken || !!settings.value.gtmId,
);
const providers = computed(() =>
    [
        settings.value.analyticsToken ? 'Cloudflare Web Analytics' : '',
        settings.value.gtmId
            ? 'Google Analytics (prin Google Tag Manager)'
            : '',
    ]
        .filter(Boolean)
        .join(' și '),
);
const visible = ref(false),
    details = ref(false),
    analytics = ref(false),
    storageError = ref('');
const panel = ref<HTMLElement | null>(null);
let observer: ResizeObserver | undefined;
let beaconLoaded = false;
let removeVisitListener: (() => void) | undefined;
function appendScript(src: string, configure: (s: HTMLScriptElement) => void) {
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
function activateAnalytics() {
    if (
        !analytics.value ||
        !available.value ||
        !settings.value.analyticsAllowed ||
        beaconLoaded
    )
        return;
    const { analyticsToken, gtmId } = settings.value;
    if (analyticsToken)
        appendScript(
            'https://static.cloudflareinsights.com/beacon.min.js',
            (script) => {
                script.dataset.optionalAnalytics = 'cloudflare';
                script.dataset.cfBeacon = JSON.stringify({
                    token: analyticsToken,
                    spa: false,
                });
                script.defer = true;
            },
        );
    if (gtmId) {
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
    }
    beaconLoaded = true;
}
function removeGoogleCookies() {
    for (const name of googleAnalyticsCookies(document.cookie)) {
        for (const domain of cookieDomains(window.location.hostname)) {
            document.cookie = `${name}=; Max-Age=0; Path=/${domain ? `; Domain=${domain}` : ''}`;
        }
    }
}
function loadPreference() {
    let value: CookieChoice | null = null;
    try {
        value = readCookieChoice(window.localStorage, settings.value.version);
    } catch {
        /* Storage can be unavailable. Optional analytics stays off. */
    }
    analytics.value = !!value?.analytics && available.value;
    visible.value = !value;
    if (analytics.value) activateAnalytics();
    else removeGoogleCookies();
}
function choose(choice: CookieChoice['choice']) {
    const allowed =
        choice === 'accepted'
            ? available.value
            : choice === 'custom' && analytics.value && available.value;
    try {
        saveCookieChoice(
            window.localStorage,
            settings.value.version,
            choice,
            allowed,
        );
    } catch {
        try {
            window.localStorage.removeItem('ipontaj:cookie-preferences');
        } catch {
            /* Storage may be blocked. */
        }
        analytics.value = false;
        storageError.value =
            'Browserul nu permite salvarea preferințelor. Analiza opțională rămâne oprită.';
        if (beaconLoaded) window.location.reload();
        return;
    }
    analytics.value = allowed;
    visible.value = false;
    details.value = false;
    if (!allowed) removeGoogleCookies();
    if (!allowed && beaconLoaded) {
        // Reload terminates the provider's event listeners as well as the script element.
        window.location.reload();
        return;
    }
    activateAnalytics();
    window.dispatchEvent(
        new CustomEvent('ipontaj:cookie-consent', {
            detail: { necessary: true, analytics: allowed },
        }),
    );
}
function open() {
    details.value = true;
    visible.value = true;
    void nextTick(() => panel.value?.focus());
}
watch(visible, async (value) => {
    observer?.disconnect();
    document.documentElement.style.setProperty('--cookie-panel-height', '0px');
    if (!value) return;
    await nextTick();
    if (!panel.value) return;
    observer = new ResizeObserver(() =>
        document.documentElement.style.setProperty(
            '--cookie-panel-height',
            `${panel.value?.getBoundingClientRect().height || 0}px`,
        ),
    );
    observer.observe(panel.value);
});
watch(
    () => settings.value.version,
    () => {
        if (beaconLoaded) window.location.reload();
        else loadPreference();
    },
);
onMounted(() => {
    loadPreference();
    window.addEventListener('ipontaj:cookie-settings', open);
    removeVisitListener = router.on('before', (event) => {
        // Leave the public analytics document before visiting account/private URLs.
        if (beaconLoaded && event.detail.visit.method === 'get') {
            event.preventDefault();
            window.location.assign(event.detail.visit.url.href);
        }
    });
});
onBeforeUnmount(() => {
    removeVisitListener?.();
    observer?.disconnect();
    window.removeEventListener('ipontaj:cookie-settings', open);
    document.documentElement.style.removeProperty('--cookie-panel-height');
});
</script>
<template>
    <aside
        v-if="visible"
        ref="panel"
        class="cookie-panel"
        tabindex="-1"
        aria-label="Preferințe pentru cookie-uri"
    >
        <div class="cookie-copy">
            <strong>Tu alegi ce ne permiți</strong>
            <p>
                Folosim cookie-uri necesare pentru autentificare și securitate.
                <template v-if="available"
                    >Analiza utilizării este opțională și pornește doar dacă
                    accepți.</template
                ><template v-else
                    >Momentan folosim doar tehnologii necesare.</template
                >
                {{ ' ' }}<a href="/cookies">Politica de cookie-uri</a>.
            </p>
        </div>
        <div v-if="details" class="cookie-options">
            <label
                ><input type="checkbox" checked disabled />Strict necesare
                <span
                    >Mereu active pentru funcționarea serviciului.</span
                ></label
            >
            <label
                ><input
                    v-model="analytics"
                    type="checkbox"
                    :disabled="!available"
                />Analiza utilizării
                <span>{{
                    available
                        ? `${providers}, numai cu acordul tău.`
                        : 'Nu folosim analiză opțională în această instalare.'
                }}</span></label
            >
            <p>
                Preferințele se păstrează 180 de zile. Le poți modifica oricând
                din „Setări cookie-uri”.
            </p>
        </div>
        <p v-if="storageError" class="form-error" role="status">
            {{ storageError }}
        </p>
        <div class="cookie-actions">
            <button
                type="button"
                class="button secondary"
                @click="choose('accepted')"
            >
                Acceptă toate</button
            ><button
                type="button"
                class="button secondary"
                @click="choose('rejected')"
            >
                Refuză opționalele</button
            ><button
                v-if="!details"
                type="button"
                class="text-button"
                @click="details = true"
            >
                Preferințe</button
            ><button
                v-else
                type="button"
                class="text-button"
                @click="choose('custom')"
            >
                Salvează preferințele
            </button>
        </div>
    </aside>
    <button
        v-else-if="usePage().component !== 'Workspace'"
        type="button"
        class="cookie-settings-toggle"
        @click="open"
        aria-label="Setări cookie-uri"
    >
        Cookie-uri
    </button>
</template>
