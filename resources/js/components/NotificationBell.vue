<script setup lang="ts">
import { onMounted, onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import Icon from './UiIcon.vue';
type Item = {
    id: number;
    title: string;
    message: string;
    url: string;
    created_at: string;
    read_at: string | null;
};
const items = ref<Item[]>([]);
const unread = ref(0);
const failed = ref(false);
const menu = ref<HTMLDetailsElement>();
let timer: ReturnType<typeof setInterval> | undefined;
let stopped = false;
let removeNavigationListener: (() => void) | undefined;
async function refresh() {
    if (document.hidden) return;
    try {
        const response = await fetch('/notifications', {
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        });
        if (!response.ok) throw new Error('Unavailable');
        const data = await response.json();
        if (!stopped) {
            items.value = data.items;
            unread.value = data.unread;
            failed.value = false;
        }
    } catch {
        if (!stopped) failed.value = true;
    }
}
function read(item: Item) {
    router.post(
        `/notifications/${item.id}/open`,
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                if (menu.value) menu.value.open = false;
            },
        },
    );
}
function readAll() {
    router.post(
        '/notifications/read-all',
        {},
        { preserveScroll: true, onSuccess: () => void refresh() },
    );
}
function dismiss(event: KeyboardEvent) {
    if (event.key === 'Escape' && menu.value) menu.value.open = false;
}
onMounted(() => {
    void refresh();
    removeNavigationListener = router.on('success', () => void refresh());
    timer = setInterval(() => void refresh(), 30000);
    document.addEventListener('keydown', dismiss);
});
onUnmounted(() => {
    stopped = true;
    removeNavigationListener?.();
    if (timer) clearInterval(timer);
    document.removeEventListener('keydown', dismiss);
});
</script>
<template>
    <details ref="menu" class="notification-menu">
        <summary
            class="icon-button"
            :aria-label="`Notificări${unread ? `, ${unread} necitite` : ''}`"
            @click="refresh"
        >
            <Icon name="bell" :size="20" /><span
                v-if="unread"
                class="notification-count"
                >{{ unread > 99 ? '99+' : unread }}</span
            >
        </summary>
        <section class="notification-panel" aria-label="Notificările tale">
            <div class="flex-between">
                <strong>Notificări</strong
                ><button v-if="unread" class="text-button" @click="readAll">
                    Marchează toate ca citite
                </button>
            </div>
            <p v-if="failed" role="status">
                Nu am putut încărca notificările.
                <button class="text-button" @click="refresh">Reîncearcă</button>
            </p>
            <p v-else-if="!items.length" class="hint">
                Nu ai notificări încă. Aici vei găsi activitatea contului și a
                echipei.
            </p>
            <ul v-else>
                <li v-for="item in items" :key="item.id">
                    <button
                        :class="{ unread: !item.read_at }"
                        @click="read(item)"
                    >
                        <strong>{{ item.title }}</strong
                        ><span>{{ item.message }}</span
                        ><time :datetime="item.created_at">{{
                            new Date(item.created_at).toLocaleString('ro-RO')
                        }}</time>
                    </button>
                </li>
            </ul>
        </section>
    </details>
</template>
