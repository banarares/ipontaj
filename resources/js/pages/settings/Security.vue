<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import Brand from '@/components/Brand.vue';
import NewPasswordField from '@/components/NewPasswordField.vue';
const user = usePage().props.auth.user;
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Passkey } from '@/types';
defineProps<{
    canManageTwoFactor?: boolean;
    twoFactorEnabled?: boolean;
    requiresConfirmation?: boolean;
    canManagePasskeys?: boolean;
    passkeys?: Passkey[];
}>();
const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});
</script>
<template>
    <Head title="Securitatea contului" />
    <div class="onboarding-page">
        <header class="onboarding-header">
            <Link href="/dashboard"><Brand /></Link
            ><Link href="/settings/profile" class="text-button"
                >Înapoi la cont</Link
            >
        </header>
        <div class="onboarding-form">
            <span class="eyebrow">CONTUL MEU</span>
            <h1>Parolă și securitate</h1>
            <p>Folosește o parolă unică pentru contul tău.</p>
            <form
                @submit.prevent="
                    form.put('/settings/password', {
                        onSuccess: () => form.reset(),
                    })
                "
            >
                <label
                    >Parola curentă<input
                        v-model="form.current_password"
                        type="password"
                        autocomplete="current-password"
                        required
                    /><span class="form-error">{{
                        form.errors.current_password
                    }}</span></label
                ><NewPasswordField
                    @update:model-value="form.clearErrors('password')"
                    id="change-password"
                    v-model="form.password"
                    label="Parola nouă"
                    :error="form.errors.password"
                    :user-inputs="[user.first_name, user.last_name, user.email]"
                />
                <div class="password-confirmation-field">
                    <label for="change-password-confirmation"
                        >Confirmă parola nouă</label
                    ><input
                        id="change-password-confirmation"
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                    <p
                        v-if="
                            form.password_confirmation &&
                            form.password !== form.password_confirmation
                        "
                        class="form-error"
                    >
                        Parolele nu coincid.
                    </p>
                    <p
                        v-if="form.errors.password_confirmation"
                        class="form-error"
                    >
                        {{ form.errors.password_confirmation }}
                    </p>
                </div>
                <button class="button primary" :disabled="form.processing">
                    Schimbă parola
                </button>
                <p v-if="form.recentlySuccessful" class="info-note">
                    Parola a fost schimbată.
                </p>
            </form>
            <div v-if="canManageTwoFactor" class="security-extra">
                <ManageTwoFactor
                    :two-factor-enabled="twoFactorEnabled || false"
                    :requires-confirmation="requiresConfirmation || false"
                />
            </div>
            <div v-if="canManagePasskeys" class="security-extra">
                <ManagePasskeys :passkeys="passkeys || []" />
            </div>
        </div>
    </div>
</template>
