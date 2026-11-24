<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
const props = defineProps<{
    action: 'login' | 'register' | 'contact';
    error?: string;
}>();
const token = defineModel<string>({ required: true });
const settings = computed(() => usePage().props.turnstile);
const host = ref<HTMLElement | null>(null);
const failure = ref('');
let widget: string | undefined;
let disposed = false;
let responsiveSize: 'compact' | 'flexible' = 'flexible';
let sizeObserver: ResizeObserver | undefined;
type TurnstileApi = {
    render: (el: HTMLElement, options: Record<string, unknown>) => string;
    reset: (id: string) => void;
    remove: (id: string) => void;
};
const api = () => (window as Window & { turnstile?: TurnstileApi }).turnstile;
async function loadScript() {
    if (api()) return;
    let script = document.querySelector<HTMLScriptElement>(
        'script[data-turnstile]',
    );
    if (script?.dataset.failed) script.remove();
    script = document.querySelector<HTMLScriptElement>(
        'script[data-turnstile]',
    );
    if (!script) {
        script = document.createElement('script');
        script.dataset.turnstile = 'true';
        script.src =
            'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit';
        script.async = true;
        const nonce = document.querySelector<HTMLMetaElement>(
            'meta[name="csp-nonce"]',
        )?.content;
        if (nonce) script.nonce = nonce;
        document.head.appendChild(script);
    }
    await new Promise<void>((resolve, reject) => {
        const timer = window.setTimeout(
            () => reject(new Error('timeout')),
            15000,
        );
        script.addEventListener(
            'load',
            () => {
                clearTimeout(timer);
                resolve();
            },
            { once: true },
        );
        script.addEventListener(
            'error',
            () => {
                clearTimeout(timer);
                script.dataset.failed = 'true';
                reject(new Error('load'));
            },
            { once: true },
        );
        if (api()) {
            clearTimeout(timer);
            resolve();
        }
    });
}
async function mountWidget() {
    if (!settings.value.enabled || !host.value) return;
    failure.value = '';
    token.value = '';
    try {
        await loadScript();
        if (disposed || !host.value) return;
        responsiveSize = host.value.clientWidth < 300 ? 'compact' : 'flexible';
        if (widget) api()?.remove(widget);
        widget = api()?.render(host.value, {
            sitekey: settings.value.siteKey,
            action: props.action,
            theme: 'light',
            size: responsiveSize,
            language: 'ro',
            callback: (value: string) => {
                token.value = value;
                failure.value = '';
            },
            'expired-callback': () => {
                token.value = '';
                failure.value =
                    'Verificarea a expirat. Reîncearcă verificarea.';
            },
            'error-callback': () => {
                token.value = '';
                failure.value =
                    'Verificarea nu s-a încărcat. Reîncearcă sau verifică blocarea scripturilor.';
            },
        });
    } catch {
        failure.value =
            'Verificarea nu s-a încărcat. Reîncearcă sau contactează office@ipontaj.ro.';
    }
}
function reset() {
    token.value = '';
    if (widget) api()?.reset(widget);
}
function retry() {
    if (widget) {
        failure.value = '';
        reset();
    } else void mountWidget();
}
defineExpose({ reset });
onMounted(() => {
    void mountWidget();
    if (host.value && settings.value.enabled) {
        sizeObserver = new ResizeObserver(() => {
            const nextSize =
                (host.value?.clientWidth || 0) < 300 ? 'compact' : 'flexible';
            if (widget && nextSize !== responsiveSize) void mountWidget();
        });
        sizeObserver.observe(host.value);
    }
});
onBeforeUnmount(() => {
    disposed = true;
    sizeObserver?.disconnect();
    if (widget) api()?.remove(widget);
});
</script>
<template>
    <div v-if="settings.enabled" class="turnstile-field">
        <div ref="host" aria-label="Verificare de securitate Cloudflare" />
        <p v-if="error || failure" class="form-error" role="alert">
            {{ error || failure }}
        </p>
        <button v-if="failure" type="button" class="text-button" @click="retry">
            Reîncearcă verificarea
        </button>
        <p class="hint">
            Protecție împotriva mesajelor automate prin Cloudflare Turnstile.
            <a
                href="/confidentialitate"
                target="_blank"
                rel="noopener noreferrer"
                >Despre datele personale</a
            >.
        </p>
    </div>
</template>
