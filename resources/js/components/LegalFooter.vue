<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from './UiIcon.vue';
const props = defineProps<{ compact?: boolean }>();
const legal = computed(() => usePage().props.legal);
function cookiePreferences() {
    window.dispatchEvent(new Event('ipontaj:cookie-settings'));
}
const authorities = [
    {
        href: 'https://anpc.ro',
        label: 'ANPC · Protecția consumatorilor',
        title: 'Protecția consumatorilor',
        description: 'ANPC · Reclamații în România',
        icon: 'shield',
    },
    {
        href: 'https://reclamatiisal.anpc.ro',
        label: 'SAL · Soluționarea litigiilor',
        title: 'Soluționarea litigiilor',
        description: 'SAL · Soluționare alternativă',
        icon: 'users',
    },
    {
        href: 'https://consumer-redress.ec.europa.eu/select-language?destination=/node/1',
        label: 'UE · Drepturile consumatorilor',
        title: 'Drepturile consumatorilor',
        description: 'Resurse în Uniunea Europeană',
        icon: 'globe',
    },
    {
        href: 'https://www.dataprotection.ro',
        label: 'ANSPDCP · Protecția datelor',
        title: 'Protecția datelor',
        description: 'ANSPDCP · Informații și plângeri',
        icon: 'lock',
    },
];
const links = computed(() =>
    legal.value.links.filter(
        (link) =>
            !props.compact ||
            [
                '/termeni-si-conditii',
                '/confidentialitate',
                '/cookies',
                '/contact',
                '/retragere',
                '/licenta',
            ].includes(link.href),
    ),
);
</script>
<template>
    <footer
        class="legal-footer"
        :class="{ compact }"
        aria-label="Informații juridice și contact"
    >
        <template v-if="compact">
            <div class="footer-slim-row">
                <span class="footer-slim-brand"
                    >iPontaj <span>· Timp pentru ce contează.</span></span
                >
                <nav class="footer-slim-links" aria-label="Linkuri utile">
                    <Link href="/termeni-si-conditii">Termeni</Link>
                    <Link href="/confidentialitate">Confidențialitate</Link>
                    <Link href="/contact">Contact</Link>
                    <button type="button" @click="cookiePreferences">
                        Setări cookie-uri
                    </button>
                </nav>
            </div>
            <details class="footer-legal-details">
                <summary>Informații legale și documente</summary>
                <div class="footer-legal-expanded">
                    <div class="legal-footer-identity">
                        <span>{{ legal.operator.name }}</span>
                        <span
                            >CIF {{ legal.operator.cif }} · ONRC
                            {{ legal.operator.register }}</span
                        >
                        <span>{{ legal.operator.address }}</span>
                        <span>Operator neînregistrat în scopuri de TVA.</span>
                        <p class="footer-contact">
                            <a :href="`mailto:${legal.operator.email}`">{{
                                legal.operator.email
                            }}</a
                            ><a :href="`tel:+4${legal.operator.phone}`">{{
                                legal.operator.phone
                            }}</a>
                        </p>
                    </div>
                    <nav
                        class="legal-footer-links"
                        aria-label="Documentele serviciului"
                    >
                        <Link
                            v-for="link in links"
                            :key="link.href"
                            :href="link.href"
                            >{{ link.title }}</Link
                        >
                    </nav>
                </div>
            </details>
        </template>
        <template v-else>
            <div class="legal-footer-identity">
                <strong>iPontaj · Timp pentru ce contează.</strong>
                <span>{{ legal.operator.name }}</span>
                <span
                    >CIF {{ legal.operator.cif }} · ONRC
                    {{ legal.operator.register }}</span
                >
                <span v-if="!compact">{{ legal.operator.address }}</span>
                <span>Operator neînregistrat în scopuri de TVA.</span>
                <p class="footer-contact">
                    <a :href="`mailto:${legal.operator.email}`">{{
                        legal.operator.email
                    }}</a>
                    <a
                        v-if="!compact"
                        :href="`tel:+4${legal.operator.phone}`"
                        >{{ legal.operator.phone }}</a
                    >
                </p>
            </div>
            <nav
                class="legal-footer-links"
                aria-label="Documentele serviciului"
            >
                <Link v-for="link in links" :key="link.href" :href="link.href"
                    ><Icon :name="link.icon" :size="16" />{{ link.title }}</Link
                >
                <button
                    type="button"
                    class="footer-cookie-button"
                    @click="cookiePreferences"
                >
                    <Icon name="cookie" :size="16" />Setări cookie-uri
                </button>
            </nav>
            <div v-if="!compact" class="legal-authorities">
                <nav
                    class="authority-links"
                    aria-label="Autorități și resurse pentru reclamații"
                >
                    <a
                        v-for="authority in authorities"
                        :key="authority.href"
                        class="authority-link"
                        :href="authority.href"
                        :aria-label="authority.label"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span class="authority-icon" aria-hidden="true"
                            ><Icon :name="authority.icon" :size="19"
                        /></span>
                        <span class="authority-copy"
                            ><strong>{{ authority.title }}</strong
                            ><small>{{ authority.description }}</small></span
                        >
                        <span class="authority-external" aria-hidden="true"
                            ><Icon name="external" :size="14"
                        /></span>
                    </a>
                </nav>
                <a
                    v-if="legal.sal_asset"
                    class="authority-sal-link"
                    href="https://reclamatiisal.anpc.ro"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="ANPC — Soluționarea alternativă a litigiilor"
                >
                    <img
                        :src="legal.sal_asset"
                        width="250"
                        height="50"
                        alt="ANPC — Soluționarea alternativă a litigiilor"
                    />
                </a>
                <p>Linkuri către autorități și resurse oficiale.</p>
            </div>
        </template>
    </footer>
</template>
