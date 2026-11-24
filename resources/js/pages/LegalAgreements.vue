<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Brand from '@/components/Brand.vue';
import LegalFooter from '@/components/LegalFooter.vue';
const props = defineProps<{
    companies: { id: number; name: string; customer_type: string }[];
}>();
const form = useForm({
    terms_accepted: false,
    dpa_accepted: false,
    company_id: props.companies[0]?.id || null,
    legal_version: usePage().props.legal.version,
});
const selectedCompany = computed(() =>
    props.companies.find((company) => company.id === Number(form.company_id)),
);
</script>
<template>
    <Head title="Documentele contului" />
    <div class="onboarding-page">
        <header class="onboarding-header">
            <Link href="/"><Brand /></Link
            ><Link href="/settings/profile">Contul meu</Link>
        </header>
        <form class="onboarding-form" @submit.prevent="form.post('/acorduri')">
            <span class="eyebrow">DOCUMENTELE CONTULUI</span>
            <h1>Condiții clare, la îndemână.</h1>
            <p>
                Consultă documentele înainte de acceptare. Dovada și textul
                versiunii acceptate sunt disponibile ulterior în Contul meu.
            </p>
            <label class="checkbox-label"
                ><input
                    v-model="form.terms_accepted"
                    type="checkbox"
                    required
                /><span
                    >Accept
                    <Link href="/termeni-si-conditii" target="_blank"
                        >Termenii și condițiile</Link
                    >. Am la dispoziție
                    <Link href="/confidentialitate" target="_blank"
                        >informarea de confidențialitate</Link
                    >.</span
                ></label
            >
            <p v-if="form.errors.terms_accepted" class="form-error">
                {{ form.errors.terms_accepted }}
            </p>
            <template v-if="companies.length"
                ><label
                    >Firma / spațiul pentru care accepți acordul<select
                        v-model="form.company_id"
                    >
                        <option
                            v-for="company in companies"
                            :key="company.id"
                            :value="company.id"
                        >
                            {{ company.name }}
                        </option>
                    </select></label
                ><label
                    v-if="selectedCompany?.customer_type !== 'consumer'"
                    class="checkbox-label"
                    ><input
                        v-model="form.dpa_accepted"
                        type="checkbox"
                        required
                    /><span
                        >Sunt autorizat să reprezint clientul și accept
                        <Link href="/acord-prelucrare-date" target="_blank"
                            >acordul de prelucrare a datelor</Link
                        >
                        pentru prelucrările în numele său și
                        <Link href="/furnizori" target="_blank"
                            >lista furnizorilor</Link
                        >. Acordul se aplică potrivit rolurilor reale.</span
                    ></label
                >
                <p v-if="form.errors.dpa_accepted" class="form-error">
                    {{ form.errors.dpa_accepted }}
                </p></template
            >
            <p v-if="form.errors.legal_version" class="form-error">
                Documentele au fost actualizate. Reîncarcă pagina și consultă
                versiunea curentă.
            </p>
            <button class="button primary full" :disabled="form.processing">
                Înregistrează acceptarea
            </button>
        </form>
        <LegalFooter compact />
    </div>
</template>
