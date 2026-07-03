<template>
    <div class="h-full w-full overflow-hidden bg-slate-50 font-sans">
        <!-- Stats Row -->
        <div class="grid shrink-0 gap-4 p-4 md:grid-cols-3 lg:grid-cols-4">
            <div
                class="flex flex-col gap-1 rounded-lg border border-slate-100 bg-white p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-extrabold text-slate-900">{{
                        attendanceSummary.present
                    }}</span>
                    <component
                        :is="CheckCircle"
                        class="h-6 w-6 text-green-500"
                    />
                </div>
                <div
                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                >
                    Present
                </div>
            </div>

            <div
                class="flex flex-col gap-1 rounded-lg border border-slate-100 bg-white p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-extrabold text-slate-900">{{
                        attendanceSummary.absent
                    }}</span>
                    <component :is="XCircle" class="h-6 w-6 text-red-500" />
                </div>
                <div
                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                >
                    Absent
                </div>
            </div>

            <div
                class="flex flex-col gap-1 rounded-lg border border-slate-100 bg-white p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-extrabold text-slate-900">{{
                        attendanceSummary.late
                    }}</span>
                    <component :is="Clock" class="h-6 w-6 text-yellow-500" />
                </div>
                <div
                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                >
                    Late
                </div>
            </div>

            <div
                class="flex flex-col gap-1 rounded-lg border border-slate-100 bg-white p-4 shadow-sm"
            >
                <div class="flex items-center justify-between">
                    <span class="text-2xl font-extrabold text-slate-900">{{
                        borrowingSummary.borrowed
                    }}</span>
                    <component :is="Package" class="h-6 w-6 text-blue-500" />
                </div>
                <div
                    class="text-xs font-semibold tracking-wider text-slate-500 uppercase"
                >
                    Items Borrowed
                </div>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div class="flex flex-col gap-4 p-4">
            <h2
                class="text-xs font-bold tracking-widest text-slate-400 uppercase"
            >
                Recent Attendance
            </h2>
            <div class="rounded-lg border border-slate-100 bg-white shadow-sm">
                <table class="w-full">
                    <thead class="border-b border-slate-100 bg-slate-50">
                        <tr
                            class="text-xs font-semibold tracking-wider text-slate-600 uppercase"
                        >
                            <th class="px-4 py-3 text-left">Date</th>
                            <th class="px-4 py-3 text-left">Subject</th>
                            <th class="px-4 py-3 text-left">Time In</th>
                            <th class="px-4 py-3 text-left">Time Out</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="record in recentAttendance"
                            :key="record.attendance_id"
                            class="text-sm text-slate-600 hover:bg-slate-50"
                        >
                            <td class="px-4 py-3">
                                {{ formatDate(record.date) }}
                            </td>
                            <td class="px-4 py-3">{{ record.subject_code }}</td>
                            <td class="px-4 py-3">
                                {{ record.time_in || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                {{ record.time_out || '-' }}
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    :class="getStatusBadgeClass(record.status)"
                                    class="inline-block rounded px-2 py-1 text-xs font-semibold"
                                >
                                    {{ record.status }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="recentAttendance.length === 0">
                            <td
                                colspan="5"
                                class="px-4 py-6 text-center text-slate-400"
                            >
                                No attendance records yet
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { CheckCircle, XCircle, Clock, Package } from 'lucide-vue-next';
import { usePage } from '@inertiajs/vue3';

const page = usePage();

const props = defineProps({
    attendance: {
        type: Array,
        default: () => [],
    },
    borrowing: {
        type: Array,
        default: () => [],
    },
});

const recentAttendance = computed(() => props.attendance.slice(0, 5) || []);

const attendanceSummary = computed(() => {
    const records = props.attendance || [];
    return {
        present: records.filter((r) => r.status === 'present').length,
        absent: records.filter((r) => r.status === 'absent').length,
        late: records.filter((r) => r.status === 'late').length,
    };
});

const borrowingSummary = computed(() => ({
    borrowed: (props.borrowing || []).filter((b) => b.status === 'borrowed')
        .length,
}));

function formatDate(date) {
    return new Date(date).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

function getStatusBadgeClass(status) {
    const baseClass = 'bg-opacity-10';
    switch (status?.toLowerCase()) {
        case 'present':
            return `${baseClass} bg-green-500 text-green-700`;
        case 'absent':
            return `${baseClass} bg-red-500 text-red-700`;
        case 'late':
            return `${baseClass} bg-yellow-500 text-yellow-700`;
        default:
            return `${baseClass} bg-slate-500 text-slate-700`;
    }
}
</script>
