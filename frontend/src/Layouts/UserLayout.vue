<script setup lang="ts">
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import FlashToast from '@/Components/FlashToast.vue';
import NotificationBell from '@/Components/NotificationBell.vue';

const page = usePage();
const user = computed(() => page.props.auth.user);
const badges = computed(() => (page.props as any).sidebar_badges || {});
const isFaculty = computed(() => user.value.role === 'faculty');
const prefix = computed(() => isFaculty.value ? 'faculty' : 'student');
const sidebarOpen = ref(false);
const initials = computed(() => (user.value.name || '').split(' ').map((n: string) => n[0]).slice(0, 2).join('').toUpperCase());
const portalLabel = computed(() => isFaculty.value ? 'Faculty Portal' : 'Student Portal');
const portalColor = computed(() => isFaculty.value ? 'text-blue-400' : 'text-green-400');

const navigation = computed(() => [
    { name: 'Dashboard',       href: `${prefix.value}.dashboard`,         icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6' },
    { name: 'Browse Equipment', href: `${prefix.value}.equipment`,         icon: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z' },
    { name: 'Activities',      href: `${prefix.value}.activities.index`,   icon: 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2' },
    { name: 'My Borrowings',   href: `${prefix.value}.borrowings.index`,   icon: 'M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4', badge: isFaculty.value ? 'pending_borrowings' : 'active_borrowings' },
    { name: 'New Request',     href: `${prefix.value}.borrowings.create`,  icon: 'M12 4v16m8-8H4' },
    { name: 'History',         href: `${prefix.value}.history`,            icon: 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z' },
]);

const isActive = (routeName: string) => route().current(routeName) || route().current(routeName + '.*');
const currentSection = computed(() => navigation.value.find(item => isActive(item.href))?.name ?? null);
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Sidebar desktop -->
        <div class="hidden md:fixed md:inset-y-0 md:flex md:w-64 md:flex-col">
            <div class="flex min-h-0 flex-1 flex-col bg-green-950">
                <!-- Logo -->
                <div class="flex items-center gap-3 px-5 pt-5 pb-4 border-b border-white/10">
                    <div class="w-8 h-8 rounded-lg bg-green-500 flex items-center justify-center flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-white">ChemLab</p>
                        <p :class="['text-xs font-medium', portalColor]">{{ portalLabel }}</p>
                    </div>
                </div>

                <!-- Nav -->
                <div class="flex flex-1 flex-col overflow-y-auto py-3">
                    <nav class="flex-1 px-3 space-y-0.5">
                        <Link
                            v-for="item in navigation"
                            :key="item.name"
                            :href="route(item.href)"
                            :class="[
                                isActive(item.href)
                                    ? 'bg-green-500/15 text-white border-l-2 border-green-400'
                                    : 'text-green-200 hover:bg-green-800/50 hover:text-white border-l-2 border-transparent',
                                'group flex items-center gap-3 pl-3 pr-3 py-2.5 text-sm font-medium rounded-r-lg transition-all duration-150'
                            ]"
                        >
                            <svg :class="[isActive(item.href) ? 'text-green-400' : 'text-green-400 group-hover:text-green-200']" class="h-[18px] w-[18px] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                            </svg>
                            <span class="flex-1">{{ item.name }}</span>
                            <span v-if="item.badge && badges[item.badge]" class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-red-500 text-white">
                                {{ badges[item.badge] > 99 ? '99+' : badges[item.badge] }}
                            </span>
                        </Link>
                    </nav>
                </div>

                <!-- User footer -->
                <div class="border-t border-white/10 p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-green-600 flex items-center justify-center flex-shrink-0 text-white text-xs font-bold">
                            {{ initials }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-white leading-tight break-words">{{ user.name }}</p>
                            <p class="text-xs text-green-300 capitalize">{{ user.role }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile top bar -->
        <div class="sticky top-0 z-10 md:hidden bg-green-950 px-4 py-3 flex items-center justify-between shadow">
            <button @click="sidebarOpen = true" class="text-gray-400 hover:text-white">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            <span class="text-white font-bold text-sm">ChemLab</span>
            <NotificationBell />
        </div>

        <!-- Mobile sidebar drawer -->
        <div v-if="sidebarOpen" class="relative z-40 md:hidden">
            <div class="fixed inset-0 bg-black/60" @click="sidebarOpen = false"></div>
            <div class="fixed inset-0 z-40 flex">
                <div class="relative flex w-full max-w-xs flex-1 flex-col bg-green-950 pt-5 pb-4">
                    <div class="absolute top-0 right-0 -mr-12 pt-2">
                        <button @click="sidebarOpen = false" class="ml-1 flex h-10 w-10 items-center justify-center rounded-full">
                            <svg class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <div class="flex items-center gap-3 px-5 pb-4 border-b border-white/10">
                        <div class="w-8 h-8 rounded-lg bg-green-500 flex items-center justify-center">
                            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                        </div>
                        <span class="text-white font-bold">ChemLab</span>
                    </div>
                    <nav class="mt-4 h-full flex-shrink-0 overflow-y-auto px-3 space-y-0.5">
                        <Link
                            v-for="item in navigation"
                            :key="item.name"
                            :href="route(item.href)"
                            @click="sidebarOpen = false"
                            :class="[
                                isActive(item.href) ? 'bg-green-500/15 text-white' : 'text-green-200 hover:bg-green-800/50 hover:text-white',
                                'flex items-center gap-3 px-3 py-2.5 text-sm font-medium rounded-lg'
                            ]"
                        >
                            <svg class="h-5 w-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                            </svg>
                            {{ item.name }}
                        </Link>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Main content area -->
        <div class="flex flex-1 flex-col md:pl-64">
            <div class="sticky top-0 z-50 flex-shrink-0 bg-white border-b border-gray-100 shadow-sm">
                <div class="flex flex-1 flex-wrap items-center justify-between gap-x-3 gap-y-2 px-4 py-3 sm:h-16 sm:flex-nowrap sm:py-0 sm:px-6">
                    <div class="flex items-center min-w-0">
                        <slot name="header" />
                    </div>
                    <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
                        <NotificationBell :dark="true" />
                        <div class="h-6 w-px bg-gray-200"></div>
                        <Dropdown align="right" width="48">
                            <template #trigger>
                                <button class="flex items-center gap-2.5 text-sm font-medium text-gray-600 hover:text-gray-800 transition-colors">
                                    <div class="w-8 h-8 rounded-full bg-green-600 flex items-center justify-center text-white text-xs font-bold">
                                        {{ initials }}
                                    </div>
                                    <span class="hidden sm:block">{{ user.name }}</span>
                                    <svg class="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </template>
                            <template #content>
                                <DropdownLink :href="route('profile.edit')">Profile</DropdownLink>
                                <DropdownLink :href="route('logout')" method="post" as="button">Log Out</DropdownLink>
                            </template>
                        </Dropdown>
                    </div>
                </div>
            </div>

            <main class="flex-1 py-6">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 md:px-8">
                    <nav v-if="currentSection" class="mb-3 flex items-center gap-1.5 text-xs text-gray-400">
                        <span>{{ portalLabel }}</span>
                        <span>›</span>
                        <span class="font-medium text-gray-600">{{ currentSection }}</span>
                    </nav>
                    <FlashToast />
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
