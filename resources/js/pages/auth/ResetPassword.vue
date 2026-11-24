<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthShell from '@/components/AuthShell.vue';
import NewPasswordField from '@/components/NewPasswordField.vue';
const props = defineProps<{ email: string; token: string }>();
const form = useForm({
    email: props.email,
    token: props.token,
    password: '',
    password_confirmation: '',
});
</script>
<template>
    <Head title="Alege o parolă nouă" /><AuthShell
        ><h1>Alege o parolă nouă</h1>
        <p>Îți securizăm din nou accesul la echipă.</p>
        <form
            @submit.prevent="
                form.post('/reset-password', {
                    onSuccess: () =>
                        form.reset('password', 'password_confirmation'),
                })
            "
        >
            <label for="reset-email"
                >Email<input
                    id="reset-email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                /><span v-if="form.errors.email" class="form-error">{{
                    form.errors.email
                }}</span></label
            ><NewPasswordField
                @update:model-value="form.clearErrors('password')"
                id="reset-password"
                v-model="form.password"
                label="Parola nouă"
                :error="form.errors.password"
                :user-inputs="[form.email]"
            />
            <div class="password-confirmation-field">
                <label for="reset-confirmation">Confirmă parola</label
                ><input
                    id="reset-confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    :aria-invalid="
                        !!form.password_confirmation &&
                        form.password !== form.password_confirmation
                    "
                    aria-describedby="reset-confirmation-error"
                />
                <p
                    id="reset-confirmation-error"
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
            <button class="button primary full" :disabled="form.processing">
                {{ form.processing ? 'Se salvează…' : 'Salvează parola' }}
            </button>
        </form></AuthShell
    >
</template>
