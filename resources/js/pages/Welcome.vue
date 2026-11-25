<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Brand from '@/components/Brand.vue';
import Icon from '@/components/UiIcon.vue';
import LegalFooter from '@/components/LegalFooter.vue';
defineProps<{ errors?: { demo?: string } }>();
const page = usePage();
const demoNotice = computed(() =>
    new URL(page.url, 'http://localhost').searchParams.get('demo') === 'expired'
        ? 'Sesiunea demo a expirat. Poți porni un demo nou sau crea un cont gratuit.'
        : '',
);
const loading = ref(false);
function demo() {
    loading.value = true;
    if (document.querySelector('script[data-optional-analytics]')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/demo';
        const token = document.createElement('input');
        token.type = 'hidden';
        token.name = '_token';
        token.value =
            document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
                ?.content || '';
        form.appendChild(token);
        document.body.appendChild(form);
        form.submit();
        return;
    }
    router.post(
        '/demo',
        {},
        {
            onFinish: () => {
                loading.value = false;
            },
        },
    );
}
</script>
<template>
    <Head title="Pontaj simplu. Timp pentru ce contează." />
    <div class="public-page">
        <header class="public-nav">
            <Link href="/"><Brand /></Link>
            <nav class="public-nav-links">
                <a href="#cum-functioneaza">Cum funcționează</a
                ><a href="#preturi">Prețuri</a
                ><Link href="/login">Intră în cont</Link
                ><Link href="/register" class="button primary"
                    >Începe gratuit</Link
                >
            </nav>
        </header>
        <main>
            <section class="public-hero">
                <div>
                    <span class="hero-label"
                        ><span class="status-dot green"></span>Făcut pentru
                        echipe mici. Și zile mai simple.</span
                    >
                    <h1>Pontajul la zi.<br /><em>O grijă în minus.</em></h1>
                    <p>
                        Confirmi ziua în câteva secunde. La final de lună ai
                        pontajul pregătit pentru contabil.
                    </p>
                    <div class="hero-actions">
                        <Link href="/register" class="button primary"
                            >Creează cont gratuit</Link
                        ><button
                            class="button secondary"
                            :disabled="loading"
                            @click="demo"
                        >
                            <Icon name="calendar" :size="17" />{{
                                loading ? 'Se pregătește…' : 'Încearcă demo-ul'
                            }}
                        </button>
                    </div>
                    <p
                        v-if="errors?.demo || demoNotice"
                        class="notice"
                        role="alert"
                    >
                        {{ errors?.demo || demoNotice }}
                    </p>
                    <div class="hero-trust">
                        <span
                            ><Icon name="check" :size="14" />Gratuit până la 5
                            persoane</span
                        ><span
                            ><Icon name="check" :size="14" />Fără card
                            bancar</span
                        >
                    </div>
                </div>
                <div
                    class="hero-demo"
                    aria-label="Exemplu de confirmare a unei zile de lucru"
                >
                    <div class="hero-day-card">
                        <div class="demo-card-top">
                            <Brand /><span>ZIUA MEA</span>
                        </div>
                        <h2>Bună, Maria!</h2>
                        <p>O zi de lucru. Câteva secunde pentru tine.</p>
                        <div class="demo-hours">
                            <div>
                                <span>ÎNCEPUT</span><strong>09:00</strong>
                            </div>
                            <b>—</b>
                            <div>
                                <span>SFÂRȘIT</span><strong>17:00</strong>
                            </div>
                        </div>
                        <button
                            class="demo-confirm"
                            :disabled="loading"
                            @click="demo"
                        >
                            <Icon name="check" :size="18" />Confirmă ziua
                        </button>
                        <div class="demo-subactions">
                            <span>Modifică orele</span
                            ><span>Marchează absența</span>
                        </div>
                    </div>
                    <div class="demo-float">
                        <span><Icon name="excel" :size="18" /></span>
                        <div>
                            <strong>Luna e pregătită pentru contabil.</strong>
                            <p>Un export Excel. Toată echipa.</p>
                        </div>
                    </div>
                </div>
            </section>
            <div class="public-proof">
                <span
                    ><Icon name="phone" :size="19" />Pe telefon sau laptop</span
                ><span
                    ><Icon name="shield" :size="19" />Fiecare firmă, spațiul
                    ei</span
                ><span
                    ><Icon name="excel" :size="19" />Excel clar pentru
                    contabil</span
                >
            </div>
            <section id="cum-functioneaza" class="public-section">
                <div class="section-intro">
                    <span class="eyebrow">FĂRĂ COMPLICAȚII</span>
                    <h2>O rutină mică. O diferență mare.</h2>
                    <p>
                        Tu îți cunoști echipa. Noi te ajutăm să ții orele în
                        ordine.
                    </p>
                </div>
                <div class="steps-grid">
                    <article class="step-card">
                        <span class="step-icon"><Icon name="users" /></span>
                        <h3>Adaugi echipa și programul</h3>
                        <p>
                            Creezi firma, confirmi programul obișnuit și inviți
                            colegii printr-un link personal.
                        </p>
                    </article>
                    <article class="step-card">
                        <span class="step-icon"
                            ><Icon name="calendarcheck"
                        /></span>
                        <h3>Fiecare confirmă ziua</h3>
                        <p>
                            Un buton pentru o zi obișnuită. Ore ajustabile,
                            pauze și absențe când planurile se schimbă.
                        </p>
                    </article>
                    <article class="step-card">
                        <span class="step-icon"><Icon name="excel" /></span>
                        <h3>Închei luna fără alergătură</h3>
                        <p>
                            Vezi ce lipsește, verifici excepțiile și descarci
                            pontajul Excel pentru contabil.
                        </p>
                    </article>
                </div>
            </section>
            <section id="preturi" class="public-section">
                <div class="section-intro">
                    <span class="eyebrow">LOC PENTRU ECHIPA TA</span>
                    <h2>Prețuri simple. De la prima zi.</h2>
                    <p>
                        Funcțiile de care ai nevoie, inclusiv în planul gratuit.
                    </p>
                </div>
                <div class="pricing-grid">
                    <article class="panel pricing-card">
                        <span class="eyebrow">PENTRU ÎNCEPUT</span>
                        <h2>Gratuit</h2>
                        <p>Până la 5 persoane active în pontaj.</p>
                        <div class="price">0 <span>lei / lună</span></div>
                        <ul class="feature-list">
                            <li>
                                <Icon name="check" />Confirmare zilnică și
                                absențe
                            </li>
                            <li><Icon name="check" />Mementouri prin email</li>
                            <li><Icon name="check" />Export Excel lunar</li>
                        </ul>
                        <Link href="/register" class="button secondary full"
                            >Începe gratuit</Link
                        >
                    </article>
                    <article class="panel pricing-card highlighted">
                        <span class="plan-tag">PENTRU MAI MULTĂ LINIȘTE</span
                        ><span class="eyebrow">ECHIPA CREȘTE</span>
                        <h2>Echipă</h2>
                        <p>Până la 15 persoane active în pontaj.</p>
                        <div class="price">
                            49 <span>lei / lună, preț final</span>
                        </div>
                        <ul class="feature-list">
                            <li>
                                <Icon name="check" />Tot ce include planul
                                Gratuit
                            </li>
                            <li>
                                <Icon name="check" />Mai mult loc pentru colegi
                            </li>
                            <li>
                                <Icon name="check" />Trimitere automată către
                                contabil
                            </li>
                        </ul>
                        <Link href="/register" class="button primary full"
                            >Creează contul echipei</Link
                        >
                    </article>
                </div>
                <p class="hint" style="text-align: center">
                    Preț final: 49 lei/lună. Operator neînregistrat în scopuri
                    de TVA. Planul Gratuit nu necesită card. Disponibilitatea
                    abonamentului Echipă este afișată în cont.
                </p>
            </section>
            <section class="public-section">
                <div class="public-cta">
                    <h2>Mai puține tabele. Mai mult timp pentru oameni.</h2>
                    <p>Vezi cum arată o lună mai simplă pentru echipa ta.</p>
                    <button
                        class="button primary"
                        :disabled="loading"
                        @click="demo"
                    >
                        {{
                            loading
                                ? 'Se pregătește…'
                                : 'Explorează demo-ul fără cont'
                        }}
                    </button>
                </div>
            </section>
        </main>
        <LegalFooter />
    </div>
</template>
