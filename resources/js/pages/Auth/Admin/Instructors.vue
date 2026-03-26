<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4 py-6">
      <!-- Header Section -->
      <section class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold">Instructors Management</h1>
            <p class="text-sm text-slate-500">View, manage, and organize instructor records and information.</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="openAddModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Add Instructor</button>
          </div>
        </div>
      </section>

      <!-- Filter Section -->
      <section class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
            <input
              v-model="search"
              type="text"
              placeholder="Name | Employee ID | Email"
              class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              @input="onFilterChange"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Course</label>
            <select v-model="selectedCourse" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Courses</option>
              <option v-for="course in props.courses" :key="course.course_id" :value="course.course_code">
                {{ course.course_code }}
              </option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select v-model="selectedStatus" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
              <option value="on_leave">On Leave</option>
            </select>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <button @click="resetFilters" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset Filters</button>
        </div>
      </section>

      <!-- Instructors Table Section -->
      <section class="bg-white shadow-lg rounded-lg p-6">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left">Employee ID</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Email</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Phone</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Course</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Status</th>
                <th class="border border-gray-300 px-4 py-3 text-left">RFID Tag</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="instructor in filteredInstructors" :key="instructor.instructor_id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ instructor.instructor_number }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ instructor.first_name }} {{ instructor.last_name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ instructor.email }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ instructor.phone || '-' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ instructor.course_code }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span
                    :class="[
                      'px-2 py-1 rounded-md text-xs font-medium',
                      instructor.status === 'active'
                        ? 'bg-green-100 text-green-800'
                        : instructor.status === 'inactive'
                        ? 'bg-yellow-100 text-yellow-800'
                        : 'bg-blue-100 text-blue-800',
                    ]"
                  >
                    {{ capitalizeFirst(instructor.status) }}
                  </span>
                </td>
                <td class="border border-gray-300 px-4 py-3">
                  <span v-if="instructor.rfid_tag" class="text-green-600">{{ instructor.rfid_tag }}</span>
                  <span v-else class="text-red-600">Not assigned</span>
                </td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="openEditModal(instructor)" class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700">Edit</button>
                    <button @click="deleteInstructor(instructor)" class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="filteredInstructors.length === 0" class="text-center py-8 text-gray-500">No instructor records found.</div>
        </div>
      </section>

      <!-- Edit/Add Instructor Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-2xl rounded-lg bg-white p-6 max-h-[90vh] overflow-y-auto">
          <h2 class="text-xl font-semibold mb-4">{{ isEditing ? 'Edit Instructor' : 'Add New Instructor' }}</h2>

          <form @submit.prevent="submitForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Employee Number</label>
                <input
                  v-model="form.instructor_number"
                  type="text"
                  placeholder="e.g., INS-001"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  required
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Instructor ID</label>
                <input v-model="form.instructor_id" type="text" disabled class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2" />
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
                  placeholder="instructor@email.com"
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

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Course *</label>
                <select v-model="form.course_id" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Course</option>
                  <option v-for="course in props.courses" :key="course.course_id" :value="String(course.course_id)">
                    {{ course.course_code }} - {{ course.course_name }}
                  </option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Gender</label>
                <select v-model="form.gender" class="w-full rounded-md border border-slate-300 px-3 py-2">
                  <option value="">Select Gender</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Status *</label>
                <select v-model="form.status" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                  <option value="on_leave">On Leave</option>
                </select>
              </div>
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

            <div class="flex justify-end gap-2 pt-4 border-t">
              <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
              <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700" :disabled="form.processing">
                {{ isEditing ? 'Update Instructor' : 'Add Instructor' }}
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

interface Instructor {
  instructor_id: string | number;
  instructor_number: string;
  first_name: string;
  middle_name: string;
  last_name: string;
  email: string;
  phone: string;
  gender: string;
  course_id: string | number;
  course_code: string;
  rfid_tag: string;
  status: 'active' | 'inactive' | 'on_leave';
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
  instructors: {
    type: Array as () => Instructor[],
    default: () => [],
  },
  courses: {
    type: Array as () => Array<{ course_id: number; course_code: string; course_name: string }>,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({ search: '', course: '', status: '' }),
  },
});

const search = ref(props.filters.search ?? '');
const selectedCourse = ref(props.filters.course ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const showModal = ref(false);
const isEditing = ref(false);
const selectedInstructor = ref<Instructor | null>(null);

const form = useForm({
  instructor_id: '',
  instructor_number: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  email: '',
  phone: '',
  gender: '',
  course_id: '',
  rfid_tag: '',
  status: 'active',
});

const filteredInstructors = computed<Instructor[]>(() => {
  return (props.instructors as Instructor[]).filter((instructor) => {
    const matchesSearch = search.value === '' ||
      [instructor.first_name, instructor.last_name, instructor.instructor_number, instructor.email].some(
        (v) => String(v ?? '').toLowerCase().includes(search.value.toLowerCase())
      );

    const matchesCourse = selectedCourse.value === '' ||
      (instructor.course_code ?? '').toLowerCase() === selectedCourse.value.toLowerCase();

    const matchesStatus = selectedStatus.value === '' ||
      instructor.status === selectedStatus.value;

    return matchesSearch && matchesCourse && matchesStatus;
  });
});

const onFilterChange = () => {
  const query = {
    search: search.value,
    course: selectedCourse.value,
    status: selectedStatus.value,
  };
  // Sync with backend route state for reload
  window.history.replaceState(
    {},
    '',
    `${window.location.pathname}?search=${encodeURIComponent(query.search)}&course=${encodeURIComponent(query.course)}&status=${encodeURIComponent(query.status)}`
  );
};

const resetFilters = () => {
  search.value = '';
  selectedCourse.value = '';
  selectedStatus.value = '';
  window.location.href = window.location.pathname;
};

const openAddModal = () => {
  isEditing.value = false;
  selectedInstructor.value = null;
  form.reset();
  form.status = 'active';
  showModal.value = true;
};

const openEditModal = (instructor: Instructor) => {
  isEditing.value = true;
  selectedInstructor.value = instructor;
  form.reset();
  form.instructor_id = String(instructor.instructor_id);
  form.instructor_number = instructor.instructor_number;
  form.first_name = instructor.first_name;
  form.middle_name = instructor.middle_name;
  form.last_name = instructor.last_name;
  form.email = instructor.email;
  form.phone = instructor.phone;
  form.gender = instructor.gender;
  form.course_id = String(instructor.course_id);
  form.rfid_tag = instructor.rfid_tag;
  form.status = instructor.status;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  isEditing.value = false;
  selectedInstructor.value = null;
  form.reset();
};

const submitForm = () => {
  if (!form.first_name || !form.last_name || !form.email || !form.course_id || !form.instructor_number) {
    alert('Please fill in all required fields.');
    return;
  }

  if (isEditing.value) {
    form.put(route('admin.instructors.update', { id: selectedInstructor.value?.instructor_id }), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        window.location.reload();
      },
      onError: (errors: Record<string, string[]>) => {
        console.error('Validation errors:', errors);
        alert('Error updating instructor: ' + Object.values(errors).flat().join(', '));
      },
    });
  } else {
    form.post(route('admin.instructors.store'), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        window.location.reload();
      },
      onError: (errors: Record<string, string[]>) => {
        console.error('Validation errors:', errors);
        alert('Error adding instructor: ' + Object.values(errors).flat().join(', '));
      },
    });
  }
};

const deleteInstructor = (instructor: Instructor) => {
  if (!confirm(`Are you sure you want to delete ${instructor.first_name} ${instructor.last_name}?`)) {
    return;
  }

  const deleteForm = useForm({});
  deleteForm.delete(route('admin.instructors.destroy', { id: instructor.instructor_id }), {
    preserveState: true,
    onSuccess: () => window.location.reload(),
  });
};

const capitalizeFirst = (str: string) => {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ');
};
</script>
