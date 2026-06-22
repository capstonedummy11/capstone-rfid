<script setup>
import { computed } from 'vue';
import {
    AlertCircle,
    CalendarCheck,
    CheckCircle2,
    Clock3,
    Users,
} from 'lucide-vue-next';

const props = defineProps({
    role: { type: String, default: 'student' },
    students: { type: Array, default: () => [] },
    stats: {
        type: Object,
        default: () => ({
            total: 0,
            present: 0,
            late: 0,
            absent: 0,
            completed: 0,
        }),
    },
    recentLogs: { type: Array, default: () => [] },
});

const isParent = computed(() => props.role === 'parent');
const statCards = computed(() => [
    {
        label: 'Present',
        value: props.stats.present,
        icon: CheckCircle2,
        color: 'text-emerald-700',
        bg: 'bg-emerald-50',
    },
    {
        label: 'Late',
        value: props.stats.late,
        icon: Clock3,
        color: 'text-amber-700',
        bg: 'bg-amber-50',
    },
    {
        label: 'Absent',
        value: props.stats.absent,
        icon: AlertCircle,
        color: 'text-red-700',
        bg: 'bg-red-50',
    },
    {
        label: 'Completed',
        value: props.stats.completed,
        icon: CalendarCheck,
        color: 'text-brand',
        bg: 'bg-sky-50',
    },
]);

const statusClass = (status) => {
    if (status === 'absent') return 'bg-red-50 text-red-700';
    if (status === 'late') return 'bg-amber-50 text-amber-700';
    if (status === 'completed') return 'bg-sky-50 text-brand';
    return 'bg-emerald-50 text-emerald-700';
};
</script>

<template>
    <div class="min-h-full bg-slate-50 p-4">
        <div class="mx-auto max-w-7xl space-y-4">
            <section
                class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm"
            >
                <div
                    class="flex flex-col gap-3 border-b border-slate-100 p-5 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <p class="text-xs font-bold tracking-wide text-brand uppercase">
                            RFID Attendance Portal
                        </p>
                        <h1 class="mt-1 text-2xl font-bold text-slate-900">
                            {{ isParent ? 'Parent Dashboard' : 'Student Dashboard' }}
                        </h1>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ isParent ? 'Monitor linked student attendance records.' : 'Review your attendance record and recent class logs.' }}
                        </p>
                    </div>
                    <div
                        class="flex items-center gap-3 rounded-md border border-slate-100 bg-slate-50 px-4 py-3"
                    >
                        <Users class="h-5 w-5 text-brand" />
                        <div>
                            <p class="text-xs font-semibold text-slate-400 uppercase">
                                Linked {{ isParent ? 'Students' : 'Profile' }}
                            </p>
                            <p class="text-lg font-bold text-slate-900">
                                {{ students.length }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
                    <div
                        v-for="card in statCards"
                        :key="card.label"
                        class="rounded-md border border-slate-200 bg-white p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <p class="text-xs font-semibold text-slate-400 uppercase">
                                {{ card.label }}
                            </p>
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-md"
                                :class="[card.bg, card.color]"
                            >
                                <component :is="card.icon" class="h-5 w-5" />
                            </div>
                        </div>
                        <p class="mt-3 text-3xl font-bold text-slate-900">
                            {{ card.value }}
                        </p>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
                <aside class="rounded-md border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <h2 class="text-sm font-bold text-slate-900">
                            {{ isParent ? 'Linked Students' : 'Student Profile' }}
                        </h2>
                    </div>
                    <div v-if="students.length" class="divide-y divide-slate-100">
                        <article
                            v-for="student in students"
                            :key="student.id"
                            class="p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate text-sm font-bold text-slate-900">
                                        {{ student.name }}
                                    </h3>
                                    <p class="text-xs text-slate-500">
                                        {{ student.student_number }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-md bg-slate-100 px-2 py-1 text-xs font-bold text-slate-600"
                                >
                                    {{ student.status }}
                                </span>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">
                                {{ [student.strand, student.section].filter(Boolean).join(' / ') || 'No section assigned' }}
                            </p>
                            <div class="mt-3 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-md bg-emerald-50 px-2 py-2">
                                    <p class="text-xs font-semibold text-emerald-700">
                                        Present
                                    </p>
                                    <p class="text-lg font-bold text-emerald-800">
                                        {{ student.stats.present }}
                                    </p>
                                </div>
                                <div class="rounded-md bg-amber-50 px-2 py-2">
                                    <p class="text-xs font-semibold text-amber-700">
                                        Late
                                    </p>
                                    <p class="text-lg font-bold text-amber-800">
                                        {{ student.stats.late }}
                                    </p>
                                </div>
                                <div class="rounded-md bg-red-50 px-2 py-2">
                                    <p class="text-xs font-semibold text-red-700">
                                        Absent
                                    </p>
                                    <p class="text-lg font-bold text-red-800">
                                        {{ student.stats.absent }}
                                    </p>
                                </div>
                            </div>
                        </article>
                    </div>
                    <div v-else class="p-6 text-center text-sm text-slate-500">
                        No linked student record is available yet.
                    </div>
                </aside>

                <section class="rounded-md border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-3">
                        <h2 class="text-sm font-bold text-slate-900">
                            Recent Attendance
                        </h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[720px] text-sm">
                            <thead class="bg-slate-50 text-left text-xs text-slate-500 uppercase">
                                <tr>
                                    <th class="px-4 py-3">Date</th>
                                    <th v-if="isParent" class="px-4 py-3">Student</th>
                                    <th class="px-4 py-3">Subject</th>
                                    <th class="px-4 py-3">Room</th>
                                    <th class="px-4 py-3">Time In</th>
                                    <th class="px-4 py-3">Time Out</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="log in recentLogs"
                                    :key="log.id"
                                    class="border-t border-slate-100"
                                >
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ log.date }}
                                    </td>
                                    <td v-if="isParent" class="px-4 py-3 font-semibold text-slate-800">
                                        {{ log.student_name || '-' }}
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-slate-800">
                                        {{ log.subject }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ log.room || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ log.time_in || '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        {{ log.time_out || '-' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-md px-2 py-1 text-xs font-bold capitalize"
                                            :class="statusClass(log.status)"
                                        >
                                            {{ log.status }}
                                        </span>
                                    </td>
                                </tr>
                                <tr v-if="recentLogs.length === 0">
                                    <td
                                        :colspan="isParent ? 7 : 6"
                                        class="px-4 py-8 text-center text-sm text-slate-500"
                                    >
                                        No attendance records found yet.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>
        </div>
    </div>
</template>
