<template>
    <div class="w-full">
        <div class="mx-auto max-w-[1400px] px-4 py-6">
            <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h1 class="text-3xl font-bold">Students Management</h1>
                        <p class="text-sm text-slate-500">
                            {{
                                canManageStudents
                                    ? 'Manage students using strands, sections, and RFID assignments.'
                                    : 'View students from your handled sections.'
                            }}
                        </p>
                    </div>
                    <div
                        v-if="canManageStudents"
                        class="flex items-center gap-2"
                    >
                        <button
                            @click="openAddModal"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
                        >
                            Add Student
                        </button>
                    </div>
                </div>
            </section>

            <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-6">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Search</label
                        >
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Name | Student No. | Email | RFID"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            @input="onFilterChange"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Strand</label
                        >
                        <select
                            v-model="selectedStrand"
                            @change="onFilterChange"
                            class="w-full rounded-md border border-slate-300 px-3 py-2"
                        >
                            <option value="">All Strands</option>
                            <option
                                v-for="strand in strandOptions"
                                :key="strand.strand_id"
                                :value="String(strand.strand_id)"
                            >
                                {{ strand.strand_code }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Section</label
                        >
                        <select
                            v-model="selectedSection"
                            @change="onFilterChange"
                            class="w-full rounded-md border border-slate-300 px-3 py-2"
                        >
                            <option value="">
                                {{
                                    canManageStudents
                                        ? 'All Sections'
                                        : 'Select Section'
                                }}
                            </option>
                            <option
                                v-for="section in sectionOptions"
                                :key="section.section_id"
                                :value="String(section.section_id)"
                            >
                                {{ section.label }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Grade</label
                        >
                        <select
                            v-model="selectedYear"
                            @change="onFilterChange"
                            class="w-full rounded-md border border-slate-300 px-3 py-2"
                        >
                            <option value="">All Grades</option>
                            <option value="11">Grade 11</option>
                            <option value="12">Grade 12</option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >School Year</label
                        >
                        <select
                            v-model="selectedSchoolYear"
                            @change="onFilterChange"
                            class="w-full rounded-md border border-slate-300 px-3 py-2"
                        >
                            <option value="">All School Years</option>
                            <option
                                v-for="schoolYear in availableSchoolYearOptions"
                                :key="schoolYear"
                                :value="schoolYear"
                            >
                                {{ schoolYear }}
                            </option>
                        </select>
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Status</label
                        >
                        <select
                            v-model="selectedStatus"
                            @change="onFilterChange"
                            class="w-full rounded-md border border-slate-300 px-3 py-2"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="graduated">Graduated</option>
                            <option value="dropped">Dropped</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex justify-end">
                    <button
                        @click="resetFilters"
                        class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    >
                        Reset Filters
                    </button>
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
                                    Student No.
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Name
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Email
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Strand
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Section
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Grade
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    School Year
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Status
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    RFID Tag
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Face Images
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Parents
                                </th>
                                <th
                                    v-if="canManageStudents"
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="student in filteredStudents"
                                :key="student.student_id"
                                class="hover:bg-gray-50"
                            >
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ student.student_number }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ student.first_name }}
                                    {{ student.last_name }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ student.email }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div class="font-medium">
                                        {{ student.strand_code ?? 'N/A' }}
                                    </div>
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ student.section_name ?? 'N/A' }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ student.year_level }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ student.school_year }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <span
                                        :class="statusClasses(student.status)"
                                        >{{
                                            capitalizeFirst(student.status)
                                        }}</span
                                    >
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <span
                                        v-if="student.rfid_tag"
                                        class="text-green-600"
                                        >{{ student.rfid_tag }}</span
                                    >
                                    <span v-else class="text-red-600"
                                        >Not assigned</span
                                    >
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div
                                        v-if="
                                            student.face_images &&
                                            student.face_images.length
                                        "
                                        class="flex items-center gap-1"
                                    >
                                        <img
                                            v-for="(
                                                img, i
                                            ) in student.face_images.slice(
                                                0,
                                                3,
                                            )"
                                            :key="i"
                                            :src="`/storage/${img}`"
                                            class="h-8 w-8 rounded-full border border-slate-200 object-cover"
                                            :alt="`Face ${i + 1}`"
                                        />
                                        <span
                                            v-if="
                                                student.face_images.length > 3
                                            "
                                            class="ml-1 text-xs text-slate-500"
                                            >+{{
                                                student.face_images.length - 3
                                            }}</span
                                        >
                                    </div>
                                    <span
                                        v-else
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-400"
                                        >None</span
                                    >
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div
                                        v-if="student.parents?.length"
                                        class="space-y-1"
                                    >
                                        <div
                                            v-for="parent in student.parents"
                                            :key="parent.id"
                                            class="text-sm"
                                        >
                                            <div
                                                class="font-medium text-slate-800"
                                            >
                                                {{ parent.name }}
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                {{ parent.relationship }} -
                                                {{ parent.email }}
                                            </div>
                                        </div>
                                    </div>
                                    <span
                                        v-else
                                        class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-400"
                                        >None</span
                                    >
                                </td>
                                <td
                                    v-if="canManageStudents"
                                    class="border border-gray-300 px-4 py-3"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <button
                                            @click="openEditModal(student)"
                                            class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="openParentModal(student)"
                                            class="rounded-md bg-emerald-600 px-3 py-1 text-sm text-white hover:bg-emerald-700"
                                        >
                                            Parents
                                        </button>
                                        <button
                                            @click="openEnrollmentHistory(student)"
                                            class="rounded-md bg-sky-600 px-3 py-1 text-sm text-white hover:bg-sky-700"
                                        >
                                            Enrollment History
                                        </button>
                                        <button
                                            @click="
                                                resetStudentPassword(student)
                                            "
                                            class="rounded-md bg-amber-500 px-3 py-1 text-sm text-white hover:bg-amber-600"
                                        >
                                            Reset Password
                                        </button>
                                        <button
                                            @click="deleteStudent(student)"
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
                        v-if="filteredStudents.length === 0"
                        class="py-8 text-center text-gray-500"
                    >
                        No student records found.
                    </div>
                </div>
            </section>

            <div
                v-if="showModal && canManageStudents"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            >
                <div
                    class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-6"
                >
                    <h2 class="mb-4 text-xl font-semibold">
                        {{ isEditing ? 'Edit Student' : 'Add New Student' }}
                    </h2>

                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Student Number</label
                                >
                                <input
                                    v-model="form.student_number"
                                    type="text"
                                    placeholder="e.g., 2025-001"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Student ID</label
                                >
                                <input
                                    v-model="form.student_id"
                                    type="text"
                                    disabled
                                    class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >First Name *</label
                                >
                                <input
                                    v-model="form.first_name"
                                    type="text"
                                    placeholder="First name"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    required
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Middle Name</label
                                >
                                <input
                                    v-model="form.middle_name"
                                    type="text"
                                    placeholder="Middle name"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Last Name *</label
                                >
                                <input
                                    v-model="form.last_name"
                                    type="text"
                                    placeholder="Last name"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    required
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Email *</label
                                >
                                <input
                                    v-model="form.email"
                                    type="email"
                                    placeholder="student@email.com"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    required
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Phone</label
                                >
                                <input
                                    v-model="form.phone"
                                    type="tel"
                                    placeholder="+63 9XX XXXX XXX"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Strand *</label
                                >
                                <select
                                    v-model="form.strand_id"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                    required
                                >
                                    <option value="">Select Strand</option>
                                    <option
                                        v-for="strand in strandOptions"
                                        :key="strand.strand_id"
                                        :value="String(strand.strand_id)"
                                    >
                                        {{ strand.strand_code }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Section *</label
                                >
                                <select
                                    v-model="form.section_id"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                    required
                                >
                                    <option value="">Select Section</option>
                                    <option
                                        v-for="section in availableSections"
                                        :key="section.section_id"
                                        :value="String(section.section_id)"
                                    >
                                        {{ section.label }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Grade *</label
                                >
                                <select
                                    v-model="form.year_level"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                    required
                                >
                                    <option value="">Select Grade</option>
                                    <option value="11">Grade 11</option>
                                    <option value="12">Grade 12</option>
                                </select>
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

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >School Year *</label
                                >
                                <select
                                    v-model="form.school_year"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                    required
                                >
                                    <option value="">Select School Year</option>
                                    <option
                                        v-for="schoolYear in availableSchoolYearOptions"
                                        :key="schoolYear"
                                        :value="schoolYear"
                                    >
                                        {{ schoolYear }}
                                    </option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Status *</label
                                >
                                <select
                                    v-model="form.status"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                    required
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="graduated">Graduated</option>
                                    <option value="dropped">Dropped</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Gender</label
                                >
                                <select
                                    v-model="form.gender"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                >
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >RFID Tag</label
                                >
                                <input
                                    v-model="form.rfid_tag"
                                    type="text"
                                    placeholder="RFID tag (if assigned)"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                            </div>
                        </div>

                        <!-- Face Images Management (edit mode only) -->
                        <div v-if="false && isEditing" class="border-t pt-4">
                            <div class="mb-2 flex items-center justify-between">
                                <label
                                    class="text-sm font-medium text-slate-700"
                                    >Face Images for Recognition
                                    <span class="text-xs text-slate-400"
                                        >(max 5, used by CompreFace)</span
                                    ></label
                                >
                                <span class="text-xs text-slate-500"
                                    >{{ faceImages.length }} / 5</span
                                >
                            </div>

                            <!-- Existing images -->
                            <div
                                v-if="faceImages.length"
                                class="mb-3 flex flex-wrap gap-2"
                            >
                                <div
                                    v-for="(img, i) in faceImages"
                                    :key="i"
                                    class="relative"
                                >
                                    <img
                                        :src="`/storage/${img}`"
                                        class="h-20 w-20 rounded-lg border border-slate-200 object-cover"
                                        :alt="`Face ${i + 1}`"
                                    />
                                    <button
                                        type="button"
                                        @click="removeFaceImage(i)"
                                        :disabled="faceImageUploading"
                                        class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-rose-500 text-xs font-bold text-white hover:bg-rose-600 disabled:opacity-50"
                                    >
                                        ×
                                    </button>
                                </div>
                            </div>
                            <p v-else class="mb-3 text-xs text-slate-400">
                                No face images enrolled yet.
                            </p>

                            <!-- Upload controls -->
                            <div
                                v-if="faceImages.length < 5"
                                class="flex flex-wrap items-center gap-2"
                            >
                                <label
                                    class="cursor-pointer rounded-md border border-slate-300 bg-slate-50 px-3 py-1.5 text-sm hover:bg-slate-100"
                                >
                                    <span>Upload File</span>
                                    <input
                                        type="file"
                                        accept="image/jpeg,image/png,image/jpg"
                                        class="hidden"
                                        @change="handleFaceImageFile"
                                        :disabled="faceImageUploading"
                                    />
                                </label>
                                <button
                                    type="button"
                                    @click="showFaceCamera = !showFaceCamera"
                                    class="rounded-md border border-slate-300 bg-slate-50 px-3 py-1.5 text-sm hover:bg-slate-100"
                                >
                                    {{
                                        showFaceCamera
                                            ? 'Hide Camera'
                                            : 'Use Camera'
                                    }}
                                </button>
                                <span
                                    v-if="faceImageUploading"
                                    class="text-xs text-blue-600"
                                    >Uploading...</span
                                >
                                <span
                                    v-if="faceImageError"
                                    class="text-xs text-rose-600"
                                    >{{ faceImageError }}</span
                                >
                            </div>

                            <!-- Camera capture -->
                            <div
                                v-if="showFaceCamera && faceImages.length < 5"
                                class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3"
                            >
                                <CameraCapture ref="faceImageCameraRef" />
                                <button
                                    type="button"
                                    @click="captureAndUploadFaceImage"
                                    :disabled="faceImageUploading"
                                    class="mt-2 rounded-md bg-blue-600 px-3 py-1.5 text-sm text-white hover:bg-blue-700 disabled:opacity-50"
                                >
                                    Capture & Save
                                </button>
                            </div>
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
                                    isEditing ? 'Update Student' : 'Add Student'
                                }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div
                v-if="showEnrollmentModal && selectedStudent"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
                @click.self="closeEnrollmentHistory"
            >
                <div class="max-h-[85vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6 shadow-xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-xl font-semibold text-slate-900">Enrollment History</h2>
                            <p class="mt-1 text-sm text-slate-500">
                                {{ selectedStudent.first_name }} {{ selectedStudent.last_name }} · {{ selectedStudent.student_number }}
                            </p>
                        </div>
                        <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700" @click="closeEnrollmentHistory">Close</button>
                    </div>
                    <div v-if="!selectedStudent.enrollments?.length" class="mt-6 rounded-lg bg-slate-50 p-6 text-center text-sm text-slate-500">
                        No enrollment-history record is available yet.
                    </div>
                    <div v-else class="mt-6 overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                <tr>
                                    <th class="px-3 py-2">School year</th>
                                    <th class="px-3 py-2">Semester</th>
                                    <th class="px-3 py-2">Grade</th>
                                    <th class="px-3 py-2">Section</th>
                                    <th class="px-3 py-2">Strand</th>
                                    <th class="px-3 py-2">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr v-for="enrollment in selectedStudent.enrollments" :key="enrollment.student_enrollment_id">
                                    <td class="px-3 py-3 font-medium text-slate-900">{{ enrollment.academic_year }}</td>
                                    <td class="px-3 py-3 text-slate-600">{{ enrollment.semester }}</td>
                                    <td class="px-3 py-3 text-slate-600">{{ enrollment.year_level }}</td>
                                    <td class="px-3 py-3 text-slate-600">{{ enrollment.section_name }}</td>
                                    <td class="px-3 py-3 text-slate-600">{{ enrollment.strand_code }}</td>
                                    <td class="px-3 py-3 capitalize text-slate-600">{{ enrollment.status }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div
                v-if="showParentModal && canManageStudents && selectedStudent"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            >
                <div
                    class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6"
                >
                    <div
                        class="mb-5 flex flex-col gap-2 md:flex-row md:items-start md:justify-between"
                    >
                        <div>
                            <h2 class="text-xl font-semibold">
                                Parent Accounts
                            </h2>
                            <p class="text-sm text-slate-500">
                                {{ selectedStudent.first_name }}
                                {{ selectedStudent.last_name }} -
                                {{ selectedStudent.student_number }}
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="closeParentModal"
                            class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            Close
                        </button>
                    </div>

                    <div class="mb-6 rounded-md border border-slate-200">
                        <div
                            v-if="selectedStudent.parents?.length"
                            class="divide-y divide-slate-200"
                        >
                            <div
                                v-for="parent in selectedStudent.parents"
                                :key="parent.id"
                                class="flex flex-col gap-3 p-4 md:flex-row md:items-center md:justify-between"
                            >
                                <div>
                                    <div class="font-medium text-slate-900">
                                        {{ parent.name }}
                                    </div>
                                    <div class="text-sm text-slate-500">
                                        {{ parent.email }}
                                    </div>
                                    <div class="text-xs text-slate-500">
                                        {{ parent.relationship }}
                                        <span v-if="parent.phone">
                                            - {{ parent.phone }}
                                        </span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        @click="editParent(parent)"
                                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm text-white hover:bg-indigo-700"
                                    >
                                        Edit
                                    </button>
                                    <button
                                        type="button"
                                        @click="unlinkParent(parent)"
                                        class="rounded-md bg-rose-500 px-3 py-1.5 text-sm text-white hover:bg-rose-600"
                                    >
                                        Unlink
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="p-4 text-sm text-slate-500">
                            No parent account is linked to this student yet.
                        </div>
                    </div>

                    <form @submit.prevent="submitParentForm" class="space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-slate-900">
                                {{
                                    selectedParent
                                        ? 'Edit Parent'
                                        : 'Create or Link Parent'
                                }}
                            </h3>
                            <button
                                v-if="selectedParent"
                                type="button"
                                @click="resetParentForm"
                                class="text-sm text-blue-600 hover:text-blue-700"
                            >
                                New Parent
                            </button>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                >
                                    Parent Name *
                                </label>
                                <input
                                    v-model="parentForm.name"
                                    type="text"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    required
                                />
                                <p
                                    v-if="parentForm.errors.name"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ parentForm.errors.name }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                >
                                    Email *
                                </label>
                                <input
                                    v-model="parentForm.email"
                                    type="email"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    required
                                />
                                <p
                                    v-if="parentForm.errors.email"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ parentForm.errors.email }}
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                >
                                    Relationship *
                                </label>
                                <input
                                    v-model="parentForm.relationship"
                                    type="text"
                                    placeholder="mother, father, guardian"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    required
                                />
                                <p
                                    v-if="parentForm.errors.relationship"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ parentForm.errors.relationship }}
                                </p>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                >
                                    Phone
                                </label>
                                <input
                                    v-model="parentForm.phone"
                                    type="tel"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                >
                                    Gender
                                </label>
                                <select
                                    v-model="parentForm.gender"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2"
                                >
                                    <option value="">Select Gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                </select>
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                >
                                    Password
                                </label>
                                <input
                                    v-model="parentForm.password"
                                    type="password"
                                    placeholder="Required for new"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                />
                                <p
                                    v-if="parentForm.errors.password"
                                    class="mt-1 text-xs text-rose-600"
                                >
                                    {{ parentForm.errors.password }}
                                </p>
                            </div>
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button
                                type="button"
                                @click="resetParentForm"
                                class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50"
                            >
                                Reset
                            </button>
                            <button
                                type="submit"
                                class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700 disabled:opacity-50"
                                :disabled="parentForm.processing"
                            >
                                {{
                                    selectedParent
                                        ? 'Update Parent'
                                        : 'Save Parent'
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
import { router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import CameraCapture from '@/components/CameraCapture.vue';

interface Student {
    student_id: string | number;
    student_number: string;
    first_name: string;
    middle_name: string;
    last_name: string;
    email: string;
    phone: string;
    gender: string;
    strand_id: string | number;
    strand_code: string;
    section_id: string | number;
    section_name: string;
    year_level: string | number;
    semester: string;
    school_year: string;
    rfid_tag: string;
    face_images?: string[];
    status: 'active' | 'inactive' | 'graduated' | 'dropped';
    parents?: ParentAccount[];
    enrollments?: StudentEnrollment[];
}

interface StudentEnrollment {
    student_enrollment_id: string | number;
    academic_year: string;
    semester: string;
    year_level: string | number;
    section_name: string;
    strand_code: string;
    status: string;
}

interface ParentAccount {
    id: string | number;
    name: string;
    email: string;
    phone?: string;
    gender?: string;
    relationship: string;
}

interface StrandOption {
    strand_id: string | number;
    strand_code: string;
    strand_name: string;
}

interface SectionOption {
    section_id: string | number;
    section_name: string;
    strand_id: string | number;
    school_year: string;
    label: string;
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
    students: {
        type: Array as () => Student[],
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({
            search: '',
            strand: '',
            section: '',
            year: '',
            school_year: '',
            status: '',
        }),
    },
    strandOptions: {
        type: Array as () => StrandOption[],
        default: () => [],
    },
    sectionOptions: {
        type: Array as () => SectionOption[],
        default: () => [],
    },
    schoolYearOptions: {
        type: Array as () => string[],
        default: () => [],
    },
    currentUserRole: {
        type: String,
        default: '',
    },
    canManageStudents: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const currentRole = computed(() =>
    String(
        props.currentUserRole || page.props.auth?.user?.role || '',
    ).toLowerCase(),
);
const canManageStudents = computed(
    () => props.canManageStudents || currentRole.value === 'admin',
);
const search = ref(props.filters.search ?? '');
const selectedStrand = ref(props.filters.strand ?? '');
const selectedSection = ref(props.filters.section ?? '');
const selectedYear = ref(props.filters.year ?? '');
const selectedSchoolYear = ref(props.filters.school_year ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const showModal = ref(false);
const isEditing = ref(false);
const selectedStudent = ref<Student | null>(null);
const showParentModal = ref(false);
const showEnrollmentModal = ref(false);
const selectedParent = ref<ParentAccount | null>(null);
const defaultSchoolYearOptions = Array.from({ length: 6 }, (_, index) => {
    const startYear = 2025 + index;
    return `${startYear}-${startYear + 1}`;
});

// Face image management state
const faceImages = ref<string[]>([]);
const faceImageUploading = ref(false);
const faceImageError = ref('');
const showFaceCamera = ref(false);
const faceImageCameraRef = ref<InstanceType<typeof CameraCapture> | null>(null);

const form = useForm({
    student_id: '',
    student_number: '',
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    phone: '',
    gender: '',
    strand_id: '',
    section_id: '',
    year_level: '',
    semester: '1st Semester',
    school_year: '',
    rfid_tag: '',
    status: 'active',
});

const parentForm = useForm({
    name: '',
    email: '',
    phone: '',
    gender: '',
    relationship: 'parent',
    password: '',
});

const filteredStudents = computed<Student[]>(() => {
    return (props.students as Student[]).filter((student) => {
        const query = search.value.toLowerCase();
        const matchesSearch =
            query === '' ||
            [
                student.first_name,
                student.last_name,
                student.student_number,
                student.email,
                student.rfid_tag,
            ].some((value) =>
                String(value ?? '')
                    .toLowerCase()
                    .includes(query),
            );
        const matchesStrand =
            selectedStrand.value === '' ||
            String(student.strand_id) === selectedStrand.value;
        const matchesSection =
            selectedSection.value === '' ||
            String(student.section_id) === selectedSection.value;
        const matchesYear =
            selectedYear.value === '' ||
            String(student.year_level) === selectedYear.value;
        const matchesSchoolYear =
            selectedSchoolYear.value === '' ||
            String(student.school_year) === selectedSchoolYear.value;
        const matchesStatus =
            selectedStatus.value === '' ||
            student.status === selectedStatus.value;

        return (
            matchesSearch &&
            matchesStrand &&
            matchesSection &&
            matchesYear &&
            matchesSchoolYear &&
            matchesStatus
        );
    });
});

const availableSections = computed<SectionOption[]>(() => {
    return (props.sectionOptions as SectionOption[]).filter((section) => {
        const matchesStrand =
            !form.strand_id ||
            String(section.strand_id) === String(form.strand_id);
        return matchesStrand;
    });
});

const availableSchoolYearOptions = computed(() => {
    return Array.from(
        new Set(
            [
                ...defaultSchoolYearOptions,
                ...(props.schoolYearOptions as string[]),
                ...(props.students as Student[]).map(
                    (student) => student.school_year,
                ),
            ].filter(Boolean),
        ),
    ).sort();
});

const onFilterChange = () => {
    router.get(
        route('admin.students.index'),
        {
            search: search.value,
            strand: selectedStrand.value,
            section: selectedSection.value,
            year: selectedYear.value,
            school_year: selectedSchoolYear.value,
            status: selectedStatus.value,
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
    selectedStrand.value = '';
    selectedSection.value = '';
    selectedYear.value = '';
    selectedSchoolYear.value = '';
    selectedStatus.value = '';
    onFilterChange();
};

const openAddModal = () => {
    if (!canManageStudents.value) return;
    isEditing.value = false;
    selectedStudent.value = null;
    form.reset();
    form.status = 'active';
    form.semester = '1st Semester';
    showModal.value = true;
};

const openEditModal = (student: Student) => {
    if (!canManageStudents.value) return;
    isEditing.value = true;
    selectedStudent.value = student;
    form.reset();
    form.student_id = String(student.student_id);
    form.student_number = student.student_number;
    form.first_name = student.first_name;
    form.middle_name = student.middle_name;
    form.last_name = student.last_name;
    form.email = student.email;
    form.phone = student.phone;
    form.gender = student.gender;
    form.strand_id = String(student.strand_id ?? '');
    form.section_id = String(student.section_id ?? '');
    form.year_level = String(student.year_level);
    form.semester = student.semester;
    form.school_year = student.school_year;
    form.rfid_tag = student.rfid_tag;
    form.status = student.status;
    faceImages.value = [...(student.face_images ?? [])];
    faceImageError.value = '';
    showFaceCamera.value = false;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    isEditing.value = false;
    selectedStudent.value = null;
    faceImages.value = [];
    faceImageError.value = '';
    showFaceCamera.value = false;
    form.reset();
};

const resetParentForm = () => {
    selectedParent.value = null;
    parentForm.reset();
    parentForm.clearErrors();
    parentForm.relationship = 'parent';
};

const openParentModal = (student: Student) => {
    if (!canManageStudents.value) return;
    selectedStudent.value = student;
    showParentModal.value = true;
    resetParentForm();
};

const closeParentModal = () => {
    showParentModal.value = false;
    selectedStudent.value = null;
    resetParentForm();
};

const openEnrollmentHistory = (student: Student) => {
    selectedStudent.value = student;
    showEnrollmentModal.value = true;
};

const closeEnrollmentHistory = () => {
    showEnrollmentModal.value = false;
    selectedStudent.value = null;
};

const editParent = (parent: ParentAccount) => {
    selectedParent.value = parent;
    parentForm.clearErrors();
    parentForm.name = parent.name;
    parentForm.email = parent.email;
    parentForm.phone = parent.phone ?? '';
    parentForm.gender = parent.gender ?? '';
    parentForm.relationship = parent.relationship || 'parent';
    parentForm.password = '';
};

const submitParentForm = () => {
    if (!selectedStudent.value) return;

    const options = {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            router.reload({
                only: ['students'],
                onSuccess: () => {
                    const refreshed = (props.students as Student[]).find(
                        (student) =>
                            String(student.student_id) ===
                            String(selectedStudent.value?.student_id),
                    );
                    if (refreshed) selectedStudent.value = refreshed;
                    resetParentForm();
                },
            });
        },
    };

    if (selectedParent.value) {
        parentForm.put(
            route('admin.students.parents.update', {
                id: selectedStudent.value.student_id,
                parent: selectedParent.value.id,
            }),
            options,
        );
        return;
    }

    parentForm.post(
        route('admin.students.parents.store', {
            id: selectedStudent.value.student_id,
        }),
        options,
    );
};

const unlinkParent = (parent: ParentAccount) => {
    if (!selectedStudent.value) return;
    if (
        !confirm(
            `Unlink ${parent.name} from ${selectedStudent.value.first_name} ${selectedStudent.value.last_name}?`,
        )
    ) {
        return;
    }

    const unlinkForm = useForm({});
    unlinkForm.delete(
        route('admin.students.parents.destroy', {
            id: selectedStudent.value.student_id,
            parent: parent.id,
        }),
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                router.reload({
                    only: ['students'],
                    onSuccess: () => {
                        const refreshed = (props.students as Student[]).find(
                            (student) =>
                                String(student.student_id) ===
                                String(selectedStudent.value?.student_id),
                        );
                        if (refreshed) selectedStudent.value = refreshed;
                        if (
                            String(selectedParent.value?.id) ===
                            String(parent.id)
                        ) {
                            resetParentForm();
                        }
                    },
                });
            },
        },
    );
};

const defaultStudentPassword = (student: Student) =>
    `${student.first_name ?? ''}${student.last_name ?? ''}`.replace(
        /\s+/g,
        '',
    ) || String(student.student_number ?? '');

const resetStudentPassword = (student: Student) => {
    if (!canManageStudents.value) return;

    const password = defaultStudentPassword(student);
    if (
        !confirm(
            `Reset ${student.first_name} ${student.last_name}'s portal password to "${password}"?`,
        )
    ) {
        return;
    }

    const resetForm = useForm({});
    resetForm.put(
        route('admin.students.password.reset-default', {
            id: student.student_id,
        }),
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['students'] }),
        },
    );
};

const submitForm = () => {
    if (
        !form.first_name ||
        !form.last_name ||
        !form.email ||
        !form.strand_id ||
        !form.section_id ||
        !form.year_level ||
        !form.semester ||
        !form.school_year
    ) {
        alert('Please fill in all required fields.');
        return;
    }

    if (isEditing.value) {
        form.put(
            route('admin.students.update', {
                id: selectedStudent.value?.student_id,
            }),
            {
                preserveState: true,
                onSuccess: () => {
                    closeModal();
                    router.reload({ only: ['students'] });
                },
            },
        );
    } else {
        form.post(route('admin.students.store'), {
            preserveState: true,
            onSuccess: () => {
                closeModal();
                router.reload({ only: ['students'] });
            },
        });
    }
};

const getXsrf = () => {
    const raw = document.cookie
        .split('; ')
        .find((r) => r.startsWith('XSRF-TOKEN='))
        ?.split('=')[1];
    return raw ? decodeURIComponent(raw) : '';
};

const uploadFaceImageBlob = async (blob: Blob, filename: string) => {
    if (!selectedStudent.value) return;
    faceImageUploading.value = true;
    faceImageError.value = '';
    const formData = new FormData();
    formData.append('image', blob, filename);
    try {
        const res = await fetch(
            route('admin.students.face-images.upload', {
                id: selectedStudent.value.student_id,
            }),
            {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrf(),
                },
                body: formData,
            },
        );
        const data = await res.json();
        if (data.ok) {
            faceImages.value = data.face_images ?? [];
            // Sync back into the local students list so the table updates
            const student = (props.students as Student[]).find(
                (s) =>
                    String(s.student_id) ===
                    String(selectedStudent.value?.student_id),
            );
            if (student) student.face_images = data.face_images;
        } else {
            faceImageError.value = data.message ?? 'Upload failed.';
        }
    } catch {
        faceImageError.value = 'Network error during upload.';
    } finally {
        faceImageUploading.value = false;
    }
};

const handleFaceImageFile = async (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) return;
    await uploadFaceImageBlob(file, file.name);
    (event.target as HTMLInputElement).value = '';
};

const captureAndUploadFaceImage = async () => {
    const dataUrl = faceImageCameraRef.value?.captureFrame();
    if (!dataUrl) return;
    const res = await fetch(dataUrl);
    const blob = await res.blob();
    await uploadFaceImageBlob(blob, 'capture.jpg');
};

const removeFaceImage = async (index: number) => {
    if (!selectedStudent.value) return;
    faceImageUploading.value = true;
    faceImageError.value = '';
    try {
        const res = await fetch(
            route('admin.students.face-images.delete', {
                id: selectedStudent.value.student_id,
                index,
            }),
            {
                method: 'DELETE',
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': getXsrf(),
                },
            },
        );
        const data = await res.json();
        if (data.ok) {
            faceImages.value = data.face_images ?? [];
            const student = (props.students as Student[]).find(
                (s) =>
                    String(s.student_id) ===
                    String(selectedStudent.value?.student_id),
            );
            if (student) student.face_images = data.face_images;
        } else {
            faceImageError.value = data.message ?? 'Remove failed.';
        }
    } catch {
        faceImageError.value = 'Network error while removing image.';
    } finally {
        faceImageUploading.value = false;
    }
};

const deleteStudent = (student: Student) => {
    if (!canManageStudents.value) return;
    if (
        !confirm(
            `Are you sure you want to delete ${student.first_name} ${student.last_name}?`,
        )
    ) {
        return;
    }

    const deleteForm = useForm({});
    deleteForm.delete(
        route('admin.students.destroy', { id: student.student_id }),
        {
            preserveState: true,
            onSuccess: () => router.reload({ only: ['students'] }),
        },
    );
};

const capitalizeFirst = (str: string) =>
    str ? str.charAt(0).toUpperCase() + str.slice(1) : '';

const statusClasses = (status: string) => [
    'rounded-md px-2 py-1 text-xs font-medium',
    status === 'active'
        ? 'bg-green-100 text-green-800'
        : status === 'inactive'
          ? 'bg-yellow-100 text-yellow-800'
          : status === 'graduated'
            ? 'bg-sky-100 text-sky-800'
            : 'bg-rose-100 text-rose-800',
];
</script>
