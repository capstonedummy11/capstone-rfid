<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4 py-6">
      <!-- Header Section -->
      <section class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold">Courses Management</h1>
            <p class="text-sm text-slate-500">View, manage, and organize course records and information.</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="openAddModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Add Course</button>
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
              placeholder="Course code | Course name | Department"
              class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              @input="onFilterChange"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Status</label>
            <select v-model="selectedStatus" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </select>
          </div>
          <div class="flex items-end">
            <button @click="resetFilters" class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset Filters</button>
          </div>
        </div>
      </section>

      <!-- Courses Table Section -->
      <section class="bg-white shadow-lg rounded-lg p-6">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left">Course Code</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Course Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Department</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Status</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="course in filteredCourses" :key="course.course_id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3 font-medium">{{ course.course_code }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ course.course_name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ course.department }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span
                    :class="[
                      'px-2 py-1 rounded-md text-xs font-medium',
                      course.status === 'active'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-yellow-100 text-yellow-800',
                    ]"
                  >
                    {{ capitalizeFirst(course.status) }}
                  </span>
                </td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="openEditModal(course)" class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700">Edit</button>
                    <button @click="deleteCourse(course)" class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="filteredCourses.length === 0" class="text-center py-8 text-gray-500">No course records found.</div>
        </div>
      </section>

      <!-- Edit/Add Course Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-2xl rounded-lg bg-white p-6 max-h-[90vh] overflow-y-auto">
          <h2 class="text-xl font-semibold mb-4">{{ isEditing ? 'Edit Course' : 'Add New Course' }}</h2>

          <form @submit.prevent="submitForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Course ID</label>
                <input v-model="form.course_id" type="text" disabled class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Course Code *</label>
                <input
                  v-model="form.course_code"
                  type="text"
                  placeholder="e.g., BSIT"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  required
                />
              </div>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Course Name *</label>
              <input
                v-model="form.course_name"
                type="text"
                placeholder="e.g., Bachelor of Science in Information Technology"
                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Department *</label>
              <input
                v-model="form.department"
                type="text"
                placeholder="e.g., College of Information Technology"
                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                required
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Status *</label>
              <select v-model="form.status" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t">
              <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
              <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700" :disabled="form.processing">
                {{ isEditing ? 'Update Course' : 'Add Course' }}
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

interface Course {
  course_id: string | number;
  course_code: string;
  course_name: string;
  department: string;
  status: 'active' | 'inactive';
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
  courses: {
    type: Array as () => Course[],
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({ search: '', status: '' }),
  },
});

const search = ref(props.filters.search ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const showModal = ref(false);
const isEditing = ref(false);
const selectedCourse = ref<Course | null>(null);

const form = useForm({
  course_id: '',
  course_code: '',
  course_name: '',
  department: '',
  status: 'active',
});

const filteredCourses = computed<Course[]>(() => {
  return (props.courses as Course[]).filter((course) => {
    const matchesSearch = search.value === '' ||
      [course.course_code, course.course_name, course.department].some(
        (v) => String(v ?? '').toLowerCase().includes(search.value.toLowerCase())
      );

    const matchesStatus = selectedStatus.value === '' ||
      course.status === selectedStatus.value;

    return matchesSearch && matchesStatus;
  });
});

const onFilterChange = () => {
  const query = {
    search: search.value,
    status: selectedStatus.value,
  };
  // Sync with backend route state for reload
  window.history.replaceState(
    {},
    '',
    `${window.location.pathname}?search=${encodeURIComponent(query.search)}&status=${encodeURIComponent(query.status)}`
  );
};

const resetFilters = () => {
  search.value = '';
  selectedStatus.value = '';
  window.location.href = window.location.pathname;
};

const openAddModal = () => {
  isEditing.value = false;
  selectedCourse.value = null;
  form.reset();
  form.status = 'active';
  showModal.value = true;
};

const openEditModal = (course: Course) => {
  isEditing.value = true;
  selectedCourse.value = course;
  form.reset();
  form.course_id = String(course.course_id);
  form.course_code = course.course_code;
  form.course_name = course.course_name;
  form.department = course.department;
  form.status = course.status;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  isEditing.value = false;
  selectedCourse.value = null;
  form.reset();
};

const submitForm = () => {
  if (!form.course_code || !form.course_name || !form.department) {
    alert('Please fill in all required fields.');
    return;
  }

  if (isEditing.value) {
    form.put(route('admin.courses.update', { id: selectedCourse.value?.course_id }), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        window.location.reload();
      },
    });
  } else {
    form.post(route('admin.courses.store'), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        window.location.reload();
      },
    });
  }
};

const deleteCourse = (course: Course) => {
  if (!confirm(`Are you sure you want to delete ${course.course_code} - ${course.course_name}?`)) {
    return;
  }

  const deleteForm = useForm({});
  deleteForm.delete(route('admin.courses.destroy', { id: course.course_id }), {
    preserveState: true,
    onSuccess: () => window.location.reload(),
  });
};

const capitalizeFirst = (str: string) => {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
};
</script>
