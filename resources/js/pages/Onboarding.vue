<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Brand from '@/components/Brand.vue';
import Icon from '@/components/UiIcon.vue';
import CompanyIdentityFields from '@/components/CompanyIdentityFields.vue';
import LegalFooter from '@/components/LegalFooter.vue';
const form = useForm({
    name: '',
    cif: '',
    timezone: 'Europe/Bucharest',
    start_time: '09:00',
    end_time: '17:00',
    include_owner: true,
    schedule_confirmed: false,
    customer_type: 'business',
    dpa_accepted: false,
    legal_version: usePage().props.legal.version,
});
</script>
<template>
    <Head title="Configurează firma" />
    <div class="onboarding-page">
        <header class="onboarding-header">
            <Link href="/"><Brand /></Link
            ><Link href="/logout" method="post" as="button" class="text-button"
                >Ieși din cont</Link
            >
        </header>
        <form class="onboarding-form" @submit.prevent="form.post('/companies')">
            <span class="eyebrow">BINE AI VENIT ÎN ECHIPĂ</span>
            <h1>
                {{
                    form.customer_type === 'consumer'
                        ? 'Să pregătim spațiul tău.'
                        : 'Să pregătim firma ta.'
                }}
            </h1>
            <p>
                Începem cu programul obișnuit. Colegii vor confirma zilnic orele
                efectiv lucrate.
            </p>
            <div class="onboarding-customer-field">
                <label for="customer-type">Folosesc iPontaj</label
                ><select id="customer-type" v-model="form.customer_type">
                    <option value="business">
                        Pentru o firmă / PFA, în scop profesional
                    </option>
                    <option value="consumer">
                        Ca persoană fizică, în scop personal
                    </option>
                </select>
            </div>
            <CompanyIdentityFields
                id="onboarding-company"
                v-model:cif="form.cif"
                v-model:name="form.name"
                :cif-error="form.errors.cif"
                :name-error="form.errors.name"
                :show-cif="form.customer_type === 'business'"
                :name-label="
                    form.customer_type === 'consumer'
                        ? 'Numele spațiului'
                        : 'Numele firmei'
                "
                :require-cif="form.customer_type === 'business'"
                @update:cif="form.clearErrors('cif')"
                @update:name="form.clearErrors('name')"
            />
            <label
                >Fusul orar<select v-model="form.timezone">
                    <option>Europe/Bucharest</option>
                    <option>Europe/Chisinau</option>
                    <option>Europe/London</option>
                    <option>Europe/Berlin</option>
                    <option>UTC</option></select
                ><span class="form-error">{{
                    form.errors.timezone
                }}</span></label
            >
            <fieldset>
                <legend>Program propus · luni–vineri</legend>
                <div class="two-columns">
                    <label
                        >Început<input
                            v-model="form.start_time"
                            type="time"
                            required
                        /><span class="form-error">{{
                            form.errors.start_time
                        }}</span></label
                    ><label
                        >Sfârșit<input
                            v-model="form.end_time"
                            type="time"
                            required
                        /><span class="form-error">{{
                            form.errors.end_time
                        }}</span></label
                    >
                </div>
            </fieldset>
            <label class="checkbox-label"
                ><input v-model="form.include_owner" type="checkbox" />Sunt
                inclus și eu în pontajul firmei.</label
            ><label class="checkbox-label"
                ><input
                    v-model="form.schedule_confirmed"
                    type="checkbox"
                    required
                />Confirm programul propus. Îl pot adapta ulterior pentru echipă
                sau pentru fiecare coleg.</label
            >
            <p class="form-error">{{ form.errors.schedule_confirmed }}</p>
            <label
                v-if="form.customer_type === 'business'"
                class="checkbox-label legal-consent"
                ><input
                    v-model="form.dpa_accepted"
                    type="checkbox"
                    required
                /><span
                    >Sunt autorizat să reprezint firma și accept
                    <Link href="/acord-prelucrare-date" target="_blank"
                        >acordul de prelucrare a datelor</Link
                    >
                    și
                    <Link href="/furnizori" target="_blank"
                        >lista furnizorilor</Link
                    >.</span
                ></label
            >
            <p v-if="form.errors.dpa_accepted" class="form-error" role="alert">
                {{ form.errors.dpa_accepted }}
            </p>
            <p v-if="form.errors.legal_version" class="form-error" role="alert">
                Documentele s-au actualizat. Reîncarcă pagina și consultă
                versiunea curentă.
            </p>
            <p class="info-note">
                <Icon name="shield" :size="18" />Nu avem nevoie de CNP. Adaugi
                colegii în pasul următor, prin invitații personale.
            </p>
            <button class="button primary full" :disabled="form.processing">
                <Icon name="check" :size="18" />{{
                    form.processing
                        ? 'Se pregătește firma…'
                        : 'Creează firma și continuă'
                }}
            </button>
        </form>
        <LegalFooter compact />
    </div>
</template>
