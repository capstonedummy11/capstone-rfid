<script setup>
import { Link } from '@inertiajs/vue3';
import {
    Bell,
    CalendarDays,
    CheckCircle2,
    Clock3,
    MonitorCheck,
    School,
    Users,
} from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps({
    role: { type: String, default: 'admin' },
    scopeLabel: { type: String, default: 'Whole system' },
    stats: { type: Object, default: () => ({}) },
    schedules: { type: Array, default: () => [] },
    attendance: { type: Array, default: () => [] },
    onlineClasses: { type: Array, default: () => [] },
});

const isInstructor = computed(() => props.role === 'instructor');

const cards = computed(() => [
    {
        label: isInstructor.value ? 'Assigned Schedules' : 'Schedules',
        value: props.stats.schedules || 0,
        helper: props.scopeLabel,
        icon: CalendarDays,
        tone: 'text-sky-600 bg-sky-50',
    },
    {
        label: isInstructor.value ? 'Handled Students' : 'Students',
        value: props.stats.students || 0,
        helper: isInstructor.value
            ? 'From your sections'
            : 'Registered learners',
        icon: Users,
        tone: 'text-emerald-600 bg-emerald-50',
    },
    {
        label: 'Today Present',
        value: props.stats.todayPresent || 0,
        helper: `${props.stats.todayLate || 0} late, ${props.stats.todayAbsent || 0} absent`,
        icon: CheckCircle2,
        tone: 'text-indigo-600 bg-indigo-50',
    },
    {
        label: 'Online Classes',
        value: props.stats.onlineClasses || 0,
        helper: 'Upcoming and active',
        icon: MonitorCheck,
        tone: 'text-amber-600 bg-amber-50',
    },
]);

const statusClass = (status) => {
    const value = String(status || '').toLowerCase();
    if (value.includes('present') || value.includes('joined')) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-100';
    }
    if (value.includes('late')) {
        return 'bg-amber-50 text-amber-700 ring-amber-100';
    }
    if (value.includes('cancel')) {
        return 'bg-slate-100 text-slate-500 ring-slate-200';
    }
    return 'bg-rose-50 text-rose-700 ring-rose-100';
};
</script>

<template>
    <div class="min-h-full bg-slate-100 p-4 text-slate-900 sm:p-6">
        <div class="mx-auto flex max-w-7xl flex-col gap-5">
            <header
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-wide text-brand uppercase"
                    >
                        {{
                            isInstructor
                                ? 'Instructor Workspace'
                                : 'Admin Overview'
                        }}
                    </p>
                    <h1 class="mt-1 text-2xl font-black text-slate-950">
                        {{
                            isInstructor
                                ? 'My Teaching Dashboard'
                                : 'System Dashboard'
                        }}
                    </h1>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ scopeLabel }} · {{ new Date().toLocaleDateString() }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Link
                        :href="route('admin.schedules.index')"
                        class="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-bold text-slate-700 shadow-sm"
                    >
                        <CalendarDays class="h-4 w-4 text-brand" />
                        Schedules
                    </Link>
                    <Link
                        :href="route('admin.attendance.logs')"
                        class="inline-flex items-center gap-2 rounded-md bg-brand px-3 py-2 text-sm font-bold text-white shadow-sm"
                    >
                        <Clock3 class="h-4 w-4" />
                        Attendance
                    </Link>
                </div>
            </header>

            <section class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <article
                    v-for="card in cards"
                    :key="card.label"
                    class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-3xl font-black text-slate-950">
                                {{ card.value }}
                            </p>
                            <p class="mt-1 text-sm font-bold text-slate-700">
                                {{ card.label }}
                            </p>
                        </div>
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md"
                            :class="card.tone"
                        >
                            <component :is="card.icon" class="h-5 w-5" />
                        </div>
                    </div>
                    <p class="mt-4 text-xs font-semibold text-slate-400">
                        {{ card.helper }}
                    </p>
                </article>
            </section>

            <section class="grid gap-5 xl:grid-cols-[1.2fr_0.8fr]">
                <div
                    class="rounded-md border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2 class="font-black text-slate-950">
                                {{
                                    isInstructor
                                        ? 'My Assigned Schedules'
                                        : 'Schedule Snapshot'
                                }}
                            </h2>
                            <p class="text-sm text-slate-500">
                                Class sessions ordered by day and start time.
                            </p>
                        </div>
                        <Link
                            :href="route('admin.schedules.index')"
                            class="text-sm font-bold text-brand"
                        >
                            View all
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[760px] text-left text-sm">
                            <thead
                                class="bg-slate-50 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="px-4 py-3">Class</th>
                                    <th class="px-4 py-3">Section</th>
                                    <th class="px-4 py-3">Room</th>
                                    <th class="px-4 py-3">Day / Time</th>
                                    <th v-if="!isInstructor" class="px-4 py-3">
                                        Instructor
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="schedule in schedules"
                                    :key="schedule.id"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">
                                            {{ schedule.subject }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ schedule.subject_code }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ schedule.section }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ schedule.room }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div
                                            class="font-semibold text-slate-700"
                                        >
                                            {{ schedule.weekday }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ schedule.time }}
                                        </div>
                                    </td>
                                    <td
                                        v-if="!isInstructor"
                                        class="px-4 py-3 text-slate-600"
                                    >
                                        {{ schedule.instructor }}
                                    </td>
                                </tr>
                                <tr v-if="schedules.length === 0">
                                    <td
                                        :colspan="isInstructor ? 4 : 5"
                                        class="px-4 py-10 text-center text-sm text-slate-400"
                                    >
                                        No assigned schedules yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div
                    class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="font-black text-slate-950">
                                Upcoming Online Classes
                            </h2>
                            <p class="text-sm text-slate-500">
                                Next scheduled sessions.
                            </p>
                        </div>
                        <MonitorCheck class="h-5 w-5 text-brand" />
                    </div>

                    <div class="mt-4 space-y-3">
                        <article
                            v-for="onlineClass in onlineClasses"
                            :key="onlineClass.id"
                            class="rounded-md border border-slate-100 p-3"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3
                                        class="truncate font-bold text-slate-900"
                                    >
                                        {{ onlineClass.title }}
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-500">
                                        {{ onlineClass.subject }} ·
                                        {{ onlineClass.section }}
                                    </p>
                                    <p
                                        class="mt-2 text-xs font-semibold text-slate-400"
                                    >
                                        {{ onlineClass.date }} ·
                                        {{ onlineClass.time }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-black uppercase ring-1"
                                    :class="statusClass(onlineClass.status)"
                                >
                                    {{ onlineClass.status }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-slate-400">
                                Face:
                                {{
                                    onlineClass.face_required
                                        ? 'Required'
                                        : 'Off'
                                }}
                            </p>
                        </article>

                        <div
                            v-if="onlineClasses.length === 0"
                            class="rounded-md border border-dashed border-slate-200 p-8 text-center text-sm text-slate-400"
                        >
                            No upcoming online classes.
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-5 xl:grid-cols-[1fr_320px]">
                <div
                    class="rounded-md border border-slate-200 bg-white shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-slate-100 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2 class="font-black text-slate-950">
                                Recent Attendance
                            </h2>
                            <p class="text-sm text-slate-500">
                                Latest RFID attendance records in your scope.
                            </p>
                        </div>
                        <Link
                            :href="route('admin.attendance.logs')"
                            class="text-sm font-bold text-brand"
                        >
                            Open logs
                        </Link>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[780px] text-left text-sm">
                            <thead
                                class="bg-slate-50 text-xs text-slate-500 uppercase"
                            >
                                <tr>
                                    <th class="px-4 py-3">Student</th>
                                    <th class="px-4 py-3">Subject</th>
                                    <th class="px-4 py-3">Date</th>
                                    <th class="px-4 py-3">Time</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="record in attendance"
                                    :key="record.id"
                                >
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-slate-900">
                                            {{ record.student }}
                                        </div>
                                        <div class="text-xs text-slate-400">
                                            {{ record.section }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ record.subject }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ record.date }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ record.time_in }} -
                                        {{ record.time_out }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full px-2.5 py-1 text-[10px] font-black uppercase ring-1"
                                            :class="statusClass(record.status)"
                                        >
                                            {{ record.status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="attendance.length === 0">
                                    <td
                                        colspan="5"
                                        class="px-4 py-10 text-center text-sm text-slate-400"
                                    >
                                        No attendance records yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="space-y-5">
                    <div
                        v-if="!isInstructor"
                        class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-md bg-sky-50 text-sky-600"
                            >
                                <School class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-2xl font-black">
                                    {{ stats.sections || 0 }}
                                </p>
                                <p class="text-sm font-bold text-slate-600">
                                    {{
                                        isInstructor
                                            ? 'Assigned Sections'
                                            : 'Sections'
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="!isInstructor"
                        class="rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-md bg-indigo-50 text-indigo-600"
                            >
                                <School class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-2xl font-black">
                                    {{ stats.laboratories || 0 }}
                                </p>
                                <p class="text-sm font-bold text-slate-600">
                                    Laboratories
                                </p>
                            </div>
                        </div>
                    </div>

                    <Link
                        v-if="isInstructor"
                        :href="route('admin.messages.index')"
                        class="flex items-center justify-between rounded-md border border-slate-200 bg-white p-4 shadow-sm"
                    >
                        <span class="flex items-center gap-3">
                            <span
                                class="flex h-10 w-10 items-center justify-center rounded-md bg-amber-50 text-amber-600"
                            >
                                <Bell class="h-5 w-5" />
                            </span>
                            <span>
                                <span class="block text-2xl font-black">
                                    {{ stats.unreadMessages || 0 }}
                                </span>
                                <span class="text-sm font-bold text-slate-600">
                                    Unread Messages
                                </span>
                            </span>
                        </span>
                        <span class="text-sm font-bold text-brand">Open</span>
                    </Link>
                </aside>
            </section>
        </div>
    </div>
</template>
