<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import Brand from '@/components/Brand.vue';
import Icon from '@/components/UiIcon.vue';
defineProps<{
    invitation: {
        name: string;
        first_name: string;
        last_name: string;
        email: string;
        company: string;
        accepted: boolean;
    };
    token: string;
}>();
const page = usePage();
</script>
<template>
    <Head title="Invitație în echipă" />
    <div class="invite-page">
        <div class="invite-card">
            <Brand />
            <div class="step-icon" style="margin: 0 auto">
                <Icon name="users" />
            </div>
            <h1>{{ invitation.first_name }}, te așteaptă echipa.</h1>
            <p>
                Ai primit o invitație în
                <strong>{{ invitation.company }}</strong
                >. Folosește contul cu adresa
                <strong>{{ invitation.email }}</strong
                >.
            </p>
            <template v-if="page.props.auth?.user"
                ><button
                    class="button primary full"
                    @click="router.post('/invite/' + token)"
                >
                    {{
                        invitation.accepted
                            ? 'Deschide echipa'
                            : 'Acceptă invitația'
                    }}
                </button>
                <p
                    v-for="(e, k) in page.props.errors"
                    :key="k"
                    class="form-error"
                >
                    {{ e }}
                </p>
                <Link
                    href="/logout"
                    method="post"
                    as="button"
                    class="text-button"
                    style="margin-top: 20px"
                    >Schimbă contul</Link
                ></template
            >
            <div v-else class="inline-actions">
                <Link href="/register" class="button primary"
                    >Creează contul tău</Link
                ><Link href="/login" class="button secondary"
                    >Am deja cont</Link
                >
            </div>
        </div>
    </div>
</template>
