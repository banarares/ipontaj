<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthShell from '@/components/AuthShell.vue';
import NewPasswordField from '@/components/NewPasswordField.vue';
import TurnstileWidget from '@/components/TurnstileWidget.vue';
import { ref } from 'vue';
const page = usePage();
const challenge = ref<InstanceType<typeof TurnstileWidget> | null>(null);
const form = useForm({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms_accepted: false,
    legal_version: usePage().props.legal.version,
    turnstile_token: '',
});
</script>
<template>
    <Head title="Creează cont gratuit" /><AuthShell
        ><span class="eyebrow">UN ÎNCEPUT MAI SIMPLU</span>
        <h1 style="margin-top: 12px">Bine ai venit în iPontaj</h1>
        <p>Creează contul tău. Configurezi firma imediat după.</p>
        <form
            @submit.prevent="
                form.post('/register', {
                    onSuccess: () =>
                        form.reset('password', 'password_confirmation'),
                    onFinish: () => challenge?.reset(),
                })
            "
        >
            <div class="person-name-fields">
                <label for="register-last-name"
                    >Nume<input
                        id="register-last-name"
                        v-model="form.last_name"
                        autocomplete="family-name"
                        required
                        autofocus
                        maxlength="100"
                        placeholder="Ex. Popescu"
                    /><span v-if="form.errors.last_name" class="form-error">{{
                        form.errors.last_name
                    }}</span></label
                ><label for="register-first-name"
                    >Prenume<input
                        id="register-first-name"
                        v-model="form.first_name"
                        autocomplete="given-name"
                        required
                        maxlength="100"
                        placeholder="Ex. Andrei"
                    /><span v-if="form.errors.first_name" class="form-error">{{
                        form.errors.first_name
                    }}</span></label
                >
            </div>
            <label for="register-email"
                >Adresa de email<input
                    id="register-email"
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    placeholder="nume@firma.ro"
                /><span v-if="form.errors.email" class="form-error">{{
                    form.errors.email
                }}</span></label
            >
            <NewPasswordField
                @update:model-value="form.clearErrors('password')"
                id="register-password"
                v-model="form.password"
                :error="form.errors.password"
                :user-inputs="[form.first_name, form.last_name, form.email]"
            />
            <div class="password-confirmation-field">
                <label for="register-password-confirmation"
                    >Confirmă parola</label
                ><input
                    id="register-password-confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    :aria-invalid="
                        !!form.password_confirmation &&
                        form.password !== form.password_confirmation
                    "
                    aria-describedby="register-password-confirmation-error"
                />
                <p
                    id="register-password-confirmation-error"
                    class="form-error"
                    v-if="
                        form.password_confirmation &&
                        form.password !== form.password_confirmation
                    "
                >
                    Parolele nu coincid.
                </p>
                <p v-if="form.errors.password_confirmation" class="form-error">
                    {{ form.errors.password_confirmation }}
                </p>
            </div>
            <label class="checkbox-label legal-consent"
                ><input
                    v-model="form.terms_accepted"
                    type="checkbox"
                    required
                /><span
                    >Accept
                    <Link href="/termeni-si-conditii" target="_blank"
                        >Termenii și condițiile</Link
                    >.</span
                ></label
            >
            <p
                v-if="form.errors.terms_accepted"
                class="form-error"
                role="alert"
            >
                {{ form.errors.terms_accepted }}
            </p>
            <p class="hint legal-privacy-notice">
                Vezi cum folosim datele în
                <Link href="/confidentialitate" target="_blank"
                    >informarea de confidențialitate</Link
                >. Nu te înscriem la marketing.
            </p>
            <p v-if="form.errors.legal_version" class="form-error" role="alert">
                Documentele s-au actualizat. Reîncarcă pagina pentru a consulta
                versiunea curentă.
            </p>
            <TurnstileWidget
                @update:model-value="
                    (value) => value && form.clearErrors('turnstile_token')
                "
                ref="challenge"
                v-model="form.turnstile_token"
                action="register"
                :error="form.errors.turnstile_token"
            />
            <button
                class="button primary full"
                :disabled="
                    form.processing ||
                    (page.props.turnstile.enabled && !form.turnstile_token)
                "
            >
                {{
                    form.processing
                        ? 'Se creează contul…'
                        : 'Creează contul gratuit'
                }}
            </button>
        </form>
        <p class="auth-switch">
            Ai deja un cont? <Link href="/login">Intră în cont</Link>
        </p></AuthShell
    >
</template>
