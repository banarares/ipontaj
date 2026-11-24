<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Brand from './Brand.vue';
import LegalFooter from './LegalFooter.vue';
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
                <LegalFooter compact />
            </div>
        </main>
    </div>
</template>
