<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Icon from './UiIcon.vue';
const props = defineProps<{ compact?: boolean }>();
const legal = computed(() => usePage().props.legal);
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
        <div class="legal-footer-identity">
            <strong>iPontaj · Timp pentru ce contează.</strong>
            <span>{{ legal.operator.name }}</span>
            <span
                >CIF {{ legal.operator.cif }} · ONRC
                {{ legal.operator.register }}</span
            >
            <span v-if="!compact">{{ legal.operator.address }}</span>
            <span>Operator neînregistrat în scopuri de TVA.</span>
            <p>
                <a :href="`mailto:${legal.operator.email}`">{{
                    legal.operator.email
                }}</a>
                |
                <a v-if="!compact" :href="`tel:+4${legal.operator.phone}`">{{
                    legal.operator.phone
                }}</a>
            </p>
        </div>
        <nav class="legal-footer-links" aria-label="Documentele serviciului">
            <Link v-for="link in links" :key="link.href" :href="link.href"
                ><Icon :name="link.icon" :size="16" />{{ link.title }}</Link
            >
        </nav>
        <div v-if="!compact" class="legal-authorities">
            <a href="https://anpc.ro" target="_blank" rel="noopener noreferrer"
                ><img
                    class="authority-logo anpc-logo"
                    src="/legal/anpc-logo.png"
                    alt=""
                    width="66"
                    height="36"
                />ANPC · Protecția consumatorilor</a
            >
            <a
                href="https://reclamatiisal.anpc.ro"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="ANPC — Soluționarea alternativă a litigiilor"
            >
                <img
                    v-if="legal.sal_asset"
                    :src="legal.sal_asset"
                    width="250"
                    height="50"
                    alt="ANPC — Soluționarea alternativă a litigiilor"
                />
                <span v-else class="sal-text-link"
                    ><Icon name="users" :size="19" />SAL · Soluționarea
                    alternativă a litigiilor</span
                >
            </a>
            <a
                href="https://www.dataprotection.ro"
                target="_blank"
                rel="noopener noreferrer"
                ><img
                    class="authority-logo anspdcp-logo"
                    src="/legal/anspdcp-logo.png"
                    alt=""
                    width="36"
                    height="36"
                />ANSPDCP · Protecția datelor</a
            >
            <p>Legături către autorități și mecanisme de reclamații.</p>
        </div>
    </footer>
</template>
