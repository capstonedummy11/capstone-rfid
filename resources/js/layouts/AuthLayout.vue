<script setup>
import AuthNavbar from './AuthNavbar.vue';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { Menu } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import {
    consumeStaffSavePreference,
    consumeStudentParentSavePreference,
    removeSavedStaffProfile,
    removeSavedStudentParentProfile,
    saveStaffProfile,
    saveStudentParentProfile,
} from '@/composables/useSavedStudentParentProfiles';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? {});
const userInitial = computed(
    () => currentUser.value?.name?.charAt(0)?.toUpperCase() || '?',
);

const isNavOpen = ref(false);
const unreadMessageCount = ref(0);
const latestUnreadMessageId = ref(null);
let messagePollTimer = null;
let messagePollingInitialized = false;

const pollUnreadMessages = async (notify = true) => {
    try {
        const response = await fetch(route('messages.unread-status'), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (!response.ok) return;

        const status = await response.json();
        const latest = status.latest;
        const hasNewMessage =
            notify &&
            messagePollingInitialized &&
            latest?.id &&
            Number(latest.id) !== Number(latestUnreadMessageId.value);

        unreadMessageCount.value = Number(status.unread_count || 0);
        latestUnreadMessageId.value = latest?.id ?? null;
        messagePollingInitialized = true;

        if (hasNewMessage) {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'info',
                title: `New message from ${latest.sender}`,
                text: latest.preview,
                showConfirmButton: true,
                confirmButtonText: 'Open Messenger',
                timer: 8000,
                timerProgressBar: true,
            }).then((result) => {
                if (result.isConfirmed) router.visit(route('messages.index'));
            });
        }
    } catch {
        // A temporary polling failure must not interrupt the current page.
    }
};

onMounted(async () => {
    await pollUnreadMessages(false);
    messagePollTimer = window.setInterval(() => pollUnreadMessages(), 10000);
});

onUnmounted(() => {
    if (messagePollTimer) window.clearInterval(messagePollTimer);
});

watch(
    currentUser,
    (user) => {
        const role = String(user?.role || '').toLowerCase();

        if (['student', 'parent'].includes(role)) {
            const shouldSave = consumeStudentParentSavePreference(user?.email);
            if (shouldSave === true) saveStudentParentProfile(user);
            else if (shouldSave === false)
                removeSavedStudentParentProfile(user?.email);
        }

        if (['admin', 'instructor', 'registrar', 'clinic'].includes(role)) {
            const shouldSave = consumeStaffSavePreference(user?.email);
            if (shouldSave === true) saveStaffProfile(user);
            else if (shouldSave === false) removeSavedStaffProfile(user?.email);
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex h-screen overflow-hidden">
        <AuthNavbar
            v-if="$page.props.auth.user"
            v-model:isNavOpen="isNavOpen"
            :unread-message-count="unreadMessageCount"
        />
        <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
            <!-- Top Header -->
            <header
                class="flex h-20 shrink-0 items-center justify-between gap-4 bg-white p-4 drop-shadow-sm"
            >
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        v-if="$page.props.auth.user"
                        type="button"
                        @click="isNavOpen = !isNavOpen"
                        class="shrink-0 rounded-md p-2 text-slate-600 hover:bg-slate-100"
                    >
                        <Menu class="h-5 w-5" />
                    </button>
                    <h1 class="truncate text-[20px] font-bold text-black">
                        {{ $page.props.title }}
                    </h1>
                </div>

                <div
                    v-if="$page.props.auth.user"
                    class="flex min-w-0 items-center gap-3 rounded-md border border-slate-100 bg-white px-3 py-2"
                >
                    <div class="min-w-0 text-right">
                        <div
                            class="truncate text-sm font-semibold text-slate-800"
                        >
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
                <div
                    class="mx-auto min-h-full w-full max-w-[1180px] p-4 sm:p-6"
                >
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
