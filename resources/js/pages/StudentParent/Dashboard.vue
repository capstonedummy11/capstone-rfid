<script setup>
import { Link } from '@inertiajs/vue3';
import StatCard from '@/components/StudentPortal/StatCard.vue';
import LinkedStudentSelector from '@/components/StudentPortal/LinkedStudentSelector.vue';
import { Bell, Clock3, Search, Users } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    student: { type: Object, default: null },
    linkedStudents: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
    stats: { type: Object, default: () => ({}) },
    recentAttendance: { type: Array, default: () => [] },
    attendance: { type: Array, default: () => [] },
    recentMessages: { type: Array, default: () => [] },
});

const search = ref('');
const statusFilter = ref('');
const currentPage = ref(1);
const pageSize = 8;
const today = new Date();

const calendarDays = computed(() => {
    const days = [];
    const year = today.getFullYear();
    const month = today.getMonth();
    const monthLength = new Date(year, month + 1, 0).getDate();
    const firstDayOffset = new Date(year, month, 1).getDay();

    for (let i = 0; i < firstDayOffset; i += 1) days.push(null);
    for (let day = 1; day <= monthLength; day += 1) days.push(day);

    return days;
});

const monthLabel = computed(() =>
    today.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }),
);

const filteredAttendance = computed(() => {
    const term = search.value.trim().toLowerCase();
    return props.attendance.filter((record) => {
        const matchesSearch =
            !term ||
            [record.subject, record.room, record.date, record.status].some(
                (value) =>
                    String(value || '')
                        .toLowerCase()
                        .includes(term),
            );
        const matchesStatus =
            !statusFilter.value ||
            String(record.status || '').toLowerCase() === statusFilter.value;

        return matchesSearch && matchesStatus;
    });
});

const totalPages = computed(() =>
    Math.max(1, Math.ceil(filteredAttendance.value.length / pageSize)),
);
const paginatedAttendance = computed(() => {
    const start = (currentPage.value - 1) * pageSize;
    return filteredAttendance.value.slice(start, start + pageSize);
});

watch([search, statusFilter], () => {
    currentPage.value = 1;
});

const resetFilters = () => {
    search.value = '';
    statusFilter.value = '';
    currentPage.value = 1;
};

const statusClass = (status) => {
    const value = String(status || '').toLowerCase();
    if (value.includes('present')) return 'bg-emerald-100 text-emerald-700';
    if (value.includes('late')) return 'bg-amber-100 text-amber-700';
    return 'bg-rose-100 text-rose-700';
};
</script>

<template>
    <div class="student-portal-page">
        <div class="student-portal-shell flex flex-col gap-4">
            <header
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="min-w-0">
                    <h1 class="text-xl font-extrabold text-[#172554]">
                        Student Portal
                    </h1>
                    <p class="mt-1 text-xs font-semibold text-slate-500">
                        {{ student?.name || 'Student' }} |
                        {{ student?.section || 'No section linked' }}
                    </p>
                </div>
                <div
                    class="flex w-full flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center lg:w-auto lg:justify-end"
                >
                    <LinkedStudentSelector
                        :students="linkedStudents"
                        :selected-student-id="selectedStudentId"
                    />
                    <div
                        class="flex min-w-0 items-center gap-3 rounded-full bg-white px-4 py-2 shadow-sm"
                    >
                        <div
                            class="h-9 w-9 shrink-0 rounded-full bg-slate-200"
                        />
                        <div class="min-w-0 text-left sm:text-right">
                            <p
                                class="truncate text-sm font-bold text-slate-900"
                            >
                                {{ student?.name || 'Student' }}
                            </p>
                            <p class="truncate text-xs text-slate-500">
                                {{ student?.email || 'No email linked' }}
                            </p>
                        </div>
                    </div>
                </div>
            </header>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                <StatCard
                    :value="stats.present || 0"
                    label="Total Present"
                    helper="This school year"
                    :icon="Users"
                />
                <StatCard
                    :value="stats.late || 0"
                    label="Total Late"
                    helper="This school year"
                    :icon="Clock3"
                />
                <StatCard
                    :value="stats.excuse_letters || 0"
                    label="Total Absences"
                    helper="Excuse letters filed"
                    :icon="Users"
                />
                <StatCard
                    :value="stats.online_classes || 0"
                    label="Online Classes"
                    helper="Currently scheduled"
                    :icon="Users"
                />
                <div
                    class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100"
                >
                    <p class="text-sm font-bold text-slate-900">Today's Year</p>
                    <p class="mt-2 text-2xl font-black text-[#172554]">
                        {{ student?.school_year || '2026 - 2027' }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{ student?.strand || 'Academic Program' }} |
                        {{ student?.semester || 'Semester' }}
                    </p>
                </div>
            </section>

            <section class="grid gap-4 xl:grid-cols-[330px_1fr]">
                <div
                    class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100"
                >
                    <h2 class="text-sm font-black text-slate-900 uppercase">
                        Calendar
                    </h2>
                    <p class="mt-1 text-xs font-semibold text-slate-500">
                        {{ monthLabel }}
                    </p>
                    <div
                        class="mt-4 grid grid-cols-7 gap-2 text-center text-[11px] font-bold text-slate-500"
                    >
                        <span>Sun</span><span>Mon</span><span>Tue</span
                        ><span>Wed</span><span>Thu</span><span>Fri</span
                        ><span>Sat</span>
                    </div>
                    <div
                        class="mt-2 grid grid-cols-7 gap-2 text-center text-xs text-slate-700"
                    >
                        <span
                            v-for="(day, index) in calendarDays"
                            :key="index"
                            class="rounded-full py-1"
                            :class="{
                                'bg-slate-100 font-black text-sky-600':
                                    day === today.getDate(),
                            }"
                        >
                            {{ day || '' }}
                        </span>
                    </div>
                </div>

                <div
                    class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100"
                >
                    <h2 class="text-sm font-black text-slate-900 uppercase">
                        Reminder
                    </h2>
                    <div class="mt-3 divide-y divide-slate-100">
                        <div
                            v-for="message in recentMessages"
                            :key="message.id"
                            class="flex items-start gap-3 py-3"
                        >
                            <Bell
                                class="mt-0.5 h-4 w-4 shrink-0 text-indigo-400"
                            />
                            <div>
                                <p class="text-sm font-semibold text-slate-800">
                                    {{ message.subject }}
                                </p>
                                <p class="mt-1 text-xs text-slate-500">
                                    {{ message.created_at }}
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="recentMessages.length === 0"
                            class="flex items-start gap-3 py-3"
                        >
                            <Bell
                                class="mt-0.5 h-4 w-4 shrink-0 text-indigo-400"
                            />
                            <p class="text-sm font-semibold text-slate-700">
                                You have no new reminders.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section
                class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-100"
            >
                <div
                    class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
                >
                    <h2 class="text-sm font-black text-slate-900 uppercase">
                        Attendance History
                    </h2>
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center"
                    >
                        <div
                            class="flex min-w-0 items-center gap-2 rounded-full border border-slate-200 px-3 py-2 text-xs text-slate-500"
                        >
                            <Search class="h-3.5 w-3.5" />
                            <input
                                v-model="search"
                                type="search"
                                class="w-full min-w-0 bg-transparent outline-none placeholder:text-slate-400 sm:w-36"
                                placeholder="Quick search..."
                            />
                        </div>
                        <select
                            v-model="statusFilter"
                            class="rounded-md border border-slate-200 px-3 py-2 text-xs font-bold text-slate-500"
                        >
                            <option value="">All status</option>
                            <option value="present">Present</option>
                            <option value="late">Late</option>
                            <option value="absent">Absent</option>
                        </select>
                        <button
                            class="rounded-md border border-slate-200 px-3 py-2 text-xs font-bold text-slate-500"
                            @click="resetFilters"
                        >
                            Reset
                        </button>
                        <Link
                            :href="route('student-parent.excuse-letters.index')"
                            class="rounded-md bg-sky-500 px-4 py-2 text-xs font-bold text-white"
                        >
                            Submit Excuse Letter
                        </Link>
                    </div>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[920px] text-left text-xs">
                        <thead
                            class="border-y border-slate-100 text-[10px] text-slate-500 uppercase"
                        >
                            <tr>
                                <th class="px-3 py-3">Subject</th>
                                <th class="px-3 py-3">Room</th>
                                <th class="px-3 py-3">Date</th>
                                <th class="px-3 py-3">Class Time</th>
                                <th class="px-3 py-3">Time In / Time Out</th>
                                <th class="px-3 py-3">Duration</th>
                                <th class="px-3 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="record in paginatedAttendance"
                                :key="record.attendance_id"
                            >
                                <td class="px-3 py-4 font-bold text-slate-800">
                                    {{ record.subject || 'Subject' }}
                                </td>
                                <td class="px-3 py-4 text-slate-500">
                                    {{ record.room || '-' }}
                                </td>
                                <td class="px-3 py-4 text-slate-500">
                                    {{ record.date || '-' }}
                                </td>
                                <td class="px-3 py-4 text-slate-500">
                                    {{ record.class_time || '-' }}
                                </td>
                                <td class="px-3 py-4 text-sky-600">
                                    {{ record.time_in || '--' }}
                                    <span class="text-slate-300">...</span>
                                    {{ record.time_out || '--' }}
                                </td>
                                <td class="px-3 py-4 text-slate-500">
                                    {{ record.duration || '-' }}
                                </td>
                                <td class="px-3 py-4">
                                    <span
                                        class="rounded-full px-3 py-1 text-[10px] font-black uppercase"
                                        :class="statusClass(record.status)"
                                    >
                                        {{ record.status || 'Absent' }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="paginatedAttendance.length === 0">
                                <td
                                    colspan="7"
                                    class="px-3 py-10 text-center text-slate-400"
                                >
                                    No attendance records found.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-end gap-2 text-xs">
                    <button
                        class="rounded-md border border-slate-200 px-3 py-2 text-slate-500 disabled:text-slate-300"
                        :disabled="currentPage === 1"
                        @click="currentPage = Math.max(1, currentPage - 1)"
                    >
                        Previous
                    </button>
                    <span class="px-3 py-2 text-slate-500"
                        >Page {{ currentPage }} of {{ totalPages }}</span
                    >
                    <button
                        class="rounded-md border border-sky-200 px-3 py-2 font-bold text-sky-500 disabled:text-slate-300"
                        :disabled="currentPage === totalPages"
                        @click="
                            currentPage = Math.min(totalPages, currentPage + 1)
                        "
                    >
                        Next
                    </button>
                </div>
            </section>
        </div>
    </div>
</template>
