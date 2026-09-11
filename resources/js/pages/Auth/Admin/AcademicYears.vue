<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Swal from 'sweetalert2';
import AuthLayout from '@/layouts/AuthLayout.vue';

const props = defineProps({
    academicYears: { type: Array, default: () => [] },
    activeAcademicYearId: { type: Number, default: null },
    rollovers: { type: Array, default: () => [] },
    legacyFallbacks: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    starts_on: '',
    ends_on: '',
    active_semester: '',
});

const submit = () => {
    form.post(route('admin.academic-years.store'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const perform = async (year, action, warning) => {
    const result = await Swal.fire({
        icon: 'warning',
        title: `${action.charAt(0).toUpperCase()}${action.slice(1)} ${year.name}?`,
        text: warning,
        showCancelButton: true,
        confirmButtonText: action.charAt(0).toUpperCase() + action.slice(1),
        confirmButtonColor: action === 'activate' ? '#2563eb' : '#dc2626',
    });
    if (!result.isConfirmed) return;

    form.post(
        route(`admin.academic-years.${action}`, year.academic_year_id),
        { preserveScroll: true },
    );
};

const reopen = async (year) => {
    const result = await Swal.fire({
        icon: 'warning',
        title: `Reopen ${year.name}?`,
        text: 'It will return to Draft and remain inactive until explicitly activated.',
        input: 'textarea',
        inputLabel: 'Required audit reason',
        inputPlaceholder: 'Explain why this academic year must be reopened...',
        inputValidator: (value) =>
            !value || value.trim().length < 10
                ? 'Enter a reason containing at least 10 characters.'
                : undefined,
        showCancelButton: true,
        confirmButtonText: 'Reopen as Draft',
    });
    if (!result.isConfirmed) return;

    useForm({ reason: result.value.trim() }).post(
        route('admin.academic-years.reopen', year.academic_year_id),
        { preserveScroll: true },
    );
};

const badgeClass = (status) => ({
    draft: 'bg-amber-100 text-amber-800',
    active: 'bg-emerald-100 text-emerald-800',
    closed: 'bg-slate-200 text-slate-700',
    archived: 'bg-purple-100 text-purple-800',
}[status] ?? 'bg-slate-100 text-slate-700');

const rolloverSourceId = ref('');
const rolloverDestinationId = ref('');
const rolloverMode = ref('year');
const destinationSemester = ref('2nd Semester');
const preview = ref(null);
const previewBusy = ref(false);
const sectionMappings = ref([]);

const loadPreview = async () => {
    if (!rolloverSourceId.value || !rolloverDestinationId.value) return;
    previewBusy.value = true;
    try {
        const params = new URLSearchParams({
            destination_academic_year_id: rolloverDestinationId.value,
            mode: rolloverMode.value,
        });
        if (rolloverMode.value === 'semester') params.set('destination_semester', '2nd Semester');
        const url = route('admin.academic-years.rollover-preview', rolloverSourceId.value) + `?${params}`;
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Preview failed.');
        preview.value = data;
        sectionMappings.value = data.source_sections.map((section) => ({
            source_section_id: section.section_id,
            source_label: `${section.section_name} · Grade ${section.year_level} · ${section.semester}`,
            destination_section_id: '',
            destination_name: rolloverMode.value === 'semester' ? section.section_name : '',
            destination_year_level: data.transition.advance_grade ? 12 : Number(section.year_level),
        }));
    } catch (error) {
        Swal.fire('Preview unavailable', error.message, 'error');
    } finally {
        previewBusy.value = false;
    }
};

const executeRollover = async () => {
    const invalid = sectionMappings.value.some((mapping) => !mapping.destination_section_id && !mapping.destination_name.trim());
    if (invalid) return Swal.fire('Mapping required', 'Select or name a destination for every copied section.', 'error');
    const result = await Swal.fire({
        icon: 'warning', title: 'Execute this rollover?',
        text: 'This creates only destination configuration and enrollments. Historical operational records are never copied.',
        showCancelButton: true, confirmButtonText: 'Execute Rollover', confirmButtonColor: '#2563eb',
    });
    if (!result.isConfirmed) return;
    useForm({
        destination_academic_year_id: Number(rolloverDestinationId.value),
        mode: rolloverMode.value,
        destination_semester: rolloverMode.value === 'semester' ? '2nd Semester' : null,
        section_mappings: sectionMappings.value.map(({ source_label, ...mapping }) => ({
            ...mapping,
            destination_section_id: mapping.destination_section_id ? Number(mapping.destination_section_id) : null,
        })),
        decisions: preview.value.items.map((item) => ({
            source_student_enrollment_id: item.source_student_enrollment_id,
            decision: item.recommended_decision,
        })),
    }).post(route('admin.academic-years.rollover', rolloverSourceId.value), { preserveScroll: true });
};

const academicYearsForRollover = (kind) => props.academicYears.filter((year) => {
    if (rolloverMode.value === 'semester') return ['active', 'draft'].includes(year.status);
    return kind === 'source' ? ['active', 'closed'].includes(year.status) : year.status === 'draft';
});
const selectedRolloverSource = computed(() => props.academicYears.find((year) => String(year.academic_year_id) === String(rolloverSourceId.value)));
const semesterRolloverUnavailable = computed(() => selectedRolloverSource.value?.active_semester === '2nd Semester');
const rolloverSourceOptions = computed(() => academicYearsForRollover('source'));
const rolloverDestinationOptions = computed(() => academicYearsForRollover('destination'));
const onRolloverSourceChange = () => {
    if (rolloverMode.value === 'semester' && semesterRolloverUnavailable.value) {
        rolloverMode.value = 'year';
        destinationSemester.value = '2nd Semester';
    }
    if (rolloverMode.value === 'semester') rolloverDestinationId.value = rolloverSourceId.value;
    preview.value = null;
};
</script>

<template>
    <Head title="Academic Years" />
    <AuthLayout>
        <div class="space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-slate-900">Academic Years</h1>
                <p class="mt-1 text-sm text-slate-600">
                    Prepare school years as drafts, activate one for operations, and close completed years.
                </p>
            </header>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Create draft academic year</h2>
                <form class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4" @submit.prevent="submit">
                    <label class="text-sm font-medium text-slate-700">
                        Year name
                        <input v-model="form.name" class="mt-1 w-full rounded-lg border-slate-300" placeholder="2026-2027" required />
                        <span v-if="form.errors.name" class="mt-1 block text-xs text-red-600">{{ form.errors.name }}</span>
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        Start date
                        <input v-model="form.starts_on" type="date" class="mt-1 w-full rounded-lg border-slate-300" required />
                        <span v-if="form.errors.starts_on" class="mt-1 block text-xs text-red-600">{{ form.errors.starts_on }}</span>
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        End date
                        <input v-model="form.ends_on" type="date" class="mt-1 w-full rounded-lg border-slate-300" required />
                        <span v-if="form.errors.ends_on" class="mt-1 block text-xs text-red-600">{{ form.errors.ends_on }}</span>
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        Current semester (optional)
                        <select v-model="form.active_semester" class="mt-1 w-full rounded-lg border-slate-300">
                            <option value="">Not selected</option>
                            <option>1st Semester</option>
                            <option>2nd Semester</option>
                        </select>
                    </label>
                    <div class="md:col-span-2 xl:col-span-4">
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                            {{ form.processing ? 'Creating...' : 'Create Draft' }}
                        </button>
                    </div>
                </form>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Year rollover</h2>
                <p class="mt-1 text-sm text-slate-600">Preview first, map sections, then create destination enrollments transactionally. Subjects and schedules are configured separately for each semester.</p>
                <div class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]">
                    <select v-model="rolloverMode" class="rounded-lg border-slate-300" @change="rolloverSourceId = ''; rolloverDestinationId = ''; destinationSemester = '2nd Semester'; preview = null">
                        <option value="year">Year rollover</option>
                        <option value="semester" :disabled="semesterRolloverUnavailable">Semester-only rollover</option>
                    </select>
                    <select v-model="rolloverSourceId" class="rounded-lg border-slate-300" @change="onRolloverSourceChange">
                        <option value="">Source academic year</option>
                        <option v-for="year in rolloverSourceOptions" :key="year.academic_year_id" :value="year.academic_year_id">{{ year.name }} ({{ year.status }})</option>
                    </select>
                    <p v-if="semesterRolloverUnavailable" class="text-xs font-medium text-amber-700 md:col-span-4">
                        Semester-only rollover is unavailable because this academic year is already on 2nd Semester.
                    </p>
                    <select v-model="rolloverDestinationId" class="rounded-lg border-slate-300" :disabled="rolloverMode === 'semester'">
                        <option value="">Destination academic year</option>
                        <option v-for="year in rolloverDestinationOptions" :key="year.academic_year_id" :value="year.academic_year_id">{{ year.name }} ({{ year.status }})</option>
                    </select>
                    <div v-if="rolloverMode === 'semester'" class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700">
                        Destination: 2nd Semester
                    </div>
                    <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50" :disabled="previewBusy || !rolloverSourceId || !rolloverDestinationId" @click="loadPreview">
                        {{ previewBusy ? 'Previewing…' : 'Preview' }}
                    </button>
                </div>

                <div v-if="preview" class="mt-5 space-y-4">
                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-sm text-blue-900">
                        <strong>Automatic rollover:</strong> {{ preview.transition.description }}
                        <span class="ml-1">Destination semester: {{ preview.transition.destination_semester }}.</span>
                        <span v-if="preview.transition.advance_grade" class="ml-1">Grade 12 students will be archived as graduated and will not receive a destination enrollment.</span>
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                        <div v-for="(value, label) in preview.counts" :key="label" class="rounded-lg bg-slate-50 p-3">
                            <div class="text-xs font-semibold uppercase text-slate-500">{{ label }}</div><div class="text-xl font-bold">{{ value }}</div>
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div v-for="mapping in sectionMappings" :key="mapping.source_section_id" class="grid gap-2 rounded-lg border border-slate-200 p-3 lg:grid-cols-[1.2fr_1fr_1fr_120px]">
                            <div class="text-sm font-semibold text-slate-700">{{ mapping.source_label }}</div>
                            <div v-if="rolloverMode === 'semester'" class="rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                                Same section branch → 2nd Semester
                            </div>
                            <select v-else v-model="mapping.destination_section_id" class="rounded-md border-slate-300 text-sm" @change="mapping.destination_name = ''">
                                <option value="">Create a new destination section</option>
                                <option v-for="section in preview.destination_sections" :key="section.section_id" :value="section.section_id">{{ section.section_name }} · Grade {{ section.year_level }}</option>
                            </select>
                            <input v-if="rolloverMode !== 'semester'" v-model="mapping.destination_name" :disabled="Boolean(mapping.destination_section_id)" class="rounded-md border-slate-300 text-sm disabled:bg-slate-100" placeholder="New destination section name" />
                            <div v-else class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600">{{ mapping.destination_name }}</div>
                            <select v-model="mapping.destination_year_level" disabled class="rounded-md border-slate-300 bg-slate-100 text-sm"><option :value="11">Grade 11</option><option :value="12">Grade 12</option></select>
                        </div>
                    </div>
                    <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" @click="executeRollover">Execute reviewed rollover</button>
                </div>

                <div v-if="rollovers.length" class="mt-6 overflow-x-auto">
                    <table class="min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2">Source</th><th>Destination</th><th>Status</th><th>Completed</th></tr></thead>
                        <tbody><tr v-for="rollover in rollovers" :key="rollover.id" class="border-b border-slate-100"><td class="py-2">{{ rollover.source }}</td><td>{{ rollover.destination }}</td><td class="capitalize">{{ rollover.status }}</td><td>{{ rollover.completed_at || '—' }}</td></tr></tbody>
                    </table>
                </div>
            </section>

            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Legacy fallback monitor</h2>
                <p v-if="!legacyFallbacks.length" class="mt-2 text-sm font-semibold text-emerald-700">No legacy academic fallback has been observed.</p>
                <div v-else class="mt-3 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2">Context</th><th>Uses</th><th>Last used</th></tr></thead><tbody><tr v-for="event in legacyFallbacks" :key="event.context" class="border-b border-slate-100"><td class="py-2 font-medium">{{ event.context }}</td><td>{{ event.use_count }}</td><td>{{ event.last_used_at }}</td></tr></tbody></table></div>
            </section>

            <section class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div v-if="!props.academicYears.length" class="p-8 text-center text-sm text-slate-500">
                    No academic years have been created.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Academic year</th>
                                <th class="px-4 py-3">Dates</th>
                                <th class="px-4 py-3">Semester</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="year in props.academicYears" :key="year.academic_year_id">
                                <td class="px-4 py-4 font-semibold text-slate-900">{{ year.name }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ year.starts_on }} – {{ year.ends_on }}</td>
                                <td class="px-4 py-4 text-slate-600">{{ year.active_semester || 'Not selected' }}</td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize" :class="badgeClass(year.status)">
                                        {{ year.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        <button v-if="year.status === 'draft' || year.status === 'closed'" class="rounded-md border border-blue-300 px-3 py-1.5 text-xs font-semibold text-blue-700" @click="perform(year, 'activate', 'The currently active year, if any, will be closed.')">Activate</button>
                                        <button v-if="year.status === 'active'" class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700" @click="perform(year, 'close', 'Closing removes the active year. Current academic workflows are not yet linked to this foundation.')">Close</button>
                                        <button v-if="year.status === 'closed'" class="rounded-md border border-purple-300 px-3 py-1.5 text-xs font-semibold text-purple-700" @click="perform(year, 'archive', 'Archived years remain available for historical reference.')">Archive</button>
                                        <button v-if="year.status === 'closed' || year.status === 'archived'" class="rounded-md border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-700" @click="reopen(year)">Reopen</button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </AuthLayout>
</template>
