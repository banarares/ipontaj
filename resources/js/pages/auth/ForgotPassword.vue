<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthShell from '@/components/AuthShell.vue';
defineProps<{ status?: string }>();
const form = useForm({ email: '' });
</script>
<template>
    <Head title="Recuperează parola" /><AuthShell
        ><h1>Ai uitat parola?</h1>
        <p>Îți trimitem prin email un link securizat pentru resetare.</p>
        <p v-if="status" class="info-note">{{ status }}</p>
        <form @submit.prevent="form.post('/forgot-password')">
            <label
                >Adresa de email<input
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="email"
                    autofocus
                /><span class="form-error">{{ form.errors.email }}</span></label
            ><button class="button primary full" :disabled="form.processing">
                Trimite linkul de resetare
            </button>
        </form>
        <p class="auth-switch">
            <Link href="/login">Înapoi la autentificare</Link>
        </p></AuthShell
    >
</template>
