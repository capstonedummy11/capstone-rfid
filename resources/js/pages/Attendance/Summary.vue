<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    subject: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    rows: { type: Array, default: () => [] },
    totalSessions: { type: Number, default: 0 },
});

const search = ref('');
const statusFilter = ref('');
const sortKey = ref('student_name');
const sortDirection = ref('asc');

const valueFor = (row, key) =>
    key === 'student_name' ||
    key === 'student_number' ||
    key === 'attendance_rate'
        ? row[key]
        : (row.counts[key] ?? 0);
const sortedRows = computed(() => {
    const term = search.value.toLowerCase().trim();
    return props.rows
        .filter(
            (row) =>
                !term ||
                `${row.student_name} ${row.student_number}`
                    .toLowerCase()
                    .includes(term),
        )
        .filter(
            (row) =>
                !statusFilter.value ||
                (row.counts[statusFilter.value] ?? 0) > 0,
        )
        .slice()
        .sort(
            (a, b) =>
                String(valueFor(a, sortKey.value)).localeCompare(
                    String(valueFor(b, sortKey.value)),
                    undefined,
                    { numeric: true },
                ) * (sortDirection.value === 'asc' ? 1 : -1),
        );
});
const sort = (key) => {
    if (sortKey.value === key)
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    else {
        sortKey.value = key;
        sortDirection.value = 'asc';
    }
};
</script>

<template>
    <Head title="Student Attendance Summary" />
    <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <Link
                :href="route('admin.attendance.subject', subject.id)"
                class="text-sm font-bold text-blue-700"
                >← Subject dashboard</Link
            >
            <div
                class="mt-4 flex flex-wrap items-end justify-between gap-4 rounded-3xl bg-slate-900 p-7 text-white"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-widest text-blue-300 uppercase"
                    >
                        {{ subject.code }} · {{ subject.section }}
                    </p>
                    <h1 class="mt-2 text-3xl font-black">
                        Student Attendance Summary
                    </h1>
                    <p class="mt-2 text-sm text-slate-300">
                        {{ subject.name }} · {{ totalSessions }} sessions
                    </p>
                </div>
                <div class="flex gap-2">
                    <a
                        :href="
                            route('admin.attendance.summary.export', [
                                subject.id,
                                'pdf',
                            ])
                        "
                        class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-900"
                        >Export PDF</a
                    >
                    <a
                        :href="
                            route('admin.attendance.summary.export', [
                                subject.id,
                                'xlsx',
                            ])
                        "
                        class="rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-white"
                        >Export Excel</a
                    >
                </div>
            </div>
            <div
                class="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_220px]"
            >
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search student name or number…"
                    class="rounded-xl border-slate-300 text-sm"
                />
                <select
                    v-model="statusFilter"
                    class="rounded-xl border-slate-300 text-sm"
                >
                    <option value="">All attendance statuses</option>
                    <option
                        v-for="status in statuses"
                        :key="status"
                        :value="status"
                    >
                        Has {{ status }}
                    </option>
                </select>
            </div>
            <div
                class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead
                            class="bg-slate-100 text-left text-xs tracking-wide text-slate-600 uppercase"
                        >
                            <tr>
                                <th
                                    class="cursor-pointer px-4 py-3"
                                    @click="sort('student_name')"
                                >
                                    Student ↕
                                </th>
                                <th
                                    v-for="status in statuses"
                                    :key="status"
                                    class="cursor-pointer px-4 py-3 text-center"
                                    @click="sort(status)"
                                >
                                    {{ status }} ↕
                                </th>
                                <th
                                    class="cursor-pointer px-4 py-3 text-center"
                                    @click="sort('attendance_rate')"
                                >
                                    Rate ↕
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="row in sortedRows"
                                :key="row.student_id"
                                class="hover:bg-blue-50/50"
                            >
                                <td class="px-4 py-3">
                                    <Link
                                        :href="
                                            route('admin.attendance.student', [
                                                subject.id,
                                                row.student_id,
                                            ])
                                        "
                                        class="font-bold text-blue-700 hover:underline"
                                        >{{ row.student_name }}</Link
                                    >
                                    <p class="text-xs text-slate-400">
                                        {{ row.student_number }}
                                    </p>
                                </td>
                                <td
                                    v-for="status in statuses"
                                    :key="status"
                                    class="px-4 py-3 text-center font-semibold text-slate-700"
                                >
                                    {{ row.counts[status] ?? 0 }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span
                                        class="rounded-full bg-blue-50 px-3 py-1 font-black text-blue-700"
                                        >{{ row.attendance_rate }}%</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p
                    v-if="!sortedRows.length"
                    class="p-10 text-center text-sm text-slate-500"
                >
                    No students match the current search and filter.
                </p>
            </div>
        </div>
    </main>
</template>
