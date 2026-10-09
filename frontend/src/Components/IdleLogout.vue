<script setup lang="ts">
/**
 * Logs the user out after a period of inactivity (e.g. a shared lab computer
 * left signed in). A warning with a countdown appears first. Activity in any
 * tab of the system counts — the last-activity time is shared through
 * localStorage — and logging out in one tab sends the other tabs to login.
 */
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';

const IDLE_LIMIT_MS = 30 * 60 * 1000; // log out after 30 minutes idle
const WARNING_MS = 60 * 1000;         // warn during the last 60 seconds
const ACTIVITY_KEY = 'chemlab:last-activity';
const LOGOUT_KEY = 'chemlab:logged-out';
const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'wheel'];

const showWarning = ref(false);
const secondsLeft = ref(60);

let lastActivity = Date.now();
let lastShared = 0;
let timer: number | undefined;
let loggingOut = false;

const readShared = (): number => {
    try { return Number(localStorage.getItem(ACTIVITY_KEY)) || 0; } catch { return 0; }
};

const recordActivity = (force = false) => {
    const now = Date.now();
    lastActivity = now;
    // Writing to localStorage on every mouse move is wasteful; every few
    // seconds is plenty for keeping other tabs in sync.
    if (force || now - lastShared > 5000) {
        lastShared = now;
        try { localStorage.setItem(ACTIVITY_KEY, String(now)); } catch { /* storage unavailable */ }
    }
};

// While the warning is up, only the "Stay logged in" button keeps the
// session — moving the mouse alone doesn't silently dismiss it.
const onActivity = () => { if (!showWarning.value) recordActivity(); };

const logout = () => {
    if (loggingOut) return;
    loggingOut = true;
    window.clearInterval(timer);
    try { localStorage.setItem(LOGOUT_KEY, String(Date.now())); } catch { /* storage unavailable */ }
    router.post(route('logout'), { reason: 'idle' });
};

const stayLoggedIn = () => {
    showWarning.value = false;
    recordActivity(true);
    // Touch the server too, so its session is refreshed as well.
    router.reload({ only: ['unread_notifications'] });
};

const tick = () => {
    const idleFor = Date.now() - Math.max(lastActivity, readShared());
    if (idleFor >= IDLE_LIMIT_MS) {
        logout();
    } else if (idleFor >= IDLE_LIMIT_MS - WARNING_MS) {
        secondsLeft.value = Math.ceil((IDLE_LIMIT_MS - idleFor) / 1000);
        showWarning.value = true;
    } else {
        showWarning.value = false; // e.g. activity in another tab
    }
};

// Another tab logged out (idle or manually): this tab's session is gone too.
const onStorage = (e: StorageEvent) => {
    if (e.key === LOGOUT_KEY && !loggingOut) {
        // Also drop this tab's page-history key, so Back from the login
        // page can't redraw a protected page here.
        router.clearHistory();
        window.location.href = route('login');
    }
};

onMounted(() => {
    recordActivity(true);
    ACTIVITY_EVENTS.forEach(evt => window.addEventListener(evt, onActivity, { passive: true }));
    window.addEventListener('storage', onStorage);
    timer = window.setInterval(tick, 1000);
});

onBeforeUnmount(() => {
    ACTIVITY_EVENTS.forEach(evt => window.removeEventListener(evt, onActivity));
    window.removeEventListener('storage', onStorage);
    window.clearInterval(timer);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="showWarning" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 p-4" role="alertdialog" aria-modal="true" aria-labelledby="idle-title">
            <div class="w-full max-w-sm rounded-xl bg-white p-6 text-center shadow-xl">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-yellow-100">
                    <svg class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h2 id="idle-title" class="text-lg font-semibold text-gray-900">Are you still there?</h2>
                <p class="mt-2 text-sm text-gray-600">
                    For your security, you'll be logged out in
                    <span class="font-semibold text-gray-900">{{ secondsLeft }}</span>
                    second{{ secondsLeft === 1 ? '' : 's' }} due to inactivity.
                </p>
                <div class="mt-5 flex flex-col gap-2 sm:flex-row-reverse">
                    <button type="button" @click="stayLoggedIn"
                        class="inline-flex w-full items-center justify-center rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-green-700 sm:flex-1">
                        Stay logged in
                    </button>
                    <button type="button" @click="logout"
                        class="inline-flex w-full items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 sm:flex-1">
                        Log out now
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
