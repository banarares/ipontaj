<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AuthShell from '@/components/AuthShell.vue';
defineProps<{ status?: string; canResend: boolean }>();
</script>
<template>
    <Head title="Confirmă adresa de email" /><AuthShell
        ><h1>Confirmă adresa de email</h1>
        <p>
            Deschide linkul primit prin email pentru a confirma că adresa îți
            aparține. După confirmare te vei putea autentifica.
        </p>
        <p v-if="status === 'verification-link-sent'" class="info-note">
            Am trimis un nou link de confirmare.
        </p>
        <button
            v-if="canResend"
            class="button primary full"
            @click="router.post('/email/verification-notification')"
        >
            Retrimite emailul
        </button>
        <p class="auth-switch">
            <Link href="/login">Înapoi la autentificare</Link>
        </p></AuthShell
    >
</template>
