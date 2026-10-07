<template>
    <div class="w-full">
        <div class="mx-auto max-w-[1400px] px-4">
            <!-- Header -->
            <div class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div class="flex items-center justify-between">
                    <h1 class="text-3xl font-bold">Students Management</h1>
                    <div class="flex space-x-4">
                        <button
                            @click="showAddStudentModal = true"
                            class="rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-600"
                        >
                            Add Student
                        </button>
                        <button
                            class="rounded-lg bg-green-500 px-4 py-2 font-medium text-white transition-colors hover:bg-green-600"
                        >
                            Import Students
                        </button>
                    </div>
                </div>
            </div>

            <!-- Search and Filters -->
            <div class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label
                            for="search"
                            class="mb-1 block text-sm font-medium text-gray-700"
                            >Search by Name</label
                        >
                        <input
                            v-model="searchQuery"
                            type="text"
                            id="search"
                            placeholder="Enter student name..."
                            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        />
                    </div>
                    <div>
                        <label
                            for="strandFilter"
                            class="mb-1 block text-sm font-medium text-gray-700"
                            >Filter by Strand</label
                        >
                        <select
                            v-model="strandFilter"
                            id="strandFilter"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <option value="">All Strands</option>
                            <option value="BSIT">BSIT</option>
                            <option value="BSCS">BSCS</option>
                            <option value="BSIS">BSIS</option>
                        </select>
                    </div>
                    <div>
                        <label
                            for="yearFilter"
                            class="mb-1 block text-sm font-medium text-gray-700"
                            >Filter by Year</label
                        >
                        <select
                            v-model="yearFilter"
                            id="yearFilter"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                        >
                            <option value="">All Years</option>
                            <option value="1">1st Year</option>
                            <option value="2">2nd Year</option>
                            <option value="3">3rd Year</option>
                            <option value="4">4th Year</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Students Table -->
            <div class="rounded-lg bg-white p-6 shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left font-medium"
                                >
                                    Name
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left font-medium"
                                >
                                    Student ID
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left font-medium"
                                >
                                    Strand
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left font-medium"
                                >
                                    RFID
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
                                v-for="student in filteredStudents"
                                :key="student.id"
                                class="hover:bg-gray-50"
                            >
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ student.name }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ student.studentId }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ student.strand }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <span
                                        v-if="student.rfid"
                                        class="text-green-600"
                                        >{{ student.rfid }}</span
                                    >
                                    <span v-else class="text-red-600"
                                        >Not Assigned</span
                                    >
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div class="flex space-x-2">
                                        <button
                                            @click="editStudent(student)"
                                            class="rounded bg-yellow-500 px-3 py-1 text-sm text-white transition-colors hover:bg-yellow-600"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="deleteStudent(student)"
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

                <!-- No students message -->
                <div
                    v-if="filteredStudents.length === 0"
                    class="py-8 text-center text-gray-500"
                >
                    <p>No students found matching your criteria.</p>
                </div>
            </div>

            <!-- Add/Edit Student Modal -->
            <div
                v-if="showAddStudentModal || showEditStudentModal"
                class="bg-opacity-50 fixed inset-0 z-50 flex items-center justify-center bg-black"
            >
                <div class="mx-4 w-full max-w-md rounded-lg bg-white p-6">
                    <h2 class="mb-4 text-xl font-bold">
                        {{
                            showEditStudentModal
                                ? 'Edit Student'
                                : 'Add New Student'
                        }}
                    </h2>
                    <form @submit.prevent="saveStudent" class="space-y-4">
                        <div>
                            <label
                                for="studentName"
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Name</label
                            >
                            <input
                                v-model="studentForm.name"
                                type="text"
                                id="studentName"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                placeholder="Enter student name"
                            />
                        </div>

                        <div>
                            <label
                                for="studentId"
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Student ID</label
                            >
                            <input
                                v-model="studentForm.studentId"
                                type="text"
                                id="studentId"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                placeholder="Enter student ID"
                            />
                        </div>

                        <div>
                            <label
                                for="studentStrand"
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Strand</label
                            >
                            <select
                                v-model="studentForm.strand"
                                id="studentStrand"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="">Select Strand</option>
                                <option value="BSIT">BSIT</option>
                                <option value="BSCS">BSCS</option>
                                <option value="BSIS">BSIS</option>
                            </select>
                        </div>

                        <div>
                            <label
                                for="studentYear"
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Year Level</label
                            >
                            <select
                                v-model="studentForm.year"
                                id="studentYear"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                            >
                                <option value="">Select Year</option>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>

                        <div>
                            <label
                                for="studentSection"
                                class="mb-1 block text-sm font-medium text-gray-700"
                                >Section</label
                            >
                            <input
                                v-model="studentForm.section"
                                type="text"
                                id="studentSection"
                                required
                                class="w-full rounded-md border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                                placeholder="Enter section"
                            />
                        </div>

                        <div class="flex space-x-4 pt-4">
                            <button
                                type="submit"
                                class="flex-1 rounded-lg bg-blue-500 px-4 py-2 font-medium text-white transition-colors hover:bg-blue-600"
                            >
                                {{
                                    showEditStudentModal
                                        ? 'Update Student'
                                        : 'Add Student'
                                }}
                            </button>
                            <button
                                type="button"
                                @click="closeModal"
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
import { ref, computed } from 'vue';
import { confirmActionModal } from '@/lib/feedbackModal';

const searchQuery = ref('');
const strandFilter = ref('');
const yearFilter = ref('');
const showAddStudentModal = ref(false);
const showEditStudentModal = ref(false);
const editingStudent = ref(null);

const students = ref([
    {
        id: 1,
        name: 'Juan Cruz',
        studentId: '2023001',
        strand: 'BSIT',
        year: '3',
        section: '3A',
        rfid: 'RFID200',
    },
    {
        id: 2,
        name: 'Maria Santos',
        studentId: '2023002',
        strand: 'BSCS',
        year: '2',
        section: '2B',
        rfid: 'RFID201',
    },
    {
        id: 3,
        name: 'Pedro Reyes',
        studentId: '2023003',
        strand: 'BSIT',
        year: '1',
        section: '1A',
        rfid: null,
    },
]);

const studentForm = ref({
    name: '',
    studentId: '',
    strand: '',
    year: '',
    section: '',
});

const filteredStudents = computed(() => {
    return students.value.filter((student) => {
        const matchesSearch = student.name
            .toLowerCase()
            .includes(searchQuery.value.toLowerCase());
        const matchesStrand =
            !strandFilter.value || student.strand === strandFilter.value;
        const matchesYear =
            !yearFilter.value || student.year === yearFilter.value;

        return matchesSearch && matchesStrand && matchesYear;
    });
});

// @function editStudent: Pinoproseso ang edit student para sa Students Management.
// @useIn editStudent: resources/js/pages/StudentsManagement.vue template @click
const editStudent = (student) => {
    editingStudent.value = student;
    studentForm.value = {
        name: student.name,
        studentId: student.studentId,
        strand: student.strand,
        year: student.year,
        section: student.section,
    };
    showEditStudentModal.value = true;
};

// @function deleteStudent: Tinatanggal ang student sa Students Management flow.
// @useIn deleteStudent: resources/js/pages/StudentsManagement.vue template @click
const deleteStudent = async (student) => {
    const confirmed = await confirmActionModal({
        title: 'Delete student?',
        text: `Are you sure you want to delete ${student.name}?`,
    });
    if (!confirmed) return;

    const index = students.value.findIndex((s) => s.id === student.id);
    if (index > -1) {
        students.value.splice(index, 1);
    }
};

// @function saveStudent: Sine-save ang student sa Students Management flow.
// @useIn saveStudent: resources/js/pages/StudentsManagement.vue template
const saveStudent = () => {
    if (showEditStudentModal.value) {
        // Update existing student
        Object.assign(editingStudent.value, studentForm.value);
    } else {
        // Add new student
        const newStudent = {
            id: Date.now(),
            ...studentForm.value,
            rfid: null,
        };
        students.value.push(newStudent);
    }

    closeModal();
};

// @function closeModal: Isinasara ang modal sa Students Management flow.
// @useIn closeModal: resources/js/pages/StudentsManagement.vue template @click
const closeModal = () => {
    showAddStudentModal.value = false;
    showEditStudentModal.value = false;
    editingStudent.value = null;
    studentForm.value = {
        name: '',
        studentId: '',
        strand: '',
        year: '',
        section: '',
    };
};
</script>

<style scoped>
/* Add any custom styles if needed */
</style>
