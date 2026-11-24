<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Brand from './Brand.vue';
import LegalFooter from './LegalFooter.vue';
const footerVisible = computed(
    () => !['auth/Login', 'auth/Register'].includes(usePage().component),
);
</script>
<template>
    <div class="auth-page">
        <aside class="auth-story">
            <Link href="/"><Brand /></Link>
            <div>
                <span class="eyebrow">TIMP PENTRU CE CONTEAZĂ</span>
                <h2>
                    O echipă la zi.<br />Un final de lună<br />mai liniștit.
                </h2>
                <p>
                    De la prima confirmare până la Excelul pentru contabil,
                    toate orele își găsesc locul.
                </p>
            </div>
            <span>iPontaj · Făcut pentru echipe mici.</span>
        </aside>
        <main class="auth-main">
            <div class="auth-form">
                <Link href="/" class="brand"><Brand /></Link><slot />
                <LegalFooter v-if="footerVisible" compact />
            </div>
        </main>
    </div>
</template>
