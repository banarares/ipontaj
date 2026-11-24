<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Brand from '@/components/Brand.vue';
import LegalFooter from '@/components/LegalFooter.vue';
const page = usePage();
const user = page.props.auth.user;
const form = useForm({
    first_name: user.first_name,
    last_name: user.last_name,
    email: user.email,
});
const deletion = useForm({ password: '' });
defineProps<{
    mustVerifyEmail?: boolean;
    status?: string;
    legalAcceptances: {
        id: number;
        purpose: string;
        version: string;
        accepted_at: string;
    }[];
}>();
</script>
<template>
    <Head title="Contul meu" />
    <div class="onboarding-page">
        <header class="onboarding-header">
            <Link href="/dashboard"><Brand /></Link
            ><Link href="/dashboard" class="text-button">Înapoi la echipă</Link>
        </header>
        <div class="onboarding-form">
            <span class="eyebrow">CONTUL MEU</span>
            <h1>Datele tale</h1>
            <p>Actualizează numele și adresa de email.</p>
            <form @submit.prevent="form.patch('/settings/profile')">
                <div class="person-name-fields">
                    <label for="profile-last-name"
                        >Nume<input
                            id="profile-last-name"
                            v-model="form.last_name"
                            required
                            maxlength="100"
                            autocomplete="family-name"
                        /><span
                            v-if="form.errors.last_name"
                            class="form-error"
                            >{{ form.errors.last_name }}</span
                        ></label
                    ><label for="profile-first-name"
                        >Prenume<input
                            id="profile-first-name"
                            v-model="form.first_name"
                            required
                            maxlength="100"
                            autocomplete="given-name"
                        /><span
                            v-if="form.errors.first_name"
                            class="form-error"
                            >{{ form.errors.first_name }}</span
                        ></label
                    >
                </div>
                <label
                    >Email<input
                        v-model="form.email"
                        type="email"
                        required
                        autocomplete="email"
                    /><span class="form-error">{{
                        form.errors.email
                    }}</span></label
                ><button class="button primary" :disabled="form.processing">
                    Salvează datele
                </button>
                <p
                    v-if="form.recentlySuccessful"
                    class="info-note"
                    role="status"
                >
                    Datele au fost salvate.
                </p>
            </form>
            <p
                v-if="mustVerifyEmail && !user.email_verified_at"
                class="info-note"
            >
                Adresa ta nu este confirmată.
                <Link href="/email/verify" class="text-button"
                    >Confirmă emailul</Link
                >
            </p>
            <p style="margin-top: 24px">
                <Link href="/settings/security" class="text-button"
                    >Parolă și securitatea contului</Link
                >
            </p>
            <section class="profile-agreements">
                <h2>Documentele acceptate</h2>
                <p>
                    <Link href="/acorduri" class="text-button"
                        >Consultă și actualizează acordurile</Link
                    >
                </p>
                <ul v-if="legalAcceptances.length">
                    <li
                        v-for="acceptance in legalAcceptances"
                        :key="acceptance.id"
                    >
                        <a :href="`/acorduri/${acceptance.id}`"
                            >{{
                                acceptance.purpose === 'company'
                                    ? 'Acordul clientului'
                                    : acceptance.purpose === 'checkout'
                                      ? 'Condițiile abonamentului'
                                      : 'Termenii contului'
                            }}
                            · {{ acceptance.version }} · Descarcă dovada</a
                        >
                    </li>
                </ul>
                <p v-else>
                    Contul nu are încă o acceptare înregistrată în această
                    versiune.
                </p>
                <p>
                    <Link href="/solicitari-date"
                        >Exercită un drept privind datele tale</Link
                    >
                </p>
            </section>
            <hr style="margin-top: 30px" />
            <h2 style="margin-top: 25px">Ștergerea contului</h2>
            <p style="font-size: 12px; margin-top: 12px">
                Conturile cu pontaj sau firme necesită mai întâi clarificarea
                păstrării datelor cu operatorul. Un cont fără apartenențe se
                șterge definitiv, inclusiv accesul său. Exportă datele necesare
                înainte de ștergere.
            </p>
            <form @submit.prevent="deletion.delete('/settings/profile')">
                <label
                    >Confirmă parola<input
                        v-model="deletion.password"
                        type="password"
                        required
                        autocomplete="current-password"
                    /><span class="form-error">{{
                        deletion.errors.password
                    }}</span></label
                ><button
                    class="button secondary danger-text"
                    :disabled="deletion.processing"
                >
                    Șterge definitiv contul fără apartenențe
                </button>
            </form>
        </div>
        <LegalFooter compact />
    </div>
</template>
