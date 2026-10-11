<!-- FEATURE:online-class-notifications - UI para sa online class notifications. -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed, inject, ref, watch } from 'vue';
import LinkedStudentSelector from '@/components/StudentPortal/LinkedStudentSelector.vue';

const props = defineProps({
    student: { type: Object, default: null },
    linkedStudents: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
    notifications: { type: Array, default: () => [] },
});

const notificationCenter = inject('realtimeNotificationCenter', null);
const notificationRows = ref([...props.notifications]);

const mergeNotifications = (items) => {
    const byId = new Map(notificationRows.value.map((item) => [item.id, item]));
    items.forEach((item) =>
        byId.set(item.id, { ...byId.get(item.id), ...item }),
    );
    notificationRows.value = Array.from(byId.values()).sort(
        (a, b) => new Date(b.created_at) - new Date(a.created_at),
    );
};

watch(
    () => props.notifications,
    (notifications) => mergeNotifications(notifications),
);
watch(
    () => notificationCenter?.items?.value ?? [],
    (notifications) => mergeNotifications(notifications),
    { deep: true },
);

const unreadCount = computed(
    () =>
        notificationCenter?.unreadCount?.value ??
        notificationRows.value.filter((item) => !item.read_at).length,
);

// @function markRead: Minamark ang read sa Notifications flow.
// @useIn markRead: resources/js/pages/StudentParent/Notifications/NotificationsPage.vue template @click
const markRead = (notification) => {
    if (notification.read_at) return;

    if (notificationCenter?.markRead) {
        notificationCenter.markRead(notification);
        return;
    }
    notification.read_at = new Date().toISOString();
    router.put(route('student-parent.notifications.read', notification.id), {
        student_id: props.selectedStudentId,
    }, { preserveScroll: true, preserveState: true });
};

const markAllRead = () => {
    const now = new Date().toISOString();
    notificationRows.value.forEach((notification) => {
        if (!notification.read_at) notification.read_at = now;
    });
    if (notificationCenter?.markAllRead) {
        notificationCenter.markAllRead();
        return;
    }
    router.put(
        route('student-parent.notifications.read-all'),
        { student_id: props.selectedStudentId },
        { preserveScroll: true, preserveState: true },
    );
};
</script>

<template>
    <div class="student-portal-page">
        <section
            class="student-portal-shell rounded-md border border-slate-200 bg-white p-5 shadow-sm"
        >
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">
                        Notifications
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ student?.name || 'Student' }}
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <span
                        v-if="unreadCount > 0"
                        class="rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-800"
                    >
                        {{ unreadCount }} unread
                    </span>
                    <button
                        v-if="unreadCount > 0"
                        type="button"
                        class="text-sm font-semibold text-sky-700 hover:underline"
                        @click="markAllRead"
                    >
                        Mark all read
                    </button>
                    <LinkedStudentSelector
                        :students="linkedStudents"
                        :selected-student-id="selectedStudentId"
                    />
                </div>
            </div>
            <div class="mt-4 divide-y divide-slate-100">
                <article
                    v-for="notification in notificationRows"
                    :key="notification.id"
                    class="py-4"
                    :class="notification.read_at ? 'opacity-70' : ''"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h2 class="font-bold text-slate-900">
                                <span
                                    v-if="!notification.read_at"
                                    class="mr-2 inline-block h-2 w-2 rounded-full bg-sky-500"
                                ></span>
                                {{ notification.title }}
                            </h2>
                            <p
                                class="mt-2 text-sm whitespace-pre-line text-slate-600"
                            >
                                {{ notification.body }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-md bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600 uppercase"
                                >{{ notification.event }}</span
                            >
                            <button
                                v-if="!notification.read_at"
                                class="rounded-md border border-sky-200 px-3 py-1 text-xs font-bold text-sky-600"
                                @click="markRead(notification)"
                            >
                                Mark read
                            </button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-slate-400">
                        {{ notification.created_at }}
                        <span v-if="notification.read_at">
                            | Read {{ notification.read_at }}</span
                        >
                    </p>
                </article>
                <p
                    v-if="notificationRows.length === 0"
                    class="py-8 text-center text-sm text-slate-400"
                >
                    No notifications yet.
                </p>
            </div>
        </section>
    </div>
</template>
