<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    subject: { type: Object, required: true },
    session: { type: Object, required: true },
    statuses: { type: Array, default: () => [] },
    statusTotals: { type: Object, default: () => ({}) },
    rows: { type: Array, default: () => [] },
    canEditAttendance: { type: Boolean, default: false },
    absentDefaultDays: { type: Number, default: 15 },
});

const search = ref('');
const statusFilter = ref('');
const sortDirection = ref('asc');
const editing = ref(null);
const editStatus = ref('');
const remarks = ref('');
const expandedStudents = ref({});
const filteredRows = computed(() => {
    const term = search.value.toLowerCase().trim();
    return props.rows
        .filter(
            (row) =>
                (!term ||
                    `${row.student_name} ${row.student_number}`
                        .toLowerCase()
                        .includes(term)) &&
                (!statusFilter.value || row.status === statusFilter.value),
        )
        .slice()
        .sort(
            (a, b) =>
                a.student_name.localeCompare(b.student_name) *
                (sortDirection.value === 'asc' ? 1 : -1),
        );
});
const openEdit = (row) => {
    editing.value = row;
    editStatus.value = ['Present', 'Late', 'Absent', 'Excused'].includes(
        row.status,
    )
        ? row.status.toLowerCase()
        : 'present';
    remarks.value = row.remarks ?? '';
};
const studentRowKey = (row) => String(row.student_id ?? '');
const hasTapEvents = (row) =>
    Array.isArray(row.tap_events) && row.tap_events.length > 0;
const isStudentExpanded = (row) =>
    expandedStudents.value[studentRowKey(row)] === true;
const toggleStudentDetails = (row) => {
    if (!hasTapEvents(row)) return;

    const key = studentRowKey(row);
    expandedStudents.value = {
        ...expandedStudents.value,
        [key]: !expandedStudents.value[key],
    };
};
const save = () =>
    router.patch(
        props.session.type === 'online'
            ? route('admin.attendance.online.status')
            : route('admin.attendance.logs.status'),
        props.session.type === 'online'
            ? {
                  online_class_id: Number(
                      String(props.session.id).replace('online-', ''),
                  ),
                  student_id: editing.value.student_id,
                  status: editStatus.value,
                  remarks: remarks.value,
              }
            : {
                  session_id: props.session.id,
                  student_id: editing.value.student_id,
                  status: editStatus.value,
                  remarks: remarks.value,
              },
        { preserveScroll: true, onSuccess: () => (editing.value = null) },
    );
</script>

<template>
    <Head :title="`${session.date_label} Attendance Sheet`" />
    <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <Link
                :href="route('admin.attendance.subject', subject.id)"
                class="text-sm font-bold text-blue-700"
                >← Subject dashboard</Link
            >
            <header
                class="mt-4 flex flex-wrap items-end justify-between gap-5 rounded-3xl bg-slate-900 p-7 text-white"
            >
                <div>
                    <p
                        class="text-xs font-bold tracking-widest text-blue-300 uppercase"
                    >
                        {{ subject.code }} · {{ subject.section }} ·
                        {{ session.type_label }}
                    </p>
                    <h1 class="mt-2 text-3xl font-black">
                        {{
                            session.type === 'online'
                                ? 'Online Class Attendance'
                                : 'Attendance Sheet'
                        }}
                    </h1>
                    <p class="mt-2 text-sm text-slate-300">
                        <span v-if="session.title">{{ session.title }} · </span>
                        {{ session.date_label }} · {{ session.schedule }} ·
                        {{ session.room }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <a
                        :href="
                            route('admin.attendance.session.export', [
                                subject.id,
                                session.id,
                                'pdf',
                            ])
                        "
                        class="rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-900"
                        >Export PDF</a
                    ><a
                        :href="
                            route('admin.attendance.session.export', [
                                subject.id,
                                session.id,
                                'xlsx',
                            ])
                        "
                        class="rounded-xl bg-emerald-500 px-4 py-2.5 text-sm font-bold text-white"
                        >Export Excel</a
                    >
                </div>
            </header>
            <section
                class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6"
            >
                <div
                    v-for="(count, status) in statusTotals"
                    :key="status"
                    class="rounded-2xl border border-slate-200 bg-white p-4"
                >
                    <p
                        class="truncate text-xs font-bold text-slate-500 uppercase"
                    >
                        {{ status }}
                    </p>
                    <p class="mt-1 text-2xl font-black text-blue-700">
                        {{ count }}
                    </p>
                </div>
            </section>
            <div
                class="mt-5 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 md:grid-cols-[1fr_220px_auto]"
            >
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search students…"
                    class="rounded-xl border-slate-300 text-sm"
                />
                <select
                    v-model="statusFilter"
                    class="rounded-xl border-slate-300 text-sm"
                >
                    <option value="">All statuses</option>
                    <option v-for="status in statuses" :key="status">
                        {{ status }}
                    </option>
                </select>
                <button
                    class="rounded-xl border border-slate-300 px-4 text-sm font-bold text-slate-700"
                    @click="
                        sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'
                    "
                >
                    Name {{ sortDirection === 'asc' ? 'A–Z' : 'Z–A' }}
                </button>
            </div>
            <div
                class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead
                            class="bg-slate-100 text-left text-xs text-slate-600 uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Student</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3">Time in</th>
                                <th class="px-4 py-3">Time out</th>
                                <th class="px-4 py-3">Remarks</th>
                                <th v-if="canEditAttendance" class="px-4 py-3">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template
                                v-for="row in filteredRows"
                                :key="row.student_id"
                            >
                                <tr>
                                    <td class="px-4 py-3 text-slate-900">
                                        <button
                                            type="button"
                                            class="w-full rounded-lg text-left focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none"
                                            :class="
                                                hasTapEvents(row)
                                                    ? 'cursor-pointer hover:text-blue-700'
                                                    : 'cursor-default'
                                            "
                                            :disabled="!hasTapEvents(row)"
                                            :aria-expanded="
                                                hasTapEvents(row)
                                                    ? isStudentExpanded(row)
                                                    : undefined
                                            "
                                            @click="toggleStudentDetails(row)"
                                        >
                                            <span class="font-bold">{{
                                                row.student_name
                                            }}</span>
                                            <span
                                                v-if="hasTapEvents(row)"
                                                class="ml-2 text-xs font-semibold text-blue-600"
                                            >
                                                {{
                                                    isStudentExpanded(row)
                                                        ? 'Hide time details'
                                                        : 'Show time details'
                                                }}
                                            </span>
                                            <span
                                                class="block text-xs font-normal text-slate-400"
                                            >
                                                {{ row.student_number }}
                                            </span>
                                        </button>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span
                                            class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700"
                                            >{{ row.status }}</span
                                        >
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ row.time_in || '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ row.time_out || '—' }}
                                    </td>
                                    <td
                                        class="max-w-xs px-4 py-3 text-slate-500"
                                    >
                                        {{ row.remarks || '—' }}
                                    </td>
                                    <td
                                        v-if="canEditAttendance"
                                        class="px-4 py-3"
                                    >
                                        <button
                                            v-if="row.editable"
                                            class="font-bold text-blue-700 hover:underline"
                                            @click="openEdit(row)"
                                        >
                                            Edit</button
                                        ><span
                                            v-else
                                            class="text-xs text-slate-400"
                                            >Locked</span
                                        >
                                    </td>
                                </tr>
                                <tr
                                    v-if="isStudentExpanded(row)"
                                    class="bg-slate-50/80"
                                >
                                    <td
                                        :colspan="canEditAttendance ? 6 : 5"
                                        class="px-4 py-4"
                                    >
                                        <div
                                            class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                                        >
                                            <article
                                                v-for="event in row.tap_events"
                                                :key="event.id"
                                                class="rounded-xl border border-slate-200 bg-white p-3"
                                            >
                                                <div
                                                    class="flex items-start justify-between gap-3"
                                                >
                                                    <div>
                                                        <p
                                                            class="font-bold text-slate-900"
                                                        >
                                                            {{ event.tap_type }}
                                                            <span
                                                                class="text-slate-400"
                                                            >
                                                                #{{
                                                                    event.tap_sequence_number ||
                                                                    '-'
                                                                }}
                                                            </span>
                                                        </p>
                                                        <p
                                                            class="mt-1 text-xs text-slate-500"
                                                        >
                                                            {{
                                                                event.time ||
                                                                'N/A'
                                                            }}
                                                        </p>
                                                    </div>
                                                    <span
                                                        class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600"
                                                    >
                                                        {{
                                                            event.validation_result ||
                                                            'N/A'
                                                        }}
                                                    </span>
                                                </div>
                                                <dl
                                                    class="mt-3 grid grid-cols-2 gap-2 text-xs"
                                                >
                                                    <div>
                                                        <dt
                                                            class="font-semibold text-slate-500"
                                                        >
                                                            Location
                                                        </dt>
                                                        <dd
                                                            class="text-slate-700"
                                                        >
                                                            {{
                                                                event.room_status ||
                                                                event.location ||
                                                                '-'
                                                            }}
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt
                                                            class="font-semibold text-slate-500"
                                                        >
                                                            Verification
                                                        </dt>
                                                        <dd
                                                            class="break-words text-slate-700"
                                                        >
                                                            {{
                                                                event.verification_method ||
                                                                '-'
                                                            }}
                                                        </dd>
                                                    </div>
                                                </dl>
                                                <p
                                                    v-if="event.remarks"
                                                    class="mt-3 rounded-lg bg-slate-50 px-2 py-1.5 text-xs text-slate-600"
                                                >
                                                    {{ event.remarks }}
                                                </p>
                                                <div
                                                    class="mt-3 flex flex-wrap gap-3 text-xs font-semibold"
                                                >
                                                    <a
                                                        v-if="
                                                            event.time_in_image_url
                                                        "
                                                        :href="
                                                            event.time_in_image_url
                                                        "
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="text-blue-700 hover:underline"
                                                        >View tap evidence</a
                                                    >
                                                    <a
                                                        v-if="
                                                            event.time_out_image_url
                                                        "
                                                        :href="
                                                            event.time_out_image_url
                                                        "
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="text-blue-700 hover:underline"
                                                        >View checkout
                                                        evidence</a
                                                    >
                                                </div>
                                            </article>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
            <div
                v-if="editing"
                class="fixed inset-0 z-50 grid place-items-center bg-slate-950/60 p-4"
                @click.self="editing = null"
            >
                <div
                    class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"
                >
                    <h2 class="text-xl font-black text-slate-900">
                        Edit {{ editing.student_name }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Changes are audited and allowed within
                        {{ absentDefaultDays }} days.
                    </p>
                    <label
                        class="mt-5 block text-xs font-bold text-slate-500 uppercase"
                        >Status</label
                    ><select
                        v-model="editStatus"
                        class="mt-1 w-full rounded-xl border-slate-300"
                    >
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="absent">Absent</option>
                        <option value="excused">Excused</option></select
                    ><label
                        class="mt-4 block text-xs font-bold text-slate-500 uppercase"
                        >Remarks</label
                    ><textarea
                        v-model="remarks"
                        rows="3"
                        class="mt-1 w-full rounded-xl border-slate-300"
                        placeholder="Required for Excused"
                    ></textarea>
                    <div class="mt-5 flex justify-end gap-2">
                        <button
                            class="rounded-xl px-4 py-2 text-sm font-bold text-slate-600"
                            @click="editing = null"
                        >
                            Cancel</button
                        ><button
                            class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white"
                            @click="save"
                        >
                            Save change
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</template>
