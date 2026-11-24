<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Eye, EyeOff, Check, Circle } from '@lucide/vue';
const props = withDefaults(
    defineProps<{
        id: string;
        label?: string;
        error?: string;
        userInputs?: string[];
    }>(),
    { label: 'Parola', error: '', userInputs: () => [] },
);
const model = defineModel<string>({ required: true });
const page = usePage();
const policy = computed(
    () =>
        page.props.passwordPolicy as {
            minLength: number;
            maxBytes: number;
            minScore: number;
        },
);
const visible = ref(false),
    score = ref<number | null>(null),
    evaluating = ref(false),
    unavailable = ref(false);
const byteLength = computed(() => new TextEncoder().encode(model.value).length);
const requirements = computed(() => [
    {
        label: `Cel puțin ${policy.value.minLength} caractere`,
        met: Array.from(model.value).length >= policy.value.minLength,
    },
    {
        label: 'O literă mică și o literă mare',
        met: /\p{Ll}/u.test(model.value) && /\p{Lu}/u.test(model.value),
    },
    { label: 'Cel puțin o cifră', met: /\p{N}/u.test(model.value) },
    {
        label: 'Cel puțin un simbol (ex. ! @ #)',
        met: /[\p{S}\p{P}]/u.test(model.value),
    },
]);
const compliant = computed(
    () =>
        requirements.value.every((rule) => rule.met) &&
        byteLength.value <= policy.value.maxBytes,
);
const grade = computed(() =>
    score.value === null
        ? null
        : compliant.value
          ? score.value
          : Math.min(score.value, 2),
);
const labels = [
    'Foarte slabă',
    'Slabă',
    'Acceptabilă',
    'Puternică',
    'Foarte puternică',
];
let timer: ReturnType<typeof setTimeout> | undefined;
let revision = 0;
watch(
    [model, () => JSON.stringify(props.userInputs)],
    () => {
        clearTimeout(timer);
        const version = ++revision;
        score.value = null;
        unavailable.value = false;
        if (!model.value || byteLength.value > policy.value.maxBytes) {
            evaluating.value = false;
            return;
        }
        evaluating.value = true;
        timer = setTimeout(() => {
            void import('@/lib/passwordStrength')
                .then(({ estimatePassword }) => {
                    if (version !== revision) return;
                    score.value = estimatePassword(
                        model.value,
                        props.userInputs,
                    );
                })
                .catch(() => {
                    if (version === revision) unavailable.value = true;
                })
                .finally(() => {
                    if (version === revision) evaluating.value = false;
                });
        }, 180);
    },
    { immediate: true },
);
onUnmounted(() => {
    clearTimeout(timer);
    revision++;
});
</script>
<template>
    <div class="new-password-field">
        <label :for="id">{{ label }}</label>
        <div class="password-input-wrap">
            <input
                :id="id"
                v-model="model"
                :type="visible ? 'text' : 'password'"
                autocomplete="new-password"
                required
                :minlength="policy.minLength"
                :maxlength="policy.maxBytes"
                :aria-invalid="!!error || byteLength > policy.maxBytes"
                :aria-describedby="`${id}-requirements ${id}-strength ${id}-error`"
            /><button
                type="button"
                class="password-toggle"
                :aria-label="visible ? 'Ascunde parola' : 'Arată parola'"
                :aria-pressed="visible"
                @click="visible = !visible"
            >
                <EyeOff v-if="visible" :size="19" /><Eye v-else :size="19" />
            </button>
        </div>
        <div
            class="password-strength"
            :data-grade="grade === null ? 'empty' : grade"
        >
            <div class="password-strength-heading">
                <span>Siguranța parolei</span
                ><strong
                    :id="`${id}-strength`"
                    role="status"
                    aria-live="polite"
                    >{{
                        evaluating
                            ? 'Se evaluează…'
                            : unavailable
                              ? 'Evaluare indisponibilă'
                              : grade === null
                                ? 'Introdu o parolă'
                                : labels[grade]
                    }}</strong
                >
            </div>
            <div class="password-strength-bars" aria-hidden="true">
                <span
                    v-for="n in 5"
                    :key="n"
                    :class="{ filled: grade !== null && n <= grade + 1 }"
                ></span>
            </div>
        </div>
        <ul :id="`${id}-requirements`" class="password-requirements">
            <li
                v-for="rule in requirements"
                :key="rule.label"
                :class="{ met: rule.met }"
            >
                <Check v-if="rule.met" :size="15" aria-hidden="true" /><Circle
                    v-else
                    :size="12"
                    aria-hidden="true"
                />{{ rule.label }}
            </li>
        </ul>
        <p v-if="byteLength > policy.maxBytes" class="form-error">
            Parola este prea lungă. Scurteaz-o puțin.
        </p>
        <p
            v-else-if="model && grade !== null && grade < policy.minScore"
            class="password-advice"
        >
            Alege o parolă mai lungă și mai puțin previzibilă. Țintește gradul
            „Puternică” sau „Foarte puternică”.
        </p>
        <p v-else class="hint password-advice">
            Evaluare orientativă. Evită numele, datele și parolele refolosite.
        </p>
        <p :id="`${id}-error`" v-if="error" class="form-error" role="alert">
            {{ error }}
        </p>
    </div>
</template>
