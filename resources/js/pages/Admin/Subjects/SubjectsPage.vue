<!-- FEATURE:academic-scheduling - konektadong model, service, route, o UI para sa feature na ito. -->
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
                        v-if="view === 'active'"
                        @click="openAddModal"
                        class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
                    >
                        Add Subject
                    </button>
                </div>
            </section>

            <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div
                    class="mb-4 flex gap-4 border-b border-slate-200"
                    role="tablist"
                    aria-label="Subject records"
                >
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="view === 'active'"
                        :class="
                            view === 'active'
                                ? 'border-blue-600 text-blue-700'
                                : 'border-transparent text-slate-600'
                        "
                        class="border-b-2 px-2 py-2 text-sm font-semibold"
                        @click="setView('active')"
                    >
                        Active
                    </button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="view === 'archived'"
                        :class="
                            view === 'archived'
                                ? 'border-blue-600 text-blue-700'
                                : 'border-transparent text-slate-600'
                        "
                        class="border-b-2 px-2 py-2 text-sm font-semibold"
                        @click="setView('archived')"
                    >
                        Archived
                    </button>
                </div>
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
                                    {{
                                        view === 'archived'
                                            ? 'Archived On'
                                            : 'Actions'
                                    }}
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
                                            class="text-xs"
                                        >
                                            <span>{{
                                                offering.instructor_name ||
                                                (offering.instructor_id
                                                    ? 'Instructor unavailable'
                                                    : 'Unassigned')
                                            }}</span>
                                        </div>
                                    </div>
                                    <span v-else>Unassigned</span>
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            :aria-label="`View schedule for ${subject.subject_code}`"
                                            :title="`View schedule for ${subject.subject_code}`"
                                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-slate-300 text-slate-700 hover:bg-slate-100"
                                            @click="
                                                selectedScheduleSubject =
                                                    subject
                                            "
                                        >
                                            <CalendarDays
                                                :size="18"
                                                aria-hidden="true"
                                            />
                                        </button>
                                        <template v-if="view === 'active'">
                                            <button
                                                @click="openEditModal(subject)"
                                                class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700"
                                            >
                                                Edit
                                            </button>
                                            <button
                                                @click="
                                                    openOfferingModal(subject)
                                                "
                                                class="rounded-md bg-sky-600 px-3 py-1 text-sm text-white hover:bg-sky-700"
                                            >
                                                Add Offering
                                            </button>
                                            <button
                                                data-testid="delete-subject"
                                                @click="deleteSubject(subject)"
                                                class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600"
                                            >
                                                Archive Subject
                                            </button>
                                        </template>
                                        <span
                                            v-else
                                            class="text-sm text-slate-600"
                                            >{{
                                                subject.deleted_at || 'Archived'
                                            }}</span
                                        >
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="props.subjects.length === 0"
                        class="py-8 text-center text-gray-500"
                    >
                        No
                        {{ view === 'archived' ? 'archived' : 'active' }}
                        subjects found.
                    </div>
                </div>
            </section>

            <div
                v-if="selectedScheduleSubject"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="selectedScheduleSubject = null"
            >
                <div
                    class="max-h-[85vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6 shadow-lg"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="subject-schedule-title"
                >
                    <div
                        class="flex items-start justify-between gap-4 border-b border-slate-200 pb-4"
                    >
                        <div>
                            <h2
                                id="subject-schedule-title"
                                class="text-xl font-semibold text-slate-900"
                            >
                                {{ selectedScheduleSubject.subject_code }}
                                Schedule
                            </h2>
                            <p class="text-sm text-slate-600">
                                {{ selectedScheduleSubject.subject_name }}
                            </p>
                        </div>
                        <button
                            type="button"
                            aria-label="Close schedule"
                            title="Close schedule"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md text-slate-600 hover:bg-slate-100"
                            @click="selectedScheduleSubject = null"
                        >
                            <X :size="20" aria-hidden="true" />
                        </button>
                    </div>
                    <div
                        v-if="selectedScheduleRows.length"
                        class="divide-y divide-slate-200"
                    >
                        <div
                            v-for="row in selectedScheduleRows"
                            :key="row.scheduled_id"
                            class="grid gap-2 py-4 text-sm md:grid-cols-[minmax(0,1fr)_auto] md:items-center"
                        >
                            <div class="min-w-0">
                                <p class="font-semibold text-slate-900">
                                    {{ row.section_name }}
                                    <span class="font-normal text-slate-500"
                                        >{{ row.academic_year }} ·
                                        {{ row.semester }}</span
                                    >
                                </p>
                                <p class="mt-1 text-slate-700">
                                    {{ row.weekdays }} · {{ row.time_start }}–{{
                                        row.time_end
                                    }}
                                </p>
                            </div>
                            <p class="text-slate-600 md:text-right">
                                {{ row.room || 'Room not set' }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="py-8 text-center text-sm text-slate-500">
                        No schedules recorded for this subject.
                    </p>
                </div>
            </div>

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

                        <div
                            v-if="
                                isEditing && selectedSubject?.offerings?.length
                            "
                        >
                            <h3
                                class="mb-2 text-sm font-semibold text-slate-800"
                            >
                                Instructor by offering
                            </h3>
                            <div
                                class="divide-y rounded border border-slate-200"
                            >
                                <div
                                    v-for="offering in selectedSubject.offerings"
                                    :key="offering.subject_offering_id"
                                    class="flex flex-wrap items-center justify-between gap-3 px-3 py-2 text-sm"
                                >
                                    <div>
                                        <div class="font-medium text-slate-800">
                                            {{ offering.section_name }} ·
                                            {{ offering.academic_year }} ·
                                            {{ offering.semester }}
                                        </div>
                                        <div class="text-slate-600">
                                            {{
                                                offering.instructor_name ||
                                                (offering.instructor_id
                                                    ? 'Instructor unavailable'
                                                    : 'Unassigned')
                                            }}
                                        </div>
                                    </div>
                                    <span
                                        v-if="!offering.is_writable"
                                        class="text-slate-500"
                                        >Locked</span
                                    >
                                    <div v-else class="flex items-center gap-3">
                                        <button
                                            type="button"
                                            class="text-sky-700 hover:underline"
                                            @click="
                                                openAssignmentFromEdit(offering)
                                            "
                                        >
                                            {{
                                                offering.instructor_id
                                                    ? 'Change Instructor'
                                                    : 'Assign Instructor'
                                            }}
                                        </button>
                                        <button
                                            v-if="offering.instructor_id"
                                            type="button"
                                            class="text-rose-600 hover:underline disabled:opacity-50"
                                            :disabled="removalForm.processing"
                                            @click="removeInstructor(offering)"
                                        >
                                            Remove Instructor
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <p
                                v-if="removalForm.errors.offering"
                                class="mt-2 text-sm text-rose-600"
                            >
                                {{ removalForm.errors.offering }}
                            </p>
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
            <div
                v-if="showAssignInstructorModal && selectedOffering"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeAssignInstructorModal"
            >
                <div class="w-full max-w-lg rounded-lg bg-white p-6">
                    <h2 class="text-xl font-semibold">
                        {{
                            selectedOffering.instructor_id
                                ? 'Change Instructor'
                                : 'Assign Instructor'
                        }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ selectedOffering.section_name }} ·
                        {{ selectedOffering.academic_year }} ·
                        {{ selectedOffering.semester }}
                    </p>
                    <form
                        class="mt-5 space-y-4"
                        @submit.prevent="assignInstructor"
                    >
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >Instructor *</label
                            >
                            <SearchableSelect
                                v-model="assignmentForm.user_id"
                                :options="instructorSearchOptions"
                                placeholder="Search instructor..."
                                empty-text="No active instructors with profiles"
                            />
                            <p
                                v-if="assignmentForm.errors.user_id"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ assignmentForm.errors.user_id }}
                            </p>
                            <p
                                v-if="assignmentForm.errors.offering"
                                class="mt-1 text-xs text-rose-600"
                            >
                                {{ assignmentForm.errors.offering }}
                            </p>
                        </div>
                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button
                                type="button"
                                @click="closeAssignInstructorModal"
                                class="rounded-md border border-slate-300 px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="
                                    assignmentForm.processing ||
                                    !assignmentForm.user_id
                                "
                                class="rounded-md bg-sky-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                {{
                                    selectedOffering.instructor_id
                                        ? 'Change Instructor'
                                        : 'Assign Instructor'
                                }}
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
import { CalendarDays, X } from 'lucide-vue-next';
import Swal from 'sweetalert2';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { confirmActionModal, showAlertModal } from '@/lib/feedbackModal';

interface Subject {
    subject_id: string | number;
    deleted_at: string | null;
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
    instructor_id: string | number | null;
    instructor_name: string | null;
    is_writable: boolean;
    schedules: SubjectSchedule[];
}

interface SubjectSchedule {
    scheduled_id: number;
    weekdays: string;
    time_start: string;
    time_end: string;
    room: string | null;
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
const view = ref(props.filters.view === 'archived' ? 'archived' : 'active');
const selectedScheduleSubject = ref<Subject | null>(null);
const selectedScheduleRows = computed(() =>
    (selectedScheduleSubject.value?.offerings ?? []).flatMap((offering) =>
        (offering.schedules ?? []).map((schedule) => ({
            ...schedule,
            academic_year: offering.academic_year,
            semester: offering.semester,
            section_name: offering.section_name,
        })),
    ),
);
const showModal = ref(false);
const isEditing = ref(false);
const selectedSubject = ref<Subject | null>(null);
const showOfferingModal = ref(false);
const showAssignInstructorModal = ref(false);
const selectedOffering = ref<SubjectOffering | null>(null);
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

// @function buildSectionSearchOptions: Binubuo ang section search options sa Subjects flow.
// @useIn buildSectionSearchOptions: resources/js/pages/Admin/Subjects/SubjectsPage.vue:785
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

// @function defaultSectionAcademicYearId: Kinukuha ang default section academic year id result para sa Subjects.
// @useIn defaultSectionAcademicYearId: resources/js/pages/Admin/Subjects/SubjectsPage.vue:887
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

// @function sectionsForFilter: Kinukuha ang sections for filter result para sa Subjects.
// @useIn sectionsForFilter: resources/js/pages/Admin/Subjects/SubjectsPage.vue:779
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

// @function yearLevelsForAcademicYear: Pinoproseso ang year levels for academic year para sa Subjects.
// @useIn yearLevelsForAcademicYear: resources/js/pages/Admin/Subjects/SubjectsPage.vue:791
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
// @function academicYearWarningForSection: Kinukuha ang academic year warning for section result para sa Subjects.
// @useIn academicYearWarningForSection: resources/js/pages/Admin/Subjects/SubjectsPage.vue:831
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

// @function onFilterChange: Hinahandle ang filter change sa Subjects flow.
// @useIn onFilterChange: resources/js/pages/Admin/Subjects/SubjectsPage.vue template @input
const onFilterChange = () => {
    router.get(
        route('admin.subjects.index'),
        {
            search: search.value,
            semester: selectedSemester.value,
            academic_year_id: selectedAcademicYear.value,
            view: view.value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

const setView = (nextView: 'active' | 'archived') => {
    view.value = nextView;
    selectedSemester.value = '';
    selectedAcademicYear.value =
        nextView === 'archived'
            ? 'all'
            : (props.academicYears.find((year) => year.status === 'active')
                  ?.academic_year_id ?? 'all');
    onFilterChange();
};

// @function resetFilters: Nire-reset ang filters sa Subjects flow.
// @useIn resetFilters: resources/js/pages/Admin/Subjects/SubjectsPage.vue template @click
const resetFilters = () => {
    search.value = '';
    selectedSemester.value = '';
    selectedAcademicYear.value =
        view.value === 'archived'
            ? 'all'
            : (props.academicYears.find((year) => year.status === 'active')
                  ?.academic_year_id ??
              props.academicYears[0]?.academic_year_id ??
              '');
    onFilterChange();
};

// @function openAddModal: Binubuksan ang add modal sa Subjects flow.
// @useIn openAddModal: resources/js/pages/Admin/Subjects/SubjectsPage.vue template @click
const openAddModal = () => {
    isEditing.value = false;
    selectedSubject.value = null;
    form.reset();
    form.unit = 0;
    formAcademicYearId.value = defaultSectionAcademicYearId();
    formYearLevel.value = '';
    showModal.value = true;
};

// @function openEditModal: Binubuksan ang edit modal sa Subjects flow.
// @useIn openEditModal: resources/js/pages/Admin/Subjects/SubjectsPage.vue template @click
const openEditModal = (subject: Subject) => {
    isEditing.value = true;
    selectedSubject.value = subject;
    form.reset();
    removalForm.clearErrors();
    form.subject_name = subject.subject_name;
    form.subject_code = subject.subject_code;
    form.subject_description = subject.subject_description ?? '';
    form.department = subject.department ?? '';
    form.unit = Number(subject.unit ?? 0);
    showModal.value = true;
};

// @function closeModal: Isinasara ang modal sa Subjects flow.
// @useIn closeModal: resources/js/pages/Admin/Subjects/SubjectsPage.vue template @click
const closeModal = () => {
    showModal.value = false;
    isEditing.value = false;
    selectedSubject.value = null;
    form.reset();
    formAcademicYearId.value = '';
    formYearLevel.value = '';
};

// @function openOfferingModal: Binubuksan ang offering modal sa Subjects flow.
// @useIn openOfferingModal: resources/js/pages/Admin/Subjects/SubjectsPage.vue template @click
const openOfferingModal = (subject: Subject) => {
    selectedSubject.value = subject;
    offeringForm.reset();
    offeringAcademicYearId.value = defaultSectionAcademicYearId();
    offeringYearLevel.value = '';
    showOfferingModal.value = true;
};

// @function closeOfferingModal: Isinasara ang offering modal sa Subjects flow.
// @useIn closeOfferingModal: resources/js/pages/Admin/Subjects/SubjectsPage.vue template @click
const closeOfferingModal = () => {
    showOfferingModal.value = false;
    selectedSubject.value = null;
    offeringForm.reset();
    offeringAcademicYearId.value = '';
    offeringYearLevel.value = '';
};

const assignmentForm = useForm({ user_id: '' });
const removalForm = useForm({});

const openAssignInstructorModal = (offering: SubjectOffering) => {
    selectedOffering.value = offering;
    assignmentForm.reset();
    assignmentForm.clearErrors();
    showAssignInstructorModal.value = true;
};

const openAssignmentFromEdit = (offering: SubjectOffering) => {
    closeModal();
    openAssignInstructorModal(offering);
};

const closeAssignInstructorModal = () => {
    showAssignInstructorModal.value = false;
    selectedOffering.value = null;
    assignmentForm.reset();
    assignmentForm.clearErrors();
};

const assignInstructor = () => {
    if (!selectedOffering.value || !assignmentForm.user_id) return;

    assignmentForm.patch(
        route('admin.subjects.offerings.instructor.assign', {
            subjectOffering: selectedOffering.value.subject_offering_id,
        }),
        { preserveScroll: true, onSuccess: closeAssignInstructorModal },
    );
};

// @function submitOffering: Isinusumite ang offering sa Subjects flow.
// @useIn submitOffering: resources/js/pages/Admin/Subjects/SubjectsPage.vue template
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

// @function removeInstructor: Tinatanggal ang instructor sa Subjects flow.
// @useIn removeInstructor: resources/js/pages/Admin/Subjects/SubjectsPage.vue template @click
const removeInstructor = async (offering: SubjectOffering) => {
    const result = await Swal.fire({
        icon: 'warning',
        title: 'Remove instructor?',
        text: 'The instructor will be cleared from this offering, its schedules, and upcoming online classes. Completed class history will keep the original instructor.',
        showCancelButton: true,
        confirmButtonText: 'Remove Instructor',
        confirmButtonColor: '#e11d48',
    });
    if (!result.isConfirmed) return;

    removalForm.patch(
        route('admin.subjects.offerings.instructor.remove', {
            subjectOffering: offering.subject_offering_id,
        }),
        {
            preserveScroll: true,
            onSuccess: closeModal,
        },
    );
};

// @function submitForm: Isinusumite ang form sa Subjects flow.
// @useIn submitForm: resources/js/pages/Admin/Subjects/SubjectsPage.vue template
const submitForm = () => {
    if (
        !form.subject_name ||
        !form.subject_code ||
        form.unit === null ||
        form.unit === undefined ||
        Number(form.unit) < 0
    ) {
        showAlertModal(
            'Invalid subject details',
            'Please fill in all required fields and provide a valid unit value.',
            'warning',
        );
        return;
    }
    if (!isEditing.value && (!form.section_id || !form.semester)) {
        showAlertModal(
            'Missing class assignment',
            'Please select the academic section and semester for this subject.',
            'warning',
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

// @function deleteSubject: Tinatanggal ang subject sa Subjects flow.
// @useIn deleteSubject: resources/js/pages/Admin/Subjects/SubjectsPage.vue template @click
const deleteSubject = async (subject: Subject) => {
    const confirmed = await confirmActionModal({
        title: 'Archive subject?',
        text: `${subject.subject_code} will leave active lists. Its offerings and attendance history will remain.`,
    });

    if (!confirmed) {
        return;
    }

    const deleteForm = useForm({});
    deleteForm.delete(
        route('admin.subjects.destroy', { id: subject.subject_id }),
        {
            preserveState: true,
            onSuccess: () => router.reload({ only: ['subjects'] }),
            onError: (errors) =>
                showAlertModal(
                    'Subject not archived',
                    Object.values(errors).join(' ') ||
                        'The subject could not be archived.',
                    'error',
                ),
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
