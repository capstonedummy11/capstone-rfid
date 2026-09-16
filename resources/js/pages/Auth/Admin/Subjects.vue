<template>
    <div class="w-full">
        <div class="mx-auto max-w-[1400px] px-4 py-6">
            <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h1 class="text-3xl font-bold">Subjects Management</h1>
                        <p class="text-sm text-slate-500">
                            Manage subjects, assigned section, instructor, and
                            semester.
                        </p>
                    </div>
                    <button
                        @click="openAddModal"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
                    >
                        Add Subject
                    </button>
                </div>
            </section>

            <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Search</label
                        >
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Subject name | code | department"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            @input="onFilterChange"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Semester</label
                        >
                        <select
                            v-model="selectedSemester"
                            @change="onFilterChange"
                            class="w-full rounded-md border border-slate-300 px-3 py-2"
                        >
                            <option value="">All Semesters</option>
                            <option value="1st Semester">1st Semester</option>
                            <option value="2nd Semester">2nd Semester</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Academic Year</label
                        >
                        <select
                            v-model="selectedAcademicYear"
                            @change="onFilterChange"
                            class="w-full rounded-md border border-slate-300 px-3 py-2"
                        >
                            <option value="all">All Academic Years</option>
                            <option
                                v-for="year in academicYears"
                                :key="year.academic_year_id"
                                :value="year.academic_year_id"
                            >
                                {{ year.name }} ({{ year.status }})
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end justify-end">
                        <button
                            @click="resetFilters"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            Reset Filters
                        </button>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Subject Code
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Subject Name
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Description
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Department
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Unit
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Semester
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Section
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Instructor
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="subject in props.subjects"
                                :key="subject.subject_id"
                                class="hover:bg-gray-50"
                            >
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ subject.subject_code }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ subject.subject_name }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ subject.subject_description || 'N/A' }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ subject.department || 'N/A' }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ subject.unit }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{
                                        subject.offerings?.[0]?.semester ||
                                        'N/A'
                                    }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div
                                        v-if="subject.offerings?.length"
                                        class="space-y-1"
                                    >
                                        <div
                                            v-for="offering in subject.offerings"
                                            :key="offering.subject_offering_id"
                                            class="rounded bg-slate-50 px-2 py-1 text-xs"
                                        >
                                            <strong>{{
                                                offering.section_name
                                            }}</strong>
                                            · {{ offering.academic_year }} ·
                                            {{ offering.semester }}
                                        </div>
                                    </div>
                                    <span v-else>Unassigned</span>
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div
                                        v-if="subject.offerings?.length"
                                        class="space-y-1"
                                    >
                                        <div
                                            v-for="offering in subject.offerings"
                                            :key="offering.subject_offering_id"
                                            class="flex items-center justify-between gap-2 text-xs"
                                        >
                                            <span>{{
                                                offering.instructor_name ||
                                                'Unassigned'
                                            }}</span>
                                            <button
                                                v-if="
                                                    offering.is_writable &&
                                                    offering.instructor_name
                                                "
                                                @click="
                                                    removeInstructor(offering)
                                                "
                                                class="text-rose-600 hover:underline"
                                            >
                                                Remove Instructor
                                            </button>
                                            <span v-else class="text-slate-400"
                                                >Locked</span
                                            >
                                        </div>
                                    </div>
                                    <span v-else>Unassigned</span>
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="openEditModal(subject)"
                                            class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="openOfferingModal(subject)"
                                            class="rounded-md bg-sky-600 px-3 py-1 text-sm text-white hover:bg-sky-700"
                                        >
                                            Add Offering
                                        </button>
                                        <button
                                            @click="deleteSubject(subject)"
                                            class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="props.subjects.length === 0"
                        class="py-8 text-center text-gray-500"
                    >
                        No subject records found.
                    </div>
                </div>
            </section>

            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            >
                <div
                    class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-6"
                >
                    <h2 class="mb-4 text-xl font-semibold">
                        {{ isEditing ? 'Edit Subject' : 'Add New Subject' }}
                    </h2>

                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div
                            v-if="Object.keys(form.errors).length"
                            class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700"
                        >
                            <p
                                v-for="(message, field) in form.errors"
                                :key="field"
                            >
                                {{ message }}
                            </p>
                        </div>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Subject Code *</label
                                >
                                <input
                                    v-model="form.subject_code"
                                    type="text"
                                    placeholder="e.g., CP1"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                    required
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Subject Name *</label
                                >
                                <input
                                    v-model="form.subject_name"
                                    type="text"
                                    placeholder="e.g., Computer Programming 1"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >Description</label
                            >
                            <textarea
                                v-model="form.subject_description"
                                rows="3"
                                placeholder="e.g., Introduction to programming concepts using variables, conditions, loops, and functions."
                                class="w-full rounded-md border border-slate-300 px-3 py-2"
                            />
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Department</label
                                >
                                <input
                                    v-model="form.department"
                                    type="text"
                                    placeholder="e.g., ICT"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Unit *</label
                                >
                                <input
                                    v-model.number="form.unit"
                                    type="number"
                                    min="0"
                                    placeholder="e.g., 3"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                    required
                                />
                            </div>
                        </div>

                        <div
                            v-if="!isEditing"
                            class="grid grid-cols-1 gap-4 md:grid-cols-4"
                        >
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Academic Year *</label
                                >
                                <select
                                    v-model="formAcademicYearId"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                >
                                    <option value="">All writable years</option>
                                    <option
                                        v-for="year in sectionAcademicYears"
                                        :key="year.academic_year_id"
                                        :value="year.academic_year_id"
                                    >
                                        {{ year.name }} ({{ year.status }})
                                    </option>
                                </select>
                                <p class="mt-1 text-xs text-slate-500">
                                    Offerings can use any draft or active
                                    academic year section.
                                </p>
                                <p
                                    v-if="formSectionWarning"
                                    class="mt-1 text-xs font-medium text-amber-700"
                                >
                                    {{ formSectionWarning }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Year Level</label
                                >
                                <select
                                    v-model="formYearLevel"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                >
                                    <option value="">All grades</option>
                                    <option
                                        v-for="level in formYearLevelOptions"
                                        :key="level"
                                        :value="level"
                                    >
                                        Grade {{ level }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Section *</label
                                >
                                <SearchableSelect
                                    v-model="form.section_id"
                                    :options="formSectionSearchOptions"
                                    placeholder="Select section..."
                                    empty-text="No matching sections for this filter"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Semester *</label
                                >
                                <select
                                    v-model="form.semester"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                    required
                                >
                                    <option value="1st Semester">
                                        1st Semester
                                    </option>
                                    <option value="2nd Semester">
                                        2nd Semester
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div v-if="!isEditing">
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >Instructor</label
                            >
                            <SearchableSelect
                                v-model="form.user_id"
                                :options="instructorSearchOptions"
                                placeholder="Select instructor..."
                                empty-text="No matching instructors"
                                clearable
                            />
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button
                                type="button"
                                @click="closeModal"
                                class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700"
                                :disabled="form.processing"
                            >
                                {{
                                    isEditing ? 'Update Subject' : 'Add Subject'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div
                v-if="showOfferingModal && selectedSubject"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeOfferingModal"
            >
                <div class="w-full max-w-2xl rounded-lg bg-white p-6">
                    <h2 class="text-xl font-semibold">Add Subject Offering</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ selectedSubject.subject_code }} —
                        {{ selectedSubject.subject_name }}
                    </p>
                    <form
                        class="mt-5 space-y-4"
                        @submit.prevent="submitOffering"
                    >
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Academic Year *</label
                                >
                                <select
                                    v-model="offeringAcademicYearId"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                >
                                    <option value="">All writable years</option>
                                    <option
                                        v-for="year in sectionAcademicYears"
                                        :key="year.academic_year_id"
                                        :value="year.academic_year_id"
                                    >
                                        {{ year.name }} ({{ year.status }})
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Year Level</label
                                >
                                <select
                                    v-model="offeringYearLevel"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                >
                                    <option value="">All grades</option>
                                    <option
                                        v-for="level in offeringYearLevelOptions"
                                        :key="level"
                                        :value="level"
                                    >
                                        Grade {{ level }}
                                    </option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >Section *</label
                            >
                            <SearchableSelect
                                v-model="offeringForm.section_id"
                                :options="offeringSectionSearchOptions"
                                placeholder="Search filtered sections..."
                                empty-text="No matching sections for this filter"
                            />
                            <p
                                v-if="offeringForm.errors.section_id"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ offeringForm.errors.section_id }}
                            </p>
                            <p
                                v-if="offeringSectionWarning"
                                class="mt-1 text-xs font-medium text-amber-700"
                            >
                                {{ offeringSectionWarning }}
                            </p>
                        </div>
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Semester *</label
                                >
                                <select
                                    v-model="offeringForm.semester"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                    required
                                    disabled
                                >
                                    <option value="1st Semester">
                                        1st Semester
                                    </option>
                                    <option value="2nd Semester">
                                        2nd Semester
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Instructor</label
                                >
                                <SearchableSelect
                                    v-model="offeringForm.user_id"
                                    :options="instructorSearchOptions"
                                    placeholder="Search instructor..."
                                    empty-text="No matching instructors"
                                    clearable
                                />
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button
                                type="button"
                                @click="closeOfferingModal"
                                class="rounded-md border border-slate-300 px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="offeringForm.processing"
                                class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                Add Offering
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Swal from 'sweetalert2';
import SearchableSelect from '@/components/SearchableSelect.vue';

interface Subject {
    subject_id: string | number;
    section_id: string | number | null;
    section_name: string | null;
    user_id: string | number | null;
    user_name: string | null;
    subject_name: string;
    subject_code: string;
    subject_description: string | null;
    department: string | null;
    unit: number;
    semester: string | null;
    offerings?: SubjectOffering[];
    has_locked_offerings?: boolean;
}

interface SubjectOffering {
    subject_offering_id: string | number;
    academic_year: string;
    semester: string;
    section_name: string;
    instructor_name: string | null;
    is_writable: boolean;
}

interface SectionOption {
    section_id: string | number;
    academic_year_id?: string | number | null;
    academic_year_status?: string | null;
    section_name: string;
    year_level?: string | number;
    semester?: string;
    school_year?: string;
    label: string;
}

interface InstructorOption {
    user_id: string | number;
    name: string;
    role?: string | null;
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
    subjects: {
        type: Array as () => Subject[],
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', semester: '' }),
    },
    sectionOptions: {
        type: Array as () => SectionOption[],
        default: () => [],
    },
    instructorOptions: {
        type: Array as () => InstructorOption[],
        default: () => [],
    },
    academicYears: {
        type: Array as () => Array<{
            academic_year_id: number;
            name: string;
            status: string;
        }>,
        default: () => [],
    },
    activeAcademicYearSemester: { type: String, default: '' },
    activeAcademicYearName: { type: String, default: '' },
});

const search = ref(props.filters.search ?? '');
const selectedSemester = ref(props.filters.semester ?? '');
const selectedAcademicYear = ref(props.filters.academic_year_id ?? '');
const showModal = ref(false);
const isEditing = ref(false);
const selectedSubject = ref<Subject | null>(null);
const showOfferingModal = ref(false);
const formAcademicYearId = ref<string | number | ''>('');
const formYearLevel = ref<string | number | ''>('');
const offeringAcademicYearId = ref<string | number | ''>('');
const offeringYearLevel = ref<string | number | ''>('');

const instructors = computed(() => {
    return (props.instructorOptions as InstructorOption[]).filter(
        (instructor) => {
            const role = String(instructor.role ?? '').toLowerCase();
            return role === '' || role === 'instructor' || role === 'teacher';
        },
    );
});

const buildSectionSearchOptions = (sections: SectionOption[]) =>
    sections.map((section) => ({
        value: String(section.section_id),
        label: section.label,
        keywords: `${section.section_name} ${section.year_level ?? ''} ${section.school_year ?? ''} ${section.semester ?? ''}`,
    }));

const sectionAcademicYears = computed(() => {
    const yearIdsWithSections = new Set(
        props.sectionOptions
            .map((section) => String(section.academic_year_id ?? ''))
            .filter(Boolean),
    );

    return props.academicYears.filter((year) =>
        yearIdsWithSections.has(String(year.academic_year_id)),
    );
});

const defaultSectionAcademicYearId = () => {
    if (
        selectedAcademicYear.value &&
        selectedAcademicYear.value !== 'all' &&
        sectionAcademicYears.value.some(
            (year) =>
                String(year.academic_year_id) ===
                String(selectedAcademicYear.value),
        )
    ) {
        return selectedAcademicYear.value;
    }

    return (
        sectionAcademicYears.value.find((year) => year.status === 'active')
            ?.academic_year_id ??
        sectionAcademicYears.value[0]?.academic_year_id ??
        ''
    );
};

const sectionsForFilter = (
    academicYearId: string | number | '',
    yearLevel: string | number | '',
) =>
    props.sectionOptions.filter((section) => {
        if (
            academicYearId &&
            String(section.academic_year_id) !== String(academicYearId)
        ) {
            return false;
        }
        if (yearLevel && String(section.year_level) !== String(yearLevel)) {
            return false;
        }

        return true;
    });

const yearLevelsForAcademicYear = (academicYearId: string | number | '') =>
    Array.from(
        new Set(
            props.sectionOptions
                .filter(
                    (section) =>
                        !academicYearId ||
                        String(section.academic_year_id) ===
                            String(academicYearId),
                )
                .map((section) => section.year_level)
                .filter(Boolean)
                .map(String),
        ),
    ).sort((a, b) => Number(a) - Number(b));

const formFilteredSections = computed(() =>
    sectionsForFilter(formAcademicYearId.value, formYearLevel.value),
);
const offeringFilteredSections = computed(() =>
    sectionsForFilter(offeringAcademicYearId.value, offeringYearLevel.value),
);
const formSectionSearchOptions = computed(() =>
    buildSectionSearchOptions(formFilteredSections.value),
);
const offeringSectionSearchOptions = computed(() =>
    buildSectionSearchOptions(offeringFilteredSections.value),
);
const formYearLevelOptions = computed(() =>
    yearLevelsForAcademicYear(formAcademicYearId.value),
);
const offeringYearLevelOptions = computed(() =>
    yearLevelsForAcademicYear(offeringAcademicYearId.value),
);

const instructorSearchOptions = computed(() =>
    instructors.value.map((instructor) => ({
        value: String(instructor.user_id),
        label: instructor.name,
    })),
);
const sectionById = computed(() =>
    Object.fromEntries(
        props.sectionOptions.map((section) => [
            String(section.section_id),
            section,
        ]),
    ),
);
const selectedFormSection = computed(
    () => sectionById.value[String(form.section_id)] ?? null,
);
const selectedOfferingSection = computed(
    () => sectionById.value[String(offeringForm.section_id)] ?? null,
);
const academicYearWarningForSection = (section: SectionOption | null) => {
    if (!section) return '';
    if (section.academic_year_status === 'draft') {
        return 'Warning: this section belongs to a Draft academic year. This is advance setup and will not be current until the year is activated.';
    }
    if (
        section.academic_year_status === 'closed' ||
        section.academic_year_status === 'archived'
    ) {
        return 'Warning: this section belongs to a past/locked academic year. The server may reject changes to protect historical records.';
    }
    return '';
};
const formSectionWarning = computed(() =>
    academicYearWarningForSection(selectedFormSection.value),
);
const offeringSectionWarning = computed(() =>
    academicYearWarningForSection(selectedOfferingSection.value),
);

const form = useForm({
    section_id: '',
    user_id: '',
    subject_name: '',
    subject_code: '',
    subject_description: '',
    department: '',
    unit: 0,
    semester: '',
});

const offeringForm = useForm({
    section_id: '',
    user_id: '',
    semester: '1st Semester',
    status: 'active',
});

const onFilterChange = () => {
    router.get(
        route('admin.subjects.index'),
        {
            search: search.value,
            semester: selectedSemester.value,
            academic_year_id: selectedAcademicYear.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const resetFilters = () => {
    search.value = '';
    selectedSemester.value = '';
    selectedAcademicYear.value =
        props.academicYears.find((year) => year.status === 'active')
            ?.academic_year_id ??
        props.academicYears[0]?.academic_year_id ??
        '';
    onFilterChange();
};

const openAddModal = () => {
    isEditing.value = false;
    selectedSubject.value = null;
    form.reset();
    form.unit = 0;
    formAcademicYearId.value = defaultSectionAcademicYearId();
    formYearLevel.value = '';
    showModal.value = true;
};

const openEditModal = (subject: Subject) => {
    isEditing.value = true;
    selectedSubject.value = subject;
    form.reset();
    form.subject_name = subject.subject_name;
    form.subject_code = subject.subject_code;
    form.subject_description = subject.subject_description ?? '';
    form.department = subject.department ?? '';
    form.unit = Number(subject.unit ?? 0);
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    isEditing.value = false;
    selectedSubject.value = null;
    form.reset();
    formAcademicYearId.value = '';
    formYearLevel.value = '';
};

const openOfferingModal = (subject: Subject) => {
    selectedSubject.value = subject;
    offeringForm.reset();
    offeringAcademicYearId.value = defaultSectionAcademicYearId();
    offeringYearLevel.value = '';
    showOfferingModal.value = true;
};

const closeOfferingModal = () => {
    showOfferingModal.value = false;
    selectedSubject.value = null;
    offeringForm.reset();
    offeringAcademicYearId.value = '';
    offeringYearLevel.value = '';
};

const submitOffering = () => {
    if (!selectedSubject.value || !offeringForm.section_id) return;
    offeringForm
        .transform((data) => ({
            ...data,
            section_id: Number(data.section_id),
            user_id: data.user_id === '' ? null : Number(data.user_id),
        }))
        .post(
            route('admin.subjects.offerings.store', {
                subject: selectedSubject.value.subject_id,
            }),
            {
                preserveScroll: true,
                onSuccess: closeOfferingModal,
            },
        );
};

const removeInstructor = async (offering: SubjectOffering) => {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Remove instructor?',
        text: 'The subject offering and section assignment will remain. Only the instructor will be removed.',
        showCancelButton: true,
        confirmButtonText: 'Remove Instructor',
        confirmButtonColor: '#e11d48',
    });
    if (!result.isConfirmed) return;

    useForm({}).patch(
        route('admin.subjects.offerings.instructor.remove', {
            subjectOffering: offering.subject_offering_id,
        }),
        {
            preserveScroll: true,
        },
    );
};

const submitForm = () => {
    if (
        !form.subject_name ||
        !form.subject_code ||
        form.unit === null ||
        form.unit === undefined ||
        Number(form.unit) < 0
    ) {
        alert(
            'Please fill in all required fields and provide a valid unit value.',
        );
        return;
    }
    if (!isEditing.value && (!form.section_id || !form.semester)) {
        alert(
            'Please select the academic section and semester for this subject.',
        );
        return;
    }

    const payload = {
        ...form.data(),
        unit: Number(form.unit),
        department: form.department === '' ? null : form.department,
        subject_description:
            form.subject_description === '' ? null : form.subject_description,
    };

    if (isEditing.value && selectedSubject.value) {
        form.transform(() => payload).put(
            route('admin.subjects.update', {
                id: selectedSubject.value.subject_id,
            }),
            {
                preserveState: true,
                onSuccess: () => {
                    closeModal();
                    router.reload({ only: ['subjects'] });
                },
            },
        );
        return;
    }

    form.transform(() => payload).post(route('admin.subjects.store'), {
        preserveState: true,
        onSuccess: () => {
            closeModal();
            router.reload({ only: ['subjects'] });
        },
    });
};

const deleteSubject = (subject: Subject) => {
    if (!confirm(`Are you sure you want to delete ${subject.subject_code}?`)) {
        return;
    }

    const deleteForm = useForm({});
    deleteForm.delete(
        route('admin.subjects.destroy', { id: subject.subject_id }),
        {
            preserveState: true,
            onSuccess: () => router.reload({ only: ['subjects'] }),
        },
    );
};

watch(
    () => form.section_id,
    (sectionId) => {
        if (isEditing.value) return;
        form.semester = sectionById.value[String(sectionId)]?.semester ?? '';
    },
);

watch(
    () => offeringForm.section_id,
    (sectionId) => {
        offeringForm.semester =
            sectionById.value[String(sectionId)]?.semester ?? '';
    },
);

watch(
    [formAcademicYearId, formYearLevel],
    () => {
        if (
            formYearLevel.value &&
            !formYearLevelOptions.value.includes(String(formYearLevel.value))
        ) {
            formYearLevel.value = '';
        }

        if (
            form.section_id &&
            !formFilteredSections.value.some(
                (section) =>
                    String(section.section_id) === String(form.section_id),
            )
        ) {
            form.section_id = '';
            form.semester = '';
        }
    },
    { flush: 'post' },
);

watch(
    [offeringAcademicYearId, offeringYearLevel],
    () => {
        if (
            offeringYearLevel.value &&
            !offeringYearLevelOptions.value.includes(
                String(offeringYearLevel.value),
            )
        ) {
            offeringYearLevel.value = '';
        }

        if (
            offeringForm.section_id &&
            !offeringFilteredSections.value.some(
                (section) =>
                    String(section.section_id) ===
                    String(offeringForm.section_id),
            )
        ) {
            offeringForm.section_id = '';
            offeringForm.semester = '';
        }
    },
    { flush: 'post' },
);
</script>
