<template>
  <div class="w-full">
    <div class="mx-auto max-w-[1400px] px-4 py-6">
      <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-3xl font-bold">Students Management</h1>
            <p class="text-sm text-slate-500">Manage students using strands, sections, and RFID assignments.</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="openAddModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Add Student</button>
          </div>
        </div>
      </section>

      <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Search</label>
            <input v-model="search" type="text" placeholder="Name | Student No. | Email | RFID" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" @input="onFilterChange" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Strand</label>
            <select v-model="selectedStrand" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Strands</option>
              <option v-for="strand in strandOptions" :key="strand.strand_id" :value="String(strand.strand_id)">{{ strand.strand_code }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Year Level</label>
            <select v-model="selectedYear" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Years</option>
              <option value="1">1st Year</option>
              <option value="2">2nd Year</option>
              <option value="3">3rd Year</option>
              <option value="4">4th Year</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
            <select v-model="selectedStatus" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="graduated">Graduated</option>
              <option value="dropped">Dropped</option>
            </select>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button @click="resetFilters" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset Filters</button>
        </div>
      </section>

      <section class="rounded-lg bg-white p-6 shadow-lg">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left">Student No.</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Email</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Strand</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Section</th>
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
                <td class="border border-gray-300 px-4 py-3">
                  <div class="font-medium">{{ student.strand_code ?? 'N/A' }}</div>
                </td>
                <td class="border border-gray-300 px-4 py-3">{{ student.section_name ?? 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.year_level }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ student.school_year }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span :class="statusClasses(student.status)">{{ capitalizeFirst(student.status) }}</span>
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

          <div v-if="filteredStudents.length === 0" class="py-8 text-center text-gray-500">No student records found.</div>
        </div>
      </section>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-6">
          <h2 class="mb-4 text-xl font-semibold">{{ isEditing ? 'Edit Student' : 'Add New Student' }}</h2>

          <form @submit.prevent="submitForm" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Student Number</label>
                <input v-model="form.student_number" type="text" placeholder="e.g., 2024-001" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Student ID</label>
                <input v-model="form.student_id" type="text" disabled class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2" />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">First Name *</label>
                <input v-model="form.first_name" type="text" placeholder="First name" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Middle Name</label>
                <input v-model="form.middle_name" type="text" placeholder="Middle name" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Last Name *</label>
                <input v-model="form.last_name" type="text" placeholder="Last name" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email *</label>
                <input v-model="form.email" type="email" placeholder="student@email.com" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Phone</label>
                <input v-model="form.phone" type="tel" placeholder="+63 9XX XXXX XXX" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Strand *</label>
                <select v-model="form.strand_id" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Strand</option>
                  <option v-for="strand in strandOptions" :key="strand.strand_id" :value="String(strand.strand_id)">{{ strand.strand_code }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Section *</label>
                <select v-model="form.section_id" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Section</option>
                  <option v-for="section in availableSections" :key="section.section_id" :value="String(section.section_id)">{{ section.label }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Year Level *</label>
                <select v-model="form.year_level" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Year</option>
                  <option value="1">1st Year</option>
                  <option value="2">2nd Year</option>
                  <option value="3">3rd Year</option>
                  <option value="4">4th Year</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Semester *</label>
                <select v-model="form.semester" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="1st Semester">1st Semester</option>
                  <option value="2nd Semester">2nd Semester</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">School Year *</label>
                <input v-model="form.school_year" type="text" placeholder="e.g., 2024-2025" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Status *</label>
                <select v-model="form.status" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="graduated">Graduated</option>
                  <option value="dropped">Dropped</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Gender</label>
                <select v-model="form.gender" class="w-full rounded-md border border-slate-300 px-3 py-2">
                  <option value="">Select Gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">RFID Tag</label>
                <input v-model="form.rfid_tag" type="text" placeholder="RFID tag (if assigned)" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
              </div>
            </div>

            <div class="flex justify-end gap-2 border-t pt-4">
              <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
              <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700" :disabled="form.processing">{{ isEditing ? 'Update Student' : 'Add Student' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

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
  status: 'active' | 'inactive' | 'graduated' | 'dropped';
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
    default: () => ({ search: '', strand: '', year: '', status: '' }),
  },
  strandOptions: {
    type: Array as () => StrandOption[],
    default: () => [],
  },
  sectionOptions: {
    type: Array as () => SectionOption[],
    default: () => [],
  },
});

const search = ref(props.filters.search ?? '');
const selectedStrand = ref(props.filters.strand ?? '');
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
  strand_id: '',
  section_id: '',
  year_level: '',
  semester: '1st Semester',
  school_year: '',
  rfid_tag: '',
  status: 'active',
});

const filteredStudents = computed<Student[]>(() => {
  return (props.students as Student[]).filter((student) => {
    const query = search.value.toLowerCase();
    const matchesSearch = query === '' || [student.first_name, student.last_name, student.student_number, student.email, student.rfid_tag].some((value) => String(value ?? '').toLowerCase().includes(query));
    const matchesStrand = selectedStrand.value === '' || String(student.strand_id) === selectedStrand.value;
    const matchesYear = selectedYear.value === '' || String(student.year_level) === selectedYear.value;
    const matchesStatus = selectedStatus.value === '' || student.status === selectedStatus.value;

    return matchesSearch && matchesStrand && matchesYear && matchesStatus;
  });
});

const availableSections = computed<SectionOption[]>(() => {
  return (props.sectionOptions as SectionOption[]).filter((section) => {
    const matchesStrand = !form.strand_id || String(section.strand_id) === String(form.strand_id);
    return matchesStrand;
  });
});

const onFilterChange = () => {
  router.get(route('admin.students.index'), {
    search: search.value,
    strand: selectedStrand.value,
    year: selectedYear.value,
    status: selectedStatus.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  search.value = '';
  selectedStrand.value = '';
  selectedYear.value = '';
  selectedStatus.value = '';
  onFilterChange();
};

const openAddModal = () => {
  isEditing.value = false;
  selectedStudent.value = null;
  form.reset();
  form.status = 'active';
  form.semester = '1st Semester';
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
  form.strand_id = String(student.strand_id ?? '');
  form.section_id = String(student.section_id ?? '');
  form.year_level = String(student.year_level);
  form.semester = student.semester;
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
  if (!form.first_name || !form.last_name || !form.email || !form.strand_id || !form.section_id || !form.year_level || !form.semester || !form.school_year) {
    alert('Please fill in all required fields.');
    return;
  }

  if (isEditing.value) {
    form.put(route('admin.students.update', { id: selectedStudent.value?.student_id }), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        router.reload({ only: ['students'] });
      },
    });
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

const deleteStudent = (student: Student) => {
  if (!confirm(`Are you sure you want to delete ${student.first_name} ${student.last_name}?`)) {
    return;
  }

  const deleteForm = useForm({});
  deleteForm.delete(route('admin.students.destroy', { id: student.student_id }), {
    preserveState: true,
    onSuccess: () => router.reload({ only: ['students'] }),
  });
};

const capitalizeFirst = (str: string) => str ? str.charAt(0).toUpperCase() + str.slice(1) : '';

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
