<script setup>
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

const props = defineProps({
    subjects: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    filterOptions: { type: Object, default: null },
    currentUserRole: { type: String, required: true },
});

const filters = reactive({
    school_year: props.filters.school_year ?? '',
    semester: props.filters.semester ?? '',
    department: props.filters.department ?? '',
    course: props.filters.course ?? '',
    section: props.filters.section ?? '',
    instructor: props.filters.instructor ?? '',
});

const subjectThemes = {
    emerald: {
        strip: 'from-emerald-700 to-emerald-400',
        badge: 'bg-emerald-50 text-emerald-700',
        arrow: 'text-emerald-700',
        hover: 'hover:border-emerald-300',
    },
    blue: {
        strip: 'from-blue-700 to-cyan-400',
        badge: 'bg-blue-50 text-blue-700',
        arrow: 'text-blue-700',
        hover: 'hover:border-blue-300',
    },
    amber: {
        strip: 'from-amber-600 to-yellow-400',
        badge: 'bg-amber-50 text-amber-700',
        arrow: 'text-amber-700',
        hover: 'hover:border-amber-300',
    },
    rose: {
        strip: 'from-rose-700 to-pink-400',
        badge: 'bg-rose-50 text-rose-700',
        arrow: 'text-rose-700',
        hover: 'hover:border-rose-300',
    },
    violet: {
        strip: 'from-violet-700 to-purple-400',
        badge: 'bg-violet-50 text-violet-700',
        arrow: 'text-violet-700',
        hover: 'hover:border-violet-300',
    },
    cyan: {
        strip: 'from-cyan-700 to-sky-400',
        badge: 'bg-cyan-50 text-cyan-700',
        arrow: 'text-cyan-700',
        hover: 'hover:border-cyan-300',
    },
};
const themeFor = (subject) =>
    subjectThemes[subject.color_theme] ?? subjectThemes.blue;

const applyFilters = () =>
    router.get(route('admin.attendance.logs'), filters, {
        preserveState: true,
        replace: true,
    });

const resetFilters = () => {
    Object.keys(filters).forEach((key) => (filters[key] = ''));
    applyFilters();
};
</script>

<template>
    <Head title="Attendance Subjects" />
    <main class="min-h-screen bg-slate-50 px-4 py-6 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-7xl">
            <header
                class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-blue-950 to-blue-700 p-7 text-white shadow-xl sm:p-10"
            >
                <p
                    class="text-xs font-bold tracking-[0.24em] text-blue-200 uppercase"
                >
                    Attendance workspace
                </p>
                <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">
                    {{
                        currentUserRole === 'admin'
                            ? 'Browse attendance by subject'
                            : 'Choose a subject'
                    }}
                </h1>
                <p class="mt-3 max-w-2xl text-sm leading-6 text-blue-100">
                    Open a subject to review its overall performance, student
                    summaries, sessions, individual histories, and exports.
                </p>
            </header>

            <section
                v-if="filterOptions"
                class="mt-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-bold text-slate-900">
                            Academic filters
                        </h2>
                        <p class="text-xs text-slate-500">
                            Narrow the complete subject catalog.
                        </p>
                    </div>
                    <button
                        class="text-sm font-semibold text-slate-500 hover:text-slate-900"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                    <select
                        v-model="filters.school_year"
                        class="rounded-xl border-slate-300 text-sm"
                    >
                        <option value="all">All school years</option>
                        <option
                            v-for="value in filterOptions.schoolYears"
                            :key="value"
                            :value="value"
                        >
                            {{ value }}
                        </option>
                    </select>
                    <select
                        v-model="filters.semester"
                        class="rounded-xl border-slate-300 text-sm"
                    >
                        <option value="">All terms</option>
                        <option
                            v-for="value in filterOptions.semesters"
                            :key="value"
                            :value="value"
                        >
                            {{ value }}
                        </option>
                    </select>
                    <select
                        v-model="filters.department"
                        class="rounded-xl border-slate-300 text-sm"
                    >
                        <option value="">All departments</option>
                        <option
                            v-for="value in filterOptions.departments"
                            :key="value"
                            :value="value"
                        >
                            {{ value }}
                        </option>
                    </select>
                    <select
                        v-model="filters.course"
                        class="rounded-xl border-slate-300 text-sm"
                    >
                        <option value="">All courses / strands</option>
                        <option
                            v-for="option in filterOptions.courses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <select
                        v-model="filters.section"
                        class="rounded-xl border-slate-300 text-sm"
                    >
                        <option value="">All sections</option>
                        <option
                            v-for="option in filterOptions.sections"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <select
                        v-model="filters.instructor"
                        class="rounded-xl border-slate-300 text-sm"
                    >
                        <option value="">All instructors</option>
                        <option
                            v-for="option in filterOptions.instructors"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                </div>
                <button
                    class="mt-4 rounded-xl bg-blue-700 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-800"
                    @click="applyFilters"
                >
                    Apply filters
                </button>
            </section>

            <div class="mt-7 flex items-end justify-between">
                <div>
                    <h2 class="text-xl font-black text-slate-900">Subjects</h2>
                    <p class="text-sm text-slate-500">
                        {{ subjects.length }} available
                    </p>
                </div>
            </div>

            <section
                v-if="subjects.length"
                class="mt-4 grid gap-5 sm:grid-cols-2 xl:grid-cols-3"
            >
                <button
                    v-for="subject in subjects"
                    :key="subject.id"
                    class="group min-h-56 overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-sm transition hover:-translate-y-1 hover:shadow-xl"
                    :class="themeFor(subject).hover"
                    @click="
                        router.visit(
                            route('admin.attendance.subject', subject.id),
                        )
                    "
                >
                    <div
                        class="h-2 bg-gradient-to-r"
                        :class="themeFor(subject).strip"
                    ></div>
                    <div class="p-6">
                        <div class="flex items-start justify-between gap-3">
                            <span
                                class="rounded-lg px-3 py-1 text-xs font-black tracking-wide"
                                :class="themeFor(subject).badge"
                                >{{ subject.code }}</span
                            >
                            <span
                                class="text-2xl transition group-hover:translate-x-1"
                                :class="themeFor(subject).arrow"
                                >→</span
                            >
                        </div>
                        <h3 class="mt-5 text-xl font-black text-slate-900">
                            {{ subject.name }}
                        </h3>
                        <p class="mt-2 text-sm text-slate-500">
                            {{ subject.section }} ·
                            {{ subject.school_year || 'School year not set' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ subject.semester || 'Term not set' }} ·
                            {{
                                subject.department ||
                                subject.strand ||
                                'Department not set'
                            }}
                        </p>
                        <p
                            class="mt-4 line-clamp-2 text-xs font-semibold text-slate-600"
                        >
                            {{
                                subject.instructors?.join(', ') ||
                                subject.instructor
                            }}
                        </p>
                    </div>
                </button>
            </section>
            <div
                v-else
                class="mt-4 rounded-2xl border border-dashed border-slate-300 bg-white p-14 text-center"
            >
                <p class="font-bold text-slate-700">
                    No subjects match the current scope.
                </p>
                <p class="mt-1 text-sm text-slate-500">
                    Adjust the filters or verify schedule assignments.
                </p>
            </div>
        </div>
    </main>
</template>
