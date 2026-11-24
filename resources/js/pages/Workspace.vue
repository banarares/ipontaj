<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import Brand from '@/components/Brand.vue';
import Icon from '@/components/UiIcon.vue';
import DayEditor from '@/components/DayEditor.vue';
import CompanyIdentityFields from '@/components/CompanyIdentityFields.vue';
import LegalFooter from '@/components/LegalFooter.vue';
import type {
    Audit,
    Company,
    Entry,
    Invite,
    Issue,
    Member,
    SummaryRow,
    WorkSchedule,
} from '@/types/ipontaj';
import {
    candidates,
    initials,
    minutes,
    nextDate,
    readableDate,
} from '@/lib/dates';
const props = defineProps<{
    section: string;
    company: Company;
    membership: Member;
    companies: { id: number; name: string }[];
    members: Member[];
    month: string;
    today: string;
    selectedDate: string;
    selectedDateActive: boolean;
    summary: { rows: SummaryRow[]; issues: Issue[]; days: number } | null;
    entries: Entry[];
    schedule: WorkSchedule | null;
    schedules: WorkSchedule[];
    invitations: Invite[];
    closure: { approved_at: string | null } | null;
    audit: Audit[];
    seatLimit: number;
    occupiedSeats: number;
    billingEnabled: boolean;
    billingReady: boolean;
    subscribed: boolean;
    absences: Record<string, string>;
}>();
const page = usePage();
const billingForm = useForm({
    terms_accepted: false,
    service_start_requested: false,
    legal_version: page.props.legal.version,
});
const flash = computed(
    () => page.props.flash as { success?: string; invitation_url?: string },
);
const owner = computed(() => props.membership.role === 'owner');
const mobileNav = ref(false),
    search = ref(''),
    statusFilter = ref('all'),
    showInvite = ref(false),
    copied = ref(false),
    toast = ref(''),
    online = ref(navigator.onLine),
    help = ref(false),
    reopen = ref(false);
const editor = ref<{
    member: Member;
    date: string;
    entry?: Entry | null;
    schedule?: WorkSchedule | null;
} | null>(null);
const titles: Record<string, string> = {
    overview: 'Privire de ansamblu',
    today: 'Ziua mea',
    history: 'Istoricul meu',
    team: 'Echipa ta',
    month: 'Pontaj lunar',
    settings: 'Setările firmei',
    billing: 'Abonamentul tău',
};
const nav = computed(() =>
    [
        {
            label: 'Privire de ansamblu',
            href: '/dashboard',
            icon: 'dashboard',
            key: 'overview',
            visible: owner.value,
        },
        {
            label: 'Ziua mea',
            href: '/today',
            icon: 'clock',
            key: 'today',
            visible: props.membership.tracked,
        },
        {
            label: 'Pontaj lunar',
            href: '/month',
            icon: 'calendar',
            key: 'month',
            visible: owner.value,
        },
        {
            label: 'Echipă',
            href: '/team',
            icon: 'users',
            key: 'team',
            visible: owner.value,
        },
        {
            label: 'Istoricul meu',
            href: '/history',
            icon: 'history',
            key: 'history',
            visible: true,
        },
    ].filter((n) => n.visible),
);
const rows = computed(() => props.summary?.rows || []);
const filteredRows = computed(() =>
    rows.value.filter(
        (r) =>
            r.membership.user.name
                .toLowerCase()
                .includes(search.value.toLowerCase()) &&
            (statusFilter.value === 'all' ||
                (statusFilter.value === 'complete'
                    ? r.missing + r.pending === 0
                    : r.missing + r.pending > 0)),
    ),
);
const totalMinutes = computed(() =>
    rows.value.reduce((sum, r) => sum + r.confirmed, 0),
);
const missingDays = computed(() =>
    rows.value.reduce((sum, r) => sum + r.missing, 0),
);
const pendingDays = computed(() =>
    rows.value.reduce((sum, r) => sum + r.pending, 0),
);
const complete = computed(
    () => rows.value.filter((r) => !r.missing && !r.pending).length,
);
const elapsedPlanned = computed(() =>
    rows.value.reduce(
        (sum, r) =>
            sum +
            r.days.filter((d) => d.date <= props.today && d.planned > 0).length,
        0,
    ),
);
const enteredDays = computed(() =>
    rows.value.reduce(
        (sum, r) =>
            sum +
            r.days.filter(
                (d) =>
                    d.date <= props.today &&
                    (d.entry?.confirmed_at || d.entry?.approved_at),
            ).length,
        0,
    ),
);
const progress = computed(() =>
    elapsedPlanned.value
        ? Math.min(
              100,
              Math.round((enteredDays.value / elapsedPlanned.value) * 100),
          )
        : 0,
);
const monthLabel = computed(() =>
    readableDate(props.month + '-01', { month: 'long', year: 'numeric' }),
);
const todayEntry = computed(() =>
    props.entries.find(
        (e) =>
            e.membership_id === props.membership.id &&
            e.date.slice(0, 10) === props.selectedDate,
    ),
);
const ownEntries = computed(() =>
    props.entries
        .filter((e) => e.membership_id === props.membership.id)
        .sort((a, b) => b.date.localeCompare(a.date)),
);
const invite = useForm({
    first_name: '',
    last_name: '',
    email: '',
    send_email: false,
});
const settings = useForm({
    name: props.company.name,
    cif: props.company.cif || '',
    reminders: props.company.reminders,
    accountant_email: props.company.accountant_email || '',
    accountant_confirmed: !!props.company.accountant_confirmed_at,
});
const workSchedule = useForm({
    membership_id: '',
    effective_from: props.today,
    weekdays: [1, 2, 3, 4, 5],
    start_time: '09:00',
    end_time: '17:00',
});
const closeForm = useForm({ month: props.month, reopen: false, reason: '' });
const dayError = ref(''),
    confirming = ref(false),
    closeError = ref('');
function changeMonth(value: string) {
    if (value)
        router.get(
            location.pathname,
            { month: value },
            { preserveState: false },
        );
}
function shiftMonth(delta: number) {
    const d = new Date(props.month + '-01T12:00:00Z');
    d.setUTCMonth(d.getUTCMonth() + delta);
    changeMonth(d.toISOString().slice(0, 7));
}
function openEditor(
    member = props.membership,
    date = props.selectedDate,
    entry: Entry | null | undefined = todayEntry.value,
) {
    const schedules = props.schedules.filter(
        (s) =>
            s.effective_from.slice(0, 10) <= date &&
            (!s.membership_id || s.membership_id === member.id),
    );
    const schedule =
        schedules.find((s) => s.membership_id === member.id) ||
        schedules.find((s) => !s.membership_id) ||
        props.schedule;
    editor.value = { member, date, entry, schedule };
}
function quickConfirm() {
    dayError.value = '';
    const s = props.schedule;
    if (!s) {
        dayError.value =
            'Nu ai un program propus. Adaugă orele efectiv lucrate.';
        openEditor();
        return;
    }
    const start = candidates(
            props.selectedDate + 'T' + s.start_time,
            props.company.timezone,
        ),
        end = candidates(
            (s.end_time <= s.start_time
                ? nextDate(props.selectedDate)
                : props.selectedDate) +
                'T' +
                s.end_time,
            props.company.timezone,
        );
    if (start.length !== 1 || end.length !== 1) {
        openEditor();
        return;
    }
    confirming.value = true;
    router.post(
        '/entries',
        {
            membership_id: props.membership.id,
            date: props.selectedDate,
            absence: null,
            intervals: [{ kind: 'work', start_at: start[0], end_at: end[0] }],
        },
        {
            preserveScroll: true,
            onError: (e) => {
                dayError.value = Object.values(e).join(' ');
            },
            onFinish: () => {
                confirming.value = false;
            },
        },
    );
}
function approveMonth() {
    closeError.value = '';
    closeForm.reopen = false;
    closeForm.post('/months/close', {
        preserveScroll: true,
        onError: (e) => {
            closeError.value = Object.values(e).join(' ');
        },
    });
}
function reopenMonth() {
    closeForm.reopen = true;
    closeForm.post('/months/close', {
        preserveScroll: true,
        onSuccess: () => {
            reopen.value = false;
            closeForm.reason = '';
        },
    });
}
function sendInvite() {
    invite.post('/invitations', {
        preserveScroll: true,
        onSuccess: () => {
            invite.reset();
            showInvite.value = false;
        },
    });
}
async function copyInvite() {
    try {
        await navigator.clipboard.writeText(flash.value.invitation_url || '');
        copied.value = true;
    } catch {
        toast.value = 'Selectează și copiază linkul afișat.';
    }
}
function review(id: number) {
    router.post('/entries/' + id + '/review', {}, { preserveScroll: true });
}
function memberState(m: Member) {
    router.patch(
        '/members/' + m.id,
        { active: !m.active },
        { preserveScroll: true },
    );
}
function status(row: SummaryRow) {
    return row.missing ? 'Incomplet' : row.pending ? 'De verificat' : 'La zi';
}
function handleOnline() {
    online.value = navigator.onLine;
}
let toastTimer: ReturnType<typeof setTimeout>;
onMounted(() => {
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', handleOnline);
    toastTimer = setTimeout(() => {
        toast.value = '';
    }, 5000);
});
onUnmounted(() => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', handleOnline);
    clearTimeout(toastTimer);
});
const weekdays = ['Lu', 'Ma', 'Mi', 'Jo', 'Vi', 'Sâ', 'Du'];
</script>
<template>
    <Head :title="titles[section]" />
    <div class="workspace">
        <button
            v-if="mobileNav"
            class="sidebar-overlay"
            aria-label="Închide meniul"
            @click="mobileNav = false"
        ></button>
        <aside class="sidebar" :class="{ open: mobileNav }">
            <Link href="/dashboard" class="brand-link"><Brand /></Link>
            <div class="company-switch">
                <span class="company-avatar"
                    ><Icon name="building" :size="20"
                /></span>
                <div>
                    <strong>{{ company.name }}</strong
                    ><span>Spațiul echipei tale</span>
                </div>
                <Icon name="down" :size="16" /><select
                    aria-label="Schimbă firma"
                    :value="company.id"
                    @change="
                        router.post('/companies/switch', {
                            company_id: ($event.target as HTMLSelectElement)
                                .value,
                        })
                    "
                >
                    <option v-for="c in companies" :key="c.id" :value="c.id">
                        {{ c.name }}
                    </option>
                </select>
            </div>
            <span class="nav-label">SPAȚIU DE LUCRU</span>
            <nav class="main-nav">
                <Link
                    v-for="item in nav"
                    :key="item.key"
                    :href="item.href"
                    :class="{ active: section === item.key }"
                    @click="mobileNav = false"
                    ><Icon :name="item.icon" :size="20" />{{ item.label
                    }}<span
                        v-if="item.key === 'month' && missingDays + pendingDays"
                        class="nav-count"
                        >{{ missingDays + pendingDays }}</span
                    ></Link
                >
            </nav>
            <div class="sidebar-bottom">
                <nav class="main-nav">
                    <Link
                        v-if="owner"
                        href="/settings"
                        :class="{ active: section === 'settings' }"
                        ><Icon name="settings" />Setări</Link
                    ><Link
                        v-if="owner"
                        href="/billing"
                        :class="{ active: section === 'billing' }"
                        ><Icon name="card" />Abonament<span
                            class="small-plan"
                            >{{
                                subscribed
                                    ? 'Echipă'
                                    : billingEnabled
                                      ? 'Gratuit'
                                      : 'Local'
                            }}</span
                        ></Link
                    ><button @click="help = true">
                        <Icon name="help" />Ajutor & instalare
                    </button>
                </nav>
                <div v-if="owner" class="plan-box">
                    <div class="flex-between">
                        <strong>{{
                            company.is_demo
                                ? 'Explorezi iPontaj'
                                : subscribed
                                  ? 'Planul Echipă'
                                  : billingEnabled
                                    ? 'Planul Gratuit'
                                    : 'Instalare proprie'
                        }}</strong
                        ><span class="tiny-badge">{{
                            company.is_demo
                                ? 'DEMO'
                                : `${occupiedSeats}/${seatLimit}`
                        }}</span>
                    </div>
                    <p>
                        {{
                            company.is_demo
                                ? 'Un pic mai puțină birocrație. Mai mult timp pentru echipa ta.'
                                : `${occupiedSeats} din ${seatLimit} locuri ocupate sau rezervate`
                        }}
                    </p>
                    <Link
                        :href="company.is_demo ? '/demo/exit' : '/billing'"
                        :method="company.is_demo ? 'post' : 'get'"
                        :as="company.is_demo ? 'button' : 'a'"
                        >{{
                            company.is_demo
                                ? 'Creează contul tău'
                                : 'Vezi abonamentul'
                        }}<Icon name="plus" :size="15"
                    /></Link>
                </div>
                <div class="user-block">
                    <Link
                        href="/settings/profile"
                        class="avatar avatar-owner"
                        aria-label="Contul meu"
                        >{{
                            initials(membership.user?.name || 'Utilizator')
                        }}</Link
                    >
                    <div>
                        <strong>{{ membership.user?.name }}</strong
                        ><span>{{ owner ? 'Proprietar' : 'Angajat' }}</span>
                    </div>
                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        class="icon-button"
                        aria-label="Ieși din cont"
                        ><Icon name="logout" :size="18"
                    /></Link>
                </div>
            </div>
        </aside>
        <div class="workspace-body">
            <header class="topbar">
                <div class="breadcrumb">
                    <button
                        class="icon-button mobile-menu"
                        aria-label="Deschide meniul"
                        @click="mobileNav = true"
                    >
                        <Icon name="menu" /></button
                    ><Icon name="building" :size="17" /><span
                        >Spațiu de lucru</span
                    ><span class="crumb-divider">/</span
                    ><strong>{{ titles[section] }}</strong>
                </div>
                <div class="topbar-right">
                    <span v-if="company.is_demo" class="demo-badge"
                        >Mediu demonstrativ</span
                    ><span class="timezone-label">{{ company.timezone }}</span
                    ><button
                        class="icon-button"
                        aria-label="Vezi elementele care necesită atenție"
                        @click="
                            owner ? router.get('/month') : router.get('/today')
                        "
                    >
                        <Icon name="bell" :size="20" /><span
                            v-if="missingDays + pendingDays"
                            class="notification-dot"
                        ></span>
                    </button>
                </div>
            </header>
            <div v-if="!online" class="offline-banner" role="alert">
                <Icon name="offline" />Nu ai conexiune la internet.
                Reconectează-te pentru a vedea sau salva pontajul.
            </div>
            <div v-if="flash.success" class="success-toast" role="status">
                <Icon name="success" :size="18" />{{ flash.success }}
            </div>
            <main class="main-content">
                <div class="page-heading">
                    <div>
                        <div class="heading-kicker">
                            <span class="live-dot"></span
                            >{{
                                readableDate(today, {
                                    weekday: 'long',
                                    day: 'numeric',
                                    month: 'long',
                                    year: 'numeric',
                                })
                            }}
                        </div>
                        <h1>
                            {{
                                section === 'overview'
                                    ? `Bună, ${membership.user?.first_name || ''}!`
                                    : titles[section]
                            }}<span
                                v-if="section === 'overview'"
                                class="greeting-sun"
                                ><Icon name="sun" :size="27"
                            /></span>
                        </h1>
                        <p>
                            {{
                                section === 'overview'
                                    ? 'O zi bună începe cu o echipă la zi. Iată cum stă pontajul.'
                                    : section === 'today'
                                      ? 'Câteva secunde pentru tine. O grijă în minus la final de lună.'
                                      : section === 'team'
                                        ? 'Oameni, programe și invitații. Toate într-un singur loc.'
                                        : section === 'month'
                                          ? 'Verifică excepțiile și pregătește pontajul pentru contabil.'
                                          : section === 'history'
                                            ? 'Zilele și orele pe care le-ai înregistrat.'
                                            : section === 'settings'
                                              ? 'Configurează lucrurile o dată. Noi ținem pasul.'
                                              : 'Un plan simplu, potrivit echipei tale.'
                            }}
                        </p>
                    </div>
                    <div class="heading-actions">
                        <a
                            v-if="
                                owner && ['overview', 'month'].includes(section)
                            "
                            :href="`/exports?month=${month}`"
                            class="button secondary"
                            ><Icon name="download" :size="17" />Exportă Excel</a
                        ><button
                            v-if="
                                owner && ['overview', 'team'].includes(section)
                            "
                            class="button primary"
                            @click="showInvite = true"
                        >
                            <Icon name="plus" :size="18" />Invită un coleg
                        </button>
                    </div>
                </div>

                <template v-if="section === 'overview'">
                    <div class="section-toolbar">
                        <div class="month-switch">
                            <button
                                class="icon-button"
                                aria-label="Luna anterioară"
                                @click="shiftMonth(-1)"
                            >
                                <Icon name="left" :size="17" /></button
                            ><span class="month-picker"
                                ><Icon name="calendar" :size="17" />{{
                                    monthLabel
                                }}<input
                                    type="month"
                                    :value="month"
                                    aria-label="Selectează luna"
                                    @change="
                                        changeMonth(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    " /></span
                            ><button
                                class="icon-button"
                                aria-label="Luna următoare"
                                @click="shiftMonth(1)"
                            >
                                <Icon name="right" :size="17" />
                            </button>
                        </div>
                        <span class="muted small"
                            >Date actualizate la fiecare confirmare</span
                        >
                    </div>
                    <div class="stats-grid">
                        <article class="stat-card">
                            <div class="stat-label">
                                Persoane în pontaj<span class="stat-icon"
                                    ><Icon name="users" :size="18"
                                /></span>
                            </div>
                            <div class="stat-number">
                                {{ rows.length
                                }}<span class="stat-unit">persoane</span>
                            </div>
                            <div class="stat-foot">
                                <span class="status-dot green"></span
                                >{{ complete }}
                                {{
                                    complete === 1
                                        ? 'persoană la zi'
                                        : 'persoane la zi'
                                }}
                            </div>
                        </article>
                        <article class="stat-card">
                            <div class="stat-label">
                                Ore confirmate<span class="stat-icon"
                                    ><Icon name="clock" :size="18"
                                /></span>
                            </div>
                            <div class="stat-number">
                                {{
                                    Math.floor(
                                        totalMinutes / 60,
                                    ).toLocaleString('ro-RO')
                                }}<span class="stat-unit"
                                    >ore{{
                                        totalMinutes % 60
                                            ? ` ${totalMinutes % 60} min`
                                            : ''
                                    }}</span
                                >
                            </div>
                            <div class="stat-foot">
                                Din
                                {{
                                    minutes(
                                        rows.reduce((s, r) => s + r.planned, 0),
                                    )
                                }}
                                planificate în lună
                            </div>
                        </article>
                        <article class="stat-card">
                            <div class="stat-label">
                                Zile neconfirmate<span class="stat-icon amber"
                                    ><Icon name="calendar" :size="18"
                                /></span>
                            </div>
                            <div class="stat-number">
                                {{ missingDays
                                }}<span class="stat-unit">zile</span>
                            </div>
                            <div
                                class="stat-foot"
                                :class="{ 'amber-text': missingDays }"
                            >
                                <span
                                    class="status-dot"
                                    :class="missingDays ? 'amber' : 'green'"
                                ></span
                                >{{
                                    missingDays
                                        ? 'Așteaptă confirmarea colegilor'
                                        : 'Toate zilele sunt înregistrate'
                                }}
                            </div>
                        </article>
                        <article class="stat-card">
                            <div class="stat-label">
                                Absențe & corecturi<span class="stat-icon"
                                    ><Icon name="coffee" :size="18"
                                /></span>
                            </div>
                            <div class="stat-number">
                                {{ pendingDays
                                }}<span class="stat-unit">de verificat</span>
                            </div>
                            <div class="stat-foot">
                                Verificarea ta închide cercul
                            </div>
                        </article>
                    </div>
                    <div class="overview-panels">
                        <section class="panel attention-panel">
                            <div class="panel-heading">
                                <h2>
                                    <span class="attention-symbol"
                                        ><Icon name="alert" :size="18" /></span
                                    >Necesită atenția ta
                                    <span class="count-badge">{{
                                        missingDays + pendingDays
                                    }}</span>
                                </h2>
                                <Link href="/month" class="text-button"
                                    >Vezi toate<Icon name="right" :size="16"
                                /></Link>
                            </div>
                            <div
                                v-if="!summary?.issues.length"
                                class="all-good"
                            >
                                <Icon name="success" :size="28" />
                                <div>
                                    <strong>Echipa este la zi.</strong>
                                    <p>
                                        Nu ai excepții de verificat pentru
                                        această lună.
                                    </p>
                                </div>
                            </div>
                            <div
                                v-for="(issue, i) in summary?.issues.slice(
                                    0,
                                    3,
                                )"
                                :key="`${issue.membership_id}-${issue.date}`"
                                class="attention-row"
                            >
                                <span
                                    class="avatar"
                                    :class="`avatar-${i % 4}`"
                                    >{{ initials(issue.name) }}</span
                                >
                                <div class="attention-person">
                                    <strong>{{ issue.name }}</strong
                                    ><span
                                        >{{ issue.type }} ·
                                        {{ readableDate(issue.date) }}</span
                                    >
                                </div>
                                <button
                                    v-if="issue.entry_id"
                                    class="button secondary compact"
                                    @click="review(issue.entry_id)"
                                >
                                    Verifică</button
                                ><button
                                    v-else
                                    class="icon-button"
                                    :aria-label="`Corectează ziua pentru ${issue.name}`"
                                    @click="
                                        openEditor(
                                            members.find(
                                                (m) =>
                                                    m.id ===
                                                    issue.membership_id,
                                            )!,
                                            issue.date,
                                            null,
                                        )
                                    "
                                >
                                    <Icon name="right" :size="18" />
                                </button>
                            </div>
                            <div class="panel-note">
                                <Icon name="mail" :size="15" />{{
                                    company.reminders
                                        ? 'Mementourile automate sunt activate pentru echipă.'
                                        : 'Mementourile automate sunt dezactivate.'
                                }}
                            </div>
                        </section>
                        <section class="panel month-progress">
                            <div class="panel-heading">
                                <h2>Luna, pe drumul cel bun</h2>
                                <Icon name="calendarcheck" :size="20" />
                            </div>
                            <div class="progress-top">
                                <span>Pontaj completat</span
                                ><strong>{{ progress }}<span>%</span></strong>
                            </div>
                            <div class="progress-track">
                                <div :style="{ width: progress + '%' }"></div>
                            </div>
                            <p>
                                {{ enteredDays }} din {{ elapsedPlanned }} zile
                                de lucru scadente sunt înregistrate.
                            </p>
                            <div class="month-status">
                                <span
                                    class="status-dot"
                                    :class="
                                        closure?.approved_at ? 'green' : 'amber'
                                    "
                                ></span
                                >{{
                                    closure?.approved_at
                                        ? 'Lună aprobată și blocată'
                                        : 'Lună în curs de completare'
                                }}
                            </div>
                            <Link
                                :href="`/month?month=${month}`"
                                class="button secondary full"
                                >Verifică pontajul lunar<Icon
                                    name="calendar"
                                    :size="16"
                            /></Link>
                        </section>
                    </div>
                    <section class="panel team-overview">
                        <div class="panel-heading">
                            <div>
                                <h2>Echipa, dintr-o privire</h2>
                                <p>
                                    Pontajul fiecărui coleg pentru
                                    {{ monthLabel }}.
                                </p>
                            </div>
                            <Link href="/team" class="text-button"
                                >Gestionează echipa<Icon
                                    name="right"
                                    :size="16"
                            /></Link>
                        </div>
                        <div class="table-filters">
                            <div class="filter-tabs">
                                <button
                                    :class="{
                                        selected: statusFilter === 'all',
                                    }"
                                    @click="statusFilter = 'all'"
                                >
                                    Toți colegii
                                    <span>{{ rows.length }}</span></button
                                ><button
                                    :class="{
                                        selected: statusFilter === 'incomplete',
                                    }"
                                    @click="statusFilter = 'incomplete'"
                                >
                                    Necesită atenție</button
                                ><button
                                    :class="{
                                        selected: statusFilter === 'complete',
                                    }"
                                    @click="statusFilter = 'complete'"
                                >
                                    La zi
                                </button>
                            </div>
                            <div class="search-field">
                                <Icon name="search" :size="17" /><input
                                    v-model="search"
                                    placeholder="Caută un coleg…"
                                    aria-label="Caută un coleg"
                                />
                            </div>
                        </div>
                        <div class="table-scroll">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>COLEG</th>
                                        <th>PROGRAM</th>
                                        <th>ORE CONFIRMATE</th>
                                        <th>DE COMPLETAT</th>
                                        <th>STARE</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="(row, i) in filteredRows"
                                        :key="row.membership.id"
                                    >
                                        <td>
                                            <div class="person-cell">
                                                <span
                                                    class="avatar"
                                                    :class="`avatar-${i % 4}`"
                                                    >{{
                                                        initials(
                                                            row.membership.user
                                                                .name,
                                                        )
                                                    }}</span
                                                >
                                                <div>
                                                    <strong
                                                        >{{
                                                            row.membership.user
                                                                .name
                                                        }}
                                                        <span
                                                            v-if="
                                                                row.membership
                                                                    .id ===
                                                                membership.id
                                                            "
                                                            class="you-label"
                                                            >Tu</span
                                                        ></strong
                                                    ><span>{{
                                                        row.membership.role ===
                                                        'owner'
                                                            ? 'Proprietar'
                                                            : 'Membru în echipă'
                                                    }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            {{ minutes(row.planned) }}
                                            <span class="muted">/ lună</span>
                                        </td>
                                        <td>
                                            <strong class="tabular">{{
                                                minutes(row.confirmed)
                                            }}</strong>
                                        </td>
                                        <td>
                                            <span
                                                :class="
                                                    row.missing
                                                        ? 'amber-text'
                                                        : 'muted'
                                                "
                                                >{{
                                                    row.missing
                                                        ? `${row.missing} ${row.missing === 1 ? 'zi' : 'zile'}`
                                                        : '—'
                                                }}</span
                                            >
                                        </td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="
                                                    row.missing
                                                        ? 'warning'
                                                        : row.pending
                                                          ? 'review'
                                                          : 'positive'
                                                "
                                                ><span class="status-dot"></span
                                                >{{ status(row) }}</span
                                            >
                                        </td>
                                        <td>
                                            <Link
                                                :href="`/month?month=${month}`"
                                                class="icon-button"
                                                :aria-label="`Vezi pontajul ${row.membership.user.name}`"
                                                ><Icon name="right" :size="17"
                                            /></Link>
                                        </td>
                                    </tr>
                                    <tr v-if="!filteredRows.length">
                                        <td colspan="6" class="empty-cell">
                                            {{
                                                rows.length
                                                    ? 'Niciun coleg nu corespunde căutării.'
                                                    : 'Echipa începe cu prima invitație. Adaugă un coleg pentru a începe.'
                                            }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <span>{{ rows.length }} persoane în pontaj</span
                            ><span
                                ><Icon name="shield" :size="14" />Doar orele
                                confirmate sau aprobate intră în total</span
                            >
                        </div>
                    </section>
                    <div class="bottom-tip">
                        <span class="tip-icon"
                            ><Icon name="coffee" :size="21"
                        /></span>
                        <div>
                            <strong
                                >Mai puțin pontaj. Mai mult timp pentru ce
                                contează.</strong
                            >
                            <p>
                                Colegii confirmă ziua în câteva secunde. Tu ai
                                totul pregătit la final de lună.
                            </p>
                        </div>
                        <button class="text-button" @click="help = true">
                            Cum funcționează<Icon name="external" :size="15" />
                        </button>
                    </div>
                </template>

                <template v-else-if="section === 'today'">
                    <section class="panel daily-panel">
                        <div class="panel-heading">
                            <div>
                                <span class="eyebrow">ZIUA TA DE LUCRU</span>
                                <h2>
                                    {{
                                        readableDate(selectedDate, {
                                            weekday: 'long',
                                            day: 'numeric',
                                            month: 'long',
                                        })
                                    }}
                                </h2>
                            </div>
                            <input
                                type="date"
                                :value="selectedDate"
                                :max="today"
                                :min="membership.joined_on.slice(0, 10)"
                                aria-label="Alege ziua"
                                @change="
                                    router.get('/today', {
                                        date: (
                                            $event.target as HTMLInputElement
                                        ).value,
                                        month: (
                                            $event.target as HTMLInputElement
                                        ).value.slice(0, 7),
                                    })
                                "
                            />
                        </div>
                        <div v-if="!membership.tracked" class="empty-state">
                            Nu ești inclus în pontajul firmei.
                        </div>
                        <template v-else
                            ><div class="daily-clock">
                                <span class="daily-clock-icon"
                                    ><Icon
                                        :name="
                                            todayEntry
                                                ? 'calendarcheck'
                                                : 'clock'
                                        "
                                        :size="36" /></span
                                ><span
                                    class="badge"
                                    :class="
                                        todayEntry
                                            ? 'positive'
                                            : selectedDateActive
                                              ? 'warning'
                                              : ''
                                    "
                                    >{{
                                        todayEntry
                                            ? todayEntry.absence
                                                ? 'Absență declarată'
                                                : 'Zi înregistrată'
                                            : selectedDateActive
                                              ? 'Așteaptă confirmarea ta'
                                              : 'Zi în afara perioadei active'
                                    }}</span
                                >
                                <h3>
                                    {{
                                        todayEntry
                                            ? todayEntry.absence
                                                ? absences[todayEntry.absence]
                                                : minutes(todayEntry.minutes)
                                            : !selectedDateActive
                                              ? 'Nu erai activ în echipă'
                                              : schedule
                                                ? `${schedule.start_time} – ${schedule.end_time}`
                                                : 'Adaugă orele lucrate'
                                    }}
                                </h3>
                                <p>
                                    {{
                                        todayEntry
                                            ? todayEntry.approved_at
                                                ? 'Verificată și aprobată de proprietar.'
                                                : 'Înregistrarea ta este salvată.'
                                            : selectedDateActive
                                              ? `Programul propus · ${company.timezone}`
                                              : 'Alege o zi dintr-o perioadă de activitate.'
                                    }}
                                </p>
                                <div
                                    v-if="todayEntry?.intervals.length"
                                    class="day-intervals"
                                >
                                    <span
                                        v-for="interval in todayEntry.intervals"
                                        :key="interval.id"
                                        >{{
                                            interval.kind === 'break'
                                                ? 'Pauză'
                                                : 'Lucru'
                                        }}
                                        ·
                                        {{
                                            new Date(
                                                interval.start_at,
                                            ).toLocaleTimeString('ro-RO', {
                                                timeZone: company.timezone,
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })
                                        }}–{{
                                            new Date(
                                                interval.end_at,
                                            ).toLocaleTimeString('ro-RO', {
                                                timeZone: company.timezone,
                                                hour: '2-digit',
                                                minute: '2-digit',
                                            })
                                        }}</span
                                    >
                                </div>
                            </div>
                            <div
                                v-if="
                                    selectedDateActive && !closure?.approved_at
                                "
                                class="daily-actions"
                            >
                                <button
                                    v-if="!todayEntry"
                                    class="button primary"
                                    :disabled="confirming || !online"
                                    @click="quickConfirm"
                                >
                                    <Icon name="check" />{{
                                        confirming
                                            ? 'Se confirmă…'
                                            : 'Confirmă ziua'
                                    }}</button
                                ><button
                                    class="button secondary"
                                    @click="openEditor()"
                                >
                                    <Icon name="edit" :size="17" />Modifică
                                    orele</button
                                ><button
                                    v-if="!todayEntry?.absence"
                                    class="text-button"
                                    @click="openEditor()"
                                >
                                    Marchează absența
                                </button>
                            </div>
                            <p
                                v-else-if="closure?.approved_at"
                                class="info-note"
                            >
                                <Icon name="lock" :size="18" />Luna este
                                aprobată. Contactează proprietarul pentru o
                                corectură.
                            </p>
                            <p v-else class="info-note">
                                <Icon name="alert" :size="18" />Nu poți
                                înregistra ore sau absențe pentru această zi,
                                deoarece este în afara perioadelor tale de
                                activitate.
                            </p>
                            <p v-if="dayError" class="form-error" role="alert">
                                {{ dayError }}
                            </p></template
                        >
                    </section>
                    <div class="gentle-note">
                        <Icon name="shield" :size="19" />
                        <p>
                            Confirmă orele efectiv lucrate. Programul obișnuit
                            este doar o propunere.
                        </p>
                    </div>
                    <section class="panel preferences-panel">
                        <h2>Mementourile tale</h2>
                        <label class="checkbox-label"
                            ><input
                                type="checkbox"
                                :checked="membership.reminders"
                                @change="
                                    router.post(
                                        '/preferences',
                                        {
                                            reminders: (
                                                $event.target as HTMLInputElement
                                            ).checked,
                                        },
                                        { preserveScroll: true },
                                    )
                                "
                            />Vreau un email după program dacă uit să confirm
                            ziua.</label
                        >
                    </section>
                </template>

                <template v-else-if="section === 'month'">
                    <div class="section-toolbar">
                        <div class="month-switch">
                            <button
                                class="icon-button"
                                aria-label="Luna anterioară"
                                @click="shiftMonth(-1)"
                            >
                                <Icon name="left" :size="17" /></button
                            ><span class="month-picker"
                                ><Icon name="calendar" :size="17" />{{
                                    monthLabel
                                }}<input
                                    type="month"
                                    :value="month"
                                    aria-label="Luna pontajului"
                                    @change="
                                        changeMonth(
                                            ($event.target as HTMLInputElement)
                                                .value,
                                        )
                                    " /></span
                            ><button
                                class="icon-button"
                                aria-label="Luna următoare"
                                @click="shiftMonth(1)"
                            >
                                <Icon name="right" :size="17" />
                            </button>
                        </div>
                        <span
                            class="badge"
                            :class="
                                closure?.approved_at ? 'positive' : 'warning'
                            "
                            ><Icon
                                :name="closure?.approved_at ? 'lock' : 'clock'"
                                :size="14"
                            />{{
                                closure?.approved_at ? 'Aprobată' : 'În lucru'
                            }}</span
                        >
                    </div>
                    <section class="panel">
                        <div class="panel-heading">
                            <div>
                                <h2>
                                    {{
                                        closure?.approved_at
                                            ? 'Pontaj aprobat'
                                            : 'Pregătim luna pentru contabil'
                                    }}
                                </h2>
                                <p>
                                    {{
                                        summary?.issues.length
                                            ? `${summary.issues.length} înregistrări necesită atenție înainte de aprobare.`
                                            : 'Toate înregistrările scadente sunt pregătite pentru verificarea finală.'
                                    }}
                                </p>
                            </div>
                            <button
                                v-if="!closure?.approved_at"
                                class="button primary"
                                :disabled="
                                    closeForm.processing ||
                                    !!summary?.issues.length ||
                                    month >= today.slice(0, 7)
                                "
                                @click="approveMonth"
                            >
                                <Icon name="check" :size="17" />Aprobă luna</button
                            ><button
                                v-else
                                class="button secondary"
                                @click="reopen = true"
                            >
                                <Icon name="refresh" :size="17" />Redeschide
                                luna
                            </button>
                        </div>
                        <p v-if="closeError" class="form-error" role="alert">
                            {{ closeError }}
                        </p>
                        <p
                            v-if="
                                !closure?.approved_at &&
                                month >= today.slice(0, 7)
                            "
                            class="info-note"
                            style="margin: 0 21px 20px"
                        >
                            <Icon name="calendar" :size="17" />Aprobarea finală
                            este disponibilă după încheierea lunii. Poți exporta
                            acum o variantă provizorie.
                        </p>
                        <div class="month-metrics">
                            <span
                                ><strong>{{ minutes(totalMinutes) }}</strong>
                                confirmate/aprobate</span
                            ><span
                                ><strong>{{
                                    minutes(
                                        rows.reduce((s, r) => s + r.planned, 0),
                                    )
                                }}</strong>
                                planificate</span
                            ><span
                                ><strong>{{
                                    minutes(
                                        Math.abs(
                                            totalMinutes -
                                                rows.reduce(
                                                    (s, r) => s + r.planned,
                                                    0,
                                                ),
                                        ),
                                    )
                                }}</strong>
                                diferență absolută, de verificat</span
                            >
                        </div>
                        <div class="table-scroll">
                            <table class="monthly-table">
                                <thead>
                                    <tr>
                                        <th>COLEG / ZI</th>
                                        <th v-for="d in summary?.days" :key="d">
                                            {{ d }}
                                        </th>
                                        <th>TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="row in rows"
                                        :key="row.membership.id"
                                    >
                                        <th>{{ row.membership.user.name }}</th>
                                        <td
                                            v-for="day in row.days"
                                            :key="day.date"
                                            :class="{
                                                'weekend-cell': !day.planned,
                                                'missing-cell': day.missing,
                                                'filled-cell': day.entry,
                                            }"
                                        >
                                            <button
                                                :disabled="
                                                    !!closure?.approved_at ||
                                                    day.date > today ||
                                                    !row.membership.active
                                                "
                                                :aria-label="`${row.membership.user.name}, ${readableDate(day.date)}, ${day.entry ? day.entry.absence || minutes(day.entry.minutes) : day.missing ? 'neconfirmat' : 'fără înregistrare'}`"
                                                @click="
                                                    openEditor(
                                                        row.membership,
                                                        day.date,
                                                        day.entry,
                                                    )
                                                "
                                            >
                                                {{
                                                    day.entry
                                                        ? day.entry.absence ||
                                                          (
                                                              day.entry
                                                                  .minutes / 60
                                                          ).toLocaleString(
                                                              'ro-RO',
                                                              {
                                                                  maximumFractionDigits: 2,
                                                              },
                                                          )
                                                        : day.missing
                                                          ? '?'
                                                          : '·'
                                                }}
                                            </button>
                                        </td>
                                        <td>
                                            <strong>{{
                                                minutes(row.confirmed)
                                            }}</strong>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="table-footer">
                            <span
                                ><span class="legend-square missing"></span>?
                                Neconfirmat</span
                            ><span
                                ><span class="legend-square filled"></span>Ore
                                efective</span
                            ><span>CO / CM / CFP / A · absențe</span>
                        </div>
                        <p class="hint panel-padding">
                            Valorile zilnice sunt afișate în ore. Excelul
                            păstrează minutele exacte. Diferențele necesită
                            verificare și nu determină automat drepturi
                            salariale.
                        </p>
                    </section>
                    <section class="panel exceptions-panel">
                        <div class="panel-heading">
                            <h2>
                                Excepții de verificat
                                <span class="count-badge">{{
                                    summary?.issues.length
                                }}</span>
                            </h2>
                        </div>
                        <div v-if="!summary?.issues.length" class="all-good">
                            <Icon name="success" :size="25" />Nu există excepții
                            pentru această lună.
                        </div>
                        <div
                            v-for="issue in summary?.issues"
                            :key="`${issue.membership_id}-${issue.date}`"
                            class="exception-row"
                        >
                            <div>
                                <strong>{{ issue.name }}</strong
                                ><span
                                    >{{ readableDate(issue.date) }} ·
                                    {{ issue.type }}</span
                                >
                            </div>
                            <button
                                v-if="issue.entry_id"
                                class="button secondary compact"
                                @click="review(issue.entry_id)"
                            >
                                Aprobă înregistrarea</button
                            ><button
                                v-else
                                class="button secondary compact"
                                @click="
                                    openEditor(
                                        members.find(
                                            (m) => m.id === issue.membership_id,
                                        )!,
                                        issue.date,
                                        null,
                                    )
                                "
                            >
                                Corectează
                            </button>
                        </div>
                    </section>
                    <section class="panel audit-panel">
                        <div class="panel-heading">
                            <h2>Istoric modificări și exporturi</h2>
                            <Icon name="history" :size="19" />
                        </div>
                        <div
                            v-for="event in audit"
                            :key="event.id"
                            class="audit-row"
                        >
                            <span class="audit-dot"></span>
                            <div>
                                <strong>{{ event.action }}</strong>
                                <p>
                                    {{ event.user?.name || 'Sistem'
                                    }}<template v-if="event.reason">
                                        · {{ event.reason }}</template
                                    ><template v-if="event.after?.month">
                                        · {{ event.after.month }}</template
                                    >
                                </p>
                            </div>
                            <time>{{
                                new Date(event.created_at).toLocaleString(
                                    'ro-RO',
                                    {
                                        timeZone: company.timezone,
                                        dateStyle: 'short',
                                        timeStyle: 'short',
                                    },
                                )
                            }}</time>
                        </div>
                        <p v-if="!audit.length" class="empty-cell">
                            Modificările și exporturile vor apărea aici.
                        </p>
                    </section>
                </template>

                <template v-else-if="section === 'team'">
                    <div
                        v-if="flash.invitation_url"
                        class="invitation-link-box"
                    >
                        <Icon name="success" />
                        <div>
                            <strong>Invitația este pregătită</strong
                            ><input
                                :value="flash.invitation_url"
                                readonly
                                aria-label="Link individual de invitație"
                                @focus="
                                    ($event.target as HTMLInputElement).select()
                                "
                            />
                        </div>
                        <button class="button secondary" @click="copyInvite">
                            <Icon
                                :name="copied ? 'check' : 'copy'"
                                :size="17"
                            />{{ copied ? 'Copiat' : 'Copiază linkul' }}
                        </button>
                    </div>
                    <div class="seat-banner">
                        <Icon name="users" :size="20" /><span
                            ><strong
                                >{{ occupiedSeats }} din
                                {{ seatLimit }} locuri</strong
                            >
                            ocupate sau rezervate prin invitații.</span
                        ><Link href="/billing" class="text-button"
                            >Vezi planul</Link
                        >
                    </div>
                    <section class="panel">
                        <div class="panel-heading">
                            <h2>
                                Colegii tăi
                                <span class="count-badge">{{
                                    members.length
                                }}</span>
                            </h2>
                            <span class="muted small"
                                >Invitațiile sunt valabile 7 zile</span
                            >
                        </div>
                        <div class="table-scroll">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>COLEG</th>
                                        <th>ROL</th>
                                        <th>STARE</th>
                                        <th>ACȚIUNI</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(m, i) in members" :key="m.id">
                                        <td>
                                            <div class="person-cell">
                                                <span
                                                    class="avatar"
                                                    :class="`avatar-${i % 4}`"
                                                    >{{
                                                        initials(m.user.name)
                                                    }}</span
                                                >
                                                <div>
                                                    <strong>{{
                                                        m.user.name
                                                    }}</strong
                                                    ><span>{{
                                                        m.user.email
                                                    }}</span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            {{
                                                m.role === 'owner'
                                                    ? 'Proprietar'
                                                    : 'Angajat'
                                            }}<span
                                                v-if="!m.tracked"
                                                class="muted"
                                            >
                                                · fără pontaj</span
                                            >
                                        </td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="
                                                    m.active
                                                        ? 'positive'
                                                        : 'neutral'
                                                "
                                                >{{
                                                    m.active
                                                        ? 'Activ'
                                                        : 'Inactiv'
                                                }}</span
                                            >
                                        </td>
                                        <td>
                                            <button
                                                v-if="m.role !== 'owner'"
                                                class="text-button"
                                                @click="memberState(m)"
                                            >
                                                {{
                                                    m.active
                                                        ? 'Dezactivează'
                                                        : 'Reactivează'
                                                }}
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <section class="panel invitations-panel">
                        <div class="panel-heading">
                            <h2>
                                Invitații în așteptare
                                <span class="count-badge">{{
                                    invitations.length
                                }}</span>
                            </h2>
                        </div>
                        <div
                            v-for="i in invitations"
                            :key="i.id"
                            class="exception-row"
                        >
                            <div>
                                <strong>{{ i.name }}</strong
                                ><span
                                    >{{ i.email }} · expiră
                                    {{
                                        new Date(
                                            i.expires_at,
                                        ).toLocaleDateString('ro-RO')
                                    }}</span
                                >
                            </div>
                            <div class="inline-actions">
                                <button
                                    class="text-button"
                                    @click="
                                        invite.first_name = i.first_name;
                                        invite.last_name = i.last_name;
                                        invite.email = i.email;
                                        showInvite = true;
                                    "
                                >
                                    Regenerează linkul</button
                                ><button
                                    class="text-button danger-text"
                                    @click="
                                        router.delete('/invitations/' + i.id, {
                                            preserveScroll: true,
                                        })
                                    "
                                >
                                    Revocă
                                </button>
                            </div>
                        </div>
                        <div v-if="!invitations.length" class="empty-state">
                            <Icon name="mail" :size="28" />
                            <h3>Nicio invitație în așteptare</h3>
                            <p>
                                Invită un coleg. Va putea confirma prima zi
                                imediat ce își activează contul.
                            </p>
                            <button
                                class="button secondary"
                                @click="showInvite = true"
                            >
                                <Icon name="plus" :size="16" />Invită un coleg
                            </button>
                        </div>
                    </section>
                    <p v-if="page.props.errors?.active" class="form-error">
                        {{ page.props.errors.active }}
                    </p>
                </template>

                <template v-else-if="section === 'history'">
                    <div class="section-toolbar">
                        <label class="inline-label"
                            >Luna<input
                                type="month"
                                :value="month"
                                @change="
                                    changeMonth(
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                " /></label
                        ><span class="badge positive"
                            >{{
                                minutes(
                                    ownEntries.reduce(
                                        (s, e) => s + e.minutes,
                                        0,
                                    ),
                                )
                            }}
                            înregistrate</span
                        >
                    </div>
                    <section class="panel">
                        <div class="panel-heading">
                            <h2>Zilele mele · {{ monthLabel }}</h2>
                        </div>
                        <div class="table-scroll">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>DATA</th>
                                        <th>ÎNREGISTRARE</th>
                                        <th>DURATĂ</th>
                                        <th>CONFIRMARE</th>
                                        <th>APROBARE</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="e in ownEntries" :key="e.id">
                                        <td>
                                            {{
                                                readableDate(e.date, {
                                                    weekday: 'short',
                                                    day: 'numeric',
                                                    month: 'long',
                                                })
                                            }}
                                        </td>
                                        <td>
                                            {{
                                                e.absence
                                                    ? absences[e.absence]
                                                    : 'Zi lucrată'
                                            }}
                                        </td>
                                        <td>{{ minutes(e.minutes) }}</td>
                                        <td>
                                            {{
                                                e.confirmed_at
                                                    ? 'Confirmată'
                                                    : 'Corectată de proprietar'
                                            }}
                                        </td>
                                        <td>
                                            <span
                                                class="badge"
                                                :class="
                                                    e.approved_at
                                                        ? 'positive'
                                                        : 'neutral'
                                                "
                                                >{{
                                                    e.approved_at
                                                        ? 'Aprobată'
                                                        : 'În așteptare'
                                                }}</span
                                            >
                                        </td>
                                        <td>
                                            <button
                                                v-if="!closure?.approved_at"
                                                class="icon-button"
                                                aria-label="Modifică ziua"
                                                @click="
                                                    openEditor(
                                                        membership,
                                                        e.date.slice(0, 10),
                                                        e,
                                                    )
                                                "
                                            >
                                                <Icon name="edit" :size="17" />
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!ownEntries.length">
                                        <td colspan="6" class="empty-cell">
                                            Încă nu ai înregistrări în această
                                            lună. Începe cu ziua curentă.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                    <Link href="/today" class="button primary fit"
                        ><Icon name="clock" :size="17" />Mergi la ziua mea</Link
                    >
                </template>

                <template v-else-if="section === 'settings'">
                    <div class="settings-grid">
                        <form
                            class="panel settings-form"
                            @submit.prevent="
                                settings.post('/company/settings', {
                                    preserveScroll: true,
                                })
                            "
                        >
                            <div class="panel-heading">
                                <h2>Firma și notificările</h2>
                                <Icon name="building" :size="21" />
                            </div>
                            <div class="form-body">
                                <CompanyIdentityFields
                                    id="settings-company"
                                    v-model:cif="settings.cif"
                                    v-model:name="settings.name"
                                    :cif-error="settings.errors.cif"
                                    :name-error="settings.errors.name"
                                    :verified-at="company.registry_verified_at"
                                    :show-cif="
                                        company.customer_type !== 'consumer'
                                    "
                                    :name-label="
                                        company.customer_type === 'consumer'
                                            ? 'Numele spațiului'
                                            : 'Numele firmei'
                                    "
                                    :require-cif="
                                        !!company.cif && !company.is_demo
                                    "
                                    :lookup-enabled="!company.is_demo"
                                    @update:cif="settings.clearErrors('cif')"
                                    @update:name="settings.clearErrors('name')"
                                />
                                <label
                                    >Fusul orar<input
                                        :value="company.timezone"
                                        disabled
                                    /><span class="hint"
                                        >Fusul ales la configurare păstrează
                                        consecvența istoricului.</span
                                    ></label
                                ><label class="checkbox-label"
                                    ><input
                                        v-model="settings.reminders"
                                        type="checkbox"
                                    />Trimite mementouri echipei și sinteza
                                    proprietarului</label
                                >
                                <hr />
                                <label
                                    >Emailul contabilului<input
                                        v-model="settings.accountant_email"
                                        type="email"
                                        placeholder="contabil@exemplu.ro"
                                    /><span
                                        v-if="settings.errors.accountant_email"
                                        class="form-error"
                                        >{{
                                            settings.errors.accountant_email
                                        }}</span
                                    ></label
                                ><label class="checkbox-label"
                                    ><input
                                        v-model="settings.accountant_confirmed"
                                        type="checkbox"
                                    />Confirm adresa contabilului și autorizez
                                    trimiterea pontajului după aprobarea
                                    lunii.</label
                                >
                                <p class="hint">
                                    Trimiterea automată este inclusă în planul
                                    Echipă. Linkurile sunt securizate și expiră
                                    în 7 zile.
                                </p>
                                <a class="button secondary" href="/company/data"
                                    ><Icon name="download" :size="17" />Exportă
                                    toate datele firmei (JSON)</a
                                ><button
                                    class="button primary"
                                    :disabled="settings.processing"
                                >
                                    {{
                                        settings.processing
                                            ? 'Se salvează…'
                                            : 'Salvează setările'
                                    }}
                                </button>
                            </div>
                        </form>
                        <form
                            class="panel settings-form"
                            @submit.prevent="
                                workSchedule
                                    .transform((d) => ({
                                        ...d,
                                        membership_id: d.membership_id || null,
                                    }))
                                    .post('/schedules', {
                                        preserveScroll: true,
                                    })
                            "
                        >
                            <div class="panel-heading">
                                <h2>Programul obișnuit</h2>
                                <Icon name="calendar" :size="21" />
                            </div>
                            <div class="form-body">
                                <label
                                    >Se aplică pentru<select
                                        v-model="workSchedule.membership_id"
                                    >
                                        <option value="">
                                            Întreaga echipă
                                        </option>
                                        <option
                                            v-for="m in members.filter(
                                                (m) => m.active && m.tracked,
                                            )"
                                            :key="m.id"
                                            :value="m.id"
                                        >
                                            {{ m.user.name }}
                                        </option>
                                    </select></label
                                ><label
                                    >În vigoare din<input
                                        v-model="workSchedule.effective_from"
                                        type="date"
                                        :min="today"
                                        required
                                /></label>
                                <fieldset>
                                    <legend>Zile de lucru</legend>
                                    <div class="weekday-picker">
                                        <label
                                            v-for="(day, i) in weekdays"
                                            :key="day"
                                            :class="{
                                                chosen: workSchedule.weekdays.includes(
                                                    i + 1,
                                                ),
                                            }"
                                            ><input
                                                v-model="workSchedule.weekdays"
                                                type="checkbox"
                                                :value="i + 1"
                                            />{{ day }}</label
                                        >
                                    </div>
                                </fieldset>
                                <div class="two-columns">
                                    <label
                                        >Început<input
                                            v-model="workSchedule.start_time"
                                            type="time"
                                            required /></label
                                    ><label
                                        >Sfârșit<input
                                            v-model="workSchedule.end_time"
                                            type="time"
                                            required
                                    /></label>
                                </div>
                                <p class="info-note">
                                    <Icon name="history" :size="18" />Fiecare
                                    schimbare păstrează programul anterior.
                                    Orele propuse nu confirmă automat ziua.
                                </p>
                                <p
                                    v-for="(error, k) in workSchedule.errors"
                                    :key="k"
                                    class="form-error"
                                >
                                    {{ error }}
                                </p>
                                <button
                                    class="button primary"
                                    :disabled="workSchedule.processing"
                                >
                                    Salvează programul
                                </button>
                            </div>
                        </form>
                    </div>
                    <section class="panel audit-panel">
                        <div class="panel-heading">
                            <h2>Istoricul programelor</h2>
                        </div>
                        <div
                            v-for="s in schedules"
                            :key="s.id"
                            class="exception-row"
                        >
                            <div>
                                <strong>{{
                                    s.membership_id
                                        ? members.find(
                                              (m) => m.id === s.membership_id,
                                          )?.user.name
                                        : 'Programul echipei'
                                }}</strong
                                ><span
                                    >Din
                                    {{
                                        readableDate(s.effective_from, {
                                            day: 'numeric',
                                            month: 'long',
                                            year: 'numeric',
                                        })
                                    }}
                                    ·
                                    {{
                                        s.weekdays
                                            .map((d) => weekdays[d - 1])
                                            .join(', ')
                                    }}</span
                                >
                            </div>
                            <strong
                                >{{ s.start_time }} – {{ s.end_time }}</strong
                            >
                        </div>
                    </section>
                </template>

                <template v-else-if="section === 'billing'">
                    <p class="billing-rights-links">
                        <Link href="/retragere"
                            >Retrageți-vă din contract aici</Link
                        >
                        ·
                        <Link href="/abonamente-si-anulare"
                            >Anulare, reînnoire și rambursări</Link
                        >
                    </p>
                    <div
                        v-if="!billingEnabled"
                        class="info-note billing-notice"
                    >
                        <Icon name="shield" />
                        <div>
                            <strong>Mod pentru instalare proprie</strong>
                            <p>
                                Facturarea este dezactivată. Limita configurată
                                este de {{ seatLimit }} persoane. Istoricul,
                                autorizarea și exporturile sunt disponibile.
                            </p>
                        </div>
                    </div>
                    <div
                        v-else-if="!billingReady"
                        class="info-note billing-notice"
                    >
                        <Icon name="card" />
                        <div>
                            <strong>Plățile sunt în pregătire</strong>
                            <p>
                                Checkoutul de test necesită cheile Stripe și
                                prețul configurat. Nu se procesează încasări
                                reale.
                            </p>
                        </div>
                    </div>
                    <div
                        v-if="occupiedSeats > seatLimit"
                        class="offline-banner"
                    >
                        Ai {{ occupiedSeats - seatLimit }} locuri peste limită.
                        Dezactivează membri sau revocă invitații pentru a adăuga
                        persoane. Istoricul și exporturile rămân disponibile.
                    </div>
                    <div class="pricing-grid">
                        <article class="panel pricing-card">
                            <span class="eyebrow">UN ÎNCEPUT SIMPLU</span>
                            <h2>Gratuit</h2>
                            <p>Pentru echipe la început de drum.</p>
                            <div class="price">0 <span>lei / lună</span></div>
                            <ul class="feature-list">
                                <li>
                                    <Icon name="check" />Până la 5 persoane
                                    active
                                </li>
                                <li>
                                    <Icon name="check" />Confirmare zilnică și
                                    absențe
                                </li>
                                <li>
                                    <Icon name="check" />Mementouri prin email
                                </li>
                                <li>
                                    <Icon name="check" />Export Excel, în
                                    fiecare lună
                                </li>
                            </ul>
                            <span class="button secondary full">{{
                                !subscribed
                                    ? 'Planul de bază'
                                    : 'Disponibil prin portal'
                            }}</span>
                        </article>
                        <article class="panel pricing-card highlighted">
                            <span class="plan-tag"
                                >MAI MULT LOC PENTRU ECHIPĂ</span
                            ><span class="eyebrow">GRIJĂ ÎN MINUS</span>
                            <h2>Echipă</h2>
                            <p>Pentru echipe care cresc împreună.</p>
                            <div class="price">
                                49 <span>lei / lună, preț final</span>
                            </div>
                            <ul class="feature-list">
                                <li>
                                    <Icon name="check" />Până la 15 persoane
                                    active
                                </li>
                                <li>
                                    <Icon name="check" />Toate funcțiile
                                    planului Gratuit
                                </li>
                                <li>
                                    <Icon name="check" />Trimitere automată
                                    către contabil
                                </li>
                                <li>
                                    <Icon name="check" />Link de descărcare
                                    securizat
                                </li>
                            </ul>
                            <div
                                v-if="!subscribed"
                                class="billing-legal-summary"
                            >
                                <p>
                                    49 lei/lună, reînnoire lunară până la
                                    anulare. Fără perioadă minimă anuală.
                                    Operator neînregistrat în scopuri de TVA.
                                </p>
                                <label class="checkbox-label legal-consent"
                                    ><input
                                        v-model="billingForm.terms_accepted"
                                        type="checkbox"
                                    /><span
                                        >Accept
                                        <Link
                                            href="/abonamente-si-anulare"
                                            target="_blank"
                                            >condițiile abonamentului</Link
                                        >
                                        și
                                        <Link
                                            href="/termeni-si-conditii"
                                            target="_blank"
                                            >termenii serviciului</Link
                                        >.</span
                                    ></label
                                >
                                <p
                                    v-if="billingForm.errors.terms_accepted"
                                    class="form-error"
                                    role="alert"
                                >
                                    {{ billingForm.errors.terms_accepted }}
                                </p>
                                <label
                                    v-if="company.customer_type === 'consumer'"
                                    class="checkbox-label legal-consent"
                                    ><input
                                        v-model="
                                            billingForm.service_start_requested
                                        "
                                        type="checkbox"
                                    /><span
                                        >Cer expres începerea serviciului
                                        înainte de expirarea celor 14 zile de
                                        retragere. Am citit
                                        <Link href="/retragere" target="_blank"
                                            >informarea privind retragerea</Link
                                        >
                                        și eventuala plată proporțională. Nu
                                        renunț la drepturile mele legale.</span
                                    ></label
                                >
                                <p
                                    v-if="
                                        billingForm.errors
                                            .service_start_requested
                                    "
                                    class="form-error"
                                    role="alert"
                                >
                                    {{
                                        billingForm.errors
                                            .service_start_requested
                                    }}
                                </p>
                                <p
                                    v-if="company.customer_type === 'consumer'"
                                    class="hint"
                                >
                                    Pentru începerea după perioada de retragere,
                                    contactează office@ipontaj.ro înainte de
                                    comandă.
                                </p>
                                <p
                                    v-if="billingForm.errors.legal_version"
                                    class="form-error"
                                >
                                    Documentele s-au actualizat. Reîncarcă
                                    pagina.
                                </p>
                            </div>
                            <button
                                class="button primary full"
                                :disabled="
                                    !billingReady ||
                                    company.is_demo ||
                                    billingForm.processing
                                "
                                @click="
                                    subscribed
                                        ? router.post('/billing/portal')
                                        : billingForm.post('/billing/checkout')
                                "
                            >
                                {{
                                    subscribed
                                        ? 'Gestionează abonamentul'
                                        : 'Încearcă plata în modul de test'
                                }}
                            </button>
                        </article>
                    </div>
                    <p class="hint">
                        Preț final: 49 lei/lună. Operator neînregistrat în
                        scopuri de TVA. Facturarea fiscală trebuie configurată
                        înainte de activarea încasărilor reale. Plata este
                        confirmată doar după evenimente Stripe verificate.
                    </p>
                    <button
                        v-if="billingReady && subscribed"
                        class="button secondary fit"
                        @click="router.post('/billing/portal')"
                    >
                        Deschide portalul de abonament
                    </button>
                </template>
                <LegalFooter compact />
            </main>
        </div>
        <DayEditor
            v-if="editor"
            :company="company"
            :member="editor.member"
            :date="editor.date"
            :entry="editor.entry"
            :schedule="editor.schedule"
            :owner="owner"
            :absences="absences"
            @close="editor = null"
        />
        <div
            v-if="showInvite"
            class="modal-backdrop"
            @click.self="showInvite = false"
        >
            <section
                class="modal-card"
                role="dialog"
                aria-modal="true"
                aria-labelledby="invite-title"
            >
                <form @submit.prevent="sendInvite">
                    <div class="modal-heading">
                        <div>
                            <span class="eyebrow">MAI BINE ÎMPREUNĂ</span>
                            <h2 id="invite-title">Invită un coleg</h2>
                            <p>
                                Un link personal. Câteva secunde până în echipă.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="icon-button"
                            aria-label="Închide"
                            @click="showInvite = false"
                        >
                            <Icon name="close" />
                        </button>
                    </div>
                    <p v-if="company.is_demo" class="info-note">
                        În demo poți explora echipa fictivă. Creează un cont
                        pentru a trimite invitații reale.
                    </p>
                    <template v-else
                        ><div class="person-name-fields">
                            <label for="invite-last-name"
                                >Nume<input
                                    id="invite-last-name"
                                    v-model="invite.last_name"
                                    autocomplete="family-name"
                                    required
                                    maxlength="100"
                                    placeholder="Ex. Ionescu"
                                /><span
                                    v-if="invite.errors.last_name"
                                    class="form-error"
                                    >{{ invite.errors.last_name }}</span
                                ></label
                            ><label for="invite-first-name"
                                >Prenume<input
                                    id="invite-first-name"
                                    v-model="invite.first_name"
                                    autocomplete="given-name"
                                    required
                                    maxlength="100"
                                    placeholder="Ex. Maria"
                                /><span
                                    v-if="invite.errors.first_name"
                                    class="form-error"
                                    >{{ invite.errors.first_name }}</span
                                ></label
                            >
                        </div>
                        <label
                            >Adresa de email<input
                                v-model="invite.email"
                                type="email"
                                required
                                placeholder="maria@exemplu.ro"
                            /><span class="form-error">{{
                                invite.errors.email
                            }}</span></label
                        ><label class="checkbox-label"
                            ><input
                                v-model="invite.send_email"
                                type="checkbox"
                            />Trimite invitația și prin email</label
                        >
                        <p class="hint">
                            Linkul expiră în 7 zile și rezervă un loc în plan.
                            Regenerarea dezactivează linkul anterior.
                        </p></template
                    >
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="button secondary"
                            @click="showInvite = false"
                        >
                            Închide</button
                        ><Link
                            v-if="company.is_demo"
                            href="/demo/exit"
                            method="post"
                            as="button"
                            class="button primary"
                            >Creează cont gratuit</Link
                        ><button
                            v-else
                            class="button primary"
                            :disabled="invite.processing"
                        >
                            <Icon name="plus" :size="17" />{{
                                invite.processing
                                    ? 'Se pregătește…'
                                    : 'Creează invitația'
                            }}
                        </button>
                    </div>
                </form>
            </section>
        </div>
        <div v-if="reopen" class="modal-backdrop" @click.self="reopen = false">
            <section
                class="modal-card"
                role="dialog"
                aria-modal="true"
                aria-label="Redeschide luna"
            >
                <form @submit.prevent="reopenMonth">
                    <div class="modal-heading">
                        <div>
                            <h2>Redeschide {{ monthLabel }}</h2>
                            <p>Corecturile vor fi înregistrate în istoric.</p>
                        </div>
                        <button
                            type="button"
                            class="icon-button"
                            aria-label="Închide"
                            @click="reopen = false"
                        >
                            <Icon name="close" />
                        </button>
                    </div>
                    <label
                        >Motivul redeschiderii<textarea
                            v-model="closeForm.reason"
                            required
                            maxlength="1000"
                            rows="3"
                        ></textarea
                        ><span class="form-error">{{
                            closeForm.errors.reason
                        }}</span></label
                    >
                    <div class="modal-footer">
                        <button
                            type="button"
                            class="button secondary"
                            @click="reopen = false"
                        >
                            Renunță</button
                        ><button
                            class="button primary"
                            :disabled="closeForm.processing"
                        >
                            Redeschide luna
                        </button>
                    </div>
                </form>
            </section>
        </div>
        <div v-if="help" class="modal-backdrop" @click.self="help = false">
            <section
                class="modal-card help-card"
                role="dialog"
                aria-modal="true"
                aria-label="Ajutor și instalare"
            >
                <div class="modal-heading">
                    <div>
                        <span class="eyebrow">SIMPLU, ZI DE ZI</span>
                        <h2>Cum folosești iPontaj</h2>
                    </div>
                    <button
                        class="icon-button"
                        aria-label="Închide"
                        @click="help = false"
                    >
                        <Icon name="close" />
                    </button>
                </div>
                <div class="help-item">
                    <Icon name="users" />
                    <div>
                        <h3>Invită echipa</h3>
                        <p>
                            Copiază linkul personal sau trimite-l prin email.
                            Colegul își creează contul și acceptă invitația.
                        </p>
                    </div>
                </div>
                <div class="help-item">
                    <Icon name="calendarcheck" />
                    <div>
                        <h3>Confirmați ziua</h3>
                        <p>
                            În „Ziua mea”, confirmă intervalul propus, ajustează
                            orele sau alege o absență.
                        </p>
                    </div>
                </div>
                <div class="help-item">
                    <Icon name="excel" />
                    <div>
                        <h3>Încheie luna</h3>
                        <p>
                            Rezolvă excepțiile, aprobă luna și descarcă Excelul
                            pentru verificarea contabilului.
                        </p>
                    </div>
                </div>
                <div class="help-item">
                    <Icon name="phone" />
                    <div>
                        <h3>Instalează pe telefon</h3>
                        <p>
                            Android: meniul browserului → „Instalează
                            aplicația”. iPhone: Safari → Partajează → „Adăugați
                            la ecranul principal”. Aplicația are nevoie de
                            internet.
                        </p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="button primary" @click="help = false">
                        Am înțeles
                    </button>
                </div>
            </section>
        </div>
    </div>
</template>
