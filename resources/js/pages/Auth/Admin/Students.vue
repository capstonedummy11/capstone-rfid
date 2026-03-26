<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4 py-6">
      <!-- Header Section -->
      <section class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold">Students Management</h1>
            <p class="text-sm text-slate-500">View, manage, and organize student records and information.</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="openAddModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Add Student</button>
          </div>
        </div>
      </section>

      <!-- Filter Section -->
      <section class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
            <input
              v-model="search"
              type="text"
              placeholder="Name | Student ID | Email"
              class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              @input="onFilterChange"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Course</label>
            <select v-model="selectedCourse" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Courses</option>
              <option value="BSIT">BSIT</option>
              <option value="BSCS">BSCS</option>
              <option value="BSIS">BSIS</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Year Level</label>
            <select v-model="selectedYear" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Years</option>
              <option value="1">1st Year</option>
              <option value="2">2nd Year</option>
              <option value="3">3rd Year</option>
              <option value="4">4th Year</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select v-model="selectedStatus" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="graduated">Graduated</option>
            </select>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button @click="resetFilters" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset Filters</button>
        </div>
      </section>

      <!-- Students Table Section -->
      <section class="bg-white shadow-lg rounded-lg p-6">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left">Student ID</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Email</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Course</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Year</th>
                <th class="border border-gray-300 px-4 py-3 text-left">School Year</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Status</th>
                <th class="border border-gray-300 px-4 py-3 text-left">RFID Tag</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="student in filteredStudents" :key="student.student_id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ student.student_number }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.first_name }} {{ student.last_name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.email }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.course_code }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.year_level }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.school_year }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span
                    :class="[
                      'px-2 py-1 rounded-md text-xs font-medium',
                      student.status === 'active'
                        ? 'bg-green-100 text-green-800'
                        : student.status === 'inactive'
                        ? 'bg-yellow-100 text-yellow-800'
                        : 'bg-gray-100 text-gray-800',
                    ]"
                  >
                    {{ capitalizeFirst(student.status) }}
                  </span>
                </td>
                <td class="border border-gray-300 px-4 py-3">
                  <span v-if="student.rfid_tag" class="text-green-600">{{ student.rfid_tag }}</span>
                  <span v-else class="text-red-600">Not assigned</span>
                </td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="openEditModal(student)" class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700">Edit</button>
                    <button @click="deleteStudent(student)" class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="filteredStudents.length === 0" class="text-center py-8 text-gray-500">No student records found.</div>
        </div>
      </section>

      <!-- Edit/Add Student Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-2xl rounded-lg bg-white p-6 max-h-[90vh] overflow-y-auto">
          <h2 class="text-xl font-semibold mb-4">{{ isEditing ? 'Edit Student' : 'Add New Student' }}</h2>

          <form @submit.prevent="submitForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Student Number</label>
                <input
                  v-model="form.student_number"
                  type="text"
                  placeholder="e.g., 2024-001"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Student ID</label>
                <input v-model="form.student_id" type="text" disabled class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2" />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">First Name *</label>
                <input
                  v-model="form.first_name"
                  type="text"
                  placeholder="First name"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Middle Name</label>
                <input
                  v-model="form.middle_name"
                  type="text"
                  placeholder="Middle name"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Last Name *</label>
                <input
                  v-model="form.last_name"
                  type="text"
                  placeholder="Last name"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  required
                />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email *</label>
                <input
                  v-model="form.email"
                  type="email"
                  placeholder="student@email.com"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Phone</label>
                <input
                  v-model="form.phone"
                  type="tel"
                  placeholder="+63 9XX XXXX XXX"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Course *</label>
                <select v-model="form.course_id" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Course</option>
                  <option value="1">BSIT</option>
                  <option value="2">BSCS</option>
                  <option value="3">BSIS</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Section *</label>
                <select v-model="form.section_id" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Section</option>
                  <option value="1">A</option>
                  <option value="2">B</option>
                  <option value="3">C</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Year Level *</label>
                <select v-model="form.year_level" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Year</option>
                  <option value="1">1st Year</option>
                  <option value="2">2nd Year</option>
                  <option value="3">3rd Year</option>
                  <option value="4">4th Year</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Semester *</label>
                <select v-model="form.semester" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="1">1st Semester</option>
                  <option value="2">2nd Semester</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">School Year *</label>
                <input
                  v-model="form.school_year"
                  type="text"
                  placeholder="e.g., 2024-2025"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Status *</label>
                <select v-model="form.status" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="graduated">Graduated</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Gender</label>
                <select v-model="form.gender" class="w-full rounded-md border border-slate-300 px-3 py-2">
                  <option value="">Select Gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">RFID Tag</label>
                <input
                  v-model="form.rfid_tag"
                  type="text"
                  placeholder="RFID tag (if assigned)"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                />
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t">
              <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
              <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700" :disabled="form.processing">
                {{ isEditing ? 'Update Student' : 'Add Student' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface Student {
  student_id: string | number;
  student_number: string;
  first_name: string;
  middle_name: string;
  last_name: string;
  email: string;
  phone: string;
  gender: string;
  course_id: string | number;
  course_code: string;
  section_id: string | number;
  year_level: string | number;
  semester: string | number;
  school_year: string;
  rfid_tag: string;
  status: 'active' | 'inactive' | 'graduated';
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
  students: {
    type: Array as () => Student[],
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({ search: '', course: '', year: '', status: '' }),
  },
});

const search = ref(props.filters.search ?? '');
const selectedCourse = ref(props.filters.course ?? '');
const selectedYear = ref(props.filters.year ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const showModal = ref(false);
const isEditing = ref(false);
const selectedStudent = ref<Student | null>(null);

const form = useForm({
  student_id: '',
  student_number: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  email: '',
  phone: '',
  gender: '',
  course_id: '',
  section_id: '',
  year_level: '',
  semester: '1',
  school_year: '',
  rfid_tag: '',
  status: 'active',
});

const filteredStudents = computed<Student[]>(() => {
  return (props.students as Student[]).filter((student) => {
    const matchesSearch = search.value === '' ||
      [student.first_name, student.last_name, student.student_number, student.email].some(
        (v) => String(v ?? '').toLowerCase().includes(search.value.toLowerCase())
      );

    const matchesCourse = selectedCourse.value === '' ||
      (student.course_code ?? '').toLowerCase() === selectedCourse.value.toLowerCase();

    const matchesYear = selectedYear.value === '' ||
      String(student.year_level) === selectedYear.value;

    const matchesStatus = selectedStatus.value === '' ||
      student.status === selectedStatus.value;

    return matchesSearch && matchesCourse && matchesYear && matchesStatus;
  });
});

const onFilterChange = () => {
  const query = {
    search: search.value,
    course: selectedCourse.value,
    year: selectedYear.value,
    status: selectedStatus.value,
  };
  // Sync with backend route state for reload
  window.history.replaceState(
    {},
    '',
    `${window.location.pathname}?search=${encodeURIComponent(query.search)}&course=${encodeURIComponent(query.course)}&year=${encodeURIComponent(query.year)}&status=${encodeURIComponent(query.status)}`
  );
};

const resetFilters = () => {
  search.value = '';
  selectedCourse.value = '';
  selectedYear.value = '';
  selectedStatus.value = '';
  window.location.href = window.location.pathname;
};

const openAddModal = () => {
  isEditing.value = false;
  selectedStudent.value = null;
  form.reset();
  form.status = 'active';
  form.semester = '1';
  form.school_year = '';
  showModal.value = true;
};

const openEditModal = (student: Student) => {
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
  form.course_id = String(student.course_id);
  form.section_id = String(student.section_id);
  form.year_level = String(student.year_level);
  form.semester = String(student.semester);
  form.school_year = student.school_year;
  form.rfid_tag = student.rfid_tag;
  form.status = student.status;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  isEditing.value = false;
  selectedStudent.value = null;
  form.reset();
};

const submitForm = () => {
  if (!form.first_name || !form.last_name || !form.email || !form.course_id || !form.section_id || !form.year_level || !form.semester || !form.school_year) {
    alert('Please fill in all required fields.');
    return;
  }

  if (isEditing.value) {
    form.put(route('admin.students.update', { id: selectedStudent.value?.student_id }), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        window.location.reload();
      },
    });
  } else {
    form.post(route('admin.students.store'), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        window.location.reload();
      },
    });
  }
};

const deleteStudent = (student: Student) => {
  if (!confirm(`Are you sure you want to delete ${student.first_name} ${student.last_name}?`)) {
    return;
  }

  const deleteForm = useForm({});
  deleteForm.delete(route('admin.students.destroy', { id: student.student_id }), {
    preserveState: true,
    onSuccess: () => window.location.reload(),
  });
};

const capitalizeFirst = (str: string) => {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
};
</script>
