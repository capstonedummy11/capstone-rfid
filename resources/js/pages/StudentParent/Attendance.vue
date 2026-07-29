<script setup>
import LinkedStudentSelector from '@/components/StudentPortal/LinkedStudentSelector.vue';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    student: { type: Object, default: null },
    linkedStudents: { type: Array, default: () => [] },
    selectedStudentId: { type: [Number, String, null], default: null },
    attendance: { type: Array, default: () => [] },
});

const search = ref('');
const statusFilter = ref('');
const currentPage = ref(1);
const evidencePreview = ref(null);
const expandedEvidenceRows = ref({});
const pageSize = 10;

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

const resetFilters = () => {
    search.value = '';
    statusFilter.value = '';
    currentPage.value = 1;
};

const openEvidence = (url, title) => {
    evidencePreview.value = { url, title };
};

const closeEvidence = () => {
    evidencePreview.value = null;
};

const rowKey = (record) => String(record.attendance_id ?? '');

const hasEvidenceEvents = (record) =>
    Array.isArray(record.evidence_events) && record.evidence_events.length > 0;

const isEvidenceExpanded = (record) =>
    expandedEvidenceRows.value[rowKey(record)] === true;

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
                            <tr>
                                <td class="px-3 py-3">{{ record.date }}</td>
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
                                        >Not captured</span
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
                                <td
                                    class="px-3 py-3 font-semibold text-slate-800"
                                >
                                    {{ record.status }}
                                </td>
                            </tr>
                            <tr
                                v-if="isEvidenceExpanded(record)"
                                class="bg-slate-50"
                            >
                                <td colspan="11" class="px-3 py-4">
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
                                colspan="11"
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
