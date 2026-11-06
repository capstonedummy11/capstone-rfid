<template>
    <div class="w-full">
        <div class="mx-auto max-w-[1400px] px-4">
            <!-- Header -->
            <div class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <h1 class="text-3xl font-bold">Classes Management</h1>
                    <button
                        @click="showAddClassModal = true"
                        class="rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-600"
                    >
                        Add Class
                    </button>
                </div>
            </div>

            <!-- Classes Table -->
            <div class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left font-medium"
                                >
                                    Subject
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left font-medium"
                                >
                                    Instructor
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left font-medium"
                                >
                                    Section
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left font-medium"
                                >
                                    Schedule
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left font-medium"
                                >
                                    Room
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left font-medium"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="classItem in classes"
                                :key="classItem.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ classItem.subject }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ classItem.instructor }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ classItem.section }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ classItem.schedule }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ classItem.room }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div class="flex space-x-2">
                                        <button
                                            @click="viewClassDetails(classItem)"
                                            class="rounded bg-blue-500 px-3 py-1 text-sm text-white transition-colors hover:bg-blue-600"
                                        >
                                            View Details
                                        </button>
                                        <button
                                            @click="editClass(classItem)"
                                            class="rounded bg-yellow-500 px-3 py-1 text-sm text-white transition-colors hover:bg-yellow-600"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="deleteClass(classItem)"
                                            class="rounded bg-red-500 px-3 py-1 text-sm text-white transition-colors hover:bg-red-600"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- No classes message -->
                <div
                    v-if="classes.length === 0"
                    class="py-8 text-center text-gray-500"
                >
                    <p>No classes found.</p>
                </div>
            </div>

            <!-- Class Details Modal -->
            <div
                v-if="showClassDetailsModal"
                class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black"
            >
                <div
                    class="mx-4 max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-6"
                >
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="text-2xl font-bold">
                            Class Details: {{ selectedClass?.subject }}
                        </h2>
                        <button
                            @click="closeModals"
                            class="text-gray-500 hover:text-gray-700"
                        >
                            <svg
                                class="h-6 w-6"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M6 18L18 6M6 6l12 12"
                                ></path>
                            </svg>
                        </button>
                    </div>

                    <div class="mb-6 grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <h3 class="mb-2 text-lg font-semibold">
                                Class Information
                            </h3>
                            <div class="space-y-2">
                                <p>
                                    <strong>Instructor:</strong>
                                    {{ selectedClass?.instructor }}
                                </p>
                                <p>
                                    <strong>Section:</strong>
                                    {{ selectedClass?.section }}
                                </p>
                                <p>
                                    <strong>Schedule:</strong>
                                    {{ selectedClass?.schedule }}
                                </p>
                                <p>
                                    <strong>Room:</strong>
                                    {{ selectedClass?.room }}
                                </p>
                            </div>
                        </div>

                        <div>
                            <h3 class="mb-2 text-lg font-semibold">
                                Attendance Summary
                            </h3>
                            <div class="space-y-2">
                                <p>
                                    <strong>Total Students:</strong>
                                    {{
                                        selectedClass?.enrolledStudents
                                            ?.length || 0
                                    }}
                                </p>
                                <p>
                                    <strong>Present Today:</strong>
                                    {{ getPresentTodayCount() }}
                                </p>
                                <p>
                                    <strong>Absent Today:</strong>
                                    {{
                                        (selectedClass?.enrolledStudents
                                            ?.length || 0) -
                                        getPresentTodayCount()
                                    }}
                                </p>
                                <p>
                                    <strong>Attendance Rate:</strong>
                                    {{ getAttendanceRate() }}%
                                </p>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-2 text-lg font-semibold">
                            Enrolled Students
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="w-full table-auto border-collapse">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th
                                            class="border border-gray-300 px-4 py-2 text-left font-medium"
                                        >
                                            Name
                                        </th>
                                        <th
                                            class="border border-gray-300 px-4 py-2 text-left font-medium"
                                        >
                                            Student ID
                                        </th>
                                        <th
                                            class="border border-gray-300 px-4 py-2 text-left font-medium"
                                        >
                                            Status Today
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="student in selectedClass?.enrolledStudents"
                                        :key="student.id"
                                        class="hover:bg-gray-50"
                                    >
                                        <td
                                            class="border border-gray-300 px-4 py-2"
                                        >
                                            {{ student.name }}
                                        </td>
                                        <td
                                            class="border border-gray-300 px-4 py-2"
                                        >
                                            {{ student.studentId }}
                                        </td>
                                        <td
                                            class="border border-gray-300 px-4 py-2"
                                        >
                                            <span
                                                :class="
                                                    student.statusToday ===
                                                    'Present'
                                                        ? 'text-green-600'
                                                        : 'text-red-600'
                                                "
                                            >
                                                {{
                                                    student.statusToday ||
                                                    'Not Marked'
                                                }}
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Add/Edit Class Modal -->
            <div
                v-if="showAddClassModal || showEditClassModal"
                class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black"
            >
                <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6">
                    <h2 class="mb-4 text-xl font-bold">
                        {{
                            showEditClassModal ? 'Edit Class' : 'Add New Class'
                        }}
                    </h2>
                    <form @submit.prevent="saveClass" class="space-y-4">
                        <div>
                            <label
                                for="classSubject"
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Subject</label
                            >
                            <input
                                v-model="classForm.subject"
                                type="text"
                                id="classSubject"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                placeholder="Enter subject name"
                            />
                        </div>

                        <div>
                            <label
                                for="classInstructor"
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Instructor</label
                            >
                            <select
                                v-model="classForm.instructor"
                                id="classInstructor"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="">Select Instructor</option>
                                <option value="Mr. Santos">Mr. Santos</option>
                                <option value="Ms. Garcia">Ms. Garcia</option>
                                <option value="Dr. Reyes">Dr. Reyes</option>
                                <option value="Mr. Cruz">Mr. Cruz</option>
                            </select>
                        </div>

                        <div>
                            <label
                                for="classSection"
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Section</label
                            >
                            <input
                                v-model="classForm.section"
                                type="text"
                                id="classSection"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                placeholder="e.g., BSIT 3A"
                            />
                        </div>

                        <div>
                            <label
                                for="classSchedule"
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Schedule</label
                            >
                            <input
                                v-model="classForm.schedule"
                                type="text"
                                id="classSchedule"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                placeholder="e.g., Mon 8-10, Wed 8-10"
                            />
                        </div>

                        <div>
                            <label
                                for="classRoom"
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Room</label
                            >
                            <input
                                v-model="classForm.room"
                                type="text"
                                id="classRoom"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                placeholder="e.g., Lab 1, Room 201"
                            />
                        </div>

                        <div class="flex space-x-4 pt-4">
                            <button
                                type="submit"
                                class="flex-1 rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-600"
                            >
                                {{
                                    showEditClassModal
                                        ? 'Update Class'
                                        : 'Add Class'
                                }}
                            </button>
                            <button
                                type="button"
                                @click="closeModals"
                                class="flex-1 rounded-lg bg-gray-500 px-4 py-2 font-medium text-white transition-colors hover:bg-gray-600"
                            >
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { confirmActionModal } from '@/lib/feedbackModal';

const showAddClassModal = ref(false);
const showEditClassModal = ref(false);
const showClassDetailsModal = ref(false);
const editingClass = ref(null);
const selectedClass = ref(null);

const classes = ref([
    {
        id: 1,
        subject: 'Programming 2',
        instructor: 'Mr. Cruz',
        section: 'BSIT 3A',
        schedule: 'Mon 8-10, Wed 8-10',
        room: 'Lab 1',
        enrolledStudents: [
            {
                id: 1,
                name: 'Juan Cruz',
                studentId: '2023001',
                statusToday: 'Present',
            },
            {
                id: 2,
                name: 'Maria Santos',
                studentId: '2023002',
                statusToday: 'Present',
            },
            {
                id: 3,
                name: 'Pedro Reyes',
                studentId: '2023003',
                statusToday: 'Absent',
            },
        ],
    },
    {
        id: 2,
        subject: 'Database Systems',
        instructor: 'Mr. Santos',
        section: 'BSIT 3A',
        schedule: 'Tue 1-3, Thu 1-3',
        room: 'Room 201',
        enrolledStudents: [
            {
                id: 1,
                name: 'Juan Cruz',
                studentId: '2023001',
                statusToday: 'Present',
            },
            {
                id: 2,
                name: 'Maria Santos',
                studentId: '2023002',
                statusToday: 'Absent',
            },
        ],
    },
    {
        id: 3,
        subject: 'Web Development',
        instructor: 'Ms. Garcia',
        section: 'BSCS 2A',
        schedule: 'Mon 10-12, Wed 10-12',
        room: 'Lab 2',
        enrolledStudents: [
            {
                id: 4,
                name: 'Ana Lopez',
                studentId: '2023004',
                statusToday: 'Present',
            },
            {
                id: 5,
                name: 'Carlos Mendoza',
                studentId: '2023005',
                statusToday: 'Present',
            },
        ],
    },
]);

const classForm = ref({
    subject: '',
    instructor: '',
    section: '',
    schedule: '',
    room: '',
});

// @function editClass: Pinoproseso ang edit class para sa Classes.
// @useIn editClass: resources/js/pages/Shared/Classes/ClassesPage.vue template @click
const editClass = (classItem) => {
    editingClass.value = classItem;
    classForm.value = {
        subject: classItem.subject,
        instructor: classItem.instructor,
        section: classItem.section,
        schedule: classItem.schedule,
        room: classItem.room,
    };
    showEditClassModal.value = true;
};

// @function deleteClass: Tinatanggal ang class sa Classes flow.
// @useIn deleteClass: resources/js/pages/Shared/Classes/ClassesPage.vue template @click
const deleteClass = async (classItem) => {
    const confirmed = await confirmActionModal({
        title: 'Delete class?',
        text: `Are you sure you want to delete the class "${classItem.subject}"?`,
    });
    if (!confirmed) return;

    const index = classes.value.findIndex((c) => c.id === classItem.id);
    if (index > -1) {
        classes.value.splice(index, 1);
    }
};

// @function viewClassDetails: Pinoproseso ang view class details para sa Classes.
// @useIn viewClassDetails: resources/js/pages/Shared/Classes/ClassesPage.vue template @click
const viewClassDetails = (classItem) => {
    selectedClass.value = classItem;
    showClassDetailsModal.value = true;
};

// @function saveClass: Sine-save ang class sa Classes flow.
// @useIn saveClass: resources/js/pages/Shared/Classes/ClassesPage.vue template
const saveClass = () => {
    if (showEditClassModal.value) {
        // Update existing class
        Object.assign(editingClass.value, classForm.value);
    } else {
        // Add new class
        const newClass = {
            id: Date.now(),
            ...classForm.value,
            enrolledStudents: [],
        };
        classes.value.push(newClass);
    }

    closeModals();
};

// @function closeModals: Isinasara ang modals sa Classes flow.
// @useIn closeModals: resources/js/pages/Shared/Classes/ClassesPage.vue template @click
const closeModals = () => {
    showAddClassModal.value = false;
    showEditClassModal.value = false;
    showClassDetailsModal.value = false;
    editingClass.value = null;
    selectedClass.value = null;
    classForm.value = {
        subject: '',
        instructor: '',
        section: '',
        schedule: '',
        room: '',
    };
};

// @function getPresentTodayCount: Kinukuha ang present today count sa Classes flow.
// @useIn getPresentTodayCount: resources/js/pages/Shared/Classes/ClassesPage.vue template
const getPresentTodayCount = () => {
    if (!selectedClass.value?.enrolledStudents) return 0;
    return selectedClass.value.enrolledStudents.filter(
        (student) => student.statusToday === 'Present',
    ).length;
};

// @function getAttendanceRate: Kinukuha ang attendance rate sa Classes flow.
// @useIn getAttendanceRate: resources/js/pages/Shared/Classes/ClassesPage.vue template
const getAttendanceRate = () => {
    if (
        !selectedClass.value?.enrolledStudents ||
        selectedClass.value.enrolledStudents.length === 0
    )
        return 0;
    const presentCount = getPresentTodayCount();
    return Math.round(
        (presentCount / selectedClass.value.enrolledStudents.length) * 100,
    );
};
</script>

<style scoped>
/* Add any custom styles if needed */
</style>
