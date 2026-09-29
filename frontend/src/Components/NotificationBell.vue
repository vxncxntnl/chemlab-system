<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

interface Notification {
    id: number;
    title: string;
    message: string;
    type: string;
    link: string | null;
    read_at: string | null;
    created_at: string;
}

const props = withDefaults(defineProps<{ dark?: boolean }>(), { dark: false });
const open = ref(false);
const notifications = ref<Notification[]>([]);
const unreadCount = ref(0);
const loading = ref(false);
let pollInterval: ReturnType<typeof setInterval> | null = null;

const fetchNotifications = async () => {
    try {
        const res = await axios.get('/api/notifications');
        notifications.value = res.data.notifications;
        unreadCount.value = res.data.unread_count;
    } catch {}
};

const markAsRead = async (notification: Notification) => {
    if (!notification.read_at) {
        await axios.post(`/api/notifications/${notification.id}/read`);
        notification.read_at = new Date().toISOString();
        unreadCount.value = Math.max(0, unreadCount.value - 1);
    }
    if (notification.link) {
        window.location.href = notification.link;
    }
    open.value = false;
};

const markAllAsRead = async () => {
    await axios.post('/api/notifications/read-all');
    notifications.value.forEach(n => { if (!n.read_at) n.read_at = new Date().toISOString(); });
    unreadCount.value = 0;
};

const toggleOpen = () => {
    open.value = !open.value;
    if (open.value) fetchNotifications();
};

const timeAgo = (dateStr: string) => {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
};

const typeColor = (type: string) => {
    const map: Record<string, string> = {
        success: 'bg-green-100 text-green-700',
        error:   'bg-red-100 text-red-700',
        warning: 'bg-yellow-100 text-yellow-700',
        info:    'bg-blue-100 text-blue-700',
    };
    return map[type] ?? 'bg-gray-100 text-gray-700';
};

const typeDot = (type: string) => {
    const map: Record<string, string> = {
        success: 'bg-green-500',
        error:   'bg-red-500',
        warning: 'bg-yellow-500',
        info:    'bg-blue-500',
    };
    return map[type] ?? 'bg-gray-400';
};

onMounted(() => {
    fetchNotifications();
    pollInterval = setInterval(fetchNotifications, 30000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div id="notification-bell" class="relative z-50">
        <!-- Overlay to close on outside click -->
        <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>

        <!-- Bell Button -->
        <button
            @click="toggleOpen"
            :class="[
                'relative p-2 rounded-full transition focus:outline-none',
                props.dark ? 'text-gray-500 hover:bg-gray-100' : 'text-white hover:bg-white/10'
            ]"
            title="Notifications"
        >
            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
            </svg>
            <!-- Badge -->
            <span
                v-if="unreadCount > 0"
                class="absolute top-1 right-1 flex items-center justify-center w-4 h-4 text-[10px] font-bold bg-red-500 text-white rounded-full"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown -->
        <div
            v-if="open"
            class="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-xl border border-gray-100 z-50 overflow-hidden"
            style="position: absolute;"
        >
            <!-- Header -->
            <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50">
                <span class="text-sm font-semibold text-gray-700">Notifications</span>
                <button
                    v-if="unreadCount > 0"
                    @click="markAllAsRead"
                    class="text-xs text-green-600 hover:text-green-800 font-medium"
                >
                    Mark all as read
                </button>
            </div>

            <!-- List -->
            <div class="max-h-80 overflow-y-auto divide-y divide-gray-50">
                <div v-if="notifications.length === 0" class="py-10 text-center text-sm text-gray-400">
                    <svg class="w-10 h-10 mx-auto mb-2 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    No notifications yet
                </div>

                <button
                    v-for="notif in notifications"
                    :key="notif.id"
                    @click="markAsRead(notif)"
                    class="w-full text-left px-4 py-3 hover:bg-gray-50 transition flex gap-3 items-start"
                    :class="{ 'bg-green-50/50': !notif.read_at }"
                >
                    <!-- Dot -->
                    <div class="mt-1.5 flex-shrink-0">
                        <span class="block w-2 h-2 rounded-full" :class="notif.read_at ? 'bg-gray-300' : typeDot(notif.type)"></span>
                    </div>
                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 truncate">{{ notif.title }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 line-clamp-2">{{ notif.message }}</p>
                        <p class="text-xs text-gray-400 mt-1">{{ timeAgo(notif.created_at) }}</p>
                    </div>
                </button>
            </div>

            <!-- Footer -->
            <div class="px-4 py-2 border-t border-gray-100 bg-gray-50 text-center">
                <span class="text-xs text-gray-400">Showing last 20 notifications</span>
            </div>
        </div>
    </div>
</template>
