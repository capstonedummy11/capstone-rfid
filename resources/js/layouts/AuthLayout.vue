<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell, Menu } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import { computed, provide, ref, watch } from 'vue';
import { useRealtimePolling } from '@/composables/useRealtimePolling';
import { redirectOnExpiredSession } from '@/lib/idleSession';
import {
    consumeStaffSavePreference,
    consumeStudentParentSavePreference,
    removeSavedStaffProfile,
    removeSavedStudentParentProfile,
    saveStaffProfile,
    saveStudentParentProfile,
} from '@/composables/useSavedStudentParentProfiles';
import AuthNavbar from './AuthNavbar.vue';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user ?? {});
const userInitial = computed(
    () => currentUser.value?.name?.charAt(0)?.toUpperCase() || '?',
);

const isNavOpen = ref(false);
const unreadMessageCount = ref(0);
const latestUnreadMessageId = ref(null);
let messagePollingInitialized = false;
const notificationItems = ref([]);
const unreadNotificationCount = ref(0);
const notificationCursor = ref(0);
const notificationDropdownOpen = ref(false);
let notificationPollingInitialized = false;
const currentUserId = computed(
    () => currentUser.value?.user_id ?? currentUser.value?.id ?? null,
);
const isStudentParent = computed(() =>
    ['student', 'parent'].includes(
        String(currentUser.value?.role || '').toLowerCase(),
    ),
);
const messengerEnabled = computed(() => {
    const role = String(currentUser.value?.role || '').toLowerCase();
    return page.props.featureSettings?.[`messenger_${role}_enabled`] ?? true;
});
const canPollRealtime = computed(() =>
    [
        'admin',
        'instructor',
        'clinic',
        'registrar',
        'student',
        'parent',
    ].includes(String(currentUser.value?.role || '').toLowerCase()),
);
const selectedStudentId = computed(
    () =>
        page.props.selectedStudentId ||
        new URLSearchParams(window.location.search).get('student_id') ||
        null,
);

const notificationRequestUrl = () => {
    const params = {};
    if (selectedStudentId.value) params.student_id = selectedStudentId.value;
    if (notificationCursor.value) params.after_id = notificationCursor.value;

    return route('student-parent.notifications.poll', params);
};

const mergeNotifications = (items) => {
    const byId = new Map(
        notificationItems.value.map((item) => [item.id, item]),
    );
    items.forEach((item) => {
        byId.set(item.id, { ...byId.get(item.id), ...item });
        if (item.kind !== 'message') {
            notificationCursor.value = Math.max(
                notificationCursor.value,
                Number(item.id) || 0,
            );
        }
    });
    notificationItems.value = Array.from(byId.values())
        .sort((a, b) =>
            new Date(b.timestamp || 0).getTime() -
            new Date(a.timestamp || 0).getTime(),
        )
        .slice(0, 50);
};

const openMessenger = (notification) => {
    const params = {};
    if (notification.conversation_user_id) {
        params.conversation_user_id = notification.conversation_user_id;
    }
    if (notification.student_id || selectedStudentId.value) {
        params.student_id = notification.student_id || selectedStudentId.value;
    }
    router.visit(route('messages.index', params));
    notificationDropdownOpen.value = false;
};

const markNotificationRead = (notification, visitAfter = false) => {
    if (notification.read_at) {
        if (visitAfter) {
            notification.kind === 'message'
                ? openMessenger(notification)
                : openNotificationsPage();
        }
        return;
    }

    const readAt = new Date().toISOString();
    notification.read_at = readAt;
    const centerItem = notificationItems.value.find(
        (item) => item.id === notification.id,
    );
    if (centerItem) centerItem.read_at = readAt;
    unreadNotificationCount.value = Math.max(
        0,
        unreadNotificationCount.value - 1,
    );
    const messageNotification = notification.kind === 'message';
    const portalMessageNotification = notification.kind === 'portal_message';
    router.put(
        messageNotification
            ? route('messages.read', { message: notification.message_id })
            : route('student-parent.notifications.read', notification.id),
        { student_id: selectedStudentId.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (visitAfter) {
                    messageNotification
                        ? openMessenger(notification)
                        : openNotificationsPage();
                }
            },
            onError: () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Could not mark notification as read',
                    timer: 4000,
                    showConfirmButton: false,
                });
                void pollNow();
            },
        },
    );
};

const markAllNotificationsRead = () => {
    const now = new Date().toISOString();
    notificationItems.value.forEach((notification) => {
        if (!notification.read_at) notification.read_at = now;
    });
    unreadNotificationCount.value = 0;
    const markMessagesRead = () => router.put(route('messages.read-all'), {}, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'error',
                title: 'Could not mark message notifications as read',
                timer: 4000,
                showConfirmButton: false,
            });
            void pollNow();
        },
    });

    if (!isStudentParent.value) {
        if (messengerEnabled.value) markMessagesRead();
        return;
    }

    router.put(
        route('student-parent.notifications.read-all'),
        { student_id: selectedStudentId.value },
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (messengerEnabled.value) markMessagesRead();
            },
            onError: () => {
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: 'error',
                    title: 'Could not mark notifications as read',
                    timer: 4000,
                    showConfirmButton: false,
                });
                void pollNow();
            },
        },
    );
};

const openNotification = (notification) => {
    if (notification.kind === 'message') {
        markNotificationRead(notification, true);
        return;
    }
    if (isStudentParent.value) {
        markNotificationRead(notification, true);
        notificationDropdownOpen.value = false;
        return;
    }
    notificationDropdownOpen.value = false;
};

const openNotificationsPage = () => {
    router.visit(
        route(
            'student-parent.notifications.index',
            selectedStudentId.value
                ? { student_id: selectedStudentId.value }
                : {},
        ),
    );
    notificationDropdownOpen.value = false;
};

provide('realtimeNotificationCenter', {
    items: notificationItems,
    unreadCount: unreadNotificationCount,
    markRead: markNotificationRead,
    markAllRead: markAllNotificationsRead,
});

const pollRealtime = async () => {
    if (!currentUserId.value) return;

    if (messengerEnabled.value) {
    const messageResponse = await fetch(route('messages.unread-status'), {
        headers: {
            Accept: 'application/json',
            'X-Background-Poll': '1',
        },
        credentials: 'same-origin',
    });
    if (
        redirectOnExpiredSession(
            messageResponse,
            String(page.props.idleTimeout?.login_url || '/'),
        )
    )
        return;
    if (!messageResponse.ok) {
        throw new Error(
            `Unread-message poll failed (${messageResponse.status}).`,
        );
    }

    const status = await messageResponse.json();
    const latest = status.latest;
    const hasNewMessage =
        messagePollingInitialized &&
        latest?.id &&
        Number(latest.id) !== Number(latestUnreadMessageId.value);
    unreadMessageCount.value = Number(status.unread_count || 0);
    mergeNotifications(status.items || []);
    unreadNotificationCount.value = unreadMessageCount.value;
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
            if (result.isConfirmed) openMessenger(latest);
        });
    }
    } else {
        unreadMessageCount.value = 0;
        notificationItems.value = notificationItems.value.filter(
            (item) => item.kind !== 'message',
        );
    }

    if (!isStudentParent.value) return;

    const notificationResponse = await fetch(notificationRequestUrl(), {
        headers: {
            Accept: 'application/json',
            'X-Background-Poll': '1',
        },
        credentials: 'same-origin',
    });
    if (
        redirectOnExpiredSession(
            notificationResponse,
            String(page.props.idleTimeout?.login_url || '/'),
        )
    )
        return;
    if (!notificationResponse.ok) {
        throw new Error(
            `Notification poll failed (${notificationResponse.status}).`,
        );
    }

    const notificationStatus = await notificationResponse.json();
    const newNotifications = notificationStatus.items || [];
    const unreadNotifications = newNotifications.filter(
        (notification) => !notification.read_at,
    );
    unreadNotificationCount.value =
        unreadMessageCount.value + Number(notificationStatus.unread_count || 0);
    mergeNotifications(newNotifications);

    if (notificationPollingInitialized && unreadNotifications.length > 0) {
        const newest = unreadNotifications[unreadNotifications.length - 1];
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'info',
            title: newest.title || 'New notification',
            text: newest.text,
            showConfirmButton: true,
            confirmButtonText: 'View notifications',
            timer: 8000,
            timerProgressBar: true,
        }).then((result) => {
            if (result.isConfirmed) {
                router.visit(route('student-parent.notifications.index'));
            }
        });
    }
    notificationPollingInitialized = true;
};

const { pollNow } = useRealtimePolling(
    pollRealtime,
    () => Boolean(currentUserId.value) && canPollRealtime.value,
);

watch(selectedStudentId, (studentId, previousStudentId) => {
    if (studentId === previousStudentId || !isStudentParent.value) return;
    notificationItems.value = [];
    notificationCursor.value = 0;
    unreadNotificationCount.value = 0;
    notificationPollingInitialized = false;
    void pollNow();
});

watch(currentUserId, () => {
    unreadMessageCount.value = 0;
    latestUnreadMessageId.value = null;
    messagePollingInitialized = false;
    notificationItems.value = [];
    notificationCursor.value = 0;
    unreadNotificationCount.value = 0;
    notificationPollingInitialized = false;
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
                class="relative flex h-20 shrink-0 items-center justify-between gap-4 bg-white p-4 drop-shadow-sm"
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
                    class="flex min-w-0 items-center gap-3"
                >
                    <div class="relative">
                        <button
                            type="button"
                            class="relative rounded-md border border-slate-200 p-2 text-slate-600 hover:bg-slate-50"
                            aria-label="Open notifications"
                            :aria-expanded="notificationDropdownOpen"
                            @click="
                                notificationDropdownOpen =
                                    !notificationDropdownOpen
                            "
                        >
                            <Bell class="h-5 w-5" />
                            <span
                                v-if="unreadNotificationCount > 0"
                                class="absolute -top-1 -right-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold text-white"
                            >
                                {{ unreadNotificationCount }}
                            </span>
                        </button>
                        <section
                            v-if="notificationDropdownOpen"
                            class="absolute top-12 right-0 z-50 w-80 rounded-lg border border-slate-200 bg-white p-3 shadow-xl"
                            aria-label="Latest notifications"
                        >
                            <div
                                class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2"
                            >
                                <h2 class="font-bold text-slate-900">
                                    Notifications
                                </h2>
                            <button
                                    v-if="unreadNotificationCount > 0"
                                    type="button"
                                    class="text-xs font-semibold text-sky-700 hover:underline"
                                    @click="markAllNotificationsRead"
                                >
                                    Mark all read
                                </button>
                            </div>
                            <div class="max-h-80 overscroll-contain overflow-y-auto">
                                <button
                                    v-for="notification in notificationItems"
                                    :key="notification.id"
                                    type="button"
                                    class="block w-full border-b border-slate-100 px-2 py-3 text-left hover:bg-slate-50"
                                    @click="openNotification(notification)"
                                >
                                    <span
                                        class="flex items-start gap-2 text-sm"
                                    >
                                        <span
                                            v-if="!notification.read_at"
                                            class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-sky-500"
                                        ></span>
                                        <span
                                            v-else
                                            class="mt-1.5 h-2 w-2 shrink-0"
                                        ></span>
                                        <span class="min-w-0">
                                            <strong
                                                class="block truncate text-slate-800"
                                                >{{
                                                    notification.title ||
                                                    notification.sender
                                                }}</strong
                                            >
                                            <span
                                                class="mt-1 line-clamp-2 block text-xs text-slate-500"
                                                >{{ notification.text }}</span
                                            >
                                            <span
                                                class="mt-1 block text-[11px] text-slate-400"
                                                >{{
                                                    notification.timestamp
                                                }}</span
                                            >
                                        </span>
                                    </span>
                                </button>
                                <p
                                    v-if="notificationItems.length === 0"
                                    class="px-2 py-5 text-center text-sm text-slate-400"
                                >
                                    No recent notifications.
                                </p>
                            </div>
                            <button
                                v-if="isStudentParent"
                                type="button"
                                class="mt-2 w-full rounded-md bg-slate-50 px-3 py-2 text-sm font-semibold text-sky-700 hover:bg-sky-50"
                                @click="openNotificationsPage"
                            >
                                View all notifications
                            </button>
                        </section>
                    </div>
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
                    <img
                        v-if="currentUser.profile_photo_url"
                        :src="currentUser.profile_photo_url"
                        alt="Profile picture"
                        class="h-10 w-10 shrink-0 rounded-full border border-slate-200 object-cover"
                    />
                    <div
                        v-else
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand text-sm font-bold text-white"
                    >
                        {{ userInitial }}
                    </div>
                </div>
            </header>
            <!-- Page Content -->
            <main scroll-region class="min-h-0 flex-1 overflow-y-auto bg-gray-100">
                <div
                    class="mx-auto min-h-full w-full max-w-7xl p-4 sm:p-6"
                >
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
