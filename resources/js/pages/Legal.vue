<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Brand from '@/components/Brand.vue';
import Icon from '@/components/UiIcon.vue';
import LegalFooter from '@/components/LegalFooter.vue';
import LegalRequestForm from '@/components/LegalRequestForm.vue';
defineProps<{
    document: { slug: string; title: string; icon: string; html: string };
}>();
const legal = computed(() => usePage().props.legal);
function printDocument() {
    window.print();
}
</script>
<template>
    <Head :title="document.title" />
    <div class="legal-page">
        <a class="skip-link" href="#legal-document">Mergi la document</a>
        <header class="public-nav">
            <Link href="/"><Brand /></Link>
            <nav class="legal-header-links">
                <Link href="/contact">Contact</Link
                ><Link href="/login" class="button secondary"
                    >Intră în cont</Link
                >
            </nav>
        </header>
        <main class="legal-shell">
            <aside class="legal-sidebar">
                <span class="eyebrow">TRANSPARENȚĂ ȘI DREPTURI</span>
                <nav aria-label="Pagini juridice">
                    <Link
                        v-for="link in legal.links"
                        :key="link.href"
                        :href="link.href"
                        :aria-current="
                            link.href === `/${document.slug}`
                                ? 'page'
                                : undefined
                        "
                        :class="{ active: link.href === `/${document.slug}` }"
                        ><Icon :name="link.icon" :size="18" />{{
                            link.title
                        }}</Link
                    >
                </nav>
            </aside>
            <div class="legal-main">
                <article id="legal-document" class="legal-card" tabindex="-1">
                    <div class="legal-document-icon">
                        <Icon :name="document.icon" :size="26" />
                    </div>
                    <span class="eyebrow"
                        >IPONTAJ · DOCUMENTELE SERVICIULUI</span
                    >
                    <h1>{{ document.title }}</h1>
                    <div class="legal-document-meta">
                        <span
                            >Versiunea {{ legal.version }} ·
                            {{ legal.date }}</span
                        ><a :href="`/documente/${document.slug}`"
                            ><Icon name="download" :size="16" />Descarcă
                            documentul</a
                        ><button
                            type="button"
                            class="text-button"
                            @click="printDocument"
                        >
                            Tipărește
                        </button>
                    </div>
                    <section
                        class="legal-operator-card"
                        aria-label="Identificarea operatorului"
                    >
                        <strong>{{ legal.operator.name }}</strong>
                        <p>
                            CIF {{ legal.operator.cif }} · ONRC
                            {{ legal.operator.register }}
                        </p>
                        <p>{{ legal.operator.address }}</p>
                        <p>
                            <a :href="`mailto:${legal.operator.email}`">{{
                                legal.operator.email
                            }}</a>
                            ·
                            <a :href="`tel:+4${legal.operator.phone}`">{{
                                legal.operator.phone
                            }}</a>
                        </p>
                        <p>
                            Neînregistrat în scopuri de TVA ·
                            {{ legal.operator.domain }}
                        </p>
                    </section>
                    <a
                        v-if="document.slug === 'retragere'"
                        href="#request-form-title"
                        class="button primary legal-withdrawal-action"
                        >Completează declarația de retragere</a
                    >
                    <div class="legal-prose" v-html="document.html" />
                    <section
                        v-if="
                            [
                                'furnizori',
                                'confidentialitate',
                                'acord-prelucrare-date',
                            ].includes(document.slug)
                        "
                        class="legal-infrastructure"
                    >
                        <h2>Infrastructură și păstrarea datelor</h2>
                        <dl>
                            <dt>Furnizor</dt>
                            <dd>
                                <a
                                    :href="legal.hosting.url"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    >{{ legal.hosting.provider }}</a
                                >
                            </dd>
                            <dt>Servicii</dt>
                            <dd>{{ legal.hosting.services }}</dd>
                            <template v-if="legal.hosting.contract_entity">
                                <dt>Entitate contractuală</dt>
                                <dd>{{ legal.hosting.contract_entity }}</dd>
                            </template>
                            <template v-if="legal.hosting.data_location">
                                <dt>Locațiile datelor</dt>
                                <dd>{{ legal.hosting.data_location }}</dd>
                            </template>
                            <template
                                v-if="legal.hosting.backup_retention_days"
                            >
                                <dt>Păstrarea backupurilor</dt>
                                <dd>
                                    {{ legal.hosting.backup_retention_days }}
                                    zile
                                </dd>
                            </template>
                            <template v-if="legal.retention.log_days">
                                <dt>Păstrarea logurilor</dt>
                                <dd>{{ legal.retention.log_days }} zile</dd>
                            </template>
                        </dl>
                        <p>
                            Pentru detalii suplimentare despre infrastructură
                            sau păstrarea datelor, scrie la
                            <a :href="`mailto:${legal.operator.email}`">{{
                                legal.operator.email
                            }}</a
                            >.
                        </p>
                    </section>
                    <section
                        v-if="document.slug === 'cookies'"
                        class="legal-infrastructure"
                    >
                        <h2>Configurarea tehnică actuală</h2>
                        <p>
                            Sesiune: {{ legal.session_minutes }} minute de
                            inactivitate. „Ține-mă minte”: până la
                            {{ legal.remember_days }} zile, dacă nu este revocat
                            anterior.
                        </p>
                    </section>
                    <LegalRequestForm
                        v-if="
                            ['retragere', 'solicitari-date'].includes(
                                document.slug,
                            )
                        "
                        :key="document.slug"
                        :withdrawal="document.slug === 'retragere'"
                    />
                </article>
            </div>
        </main>
        <LegalFooter />
    </div>
</template>
