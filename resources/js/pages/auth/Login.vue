<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthShell from '@/components/AuthShell.vue';
defineProps<{ status?: string; canResetPassword?: boolean }>();
const form = useForm({ email: '', password: '', remember: true });
</script>
<template>
    <Head title="Intră în cont" /><AuthShell
        ><span class="eyebrow">BINE AI REVENIT</span>
        <h1 style="margin-top: 12px">Intră în contul tău</h1>
        <p>Echipa ta e la un pas distanță.</p>
        <p v-if="status" class="info-note">{{ status }}</p>
        <form
            @submit.prevent="
                form.post('/login', { onFinish: () => form.reset('password') })
            "
        >
            <label
                >Adresa de email<input
                    v-model="form.email"
                    type="email"
                    autocomplete="username"
                    required
                    autofocus
                    placeholder="nume@firma.ro"
                /><span v-if="form.errors.email" class="form-error">{{
                    form.errors.email
                }}</span></label
            ><label
                >Parola<input
                    v-model="form.password"
                    type="password"
                    autocomplete="current-password"
                    required
                /><span v-if="form.errors.password" class="form-error">{{
                    form.errors.password
                }}</span></label
            >
            <div class="flex-between">
                <label class="checkbox-label"
                    ><input v-model="form.remember" type="checkbox" />Ține-mă
                    minte</label
                ><Link
                    href="/forgot-password"
                    class="text-button"
                    style="margin-top: 20px"
                    >Ai uitat parola?</Link
                >
            </div>
            <button class="button primary full" :disabled="form.processing">
                {{ form.processing ? 'Se autentifică…' : 'Intră în cont' }}
            </button>
        </form>
        <p class="auth-switch">
            Nu ai încă un cont? <Link href="/register">Începe gratuit</Link>
        </p></AuthShell
    >
</template>
