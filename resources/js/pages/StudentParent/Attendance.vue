<template>
    <div class="space-y-4 p-4">
        <!-- Filters -->
        <div
            class="flex flex-col gap-4 rounded-lg border border-slate-100 bg-white p-4 shadow-sm md:flex-row md:items-center md:gap-4"
        >
            <div class="flex-1">
                <label class="mb-2 block text-sm font-semibold text-slate-700"
                    >Filter by Subject</label
                >
                <select
                    v-model="filters.subject"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                >
                    <option value="">All Subjects</option>
                    <option
                        v-for="subject in uniqueSubjects"
                        :key="subject"
                        :value="subject"
                    >
                        {{ subject }}
                    </option>
                </select>
            </div>
            <div class="flex-1">
                <label class="mb-2 block text-sm font-semibold text-slate-700"
                    >Filter by Status</label
                >
                <select
                    v-model="filters.status"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-brand focus:outline-none"
                >
                    <option value="">All Status</option>
                    <option value="present">Present</option>
                    <option value="absent">Absent</option>
                    <option value="late">Late</option>
                </select>
            </div>
        </div>

        <!-- Attendance Table -->
        <div
            class="overflow-x-auto rounded-lg border border-slate-100 bg-white shadow-sm"
        >
            <table class="w-full">
                <thead class="border-b border-slate-100 bg-slate-50">
                    <tr
                        class="text-xs font-semibold tracking-wider text-slate-600 uppercase"
                    >
                        <th class="px-4 py-3 text-left">Date</th>
                        <th class="px-4 py-3 text-left">Subject</th>
                        <th class="px-4 py-3 text-left">Room</th>
                        <th class="px-4 py-3 text-left">Time In</th>
                        <th class="px-4 py-3 text-left">Time Out</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr
                        v-for="record in filteredAttendance"
                        :key="record.attendance_id"
                        class="text-sm text-slate-600 hover:bg-slate-50"
                    >
                        <td class="px-4 py-3">{{ formatDate(record.date) }}</td>
                        <td class="px-4 py-3 font-semibold">
                            {{ record.subject_code }}
                        </td>
                        <td class="px-4 py-3">{{ record.room || '-' }}</td>
                        <td class="px-4 py-3">{{ record.time_in || '-' }}</td>
                        <td class="px-4 py-3">{{ record.time_out || '-' }}</td>
                        <td class="px-4 py-3">
                            <span
                                :class="getStatusBadgeClass(record.status)"
                                class="inline-block rounded px-2 py-1 text-xs font-semibold"
                            >
                                {{ record.status }}
                            </span>
                        </td>
                    </tr>
                    <tr v-if="filteredAttendance.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-6 text-center text-slate-400"
                        >
                            No attendance records found
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination Info -->
        <div class="text-center text-sm text-slate-500">
            Showing {{ filteredAttendance.length }} of
            {{ attendance.length }} records
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    attendance: {
        type: Array,
        default: () => [],
    },
});

const filters = ref({
    subject: '',
    status: '',
});

const uniqueSubjects = computed(() => {
    const subjects = new Set(props.attendance.map((a) => a.subject_code));
    return Array.from(subjects).sort();
});

const filteredAttendance = computed(() => {
    return props.attendance.filter((record) => {
        const matchesSubject =
            !filters.value.subject ||
            record.subject_code === filters.value.subject;
        const matchesStatus =
            !filters.value.status ||
            record.status?.toLowerCase() === filters.value.status.toLowerCase();
        return matchesSubject && matchesStatus;
    });
});

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
