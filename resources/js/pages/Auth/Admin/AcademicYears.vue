<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
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

    form.post(route(`admin.academic-years.${action}`, year.academic_year_id), {
        preserveScroll: true,
    });
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

const updateSemester = (year, activeSemester) => {
    useForm({
        name: year.name,
        starts_on: year.starts_on,
        ends_on: year.ends_on,
        active_semester: activeSemester,
    }).put(route('admin.academic-years.update', year.academic_year_id), {
        preserveScroll: true,
    });
};

const badgeClass = (status) =>
    ({
        draft: 'bg-amber-100 text-amber-800',
        active: 'bg-emerald-100 text-emerald-800',
        closed: 'bg-slate-200 text-slate-700',
        archived: 'bg-purple-100 text-purple-800',
    })[status] ?? 'bg-slate-100 text-slate-700';

const rolloverSourceId = ref('');
const rolloverDestinationId = ref('');
const rolloverMode = ref('year');
const destinationSemester = ref('2nd Semester');
const preview = ref(null);
const previewBusy = ref(false);
const sectionMappings = ref([]);
const subjectSelections = ref([]);
const studentDecisions = ref({});
const selectedSectionStudents = ref(null);
const rolloverConfigurationOpen = ref(false);
const rolloverSubmitting = ref(false);

const resetRolloverSelection = () => {
    rolloverSourceId.value = '';
    rolloverDestinationId.value = '';
    rolloverMode.value = 'year';
    destinationSemester.value = '2nd Semester';
    preview.value = null;
    sectionMappings.value = [];
    subjectSelections.value = [];
    studentDecisions.value = {};
    selectedSectionStudents.value = null;
    rolloverConfigurationOpen.value = false;
};

const firstRolloverError = (errors) => {
    const firstError = Object.values(errors ?? {})
        .flat()
        .find(Boolean);

    return (
        firstError ||
        'The rollover could not be completed. Review the selections and try again.'
    );
};

const loadPreview = async () => {
    if (!rolloverSourceId.value || !rolloverDestinationId.value) return;
    previewBusy.value = true;
    try {
        const params = new URLSearchParams({
            destination_academic_year_id: rolloverDestinationId.value,
            mode: rolloverMode.value,
        });
        if (rolloverMode.value === 'semester')
            params.set('destination_semester', '2nd Semester');
        const url =
            route(
                'admin.academic-years.rollover-preview',
                rolloverSourceId.value,
            ) + `?${params}`;
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message || 'Preview failed.');
        preview.value = data;
        sectionMappings.value = data.source_sections.map((section) => ({
            source_section_id: section.section_id,
            source_label: `${section.section_name} · Grade ${section.year_level} · ${section.semester}`,
            source_year_level: Number(section.year_level),
            include: Boolean(section.will_rollover),
            destination_section_id: '',
            destination_name: section.section_name,
            destination_year_level: Number(section.year_level),
            promotion_destination_choice: '',
            promotion_destination_name: '',
        }));
        initializePromotionMappings();
        subjectSelections.value = data.source_offerings.map((offering) => ({
            ...offering,
            include: Boolean(offering.will_rollover),
        }));
        studentDecisions.value = Object.fromEntries(
            data.items.map((item) => [
                item.source_student_enrollment_id,
                {
                    decision: item.recommended_decision,
                    destination_choice: '',
                },
            ]),
        );
        rolloverConfigurationOpen.value = false;
    } catch (error) {
        Swal.fire('Preview unavailable', error.message, 'error');
    } finally {
        previewBusy.value = false;
    }
};

const executeRollover = async () => {
    const invalid = sectionMappings.value.some(
        (mapping) =>
            mapping.include &&
            !mapping.destination_section_id &&
            !mapping.destination_name.trim(),
    );
    if (invalid)
        return Swal.fire(
            'Mapping required',
            'Select or name a destination for every copied section.',
            'error',
        );
    const invalidPromotionMapping = promotionMappings.value.some(
        (mapping) =>
            !mapping.promotion_destination_choice ||
            (mapping.promotion_destination_choice === 'new' &&
                !mapping.promotion_destination_name.trim()),
    );
    if (invalidPromotionMapping)
        return Swal.fire(
            'Grade 12 section required',
            'Map every Grade 11 section to a different Grade 12 section, or enter a name for a new Grade 12 section.',
            'error',
        );
    const promotionChoices = promotionMappings.value
        .filter(
            (mapping) => mapping.promotion_destination_choice !== 'individual',
        )
        .map((mapping) =>
            mapping.promotion_destination_choice === 'new'
                ? `new:${mapping.promotion_destination_name.trim().toLowerCase()}`
                : mapping.promotion_destination_choice,
        );
    if (new Set(promotionChoices).size !== promotionChoices.length)
        return Swal.fire(
            'Duplicate Grade 12 destination',
            'Each Grade 11 section must use a different Grade 12 destination section.',
            'error',
        );
    const missingStudentDestination = preview.value.items.some((item) => {
        const studentDecision =
            studentDecisions.value[item.source_student_enrollment_id];

        return (
            ['promote', 'retain'].includes(studentDecision?.decision) &&
            !studentDecision?.destination_choice &&
            !hasSectionDefaultDestination(item, studentDecision.decision)
        );
    });
    if (missingStudentDestination)
        return Swal.fire(
            'Student destination required',
            'Select a destination section for every promoted or retained student.',
            'error',
        );
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Execute this academic rollover?',
        text: 'This creates the reviewed destination Sections, Subject Offerings, and enrollments. Instructors, Schedules, and historical operational records are not copied.',
        showCancelButton: true,
        confirmButtonText: 'Execute Academic Rollover',
        confirmButtonColor: '#2563eb',
    });
    if (!result.isConfirmed) return;
    useForm({
        destination_academic_year_id: Number(rolloverDestinationId.value),
        mode: rolloverMode.value,
        destination_semester:
            rolloverMode.value === 'semester' ? '2nd Semester' : null,
        section_mappings: sectionMappings.value.map(
            ({
                source_label,
                promotion_destination_choice: promotionChoice,
                ...mapping
            }) => ({
                ...mapping,
                destination_section_id: mapping.destination_section_id
                    ? Number(mapping.destination_section_id)
                    : null,
                promotion_destination_source_section_id:
                    sourceSectionIdFromChoice(promotionChoice),
                promotion_destination_section_id:
                    existingSectionIdFromChoice(promotionChoice),
                promotion_destination_name:
                    promotionChoice === 'new'
                        ? mapping.promotion_destination_name.trim()
                        : null,
            }),
        ),
        subject_selections: subjectSelections.value.map((selection) => ({
            source_subject_offering_id: Number(
                selection.source_subject_offering_id,
            ),
            include:
                Boolean(selection.include) &&
                isSectionIncluded(selection.source_section_id),
        })),
        decisions: preview.value.items.map((item) => ({
            source_student_enrollment_id: item.source_student_enrollment_id,
            decision:
                studentDecisions.value[item.source_student_enrollment_id]
                    ?.decision ?? item.recommended_decision,
            destination_section_id: existingSectionIdFromChoice(
                studentDecisions.value[item.source_student_enrollment_id]
                    ?.destination_choice,
            ),
            destination_source_section_id: sourceSectionIdFromChoice(
                studentDecisions.value[item.source_student_enrollment_id]
                    ?.destination_choice,
            ),
        })),
    }).post(route('admin.academic-years.rollover', rolloverSourceId.value), {
        preserveScroll: true,
        onStart: () => {
            rolloverSubmitting.value = true;
        },
        onSuccess: (page) => {
            const message =
                page.props.flash?.success ||
                'Academic rollover completed successfully.';
            resetRolloverSelection();
            Swal.fire('Rollover completed', message, 'success');
        },
        onError: (errors) => {
            Swal.fire('Rollover failed', firstRolloverError(errors), 'error');
        },
        onFinish: () => {
            rolloverSubmitting.value = false;
        },
    });
};

const studentsForSection = (mapping) =>
    preview.value?.items?.filter(
        (item) =>
            Number(item.source_section_id) ===
            Number(mapping.source_section_id),
    ) ?? [];
const openSectionStudents = (mapping) => {
    selectedSectionStudents.value = mapping;
};
const destinationSectionsForMapping = (mapping) =>
    preview.value?.destination_sections?.filter(
        (section) =>
            String(section.year_level) ===
            String(mapping.destination_year_level),
    ) ?? [];
const isSectionIncluded = (sourceSectionId) =>
    Boolean(
        sectionMappings.value.find(
            (mapping) =>
                Number(mapping.source_section_id) === Number(sourceSectionId),
        )?.include,
    );
const existingSectionIdFromChoice = (choice) => {
    if (!choice?.startsWith('existing:')) return null;

    return Number(choice.slice('existing:'.length));
};
const sourceSectionIdFromChoice = (choice) => {
    if (!choice?.startsWith('rollover:')) return null;

    return Number(choice.slice('rollover:'.length));
};
const requiredDestinationYearLevel = (item, decision = null) =>
    (decision ??
        studentDecisions.value[item.source_student_enrollment_id]?.decision) ===
    'promote'
        ? 12
        : Number(item.year_level);
const promotionMappings = computed(() =>
    rolloverMode.value === 'year'
        ? sectionMappings.value.filter(
              (mapping) => Number(mapping.source_year_level) === 11,
          )
        : [],
);
const suggestedPromotionSectionName = (mapping) => {
    const promotedName = mapping.destination_name.replace(
        /(^|\D)11(?=\D|$)/,
        (_, prefix) => `${prefix}12`,
    );

    return promotedName === mapping.destination_name
        ? `${mapping.destination_name} - Grade 12`
        : promotedName;
};
const initializePromotionMappings = () => {
    if (rolloverMode.value !== 'year') return;

    const grade11Mappings = sectionMappings.value.filter(
        (mapping) => Number(mapping.source_year_level) === 11,
    );
    const grade12Mappings = sectionMappings.value.filter(
        (mapping) =>
            Number(mapping.source_year_level) === 12 && mapping.include,
    );
    const existingGrade12Sections =
        preview.value?.destination_sections?.filter(
            (section) => Number(section.year_level) === 12,
        ) ?? [];

    grade11Mappings.forEach((mapping, index) => {
        if (grade12Mappings[index]) {
            mapping.promotion_destination_choice = `rollover:${grade12Mappings[index].source_section_id}`;
            return;
        }
        const existingSection =
            existingGrade12Sections[index - grade12Mappings.length];
        if (existingSection) {
            mapping.promotion_destination_choice = `existing:${existingSection.section_id}`;
            return;
        }
        mapping.promotion_destination_choice = 'individual';
        mapping.promotion_destination_name = '';
    });
};
const selectedRolloverSectionsForStudent = (item) =>
    sectionMappings.value.filter(
        (mapping) =>
            mapping.include &&
            Number(mapping.destination_year_level) ===
                requiredDestinationYearLevel(item),
    );
const existingDestinationSectionsForStudent = (item) =>
    preview.value?.destination_sections?.filter(
        (section) =>
            Number(section.year_level) === requiredDestinationYearLevel(item),
    ) ?? [];
const resetStudentDestinationForDecision = (item) => {
    const studentDecision =
        studentDecisions.value[item.source_student_enrollment_id];
    studentDecision.destination_choice = '';
};
const clearStudentSelectionsForMapping = (mapping) => {
    if (mapping.include) return;

    const selectedValue = `rollover:${mapping.source_section_id}`;
    Object.values(studentDecisions.value).forEach((decision) => {
        if (decision.destination_choice === selectedValue) {
            decision.destination_choice = '';
        }
    });
    if (Number(mapping.source_year_level) === 12) {
        promotionMappings.value.forEach((sourceMapping) => {
            if (sourceMapping.promotion_destination_choice === selectedValue) {
                sourceMapping.promotion_destination_choice = 'individual';
                sourceMapping.promotion_destination_name = '';
            }
        });
    }
};
const rolloverSectionLabel = (mapping) => {
    if (mapping.destination_section_id) {
        const section = preview.value?.destination_sections?.find(
            (candidate) =>
                Number(candidate.section_id) ===
                Number(mapping.destination_section_id),
        );

        if (section)
            return `${section.section_name} - Grade ${section.year_level}`;
    }

    return `${mapping.destination_name} - Grade ${mapping.destination_year_level}`;
};
const promotionChoiceUsedByAnother = (choice, mapping) =>
    promotionMappings.value.some(
        (candidate) =>
            Number(candidate.source_section_id) !==
                Number(mapping.source_section_id) &&
            candidate.promotion_destination_choice === choice,
    );
const availablePromotionRolloverMappings = (mapping) =>
    sectionMappings.value.filter(
        (candidate) =>
            candidate.include &&
            Number(candidate.source_year_level) === 12 &&
            !promotionChoiceUsedByAnother(
                `rollover:${candidate.source_section_id}`,
                mapping,
            ),
    );
const availableExistingPromotionSections = (mapping) =>
    (preview.value?.destination_sections ?? []).filter(
        (section) =>
            Number(section.year_level) === 12 &&
            !promotionChoiceUsedByAnother(
                `existing:${section.section_id}`,
                mapping,
            ),
    );
const onPromotionDestinationChange = (mapping) => {
    mapping.promotion_destination_name =
        mapping.promotion_destination_choice === 'new'
            ? suggestedPromotionSectionName(mapping)
            : '';
};
const openIndividualStudentAssignments = (mapping) => {
    rolloverConfigurationOpen.value = false;
    openSectionStudents(mapping);
};
const promotionDestinationLabel = (mapping) => {
    const choice = mapping?.promotion_destination_choice;
    if (choice === 'individual') return 'Assign each student individually';
    if (choice === 'new') {
        return mapping.promotion_destination_name
            ? `${mapping.promotion_destination_name} - Grade 12 (new)`
            : 'New Grade 12 section name required';
    }
    const sourceSectionId = sourceSectionIdFromChoice(choice);
    if (sourceSectionId) {
        const destinationMapping = sectionMappings.value.find(
            (candidate) =>
                Number(candidate.source_section_id) === sourceSectionId,
        );
        return destinationMapping
            ? rolloverSectionLabel(destinationMapping)
            : 'Selected Grade 12 rollover section';
    }
    const existingSectionId = existingSectionIdFromChoice(choice);
    if (existingSectionId) {
        const section = preview.value?.destination_sections?.find(
            (candidate) => Number(candidate.section_id) === existingSectionId,
        );
        return section
            ? `${section.section_name} - Grade ${section.year_level}`
            : 'Selected existing Grade 12 section';
    }

    return 'No Grade 12 destination configured';
};
const sourceMappingForStudent = (item) =>
    sectionMappings.value.find(
        (mapping) =>
            Number(mapping.source_section_id) ===
            Number(item.source_section_id),
    );
const sectionDefaultDestinationLabel = (item) => {
    const decision =
        studentDecisions.value[item.source_student_enrollment_id]?.decision;
    const sourceMapping = sourceMappingForStudent(item);
    if (decision === 'promote') return promotionDestinationLabel(sourceMapping);
    if (decision === 'retain' && sourceMapping?.include)
        return rolloverSectionLabel(sourceMapping);

    return 'No section mapping configured';
};
const hasSectionDefaultDestination = (item, decision) => {
    const sourceMapping = sourceMappingForStudent(item);
    if (decision === 'retain') return Boolean(sourceMapping?.include);
    if (decision !== 'promote') return false;
    if (!sourceMapping?.promotion_destination_choice) return false;
    if (sourceMapping.promotion_destination_choice === 'individual')
        return false;

    return (
        sourceMapping.promotion_destination_choice !== 'new' ||
        Boolean(sourceMapping.promotion_destination_name.trim())
    );
};
const sectionMappingForSubject = (subject) =>
    sectionMappings.value.find(
        (mapping) =>
            Number(mapping.source_section_id) ===
            Number(subject.source_section_id),
    );
const subjectDestinationLabel = (subject) => {
    if (!subject.include) return 'Excluded from rollover';
    const mapping = sectionMappingForSubject(subject);
    if (!mapping?.include) return 'Section excluded from rollover';
    if (mapping.destination_section_id) {
        return (
            preview.value?.destination_sections?.find(
                (section) =>
                    Number(section.section_id) ===
                    Number(mapping.destination_section_id),
            )?.section_name ?? 'Selected destination section'
        );
    }
    return mapping.destination_name || 'New destination section';
};
const selectedSubjectCount = computed(
    () =>
        subjectSelections.value.filter(
            (subject) =>
                subject.include && isSectionIncluded(subject.source_section_id),
        ).length,
);
const isArchivedStudent = (item) =>
    rolloverMode.value === 'year' &&
    preview.value?.transition?.advance_grade &&
    Number(item.year_level) === 12 &&
    item.recommended_decision === 'graduated';
const groupedSectionMappings = computed(() => {
    const groups = new Map();
    sectionMappings.value.forEach((mapping) => {
        const grade = Number(mapping.source_year_level) || 'Unknown';
        if (!groups.has(grade)) {
            groups.set(grade, []);
        }
        groups.get(grade).push(mapping);
    });

    return Array.from(groups.entries())
        .sort(([a], [b]) => Number(a) - Number(b))
        .map(([grade, mappings]) => ({
            grade,
            label: Number(grade) ? `Grade ${grade}` : 'Unknown grade',
            mappings,
            studentCount: mappings.reduce(
                (count, mapping) => count + studentsForSection(mapping).length,
                0,
            ),
        }));
});

const academicYearsForRollover = (kind) =>
    props.academicYears.filter((year) => {
        if (rolloverMode.value === 'semester')
            return ['active', 'draft'].includes(year.status);
        return kind === 'source'
            ? ['active', 'closed'].includes(year.status)
            : year.status === 'draft';
    });
const selectedRolloverSource = computed(() =>
    props.academicYears.find(
        (year) =>
            String(year.academic_year_id) === String(rolloverSourceId.value),
    ),
);
const semesterRolloverUnavailable = computed(
    () => selectedRolloverSource.value?.active_semester === '2nd Semester',
);
const rolloverSourceOptions = computed(() =>
    academicYearsForRollover('source'),
);
const rolloverDestinationOptions = computed(() =>
    academicYearsForRollover('destination'),
);
const onRolloverSourceChange = () => {
    if (
        rolloverMode.value === 'semester' &&
        semesterRolloverUnavailable.value
    ) {
        rolloverMode.value = 'year';
        destinationSemester.value = '2nd Semester';
    }
    if (rolloverMode.value === 'semester')
        rolloverDestinationId.value = rolloverSourceId.value;
    preview.value = null;
    sectionMappings.value = [];
    subjectSelections.value = [];
    studentDecisions.value = {};
    rolloverConfigurationOpen.value = false;
};

watch(
    () => props.academicYears,
    () => {
        if (
            rolloverMode.value === 'semester' &&
            semesterRolloverUnavailable.value
        ) {
            preview.value = null;
            sectionMappings.value = [];
            subjectSelections.value = [];
            studentDecisions.value = {};
            rolloverConfigurationOpen.value = false;
            rolloverSourceId.value = '';
            rolloverDestinationId.value = '';
        }
    },
    { deep: true },
);
</script>

<template>
    <Head title="Academic Years" />
    <AuthLayout>
        <div class="space-y-6">
            <header>
                <h1 class="text-2xl font-bold text-slate-900">
                    Academic Years
                </h1>
                <p class="mt-1 text-sm text-slate-600">
                    Prepare school years as drafts, activate one for operations,
                    and close completed years.
                </p>
            </header>

            <details
                class="rounded-xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-950"
            >
                <summary class="cursor-pointer font-semibold outline-none">
                    How academic years work
                </summary>
                <div class="mt-3 grid gap-3 md:grid-cols-2">
                    <p>
                        Create the next school year as a draft first, then add
                        its strands, sections, subjects, and schedules before
                        activating it.
                    </p>
                    <p>
                        Academic rollover recreates selected Sections and
                        Subject Offerings in the destination year while
                        preserving their semester. Grade 11 students move to
                        Grade 12, while Grade 12 students are archived unless
                        you review or retain them.
                    </p>
                    <p>
                        Semester-only rollover stays in the same academic year
                        and moves 1st Semester students into 2nd Semester
                        without changing their grade level.
                    </p>
                    <p>
                        Only one academic year should be active for daily
                        operations. You can change the active semester from the
                        list below.
                    </p>
                </div>
            </details>

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <h2 class="text-lg font-semibold text-slate-900">
                    Create draft academic year
                </h2>
                <form
                    class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    @submit.prevent="submit"
                >
                    <label class="text-sm font-medium text-slate-700">
                        Year name
                        <input
                            v-model="form.name"
                            class="mt-1 w-full rounded-lg border-slate-300"
                            placeholder="2026-2027"
                            required
                        />
                        <span
                            v-if="form.errors.name"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.name }}</span
                        >
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        Start date
                        <input
                            v-model="form.starts_on"
                            type="date"
                            class="mt-1 w-full rounded-lg border-slate-300"
                            required
                        />
                        <span
                            v-if="form.errors.starts_on"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.starts_on }}</span
                        >
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        End date
                        <input
                            v-model="form.ends_on"
                            type="date"
                            class="mt-1 w-full rounded-lg border-slate-300"
                            required
                        />
                        <span
                            v-if="form.errors.ends_on"
                            class="mt-1 block text-xs text-red-600"
                            >{{ form.errors.ends_on }}</span
                        >
                    </label>
                    <label class="text-sm font-medium text-slate-700">
                        Current semester (optional)
                        <select
                            v-model="form.active_semester"
                            class="mt-1 w-full rounded-lg border-slate-300"
                        >
                            <option value="">Not selected</option>
                            <option>1st Semester</option>
                            <option>2nd Semester</option>
                        </select>
                    </label>
                    <div class="md:col-span-2 xl:col-span-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {{
                                form.processing ? 'Creating...' : 'Create Draft'
                            }}
                        </button>
                    </div>
                </form>
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <h2 class="text-lg font-semibold text-slate-900">
                    Academic rollover
                </h2>
                <p class="mt-1 text-sm text-slate-600">
                    Preview first, edit destination Sections and Subject
                    Offerings, then create the reviewed configuration and
                    enrollments transactionally. Instructors and Schedules are
                    configured separately for each semester.
                </p>
                <div class="mt-4 grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]">
                    <select
                        v-model="rolloverMode"
                        class="rounded-lg border-slate-300"
                        @change="
                            rolloverSourceId = '';
                            rolloverDestinationId = '';
                            destinationSemester = '2nd Semester';
                            preview = null;
                        "
                    >
                        <option value="year">Academic rollover</option>
                        <option
                            value="semester"
                            :disabled="semesterRolloverUnavailable"
                        >
                            Semester-only rollover
                        </option>
                    </select>
                    <select
                        v-model="rolloverSourceId"
                        class="rounded-lg border-slate-300"
                        @change="onRolloverSourceChange"
                    >
                        <option value="">Source academic year</option>
                        <option
                            v-for="year in rolloverSourceOptions"
                            :key="year.academic_year_id"
                            :value="year.academic_year_id"
                        >
                            {{ year.name }} ({{ year.status }})
                        </option>
                    </select>
                    <p
                        v-if="semesterRolloverUnavailable"
                        class="text-xs font-medium text-amber-700 md:col-span-4"
                    >
                        Semester-only academic rollover is unavailable because
                        this academic year is already on 2nd Semester.
                    </p>
                    <select
                        v-model="rolloverDestinationId"
                        class="rounded-lg border-slate-300"
                        :disabled="rolloverMode === 'semester'"
                    >
                        <option value="">Destination academic year</option>
                        <option
                            v-for="year in rolloverDestinationOptions"
                            :key="year.academic_year_id"
                            :value="year.academic_year_id"
                        >
                            {{ year.name }} ({{ year.status }})
                        </option>
                    </select>
                    <div
                        v-if="rolloverMode === 'semester'"
                        class="rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-700"
                    >
                        Destination: 2nd Semester
                    </div>
                    <button
                        class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="
                            previewBusy ||
                            !rolloverSourceId ||
                            !rolloverDestinationId
                        "
                        @click="loadPreview"
                    >
                        {{ previewBusy ? 'Previewing…' : 'Preview' }}
                    </button>
                </div>

                <div
                    v-if="
                        preview &&
                        !(
                            rolloverMode === 'semester' &&
                            semesterRolloverUnavailable
                        )
                    "
                    class="mt-5 space-y-4"
                >
                    <div
                        class="rounded-lg border border-blue-100 bg-blue-50 p-3 text-sm text-blue-900"
                    >
                        <strong>Automatic academic rollover:</strong>
                        {{ preview.transition.description }}
                        <span class="ml-1"
                            >Destination semester:
                            {{ preview.transition.destination_semester }}.</span
                        >
                        <span
                            v-if="preview.transition.advance_grade"
                            class="ml-1"
                            >Grade 12 students will be archived as graduated and
                            will not receive a destination enrollment.</span
                        >
                    </div>
                    <div class="grid gap-2 sm:grid-cols-3 lg:grid-cols-6">
                        <div
                            v-for="(value, label) in preview.counts"
                            :key="label"
                            class="rounded-lg bg-slate-50 p-3"
                        >
                            <div
                                class="text-xs font-semibold text-slate-500 uppercase"
                            >
                                {{ label }}
                            </div>
                            <div class="text-xl font-bold">{{ value }}</div>
                        </div>
                    </div>
                    <div
                        class="flex flex-col gap-3 rounded-lg border border-indigo-200 bg-indigo-50 p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h3 class="text-sm font-bold text-indigo-950">
                                Sections and subjects to roll over
                            </h3>
                            <p class="text-xs text-indigo-800">
                                Review and edit destination Section mappings and
                                choose which Subject Offerings will be created.
                                {{ sectionMappings.length }} source sections and
                                {{ selectedSubjectCount }} selected subjects.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-800"
                            @click="rolloverConfigurationOpen = true"
                        >
                            Review and edit sections &amp; subjects
                        </button>
                    </div>
                    <div
                        class="space-y-3 rounded-lg border border-slate-200 p-3"
                    >
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">
                                Student preview by grade and section
                            </h3>
                            <p class="text-xs text-slate-500">
                                Click a section card to review its students and
                                destination assignments.
                            </p>
                        </div>
                        <div
                            v-for="group in groupedSectionMappings"
                            :key="group.grade"
                            class="rounded-md border border-slate-200"
                        >
                            <div
                                class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-3 py-2"
                            >
                                <h4
                                    class="text-sm font-semibold text-slate-800"
                                >
                                    {{ group.label }}
                                </h4>
                                <span class="text-xs text-slate-500">
                                    {{ group.mappings.length }} sections ·
                                    {{ group.studentCount }} students
                                </span>
                            </div>
                            <div class="grid gap-3 p-3 xl:grid-cols-2">
                                <button
                                    v-for="mapping in group.mappings"
                                    :key="mapping.source_section_id"
                                    type="button"
                                    class="rounded-md border border-slate-200 p-3 text-left transition hover:border-blue-300 hover:bg-blue-50 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                    @click="openSectionStudents(mapping)"
                                >
                                    <span
                                        class="block font-semibold text-blue-700"
                                    >
                                        {{ mapping.source_label }}
                                    </span>
                                    <span
                                        class="mt-1 block text-xs text-slate-500"
                                    >
                                        {{ studentsForSection(mapping).length }}
                                        students · Click to review students
                                    </span>
                                    <span
                                        class="mt-2 block text-xs font-medium text-slate-700"
                                    >
                                        Structure:
                                        {{
                                            mapping.include
                                                ? 'selected for rollover'
                                                : 'not copied'
                                        }}
                                    </span>
                                    <span
                                        v-if="
                                            rolloverMode === 'year' &&
                                            Number(
                                                mapping.source_year_level,
                                            ) === 11
                                        "
                                        class="mt-1 block text-xs font-medium text-indigo-700"
                                    >
                                        Students →
                                        {{ promotionDestinationLabel(mapping) }}
                                    </span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="rolloverConfigurationOpen"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                        @click.self="rolloverConfigurationOpen = false"
                    >
                        <div
                            class="max-h-[90vh] w-full max-w-6xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl"
                        >
                            <div
                                class="mb-5 flex items-start justify-between gap-4"
                            >
                                <div>
                                    <h3
                                        class="text-lg font-bold text-slate-900"
                                    >
                                        Review rollover Sections and Subjects
                                    </h3>
                                    <p class="mt-1 text-sm text-slate-600">
                                        Changes here update the rollover
                                        preview. The Subject catalog is reused;
                                        selected offerings are created without
                                        Instructor assignments or Schedules.
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-300 px-3 py-1.5 text-sm"
                                    @click="rolloverConfigurationOpen = false"
                                >
                                    Close
                                </button>
                            </div>

                            <section>
                                <h4 class="font-bold text-slate-900">
                                    Destination Sections
                                </h4>
                                <p class="mb-3 text-xs text-slate-500">
                                    Copy each selected Sections-table row with
                                    the same grade, or map it to an existing
                                    destination Section of that grade.
                                </p>
                                <div class="space-y-2">
                                    <div
                                        v-for="mapping in sectionMappings"
                                        :key="`review-section-${mapping.source_section_id}`"
                                        class="grid gap-2 rounded-lg border border-slate-200 p-3 lg:grid-cols-[1.2fr_1fr_1fr_120px]"
                                    >
                                        <label
                                            class="flex items-center gap-2 text-sm font-semibold text-slate-800"
                                        >
                                            <input
                                                v-model="mapping.include"
                                                type="checkbox"
                                                class="rounded border-slate-300 text-blue-600"
                                                @change="
                                                    clearStudentSelectionsForMapping(
                                                        mapping,
                                                    )
                                                "
                                            />
                                            {{ mapping.source_label }}
                                        </label>
                                        <template v-if="!mapping.include">
                                            <div
                                                class="rounded-md bg-slate-100 px-3 py-2 text-sm text-slate-600 lg:col-span-3"
                                            >
                                                Section row and its Subject
                                                Offerings are not copied.
                                                Students can still be assigned
                                                independently to another
                                                destination Section.
                                            </div>
                                        </template>
                                        <template v-else>
                                            <select
                                                v-model="
                                                    mapping.destination_section_id
                                                "
                                                class="rounded-md border-slate-300 text-sm"
                                                @change="
                                                    mapping.destination_name =
                                                        ''
                                                "
                                            >
                                                <option value="">
                                                    Create a new Section
                                                </option>
                                                <option
                                                    v-for="section in destinationSectionsForMapping(
                                                        mapping,
                                                    )"
                                                    :key="section.section_id"
                                                    :value="section.section_id"
                                                >
                                                    {{ section.section_name }} -
                                                    Grade
                                                    {{ section.year_level }}
                                                </option>
                                            </select>
                                            <input
                                                v-model="
                                                    mapping.destination_name
                                                "
                                                :disabled="
                                                    Boolean(
                                                        mapping.destination_section_id,
                                                    )
                                                "
                                                class="rounded-md border-slate-300 text-sm disabled:bg-slate-100"
                                                placeholder="Destination Section name"
                                            />
                                            <div
                                                class="rounded-md bg-slate-50 px-3 py-2 text-sm font-medium text-slate-700"
                                            >
                                                Grade
                                                {{
                                                    mapping.destination_year_level
                                                }}
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </section>

                            <section
                                v-if="promotionMappings.length"
                                class="mt-6"
                            >
                                <h4 class="font-bold text-slate-900">
                                    Grade 11 student destinations
                                </h4>
                                <p class="mb-3 text-xs text-slate-500">
                                    Each Grade 11 source section uses a
                                    different Grade 12 destination. You may
                                    create a new section or assign every student
                                    individually when no suitable section is
                                    available.
                                </p>
                                <div class="space-y-2">
                                    <div
                                        v-for="mapping in promotionMappings"
                                        :key="`promotion-${mapping.source_section_id}`"
                                        class="grid gap-2 rounded-lg border border-slate-200 p-3 md:grid-cols-[1.2fr_1fr_1fr]"
                                    >
                                        <div
                                            class="text-sm font-semibold text-slate-800"
                                        >
                                            {{ mapping.source_label }}
                                        </div>
                                        <select
                                            v-model="
                                                mapping.promotion_destination_choice
                                            "
                                            class="rounded-md border-slate-300 text-sm"
                                            @change="
                                                onPromotionDestinationChange(
                                                    mapping,
                                                )
                                            "
                                        >
                                            <option value="">
                                                Select Grade 12 destination
                                            </option>
                                            <optgroup
                                                v-if="
                                                    availablePromotionRolloverMappings(
                                                        mapping,
                                                    ).length
                                                "
                                                label="Sections selected for rollover"
                                            >
                                                <option
                                                    v-for="destinationMapping in availablePromotionRolloverMappings(
                                                        mapping,
                                                    )"
                                                    :key="`promotion-rollover-${destinationMapping.source_section_id}`"
                                                    :value="`rollover:${destinationMapping.source_section_id}`"
                                                >
                                                    {{
                                                        rolloverSectionLabel(
                                                            destinationMapping,
                                                        )
                                                    }}
                                                </option>
                                            </optgroup>
                                            <optgroup
                                                v-if="
                                                    availableExistingPromotionSections(
                                                        mapping,
                                                    ).length
                                                "
                                                label="Existing destination sections"
                                            >
                                                <option
                                                    v-for="section in availableExistingPromotionSections(
                                                        mapping,
                                                    )"
                                                    :key="`promotion-existing-${section.section_id}`"
                                                    :value="`existing:${section.section_id}`"
                                                >
                                                    {{ section.section_name }} -
                                                    Grade 12
                                                </option>
                                            </optgroup>
                                            <option value="individual">
                                                Assign students individually
                                            </option>
                                            <option value="new">
                                                Create a new Grade 12 section
                                            </option>
                                        </select>
                                        <input
                                            v-if="
                                                mapping.promotion_destination_choice ===
                                                'new'
                                            "
                                            v-model="
                                                mapping.promotion_destination_name
                                            "
                                            class="rounded-md border-slate-300 text-sm"
                                            placeholder="New Grade 12 section name"
                                        />
                                        <button
                                            v-else-if="
                                                mapping.promotion_destination_choice ===
                                                'individual'
                                            "
                                            type="button"
                                            class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-left text-sm font-semibold text-indigo-700 hover:bg-indigo-100"
                                            @click="
                                                openIndividualStudentAssignments(
                                                    mapping,
                                                )
                                            "
                                        >
                                            Select each student's destination
                                        </button>
                                        <div
                                            v-else
                                            class="rounded-md bg-slate-50 px-3 py-2 text-sm text-slate-600"
                                        >
                                            {{
                                                promotionDestinationLabel(
                                                    mapping,
                                                )
                                            }}
                                        </div>
                                    </div>
                                </div>
                            </section>

                            <section class="mt-6">
                                <div
                                    class="mb-3 flex items-end justify-between gap-3"
                                >
                                    <div>
                                        <h4 class="font-bold text-slate-900">
                                            Subject Offerings
                                        </h4>
                                        <p class="text-xs text-slate-500">
                                            Clear a checkbox to leave that
                                            Subject out of the destination
                                            academic context.
                                        </p>
                                    </div>
                                    <span
                                        class="text-sm font-semibold text-indigo-700"
                                    >
                                        {{ selectedSubjectCount }} selected
                                    </span>
                                </div>
                                <div
                                    v-if="subjectSelections.length"
                                    class="overflow-x-auto rounded-lg border border-slate-200"
                                >
                                    <table
                                        class="w-full min-w-[820px] text-left text-sm"
                                    >
                                        <thead
                                            class="bg-slate-50 text-xs text-slate-500 uppercase"
                                        >
                                            <tr>
                                                <th class="px-3 py-2">
                                                    Include
                                                </th>
                                                <th class="px-3 py-2">
                                                    Subject
                                                </th>
                                                <th class="px-3 py-2">
                                                    Source Section
                                                </th>
                                                <th class="px-3 py-2">
                                                    Destination Section
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody
                                            class="divide-y divide-slate-100"
                                        >
                                            <tr
                                                v-for="subject in subjectSelections"
                                                :key="
                                                    subject.source_subject_offering_id
                                                "
                                            >
                                                <td class="px-3 py-2">
                                                    <input
                                                        v-model="
                                                            subject.include
                                                        "
                                                        type="checkbox"
                                                        :disabled="
                                                            !isSectionIncluded(
                                                                subject.source_section_id,
                                                            )
                                                        "
                                                        class="rounded border-slate-300 text-indigo-600 disabled:opacity-50"
                                                    />
                                                </td>
                                                <td class="px-3 py-2">
                                                    <span
                                                        class="block font-semibold text-slate-900"
                                                    >
                                                        {{
                                                            subject.subject_code
                                                        }}
                                                        -
                                                        {{
                                                            subject.subject_name
                                                        }}
                                                    </span>
                                                    <span
                                                        class="text-xs text-slate-500"
                                                    >
                                                        {{ subject.unit ?? 0 }}
                                                        units
                                                    </span>
                                                </td>
                                                <td class="px-3 py-2">
                                                    {{
                                                        subject.source_section_name
                                                    }}
                                                    - Grade
                                                    {{
                                                        subject.source_year_level
                                                    }}
                                                </td>
                                                <td class="px-3 py-2">
                                                    {{
                                                        subjectDestinationLabel(
                                                            subject,
                                                        )
                                                    }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <p
                                    v-else
                                    class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500"
                                >
                                    No active Subject Offerings exist in the
                                    source semester.
                                </p>
                            </section>

                            <div class="mt-5 flex justify-end">
                                <button
                                    type="button"
                                    class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-semibold text-white"
                                    @click="rolloverConfigurationOpen = false"
                                >
                                    Save preview changes
                                </button>
                            </div>
                        </div>
                    </div>
                    <div
                        v-if="selectedSectionStudents"
                        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                        @click.self="selectedSectionStudents = null"
                    >
                        <div
                            class="max-h-[85vh] w-full max-w-5xl overflow-y-auto rounded-xl bg-white p-5 shadow-xl"
                        >
                            <div
                                class="mb-4 flex items-start justify-between gap-4"
                            >
                                <div>
                                    <h3
                                        class="text-lg font-bold text-slate-900"
                                    >
                                        Students in section
                                    </h3>
                                    <p class="text-sm text-slate-500">
                                        {{
                                            selectedSectionStudents.source_label
                                        }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="rounded-md border border-slate-300 px-3 py-1.5 text-sm"
                                    @click="selectedSectionStudents = null"
                                >
                                    Close
                                </button>
                            </div>
                            <div
                                class="overflow-x-auto rounded-lg border border-slate-200"
                            >
                                <table class="w-full min-w-[900px] text-sm">
                                    <thead
                                        class="bg-slate-50 text-left text-xs text-slate-500 uppercase"
                                    >
                                        <tr>
                                            <th class="px-3 py-2">Student</th>
                                            <th class="px-3 py-2">
                                                Current grade
                                            </th>
                                            <th class="px-3 py-2">
                                                Recommended
                                            </th>
                                            <th class="px-3 py-2">Decision</th>
                                            <th class="px-3 py-2">
                                                Destination section
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        <tr
                                            v-for="item in studentsForSection(
                                                selectedSectionStudents,
                                            )"
                                            :key="
                                                item.source_student_enrollment_id
                                            "
                                        >
                                            <td class="px-3 py-2">
                                                {{ item.student_id }}
                                            </td>
                                            <td class="px-3 py-2">
                                                Grade {{ item.year_level }}
                                            </td>
                                            <td class="px-3 py-2 capitalize">
                                                {{ item.recommended_decision }}
                                            </td>
                                            <td class="px-3 py-2">
                                                <span
                                                    v-if="
                                                        isArchivedStudent(item)
                                                    "
                                                    class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700"
                                                    >Archived</span
                                                >
                                                <select
                                                    v-else
                                                    v-model="
                                                        studentDecisions[
                                                            item
                                                                .source_student_enrollment_id
                                                        ].decision
                                                    "
                                                    class="rounded-md border-slate-300 text-sm"
                                                    @change="
                                                        resetStudentDestinationForDecision(
                                                            item,
                                                        )
                                                    "
                                                >
                                                    <option value="promote">
                                                        Promote
                                                    </option>
                                                    <option value="retain">
                                                        Retain grade
                                                    </option>
                                                    <option value="graduated">
                                                        Archive / graduate
                                                    </option>
                                                    <option value="dropped">
                                                        Drop
                                                    </option>
                                                    <option value="review">
                                                        Review
                                                    </option>
                                                </select>
                                            </td>
                                            <td class="px-3 py-2">
                                                <span
                                                    v-if="
                                                        isArchivedStudent(item)
                                                    "
                                                    class="text-sm font-medium text-slate-500"
                                                    >No destination
                                                    section</span
                                                >
                                                <select
                                                    v-else
                                                    v-model="
                                                        studentDecisions[
                                                            item
                                                                .source_student_enrollment_id
                                                        ].destination_choice
                                                    "
                                                    class="w-full rounded-md border-slate-300 text-sm"
                                                >
                                                    <option value="">
                                                        Use section mapping:
                                                        {{
                                                            sectionDefaultDestinationLabel(
                                                                item,
                                                            )
                                                        }}
                                                    </option>
                                                    <optgroup
                                                        v-if="
                                                            selectedRolloverSectionsForStudent(
                                                                item,
                                                            ).length
                                                        "
                                                        label="Selected for rollover"
                                                    >
                                                        <option
                                                            v-for="mapping in selectedRolloverSectionsForStudent(
                                                                item,
                                                            )"
                                                            :key="`rollover-${mapping.source_section_id}`"
                                                            :value="`rollover:${mapping.source_section_id}`"
                                                        >
                                                            {{
                                                                rolloverSectionLabel(
                                                                    mapping,
                                                                )
                                                            }}
                                                        </option>
                                                    </optgroup>
                                                    <optgroup
                                                        v-if="
                                                            existingDestinationSectionsForStudent(
                                                                item,
                                                            ).length
                                                        "
                                                        label="Existing destination sections"
                                                    >
                                                        <option
                                                            v-for="section in existingDestinationSectionsForStudent(
                                                                item,
                                                            )"
                                                            :key="`existing-${section.section_id}`"
                                                            :value="`existing:${section.section_id}`"
                                                        >
                                                            {{
                                                                section.section_name
                                                            }}
                                                            · Grade
                                                            {{
                                                                section.year_level
                                                            }}
                                                        </option>
                                                    </optgroup>
                                                </select>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <button
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="rolloverSubmitting"
                        @click="executeRollover"
                    >
                        {{
                            rolloverSubmitting
                                ? 'Executing academic rollover...'
                                : 'Execute reviewed academic rollover'
                        }}
                    </button>
                </div>

                <div v-if="rollovers.length" class="mt-6 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-xs text-slate-500 uppercase"
                            >
                                <th class="py-2">Source</th>
                                <th>Destination</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="rollover in rollovers"
                                :key="rollover.id"
                                class="border-b border-slate-100"
                            >
                                <td class="py-2">{{ rollover.source }}</td>
                                <td>{{ rollover.destination }}</td>
                                <td class="capitalize">
                                    {{
                                        rollover.mode === 'semester'
                                            ? 'Semester-only'
                                            : 'Academic rollover'
                                    }}
                                </td>
                                <td class="capitalize">
                                    {{ rollover.status }}
                                </td>
                                <td>{{ rollover.completed_at || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <h2 class="text-lg font-semibold text-slate-900">
                    Legacy fallback monitor
                </h2>
                <p
                    v-if="!legacyFallbacks.length"
                    class="mt-2 text-sm font-semibold text-emerald-700"
                >
                    No legacy academic fallback has been observed.
                </p>
                <div v-else class="mt-3 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr
                                class="border-b text-left text-xs text-slate-500 uppercase"
                            >
                                <th class="py-2">Context</th>
                                <th>Uses</th>
                                <th>Last used</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="event in legacyFallbacks"
                                :key="event.context"
                                class="border-b border-slate-100"
                            >
                                <td class="py-2 font-medium">
                                    {{ event.context }}
                                </td>
                                <td>{{ event.use_count }}</td>
                                <td>{{ event.last_used_at }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section
                class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm"
            >
                <div
                    v-if="!props.academicYears.length"
                    class="p-8 text-center text-sm text-slate-500"
                >
                    No academic years have been created.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead
                            class="bg-slate-50 text-left text-xs tracking-wide text-slate-500 uppercase"
                        >
                            <tr>
                                <th class="px-4 py-3">Academic year</th>
                                <th class="px-4 py-3">Dates</th>
                                <th class="px-4 py-3">Semester</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="year in props.academicYears"
                                :key="year.academic_year_id"
                            >
                                <td
                                    class="px-4 py-4 font-semibold text-slate-900"
                                >
                                    {{ year.name }}
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    {{ year.starts_on }} – {{ year.ends_on }}
                                </td>
                                <td class="px-4 py-4 text-slate-600">
                                    <select
                                        v-if="year.status === 'active'"
                                        :value="year.active_semester || ''"
                                        class="rounded-md border-slate-300 text-sm"
                                        @change="
                                            updateSemester(
                                                year,
                                                $event.target.value,
                                            )
                                        "
                                    >
                                        <option value="">Not selected</option>
                                        <option value="1st Semester">
                                            1st Semester
                                        </option>
                                        <option value="2nd Semester">
                                            2nd Semester
                                        </option>
                                    </select>
                                    <span v-else>
                                        {{
                                            year.active_semester ||
                                            'Not selected'
                                        }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-semibold capitalize"
                                        :class="badgeClass(year.status)"
                                    >
                                        {{ year.status }}
                                    </span>
                                </td>
                                <td class="px-4 py-4">
                                    <div
                                        class="flex flex-wrap justify-end gap-2"
                                    >
                                        <button
                                            v-if="
                                                year.status === 'draft' ||
                                                year.status === 'closed'
                                            "
                                            class="rounded-md border border-blue-300 px-3 py-1.5 text-xs font-semibold text-blue-700"
                                            @click="
                                                perform(
                                                    year,
                                                    'activate',
                                                    'The currently active year, if any, will be closed.',
                                                )
                                            "
                                        >
                                            Activate
                                        </button>
                                        <button
                                            v-if="year.status === 'active'"
                                            class="rounded-md border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700"
                                            @click="
                                                perform(
                                                    year,
                                                    'close',
                                                    'Closing removes the active year. Current academic workflows are not yet linked to this foundation.',
                                                )
                                            "
                                        >
                                            Close
                                        </button>
                                        <button
                                            v-if="year.status === 'closed'"
                                            class="rounded-md border border-purple-300 px-3 py-1.5 text-xs font-semibold text-purple-700"
                                            @click="
                                                perform(
                                                    year,
                                                    'archive',
                                                    'Archived years remain available for historical reference.',
                                                )
                                            "
                                        >
                                            Archive
                                        </button>
                                        <button
                                            v-if="
                                                year.status === 'closed' ||
                                                year.status === 'archived'
                                            "
                                            class="rounded-md border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-700"
                                            @click="reopen(year)"
                                        >
                                            Reopen
                                        </button>
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
