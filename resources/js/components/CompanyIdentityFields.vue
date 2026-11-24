<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import Icon from './UiIcon.vue';
const props = withDefaults(
    defineProps<{
        id: string;
        cifError?: string;
        nameError?: string;
        verifiedAt?: string | null;
        requireCif?: boolean;
        lookupEnabled?: boolean;
        showCif?: boolean;
        nameLabel?: string;
    }>(),
    {
        cifError: '',
        nameError: '',
        verifiedAt: null,
        requireCif: true,
        lookupEnabled: true,
        showCif: true,
        nameLabel: 'Numele firmei',
    },
);
const cif = defineModel<string>('cif', { required: true });
const name = defineModel<string>('name', { required: true });
const canonical = computed(() =>
    cif.value.replace(/\s+/gu, '').replace(/^ro/iu, ''),
);
const loading = ref(false),
    message = ref('');
const record = ref<{ cif: string; name: string; verified_at: string } | null>(
    props.verifiedAt
        ? {
              cif: canonical.value,
              name: name.value,
              verified_at: props.verifiedAt,
          }
        : null,
);
const verified = computed(
    () =>
        !!record.value &&
        record.value.cif === canonical.value &&
        record.value.name === name.value,
);
let version = 0;
let pending: AbortController | undefined;
watch(canonical, () => {
    version++;
    pending?.abort();
    loading.value = false;
    message.value = '';
});
watch(
    () => props.verifiedAt,
    (value) => {
        if (value)
            record.value = {
                cif: canonical.value,
                name: name.value,
                verified_at: value,
            };
    },
);
onUnmounted(() => {
    version++;
    pending?.abort();
});
async function lookup() {
    if (!props.lookupEnabled || loading.value) return;
    if (!/^[1-9]\d{1,9}$/u.test(canonical.value)) {
        message.value =
            'Introdu un CIF de 2–10 cifre, cu sau fără prefixul RO.';
        return;
    }
    const current = ++version;
    pending?.abort();
    pending = new AbortController();
    loading.value = true;
    message.value = '';
    try {
        const response = await fetch(
            `/company-lookup?cif=${encodeURIComponent(cif.value)}`,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
                signal: pending.signal,
            },
        );
        const data = (await response.json()) as {
            status?: string;
            cif?: string;
            name?: string;
            verified_at?: string;
            message?: string;
            errors?: { cif?: string[] };
        };
        if (current !== version) return;
        if (
            response.ok &&
            data.status === 'found' &&
            typeof data.cif === 'string' &&
            typeof data.name === 'string' &&
            typeof data.verified_at === 'string'
        ) {
            cif.value = data.cif;
            name.value = data.name;
            record.value = {
                cif: data.cif,
                name: data.name,
                verified_at: data.verified_at,
            };
        } else {
            record.value = null;
            message.value =
                response.status === 401
                    ? 'Sesiunea a expirat. Intră din nou în cont pentru a căuta firma.'
                    : data.errors?.cif?.[0] ||
                      (response.status === 429
                          ? 'Prea multe căutări. Așteaptă puțin și încearcă din nou.'
                          : data.message ||
                            'Căutarea nu este disponibilă. Poți completa denumirea manual.');
        }
    } catch {
        if (current === version) {
            record.value = null;
            message.value =
                'Nu am putut contacta serviciul de căutare. Verifică legătura la internet sau completează denumirea manual.';
        }
    } finally {
        if (current === version) loading.value = false;
    }
}
</script>
<template>
    <div class="company-identity-fields">
        <div v-if="showCif" class="identity-field">
            <label :for="`${id}-cif`"
                >CIF
                <span v-if="!requireCif" class="muted">(opțional)</span></label
            >
            <div class="company-cif-row">
                <input
                    :id="`${id}-cif`"
                    v-model="cif"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="20"
                    :required="requireCif"
                    :disabled="!lookupEnabled"
                    placeholder="Ex. 12345678"
                    :aria-invalid="!!cifError"
                    :aria-describedby="`${id}-cif-help ${id}-cif-error`"
                    @keydown.enter.prevent="lookup"
                /><button
                    type="button"
                    class="button secondary"
                    :disabled="loading || !lookupEnabled || !canonical"
                    :aria-busy="loading"
                    @click="lookup"
                >
                    <Icon :name="loading ? 'refresh' : 'search'" :size="17" />{{
                        loading ? 'Se caută…' : 'Caută firma'
                    }}
                </button>
            </div>
            <p :id="`${id}-cif-help`" class="hint">
                {{
                    lookupEnabled
                        ? 'Acceptăm și prefixul RO. Căutarea completează denumirea din ANAF.'
                        : 'Firma demo este fictivă. Căutarea este disponibilă în contul propriu.'
                }}
            </p>
            <p
                :id="`${id}-cif-error`"
                v-if="cifError"
                class="form-error"
                role="alert"
            >
                {{ cifError }}
            </p>
        </div>
        <div v-if="message" class="identity-notice" role="status">
            <Icon name="alert" :size="17" />
            <p>{{ message }}</p>
        </div>
        <div class="identity-field">
            <label :for="`${id}-name`">{{ nameLabel }}</label
            ><input
                :id="`${id}-name`"
                v-model="name"
                required
                maxlength="255"
                autocomplete="organization"
                placeholder="Denumirea firmei"
                :aria-invalid="!!nameError"
                :aria-describedby="`${id}-name-source ${id}-name-error`"
            />
            <p
                :id="`${id}-name-error`"
                v-if="nameError"
                class="form-error"
                role="alert"
            >
                {{ nameError }}
            </p>
            <p
                v-if="showCif"
                :id="`${id}-name-source`"
                class="identity-source"
                :class="{ verified }"
                role="status"
            >
                <Icon
                    :name="verified ? 'success' : 'edit'"
                    :size="15"
                /><template v-if="verified && record"
                    >Preluat din ANAF ·
                    {{
                        new Date(record.verified_at).toLocaleDateString(
                            'ro-RO',
                            { timeZone: 'Europe/Bucharest' },
                        )
                    }}</template
                ><template v-else
                    >Denumirea poate fi completată manual.
                    {{
                        canonical
                            ? 'Datele nu sunt confirmate de ANAF.'
                            : 'Introdu CIF-ul pentru a verifica firma.'
                    }}</template
                >
            </p>
        </div>
    </div>
</template>
