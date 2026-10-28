<!-- FEATURE:parent-student-view - konektadong model, service, route, o UI para sa feature na ito. -->
<!-- FEATURE:student-attendance-history - UI para sa attendance history. -->
<script setup>
import LinkedStudentSelector from '@/components/StudentPortal/LinkedStudentSelector.vue';
import ExcuseLettersPage from '@/pages/StudentParent/ExcuseLetters/ExcuseLettersPage.vue';
import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { FileText } from 'lucide-vue-next';

const props = defineProps({
    student: { type: Object, default: null },
    linkedStudents: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
    attendance: { type: Array, default: () => [] },
    academicYears: { type: Array, default: () => [] },
    selectedAcademicYearId: { type: [Number, String, null], default: null },
    currentUserRole: { type: String, default: 'student' },
    absentAttendanceDays: { type: Number, default: 15 },
    parentExcuseLettersEnabled: { type: Boolean, default: false },
    parentApprovalRequired: { type: Boolean, default: false },
    activeAcademicYear: { type: Object, default: null },
    recipientSuggestions: { type: Array, default: () => [] },
    letters: { type: Array, default: () => [] },
});

const search = ref('');
const statusFilter = ref('');
const currentPage = ref(1);
const evidencePreview = ref(null);
const selectedExcuseAttendance = ref(null);
const expandedEvidenceRows = ref({});
const pageSize = 10;
let previousBodyOverflow = '';

watch(selectedExcuseAttendance, (selected) => {
    if (selected) {
        previousBodyOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        return;
    }

    document.body.style.overflow = previousBodyOverflow;
});

onBeforeUnmount(() => {
    document.body.style.overflow = previousBodyOverflow;
});

const canRequestExcuseLetter = (record) => {
    if (
        props.currentUserRole !== 'student' ||
        String(record.status || '').toLowerCase() !== 'absent' ||
        !record.date
    ) {
        return false;
    }

    const [year, month, day] = String(record.date).split('-').map(Number);
    const attendanceDate = Date.UTC(year, month - 1, day);
    const todayParts = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Manila',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).format(new Date()).split('-').map(Number);
    const today = Date.UTC(todayParts[0], todayParts[1] - 1, todayParts[2]);
    const daysSinceAttendance = Math.floor((today - attendanceDate) / 86400000);

    return daysSinceAttendance >= 0 && daysSinceAttendance < props.absentAttendanceDays;
};

const openExcuseLetter = (record) => {
    selectedExcuseAttendance.value = record;
};

const closeExcuseLetter = () => {
    selectedExcuseAttendance.value = null;
};

const filteredAttendance = computed(() => {
    const term = search.value.trim().toLowerCase();

    return props.attendance.filter((record) => {
        const matchesSearch =
            !term ||
            [
                record.date,
                record.subject,
                record.room,
                record.time_in,
                record.time_out,
                record.status,
                record.source,
            ].some((value) =>
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

// @function resetFilters: Nire-reset ang filters sa Attendance flow.
// @useIn resetFilters: resources/js/pages/StudentParent/Attendance/AttendancePage.vue template @click
const resetFilters = () => {
    search.value = '';
    statusFilter.value = '';
    currentPage.value = 1;
};

// @function changeAcademicYear: Pinoproseso ang change academic year para sa Attendance.
// @useIn changeAcademicYear: resources/js/pages/StudentParent/Attendance/AttendancePage.vue template @change
const changeAcademicYear = (event) => {
    router.get(
        window.location.pathname,
        {
            academic_year_id: event.target.value || undefined,
            student_id: props.selectedStudentId || undefined,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

// @function openEvidence: Binubuksan ang evidence sa Attendance flow.
// @useIn openEvidence: resources/js/pages/StudentParent/Attendance/AttendancePage.vue template @click
const openEvidence = (url, title) => {
    evidencePreview.value = { url, title };
};

// @function closeEvidence: Isinasara ang evidence sa Attendance flow.
// @useIn closeEvidence: resources/js/pages/StudentParent/Attendance/AttendancePage.vue template @click
const closeEvidence = () => {
    evidencePreview.value = null;
};

// @function rowKey: Binubuo ang row key value.
// @useIn rowKey: resources/js/pages/StudentParent/Attendance/AttendancePage.vue:94
const rowKey = (record) => String(record.attendance_id ?? '');

// @function hasEvidenceEvents: Sinusuri kung evidence events para sa Attendance.
// @useIn hasEvidenceEvents: resources/js/pages/StudentParent/Attendance/AttendancePage.vue template
const hasEvidenceEvents = (record) =>
    Array.isArray(record.evidence_events) && record.evidence_events.length > 0;

// @function isEvidenceExpanded: Sinusuri kung evidence expanded para sa Attendance.
// @useIn isEvidenceExpanded: resources/js/pages/StudentParent/Attendance/AttendancePage.vue template
const isEvidenceExpanded = (record) =>
    expandedEvidenceRows.value[rowKey(record)] === true;

// @function toggleEvidenceEvents: Tina-toggle ang evidence events sa Attendance flow.
// @useIn toggleEvidenceEvents: resources/js/pages/StudentParent/Attendance/AttendancePage.vue template @click
const toggleEvidenceEvents = (record) => {
    const key = rowKey(record);
    expandedEvidenceRows.value = {
        ...expandedEvidenceRows.value,
        [key]: !expandedEvidenceRows.value[key],
    };
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
                        My Attendance
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

            <div class="mt-4 flex flex-wrap items-center gap-2">
                <select
                    :value="selectedAcademicYearId || ''"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                    @change="changeAcademicYear"
                >
                    <option
                        v-for="year in academicYears"
                        :key="year.academic_year_id"
                        :value="year.academic_year_id"
                    >
                        {{ year.name }} ({{ year.status }})
                    </option>
                </select>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Search attendance..."
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                />
                <select
                    v-model="statusFilter"
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm"
                >
                    <option value="">All status</option>
                    <option value="present">Present</option>
                    <option value="late">Late</option>
                    <option value="absent">Absent</option>
                    <option value="pending">Pending</option>
                </select>
                <button
                    class="rounded-md border border-slate-300 px-3 py-2 text-sm font-bold text-slate-600"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="bg-slate-50 text-xs text-slate-500 uppercase">
                        <tr>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Class Type</th>
                            <th class="px-3 py-2">Subject</th>
                            <th class="px-3 py-2">Room</th>
                            <th class="px-3 py-2">Class Time</th>
                            <th class="px-3 py-2">Time In</th>
                            <th class="px-3 py-2">Time Out</th>
                            <th class="px-3 py-2">Time In Image</th>
                            <th class="px-3 py-2">Time Out Image</th>
                            <th class="px-3 py-2">Details</th>
                            <th class="px-3 py-2">Duration</th>
                            <th class="px-3 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template
                            v-for="record in paginatedAttendance"
                            :key="record.attendance_id"
                        >
                            <tr
                                :class="
                                    record.source === 'online'
                                        ? 'bg-violet-50/60'
                                        : ''
                                "
                            >
                                <td class="px-3 py-3">{{ record.date }}</td>
                                <td class="px-3 py-3">
                                    <span
                                        class="rounded-full px-2 py-1 text-[10px] font-black uppercase"
                                        :class="
                                            record.source === 'online'
                                                ? 'bg-violet-100 text-violet-700'
                                                : 'bg-blue-50 text-blue-700'
                                        "
                                    >
                                        {{
                                            record.source === 'online'
                                                ? 'Online'
                                                : 'In Person'
                                        }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">{{ record.subject }}</td>
                                <td class="px-3 py-3">
                                    {{ record.room || '-' }}
                                </td>
                                <td class="px-3 py-3">
                                    {{ record.class_time || '-' }}
                                </td>
                                <td class="px-3 py-3">
                                    {{ record.time_in || '-' }}
                                </td>
                                <td class="px-3 py-3">
                                    {{ record.time_out || '-' }}
                                </td>
                                <td class="px-3 py-3">
                                    <button
                                        v-if="record.time_in_image_url"
                                        type="button"
                                        @click="
                                            openEvidence(
                                                record.time_in_image_url,
                                                `${record.subject} - Time In`,
                                            )
                                        "
                                    >
                                        <img
                                            :src="record.time_in_image_url"
                                            alt="Time-in face evidence"
                                            class="h-12 w-12 rounded-md border border-slate-200 object-cover hover:ring-2 hover:ring-sky-400"
                                        />
                                    </button>
                                    <span v-else class="text-xs text-slate-400"
                                        >Not captured</span
                                    >
                                </td>
                                <td class="px-3 py-3">
                                    <button
                                        v-if="record.time_out_image_url"
                                        type="button"
                                        @click="
                                            openEvidence(
                                                record.time_out_image_url,
                                                `${record.subject} - Time Out`,
                                            )
                                        "
                                    >
                                        <img
                                            :src="record.time_out_image_url"
                                            alt="Time-out face evidence"
                                            class="h-12 w-12 rounded-md border border-slate-200 object-cover hover:ring-2 hover:ring-sky-400"
                                        />
                                    </button>
                                    <span v-else class="text-xs text-slate-400"
                                        >{{ record.time_out ? 'Not captured' : 'No check-out' }}</span
                                    >
                                </td>
                                <td class="px-3 py-3">
                                    <button
                                        v-if="hasEvidenceEvents(record)"
                                        type="button"
                                        class="rounded-md border border-slate-300 px-2 py-1 text-xs font-bold text-slate-600 hover:bg-slate-50"
                                        @click="toggleEvidenceEvents(record)"
                                    >
                                        {{
                                            isEvidenceExpanded(record)
                                                ? 'Hide'
                                                : 'Show'
                                        }}
                                    </button>
                                    <span v-else class="text-xs text-slate-400"
                                        >None</span
                                    >
                                </td>
                                <td class="px-3 py-3">
                                    {{ record.duration || '-' }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-2 font-semibold text-slate-800">
                                        <span>{{ record.status }}</span>
                                        <span
                                            v-if="canRequestExcuseLetter(record)"
                                            class="group relative inline-flex"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex h-7 w-7 items-center justify-center rounded-md border border-sky-200 bg-white text-sky-700 transition hover:bg-sky-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-700"
                                                :aria-label="`Write excuse letter for ${record.subject} absent on ${record.date}`"
                                                :aria-describedby="`excuse-letter-tooltip-${record.attendance_id}`"
                                                @click="openExcuseLetter(record)"
                                            >
                                                <FileText class="h-4 w-4" aria-hidden="true" />
                                            </button>
                                            <span
                                                :id="`excuse-letter-tooltip-${record.attendance_id}`"
                                                role="tooltip"
                                                class="pointer-events-none absolute bottom-full right-0 z-20 mb-2 hidden whitespace-nowrap rounded bg-slate-900 px-2.5 py-1.5 text-xs font-medium text-white shadow group-hover:block group-focus-within:block"
                                            >
                                                Write excuse letter
                                            </span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-if="isEvidenceExpanded(record)"
                                class="bg-slate-50"
                            >
                                <td colspan="12" class="px-3 py-4">
                                    <div
                                        class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
                                    >
                                        <div
                                            v-for="event in record.evidence_events"
                                            :key="event.id"
                                            class="rounded-md border border-slate-200 bg-white p-3"
                                        >
                                            <div
                                                class="text-sm font-semibold text-slate-800"
                                            >
                                                {{ event.tap_type }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                #{{
                                                    event.tap_sequence_number
                                                }}
                                                | {{ event.time || 'N/A' }}
                                            </div>
                                            <p
                                                class="mt-2 min-h-8 text-xs text-slate-500"
                                            >
                                                {{
                                                    event.remarks ||
                                                    event.validation_result ||
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
                                                    @click="
                                                        openEvidence(
                                                            event.time_in_image_url,
                                                            `${record.subject} - ${event.tap_type}`,
                                                        )
                                                    "
                                                >
                                                    <img
                                                        :src="
                                                            event.time_in_image_url
                                                        "
                                                        alt="Tap face evidence"
                                                        class="h-12 w-12 rounded-md border border-slate-200 object-cover hover:ring-2 hover:ring-sky-400"
                                                    />
                                                </button>
                                                <button
                                                    v-if="
                                                        event.time_out_image_url
                                                    "
                                                    type="button"
                                                    @click="
                                                        openEvidence(
                                                            event.time_out_image_url,
                                                            `${record.subject} - ${event.tap_type}`,
                                                        )
                                                    "
                                                >
                                                    <img
                                                        :src="
                                                            event.time_out_image_url
                                                        "
                                                        alt="Tap face evidence"
                                                        class="h-12 w-12 rounded-md border border-slate-200 object-cover hover:ring-2 hover:ring-sky-400"
                                                    />
                                                </button>
                                                <span
                                                    v-if="
                                                        !event.time_in_image_url &&
                                                        !event.time_out_image_url
                                                    "
                                                    class="text-xs text-slate-400"
                                                    >No image</span
                                                >
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <tr v-if="paginatedAttendance.length === 0">
                            <td
                                colspan="12"
                                class="px-3 py-8 text-center text-slate-400"
                            >
                                No attendance records found.
                            </td>
                        </tr>
                    </tbody>
                </table>
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

            <div
                v-if="selectedExcuseAttendance"
                class="fixed inset-0 z-[60] flex items-center justify-center overflow-hidden bg-slate-950/60 p-3 sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-label="Write an excuse letter"
                @click.self="closeExcuseLetter"
            >
                <section class="relative max-h-[calc(100dvh-1.5rem)] w-full max-w-7xl overflow-y-auto overscroll-contain rounded-lg bg-white p-3 shadow-2xl sm:max-h-[calc(100dvh-3rem)] sm:p-5">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Excuse letter for {{ selectedExcuseAttendance.subject }}</h2>
                            <p class="text-sm text-slate-500">Absent on {{ selectedExcuseAttendance.date }}</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-md border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            aria-label="Close excuse letter"
                            @click="closeExcuseLetter"
                        >
                            Close
                        </button>
                    </div>
                    <ExcuseLettersPage
                        :key="selectedExcuseAttendance.attendance_id"
                        :student="student"
                        :linked-students="linkedStudents"
                        :selected-student-id="selectedStudentId"
                        :current-user-role="currentUserRole"
                        :letters="letters"
                        :recipient-suggestions="recipientSuggestions"
                        :parent-excuse-letters-enabled="parentExcuseLettersEnabled"
                        :parent-approval-required="parentApprovalRequired"
                        :active-academic-year="activeAcademicYear"
                        :initial-date="selectedExcuseAttendance.date"
                        :initial-subject="selectedExcuseAttendance.subject"
                    />
                </section>
            </div>

            <div class="mt-4 flex justify-end gap-2 text-sm">
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
                    @click="currentPage = Math.min(totalPages, currentPage + 1)"
                >
                    Next
                </button>
            </div>
        </section>
    </div>
</template>
