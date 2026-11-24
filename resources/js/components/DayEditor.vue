<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { Company, Member, Entry, WorkSchedule } from '@/types/ipontaj';
import { candidates, localDateTime, nextDate, readableDate } from '@/lib/dates';
import Icon from './UiIcon.vue';
const props = defineProps<{
    company: Company;
    member: Member;
    date: string;
    entry?: Entry | null;
    schedule?: WorkSchedule | null;
    owner: boolean;
    absences: Record<string, string>;
}>();
const emit = defineEmits<{ close: [] }>();
const start = props.schedule?.start_time || '09:00';
const end = props.schedule?.end_time || '17:00';
const rows = ref(
    props.entry?.intervals.length
        ? props.entry.intervals.map((i) => ({
              kind: i.kind,
              start: localDateTime(i.start_at, props.company.timezone),
              end: localDateTime(i.end_at, props.company.timezone),
              startChoice:
                  candidates(
                      localDateTime(i.start_at, props.company.timezone),
                      props.company.timezone,
                  ).find((c) => Date.parse(c) === Date.parse(i.start_at)) || '',
              endChoice:
                  candidates(
                      localDateTime(i.end_at, props.company.timezone),
                      props.company.timezone,
                  ).find((c) => Date.parse(c) === Date.parse(i.end_at)) || '',
          }))
        : [
              {
                  kind: 'work' as 'work' | 'break',
                  start: props.date + 'T' + start,
                  end:
                      (end <= start ? nextDate(props.date) : props.date) +
                      'T' +
                      end,
                  startChoice: '',
                  endChoice: '',
              },
          ],
);
const form = useForm({
    membership_id: props.member.id,
    date: props.date,
    absence: props.entry?.absence || '',
    note: props.entry?.note || '',
    reason: '',
    intervals: [] as { kind: string; start_at: string; end_at: string }[],
});
const localError = ref('');
const correction = computed(
    () => props.owner && (!!props.entry || props.member.role !== 'owner'),
);
function save() {
    localError.value = '';
    if (!form.absence) {
        const intervals = [];
        for (const r of rows.value) {
            const starts = candidates(r.start, props.company.timezone),
                ends = candidates(r.end, props.company.timezone);
            const a =
                starts.length === 1
                    ? starts[0]
                    : starts.find((c) => c === r.startChoice);
            const b =
                ends.length === 1
                    ? ends[0]
                    : ends.find((c) => c === r.endChoice);
            if (!a || !b) {
                localError.value =
                    'Verifică orele. La schimbarea orei, selectează offsetul; orele inexistente trebuie corectate.';
                return;
            }
            intervals.push({ kind: r.kind, start_at: a, end_at: b });
        }
        form.intervals = intervals;
    } else form.intervals = [];
    form.transform((data) => ({ ...data, absence: data.absence || null })).post(
        '/entries',
        { preserveScroll: true, onSuccess: () => emit('close') },
    );
}
const dialog = ref<HTMLDialogElement>();
onMounted(() => dialog.value?.showModal());
onUnmounted(() => dialog.value?.close());
</script>
<template>
    <dialog
        ref="dialog"
        class="modal"
        @cancel.prevent="emit('close')"
        @click="
            (e) => {
                if (e.target === dialog) emit('close');
            }
        "
    >
        <form @submit.prevent="save">
            <div class="modal-heading">
                <div>
                    <span class="eyebrow">PONTAJ ZILNIC</span>
                    <h2>
                        {{
                            readableDate(date, {
                                day: 'numeric',
                                month: 'long',
                                year: 'numeric',
                            })
                        }}
                    </h2>
                    <p>{{ member.user.name }} · {{ company.timezone }}</p>
                </div>
                <button
                    type="button"
                    class="icon-button"
                    aria-label="Închide"
                    @click="emit('close')"
                >
                    <Icon name="close" />
                </button>
            </div>
            <label
                >Tipul zilei<select v-model="form.absence">
                    <option value="">Zi lucrată</option>
                    <option
                        v-for="(label, key) in absences"
                        :key="key"
                        :value="key"
                    >
                        {{ label }}
                    </option>
                </select></label
            >
            <div v-if="!form.absence" class="interval-list">
                <div v-for="(row, i) in rows" :key="i" class="interval-editor">
                    <div class="flex-between">
                        <strong>Interval {{ i + 1 }}</strong
                        ><button
                            v-if="rows.length > 1"
                            type="button"
                            class="text-button danger-text"
                            @click="rows.splice(i, 1)"
                        >
                            Elimină
                        </button>
                    </div>
                    <label
                        >Tip<select v-model="row.kind">
                            <option value="work">Lucru</option>
                            <option value="break">Pauză</option>
                        </select></label
                    >
                    <div class="two-columns">
                        <label
                            >Început<input
                                v-model="row.start"
                                type="datetime-local"
                                required
                                step="60"
                            /><select
                                v-if="
                                    candidates(row.start, company.timezone)
                                        .length > 1
                                "
                                v-model="row.startChoice"
                                aria-label="Offset început"
                            >
                                <option value="">Alege offsetul UTC</option>
                                <option
                                    v-for="c in candidates(
                                        row.start,
                                        company.timezone,
                                    )"
                                    :key="c"
                                    :value="c"
                                >
                                    UTC{{ c.slice(-6) }}
                                </option>
                            </select></label
                        ><label
                            >Sfârșit<input
                                v-model="row.end"
                                type="datetime-local"
                                required
                                step="60"
                            /><select
                                v-if="
                                    candidates(row.end, company.timezone)
                                        .length > 1
                                "
                                v-model="row.endChoice"
                                aria-label="Offset sfârșit"
                            >
                                <option value="">Alege offsetul UTC</option>
                                <option
                                    v-for="c in candidates(
                                        row.end,
                                        company.timezone,
                                    )"
                                    :key="c"
                                    :value="c"
                                >
                                    UTC{{ c.slice(-6) }}
                                </option>
                            </select></label
                        >
                    </div>
                </div>
                <button
                    type="button"
                    class="button secondary full"
                    @click="
                        rows.push({
                            kind: 'work',
                            start: date + 'T09:00',
                            end: date + 'T17:00',
                            startChoice: '',
                            endChoice: '',
                        })
                    "
                >
                    <Icon name="plus" :size="17" /> Adaugă interval sau pauză
                </button>
                <p class="hint">
                    Pauzele se scad din intervalele de lucru. Pentru tura de
                    noapte, alege data următoare la sfârșit.
                </p>
            </div>
            <label
                >Observații <span class="muted">(opțional)</span
                ><textarea
                    v-model="form.note"
                    rows="2"
                    maxlength="1000"
                    placeholder="Ce ar trebui să știe echipa?"
                ></textarea>
            </label>
            <label v-if="correction"
                >Motivul corecturii<textarea
                    v-model="form.reason"
                    rows="2"
                    required
                    maxlength="1000"
                    placeholder="Explică modificarea pentru istoricul pontajului."
                ></textarea>
            </label>
            <div v-if="localError" class="form-error" role="alert">
                {{ localError }}
            </div>
            <p
                v-for="(error, key) in form.errors"
                :key="key"
                class="form-error"
                role="alert"
            >
                {{ error }}
            </p>
            <p v-if="form.absence" class="info-note">
                <Icon name="alert" :size="18" /> Absența va fi verificată de
                proprietar.
            </p>
            <div class="modal-footer">
                <button
                    type="button"
                    class="button secondary"
                    @click="emit('close')"
                >
                    Renunță</button
                ><button class="button primary" :disabled="form.processing">
                    <Icon name="check" :size="18" />{{
                        form.processing ? 'Se salvează…' : 'Salvează ziua'
                    }}
                </button>
            </div>
        </form>
    </dialog>
</template>
