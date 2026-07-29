<template>
    <div class="w-full bg-slate-50">
        <div class="mx-auto max-w-none px-4 py-4">
            <div class="mb-4 rounded-lg bg-white p-5 shadow-sm">
                <h1 class="text-2xl font-bold text-slate-800">
                    Attendance Logs
                </h1>
                <p class="text-sm text-slate-500">
                    {{
                        canInspectAllAttendance
                            ? 'Filter by session, subject, attendance date, and instructor RFID.'
                            : 'View attendance records for your assigned classes only.'
                    }}
                    Default absent status is shown for the latest
                    {{ absentDefaultDays }} day(s).
                </p>
            </div>

            <div class="mb-4 rounded-lg bg-white p-4 shadow-sm">
                <div
                    class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5"
                >
                    <div>
                        <label
                            for="sessionFilter"
                            class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >Session</label
                        >
                        <select
                            v-model="attendanceSessionFilter"
                            id="sessionFilter"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <option value="">All Sessions</option>
                            <option
                                v-for="session in attendanceSessionOptions"
                                :key="session.value"
                                :value="String(session.value)"
                            >
                                {{ session.label }}
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <label
                            for="subjectFilter"
                            class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >Subject</label
                        >
                        <select
                            v-model="subjectFilter"
                            id="subjectFilter"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <option value="">All Subjects</option>
                            <option
                                v-for="subject in subjectOptions"
                                :key="subject.value"
                                :value="String(subject.value)"
                            >
                                {{ subject.label }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            for="dateFilter"
                            class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >Attendance Date</label
                        >
                        <select
                            v-model="dateFilter"
                            id="dateFilter"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <option value="">All Dates</option>
                            <option
                                v-for="date in dateOptions"
                                :key="date.value"
                                :value="date.value"
                            >
                                {{ date.label }}
                            </option>
                        </select>
                    </div>

                    <div v-if="canInspectAllAttendance">
                        <label
                            for="instructorFilter"
                            class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >Instructor</label
                        >
                        <select
                            v-model="instructorFilter"
                            id="instructorFilter"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            @change="instructorRfidFilter = ''"
                        >
                            <option value="">All Instructors</option>
                            <option
                                v-for="instructor in instructorOptions"
                                :key="instructor.value"
                                :value="String(instructor.value)"
                            >
                                {{ instructor.label }}
                            </option>
                        </select>
                    </div>
                </div>

                <div
                    class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_auto]"
                >
                    <div v-if="canInspectAllAttendance">
                        <label
                            for="instructorRfidFilter"
                            class="mb-1 block text-xs font-semibold tracking-wide text-slate-500 uppercase"
                            >Scan Instructor RFID</label
                        >
                        <input
                            v-model="instructorRfidFilter"
                            id="instructorRfidFilter"
                            type="text"
                            placeholder="Scan or type instructor RFID, then press Enter"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            @keyup.enter="applyInstructorRfidFilter"
                        />
                        <p class="mt-1 text-xs text-slate-400">
                            Admin only: scan an instructor RFID to show only
                            that instructor's sessions.
                        </p>
                    </div>
                    <div v-else></div>
                    <button
                        type="button"
                        class="self-end rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                        @click="applyFilters"
                    >
                        Apply Filters
                    </button>
                    <button
                        type="button"
                        class="self-end rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-6">
                <StatusSummaryCard
                    label="Present"
                    tone="present"
                    :count="getStatusCount('Present')"
                />
                <StatusSummaryCard
                    label="Late"
                    tone="late"
                    :count="getStatusCount('Late')"
                />
                <StatusSummaryCard
                    label="Pending"
                    tone="pending"
                    :count="getStatusCount('Pending')"
                />
                <StatusSummaryCard
                    label="Incomplete"
                    tone="incomplete"
                    :count="getStatusCount('Incomplete Attendance')"
                />
                <StatusSummaryCard
                    label="Absent"
                    tone="absent"
                    :count="getStatusCount('Absent')"
                />
                <StatusSummaryCard
                    label="Total Records"
                    tone="total"
                    :count="logs.length"
                />
            </div>

            <div class="space-y-4">
                <section
                    v-for="group in groupedLogs"
                    :key="group.key"
                    class="overflow-hidden rounded-lg bg-white shadow-sm"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50 px-4 py-3"
                    >
                        <div>
                            <h2 class="text-sm font-bold text-slate-800">
                                {{ group.date }} | {{ group.room }}
                            </h2>
                            <p class="text-xs text-slate-500">
                                {{ group.subject }} | {{ group.sessionTime }} |
                                {{ group.instructor }} |
                                {{ group.items.length }} tap record(s)
                            </p>
                        </div>
                        <div class="text-xs text-slate-500">
                            Present {{ group.counts.Present ?? 0 }} | Late
                            {{ group.counts.Late ?? 0 }} | Pending
                            {{ group.counts.Pending ?? 0 }} | Incomplete
                            {{ group.counts['Incomplete Attendance'] ?? 0 }} |
                            Absent {{ group.counts.Absent ?? 0 }}
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table
                            class="w-full border-collapse overflow-x-auto text-sm"
                        >
                            <thead>
                                <tr class="bg-white">
                                    <th
                                        class="w-[18%] border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Student
                                    </th>
                                    <th
                                        class="w-[16%] border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Subject
                                    </th>
                                    <th
                                        class="w-[18%] border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Tap
                                    </th>
                                    <th
                                        class="w-[12%] border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Check-in
                                    </th>
                                    <th
                                        class="w-[12%] border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Check-out
                                    </th>
                                    <th
                                        class="w-[10%] border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Room
                                    </th>
                                    <th
                                        class="w-[12%] border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Evidence
                                    </th>
                                    <th
                                        class="w-[12%] border-b border-slate-200 px-4 py-3 text-left font-medium text-slate-600"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <template
                                    v-for="log in group.items"
                                    :key="log.id"
                                >
                                    <tr class="hover:bg-slate-50">
                                        <td
                                            class="border-b border-slate-100 px-4 py-3 align-top"
                                        >
                                            <div
                                                class="font-medium text-slate-800"
                                            >
                                                {{ log.student }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                {{
                                                    log.student_number || 'N/A'
                                                }}
                                            </div>
                                        </td>
                                        <td
                                            class="border-b border-slate-100 px-4 py-3 align-top"
                                        >
                                            {{ log.subject }}
                                        </td>
                                        <td
                                            class="border-b border-slate-100 px-4 py-3 align-top"
                                        >
                                            <div
                                                class="font-semibold text-slate-700"
                                            >
                                                {{ log.tap_type || 'No Tap' }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                #{{
                                                    log.tap_sequence_number ||
                                                    '-'
                                                }}
                                                | {{ log.time || 'N/A' }}
                                            </div>
                                            <button
                                                v-if="hasEvidenceEvents(log)"
                                                type="button"
                                                class="mt-2 rounded-md border border-slate-300 px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                                                @click="
                                                    toggleEvidenceEvents(log)
                                                "
                                            >
                                                {{
                                                    isEvidenceExpanded(log)
                                                        ? 'Hide details'
                                                        : 'Show details'
                                                }}
                                            </button>
                                        </td>
                                        <td
                                            class="border-b border-slate-100 px-4 py-3 align-top"
                                        >
                                            {{ log.time_in || '-' }}
                                        </td>
                                        <td
                                            class="border-b border-slate-100 px-4 py-3 align-top"
                                        >
                                            {{ log.time_out || '-' }}
                                        </td>
                                        <td
                                            class="border-b border-slate-100 px-4 py-3 align-top"
                                        >
                                            {{ log.room_status || '-' }}
                                        </td>
                                        <td
                                            class="border-b border-slate-100 px-4 py-3 align-top"
                                        >
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <button
                                                    v-if="log.time_in_image_url"
                                                    type="button"
                                                    class="h-10 w-10 overflow-hidden rounded-md border border-slate-200 hover:ring-2 hover:ring-blue-400"
                                                    @click="
                                                        openEvidence(
                                                            log.time_in_image_url,
                                                            `${log.student} - Time In`,
                                                        )
                                                    "
                                                >
                                                    <img
                                                        :src="
                                                            log.time_in_image_url
                                                        "
                                                        alt="Time-in face evidence"
                                                        class="h-full w-full object-cover"
                                                    />
                                                </button>
                                                <button
                                                    v-if="
                                                        log.time_out_image_url
                                                    "
                                                    type="button"
                                                    class="h-10 w-10 overflow-hidden rounded-md border border-slate-200 hover:ring-2 hover:ring-blue-400"
                                                    @click="
                                                        openEvidence(
                                                            log.time_out_image_url,
                                                            `${log.student} - Time Out`,
                                                        )
                                                    "
                                                >
                                                    <img
                                                        :src="
                                                            log.time_out_image_url
                                                        "
                                                        alt="Time-out face evidence"
                                                        class="h-full w-full object-cover"
                                                    />
                                                </button>
                                                <span
                                                    v-if="
                                                        !log.time_in_image_url &&
                                                        !log.time_out_image_url
                                                    "
                                                    class="text-xs text-slate-400"
                                                    >Not captured</span
                                                >
                                            </div>
                                        </td>
                                        <td
                                            class="border-b border-slate-100 px-4 py-3 align-top"
                                        >
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                :class="statusClass(log.status)"
                                                >{{ log.status }}</span
                                            >
                                        </td>
                                    </tr>
                                    <tr
                                        v-if="isEvidenceExpanded(log)"
                                        class="bg-slate-50/80"
                                    >
                                        <td
                                            colspan="8"
                                            class="border-b border-slate-100 px-4 py-4"
                                        >
                                            <div
                                                class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                                            >
                                                <div
                                                    v-for="event in log.evidence_events"
                                                    :key="event.id"
                                                    class="rounded-md border border-slate-200 bg-white p-3"
                                                >
                                                    <div
                                                        class="flex items-start justify-between gap-2"
                                                    >
                                                        <div>
                                                            <div
                                                                class="text-sm font-semibold text-slate-800"
                                                            >
                                                                {{
                                                                    event.tap_type
                                                                }}
                                                            </div>
                                                            <div
                                                                class="text-xs text-slate-500"
                                                            >
                                                                #{{
                                                                    event.tap_sequence_number ||
                                                                    '-'
                                                                }}
                                                                |
                                                                {{
                                                                    event.time ||
                                                                    'N/A'
                                                                }}
                                                            </div>
                                                        </div>
                                                        <span
                                                            class="rounded-full bg-slate-100 px-2 py-1 text-[11px] font-semibold text-slate-600"
                                                        >
                                                            {{
                                                                event.validation_result ||
                                                                '-'
                                                            }}
                                                        </span>
                                                    </div>
                                                    <p
                                                        class="mt-2 min-h-8 text-xs text-slate-500"
                                                    >
                                                        {{
                                                            event.remarks ||
                                                            event.room_status ||
                                                            ''
                                                        }}
                                                    </p>
                                                    <div
                                                        class="mt-3 flex items-center gap-2"
                                                    >
                                                        <button
                                                            v-if="
                                                                event.time_in_image_url
                                                            "
                                                            type="button"
                                                            class="h-12 w-12 overflow-hidden rounded-md border border-slate-200 hover:ring-2 hover:ring-blue-400"
                                                            @click="
                                                                openEvidence(
                                                                    event.time_in_image_url,
                                                                    `${log.student} - ${event.tap_type}`,
                                                                )
                                                            "
                                                        >
                                                            <img
                                                                :src="
                                                                    event.time_in_image_url
                                                                "
                                                                alt="Tap face evidence"
                                                                class="h-full w-full object-cover"
                                                            />
                                                        </button>
                                                        <button
                                                            v-if="
                                                                event.time_out_image_url
                                                            "
                                                            type="button"
                                                            class="h-12 w-12 overflow-hidden rounded-md border border-slate-200 hover:ring-2 hover:ring-blue-400"
                                                            @click="
                                                                openEvidence(
                                                                    event.time_out_image_url,
                                                                    `${log.student} - ${event.tap_type}`,
                                                                )
                                                            "
                                                        >
                                                            <img
                                                                :src="
                                                                    event.time_out_image_url
                                                                "
                                                                alt="Tap face evidence"
                                                                class="h-full w-full object-cover"
                                                            />
                                                        </button>
                                                        <span
                                                            v-if="
                                                                !event.time_in_image_url &&
                                                                !event.time_out_image_url
                                                            "
                                                            class="text-xs text-slate-400"
                                                        >
                                                            No image
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div
                    v-if="groupedLogs.length === 0"
                    class="rounded-lg bg-white p-8 text-center text-slate-500 shadow-sm"
                >
                    No attendance logs found matching your filters.
                </div>
            </div>

            <div
                v-if="evidencePreview"
                class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/75 p-4"
                @click.self="closeEvidence"
            >
                <div class="w-full max-w-lg rounded-lg bg-white p-4 shadow-xl">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h2 class="font-bold text-slate-900">
                            {{ evidencePreview.title }}
                        </h2>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-3 py-1 text-sm font-bold text-slate-600"
                            @click="closeEvidence"
                        >
                            Close
                        </button>
                    </div>
                    <img
                        :src="evidencePreview.url"
                        :alt="evidencePreview.title"
                        class="max-h-[70vh] w-full rounded-md bg-slate-100 object-contain"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, defineComponent, h, ref } from 'vue';

const props = defineProps({
    logs: { type: Array, default: () => [] },
    filters: {
        type: Object,
        default: () => ({
            attendance_id: '',
            date: '',
            subject: '',
            instructor: '',
            instructor_rfid: '',
        }),
    },
    attendanceSessionOptions: { type: Array, default: () => [] },
    subjectOptions: { type: Array, default: () => [] },
    dateOptions: { type: Array, default: () => [] },
    instructorOptions: { type: Array, default: () => [] },
    currentUserRole: { type: String, default: '' },
    canInspectAllAttendance: { type: Boolean, default: false },
    absentDefaultDays: { type: Number, default: 15 },
});

const page = usePage();
const currentRole = computed(() =>
    String(
        props.currentUserRole || page.props.auth?.user?.role || '',
    ).toLowerCase(),
);
const canInspectAllAttendance = computed(
    () => props.canInspectAllAttendance || currentRole.value === 'admin',
);
const logs = computed(() => props.logs);

const attendanceSessionFilter = ref(props.filters.attendance_id ?? '');
const dateFilter = ref(props.filters.date ?? '');
const subjectFilter = ref(props.filters.subject ?? '');
const instructorFilter = ref(props.filters.instructor ?? '');
const instructorRfidFilter = ref(props.filters.instructor_rfid ?? '');
const evidencePreview = ref(null);
const expandedEvidenceRows = ref({});

const openEvidence = (url, title) => {
    evidencePreview.value = { url, title };
};

const closeEvidence = () => {
    evidencePreview.value = null;
};

const rowKey = (record) => String(record.id ?? record.attendance_id ?? '');

const hasEvidenceEvents = (record) =>
    Array.isArray(record.evidence_events) && record.evidence_events.length > 1;

const isEvidenceExpanded = (record) =>
    expandedEvidenceRows.value[rowKey(record)] === true;

const toggleEvidenceEvents = (record) => {
    const key = rowKey(record);
    expandedEvidenceRows.value = {
        ...expandedEvidenceRows.value,
        [key]: !expandedEvidenceRows.value[key],
    };
};

const groupedLogs = computed(() => {
    const groups = new Map();

    logs.value.forEach((log) => {
        const date = log.date || 'No date';
        const room = log.room || 'No room';
        const instructor = log.instructor || 'Unassigned Instructor';
        const subject = log.subject || 'N/A';
        const sessionTime = log.session_time || 'N/A';
        const key = `${log.session_id || ''}|${date}|${room}|${instructor}`;

        if (!groups.has(key)) {
            groups.set(key, {
                key,
                date,
                room,
                instructor,
                subject,
                sessionTime,
                items: [],
                counts: {},
            });
        }

        const group = groups.get(key);
        group.items.push(log);
        group.counts[log.status] = (group.counts[log.status] ?? 0) + 1;
    });

    return Array.from(groups.values());
});

const applyFilters = () => {
    router.get(
        route('admin.attendance.logs'),
        {
            attendance_id: attendanceSessionFilter.value,
            date: dateFilter.value,
            subject: subjectFilter.value,
            instructor: canInspectAllAttendance.value
                ? instructorFilter.value
                : '',
            instructor_rfid: canInspectAllAttendance.value
                ? instructorRfidFilter.value
                : '',
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const applyInstructorRfidFilter = () => {
    instructorFilter.value = '';
    applyFilters();
};

const resetFilters = () => {
    attendanceSessionFilter.value = '';
    dateFilter.value = '';
    subjectFilter.value = '';
    instructorFilter.value = '';
    instructorRfidFilter.value = '';
    applyFilters();
};

const getStatusCount = (status) =>
    logs.value.filter((log) => log.status === status).length;

const statusClass = (status) => {
    if (status === 'Present') return 'bg-emerald-50 text-emerald-700';
    if (status === 'Pending') return 'bg-sky-50 text-sky-700';
    if (status === 'Incomplete Attendance')
        return 'bg-purple-50 text-purple-700';
    if (status === 'Absent') return 'bg-red-50 text-red-700';
    return 'bg-amber-50 text-amber-700';
};

const summaryToneClass = {
    present: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    late: 'border-amber-200 bg-amber-50 text-amber-700',
    pending: 'border-sky-200 bg-sky-50 text-sky-700',
    incomplete: 'border-purple-200 bg-purple-50 text-purple-700',
    absent: 'border-red-200 bg-red-50 text-red-700',
    total: 'border-blue-200 bg-blue-50 text-blue-700',
};

const StatusSummaryCard = defineComponent({
    props: {
        label: { type: String, required: true },
        tone: { type: String, required: true },
        count: { type: Number, required: true },
    },
    setup(cardProps) {
        return () =>
            h(
                'div',
                {
                    class: [
                        'rounded-lg border p-4',
                        summaryToneClass[cardProps.tone] ??
                            summaryToneClass.total,
                    ],
                },
                [
                    h(
                        'h3',
                        { class: 'text-sm font-semibold' },
                        cardProps.label,
                    ),
                    h(
                        'p',
                        { class: 'text-2xl font-bold' },
                        String(cardProps.count),
                    ),
                ],
            );
    },
});
</script>
