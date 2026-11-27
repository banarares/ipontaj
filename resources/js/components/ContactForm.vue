<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { track } from '@/lib/analytics';
import TurnstileWidget from './TurnstileWidget.vue';
const page = usePage();
const reference = computed(
    () =>
        (page.props.flash as { contact_reference?: string }).contact_reference,
);
const challenge = ref<InstanceType<typeof TurnstileWidget> | null>(null);
const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    subject: '',
    message: '',
    website: '',
    turnstile_token: '',
});
function send() {
    form.post('/contact', {
        preserveScroll: true,
        onSuccess: () => {
            if (reference.value)
                track('generate_lead', { form_name: 'contact' });
            form.reset();
        },
        onFinish: () => challenge.value?.reset(),
    });
}
</script>
<template>
    <section class="contact-form" aria-labelledby="contact-form-title">
        <h2 id="contact-form-title">Trimite-ne un mesaj</h2>
        <div v-if="reference" class="contact-success" role="status">
            <strong>Mesajul tău a fost înregistrat.</strong>
            <p>
                Îți răspundem la adresa de email indicată. Referință:
                {{ reference }}.
            </p>
        </div>
        <form @submit.prevent="send">
            <div class="person-name-fields">
                <label
                    >Nume<input
                        v-model="form.last_name"
                        autocomplete="family-name"
                        maxlength="100"
                        required
                    /><span v-if="form.errors.last_name" class="form-error">{{
                        form.errors.last_name
                    }}</span></label
                >
                <label
                    >Prenume<input
                        v-model="form.first_name"
                        autocomplete="given-name"
                        maxlength="100"
                        required
                    /><span v-if="form.errors.first_name" class="form-error">{{
                        form.errors.first_name
                    }}</span></label
                >
            </div>
            <label
                >Email pentru răspuns<input
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    maxlength="254"
                    required
                /><span v-if="form.errors.email" class="form-error">{{
                    form.errors.email
                }}</span></label
            >
            <label
                >Subiect<input
                    v-model="form.subject"
                    maxlength="160"
                    required
                /><span v-if="form.errors.subject" class="form-error">{{
                    form.errors.subject
                }}</span></label
            >
            <label
                >Mesaj<textarea
                    v-model="form.message"
                    rows="6"
                    minlength="10"
                    maxlength="5000"
                    required
                /><span v-if="form.errors.message" class="form-error">{{
                    form.errors.message
                }}</span></label
            >
            <div class="contact-honeypot" aria-hidden="true">
                <label
                    >Website<input
                        v-model="form.website"
                        tabindex="-1"
                        autocomplete="off"
                /></label>
            </div>
            <p class="hint">
                Folosim datele pentru a răspunde solicitării, conform
                <a href="/confidentialitate">politicii de confidențialitate</a>.
                Nu trimite parole, CNP, documente medicale sau date complete de
                card.
            </p>
            <TurnstileWidget
                @update:model-value="
                    (value) => value && form.clearErrors('turnstile_token')
                "
                ref="challenge"
                v-model="form.turnstile_token"
                action="contact"
                :error="form.errors.turnstile_token"
            />
            <button
                class="button primary"
                :disabled="
                    form.processing ||
                    (page.props.turnstile.enabled && !form.turnstile_token)
                "
            >
                {{ form.processing ? 'Se trimite…' : 'Trimite mesajul' }}
            </button>
        </form>
    </section>
</template>
