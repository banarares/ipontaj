<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Icon from './UiIcon.vue';
const props = defineProps<{ withdrawal: boolean }>();
const page = usePage();
const review = ref(false);
const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    contract_reference: '',
    message: '',
    request_type: 'access',
    confirmed: false,
});
const receipt = computed(
    () =>
        (
            page.props.flash as {
                legal_request?: {
                    reference: string;
                    received_at: string;
                    receipt_url: string;
                };
            }
        )?.legal_request,
);
function submit() {
    form.confirmed = true;
    form.post(props.withdrawal ? '/retragere' : '/solicitari-date', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            review.value = false;
        },
    });
}
</script>
<template>
    <section class="legal-request-form" aria-labelledby="request-form-title">
        <h2 id="request-form-title">
            {{
                withdrawal
                    ? 'Declarația de retragere'
                    : 'Trimite o solicitare privind datele'
            }}
        </h2>
        <div v-if="receipt" class="legal-request-receipt" role="status">
            <Icon name="success" :size="24" />
            <div>
                <strong>Solicitarea a fost înregistrată.</strong>
                <p>Referință: {{ receipt.reference }}</p>
                <p>
                    {{
                        new Date(receipt.received_at).toLocaleString('ro-RO', {
                            timeZone: 'Europe/Bucharest',
                        })
                    }}
                </p>
                <a :href="receipt.receipt_url" class="text-button"
                    >Descarcă dovada primirii</a
                >
                <p>
                    Confirmarea prin email poate întârzia. Dovada de mai sus
                    este disponibilă imediat.
                </p>
            </div>
        </div>
        <form v-else @submit.prevent="review ? submit() : (review = true)">
            <div class="person-name-fields">
                <label for="legal-request-last-name"
                    >Nume<input
                        id="legal-request-last-name"
                        v-model="form.last_name"
                        required
                        maxlength="100"
                        autocomplete="family-name"
                        :readonly="review"
                    /><span v-if="form.errors.last_name" class="form-error">{{
                        form.errors.last_name
                    }}</span></label
                >
                <label for="legal-request-first-name"
                    >Prenume<input
                        id="legal-request-first-name"
                        v-model="form.first_name"
                        required
                        maxlength="100"
                        autocomplete="given-name"
                        :readonly="review"
                    /><span v-if="form.errors.first_name" class="form-error">{{
                        form.errors.first_name
                    }}</span></label
                >
            </div>
            <label for="legal-request-email"
                >Email pentru confirmare<input
                    id="legal-request-email"
                    v-model="form.email"
                    required
                    type="email"
                    maxlength="254"
                    autocomplete="email"
                    :readonly="review"
                /><span v-if="form.errors.email" class="form-error">{{
                    form.errors.email
                }}</span></label
            >
            <label v-if="withdrawal" for="legal-request-contract"
                >Contractul din care te retragi<input
                    id="legal-request-contract"
                    v-model="form.contract_reference"
                    required
                    maxlength="200"
                    :readonly="review"
                    placeholder="Emailul contului și data comenzii sau referința abonamentului"
                /><span
                    v-if="form.errors.contract_reference"
                    class="form-error"
                    >{{ form.errors.contract_reference }}</span
                ></label
            >
            <label v-else for="legal-request-type"
                >Ce dorești să soliciți<select
                    id="legal-request-type"
                    v-model="form.request_type"
                    :disabled="review"
                >
                    <option value="access">Acces la date</option>
                    <option value="rectification">Rectificare</option>
                    <option value="erasure">Ștergere</option>
                    <option value="restriction">Restricționare</option>
                    <option value="portability">Portabilitate</option>
                    <option value="objection">Opoziție</option>
                    <option value="other">
                        Altă solicitare privind datele
                    </option>
                </select></label
            >
            <label v-if="!withdrawal" for="legal-request-message"
                >Detalii strict necesare<textarea
                    id="legal-request-message"
                    v-model="form.message"
                    maxlength="2000"
                    rows="4"
                    :readonly="review"
                /><span v-if="form.errors.message" class="form-error">{{
                    form.errors.message
                }}</span></label
            >
            <p class="hint">
                Nu trimite CNP, parole, date complete de card sau documente
                medicale. Folosim datele pentru gestionarea solicitării.
            </p>
            <p v-if="review" class="info-note">
                {{
                    withdrawal
                        ? 'Verifică datele. Prin confirmare comunici decizia ta de retragere din contractul indicat.'
                        : 'Verifică adresa de răspuns și detaliile înainte de trimitere.'
                }}
            </p>
            <p v-if="form.errors.confirmed" class="form-error" role="alert">
                {{ form.errors.confirmed }}
            </p>
            <div class="legal-form-actions">
                <button
                    type="submit"
                    class="button primary"
                    :disabled="form.processing"
                >
                    {{
                        form.processing
                            ? 'Se înregistrează…'
                            : review
                              ? withdrawal
                                  ? 'Confirmă retragerea'
                                  : 'Confirmă trimiterea'
                              : 'Verifică solicitarea'
                    }}</button
                ><button
                    v-if="review"
                    type="button"
                    class="button secondary"
                    @click="review = false"
                >
                    Corectează datele
                </button>
            </div>
        </form>
    </section>
</template>
