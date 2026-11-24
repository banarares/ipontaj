<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthShell from '@/components/AuthShell.vue';
const recovery = ref(false);
const form = useForm({ code: '', recovery_code: '' });
</script>
<template>
    <Head title="Verificare în doi pași" /><AuthShell
        ><h1>Încă un pas pentru siguranță</h1>
        <p>
            {{
                recovery
                    ? 'Introdu un cod de recuperare.'
                    : 'Introdu codul din aplicația ta de autentificare.'
            }}
        </p>
        <form
            @submit.prevent="
                form.post('/two-factor-challenge', {
                    onFinish: () => form.reset(),
                })
            "
        >
            <label v-if="recovery"
                >Cod de recuperare<input
                    v-model="form.recovery_code"
                    autocomplete="one-time-code"
                    required /></label
            ><label v-else
                >Cod de autentificare<input
                    v-model="form.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    required
                    maxlength="6"
            /></label>
            <p v-for="(e, k) in form.errors" :key="k" class="form-error">
                {{ e }}
            </p>
            <button class="button primary full" :disabled="form.processing">
                Verifică și continuă</button
            ><button
                type="button"
                class="text-button"
                style="margin-top: 20px"
                @click="
                    recovery = !recovery;
                    form.reset();
                "
            >
                {{
                    recovery
                        ? 'Folosește codul de autentificare'
                        : 'Folosește un cod de recuperare'
                }}
            </button>
        </form></AuthShell
    >
</template>
