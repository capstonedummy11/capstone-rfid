<script setup>
import AuthNavbar from './AuthNavbar.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? {});
const userInitial = computed(() => currentUser.value?.name?.charAt(0)?.toUpperCase() || '?');
</script>

<template>
    <div class="flex h-screen overflow-hidden">
        <AuthNavbar v-if="$page.props.auth.user" />
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <!-- Top Header -->
            <header
                class="flex h-20 shrink-0 items-center justify-between gap-4 bg-white p-4 drop-shadow-sm"
            >
                <h1 class="text-[20px] font-bold">{{ $page.props.title }}</h1>
                <div
                    v-if="$page.props.auth.user"
                    class="flex min-w-0 items-center gap-3 rounded-md border border-slate-100 bg-white px-3 py-2"
                >
                    <div class="min-w-0 text-right">
                        <div class="truncate text-sm font-semibold text-slate-800">
                            {{ currentUser.name }}
                        </div>
                        <div class="truncate text-xs text-slate-500">
                            {{ currentUser.email }}
                        </div>
                    </div>
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white"
                    >
                        {{ userInitial }}
                    </div>
                </div>
            </header>
            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto bg-gray-100">
                <slot />
            </main>
        </div>
    </div>
</template>
