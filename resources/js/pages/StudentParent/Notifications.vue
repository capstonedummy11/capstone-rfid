<script setup>
import { router } from '@inertiajs/vue3';
import LinkedStudentSelector from '@/components/StudentPortal/LinkedStudentSelector.vue';

defineProps({
    student: { type: Object, default: null },
    linkedStudents: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
    notifications: { type: Array, default: () => [] },
});

const markRead = (notification) => {
    if (notification.read_at) return;

    router.put(
        route('student-parent.notifications.read', notification.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
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
                <LinkedStudentSelector
                    :students="linkedStudents"
                    :selected-student-id="selectedStudentId"
                />
            </div>
            <div class="mt-4 divide-y divide-slate-100">
                <article
                    v-for="notification in notifications"
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
                    v-if="notifications.length === 0"
                    class="py-8 text-center text-sm text-slate-400"
                >
                    No notifications yet.
                </p>
            </div>
        </section>
    </div>
</template>
