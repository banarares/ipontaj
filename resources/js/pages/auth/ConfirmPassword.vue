<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthShell from '@/components/AuthShell.vue';
const form = useForm({ password: '' });
</script>
<template>
    <Head title="Confirmă parola" /><AuthShell
        ><h1>Confirmă parola</h1>
        <p>Pentru siguranța contului, confirmă parola înainte de a continua.</p>
        <form
            @submit.prevent="
                form.post('/user/confirm-password', {
                    onFinish: () => form.reset(),
                })
            "
        >
            <label
                >Parola<input
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    autofocus
                /><span class="form-error">{{
                    form.errors.password
                }}</span></label
            ><button class="button primary full" :disabled="form.processing">
                Confirmă și continuă
            </button>
        </form></AuthShell
    >
</template>
