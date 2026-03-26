<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4 py-6">
      <!-- Header Section -->
      <section class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold">Sections Management</h1>
            <p class="text-sm text-slate-500">View, manage, and organize section records and information.</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="resetFilters" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</button>
            <button @click="openAddModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Add Section</button>
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
              placeholder="Section name"
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
            </select>
          </div>
        </div>
      </section>

      <!-- Sections Table Section -->
      <section class="bg-white shadow-lg rounded-lg p-6">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left">Section Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Course</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Year Level</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Semester</th>
                <th class="border border-gray-300 px-4 py-3 text-left">School Year</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Status</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="section in filteredSections" :key="section.section_id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ section.section_name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ section.course_code }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ getYearLabel(section.year_level) }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ getSemesterLabel(section.semester) }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ section.school_year }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span
                    :class="[
                      'px-2 py-1 rounded-md text-xs font-medium',
                      section.status === 'active'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-yellow-100 text-yellow-800',
                    ]"
                  >
                    {{ capitalizeFirst(section.status) }}
                  </span>
                </td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="openEditModal(section)" class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700">Edit</button>
                    <button @click="deleteSection(section)" class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="filteredSections.length === 0" class="text-center py-8 text-gray-500">No section records found.</div>
        </div>
      </section>

      <!-- Edit/Add Section Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-2xl rounded-lg bg-white p-6 max-h-[90vh] overflow-y-auto">
          <h2 class="text-xl font-semibold mb-4">{{ isEditing ? 'Edit Section' : 'Add New Section' }}</h2>

          <form @submit.prevent="submitForm" class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Section ID</label>
                <input v-model="form.section_id" type="text" disabled class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2" />
              </div>
              <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Section Name *</label>
                <input
                  v-model="form.section_name"
                  type="text"
                  placeholder="e.g., Section A"
                  class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                  required
                />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
                  <option value="1">1st</option>
                  <option value="2">2nd</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                </select>
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t">
              <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
              <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700" :disabled="form.processing">
                {{ isEditing ? 'Update Section' : 'Add Section' }}
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

interface Section {
  section_id: string | number;
  section_name: string;
  course_id: string | number;
  course_code: string;
  year_level: string | number;
  semester: string | number;
  school_year: string;
  status: 'active' | 'inactive';
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
  sections: {
    type: Array as () => Section[],
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
const selectedSection = ref<Section | null>(null);

const form = useForm({
  section_id: '',
  section_name: '',
  course_id: '',
  year_level: '',
  semester: '1',
  school_year: '',
  status: 'active',
});

const filteredSections = computed<Section[]>(() => {
  return (props.sections as Section[]).filter((section) => {
    const matchesSearch = search.value === '' ||
      String(section.section_name ?? '').toLowerCase().includes(search.value.toLowerCase());

    const matchesCourse = selectedCourse.value === '' ||
      (section.course_code ?? '').toLowerCase() === selectedCourse.value.toLowerCase();

    const matchesYear = selectedYear.value === '' ||
      String(section.year_level) === selectedYear.value;

    const matchesStatus = selectedStatus.value === '' ||
      section.status === selectedStatus.value;

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
  onFilterChange();
};

const openAddModal = () => {
  isEditing.value = false;
  selectedSection.value = null;
  form.reset();
  form.status = 'active';
  form.semester = '1';
  form.school_year = '';
  showModal.value = true;
};

const openEditModal = (section: Section) => {
  isEditing.value = true;
  selectedSection.value = section;
  form.reset();
  form.section_id = String(section.section_id);
  form.section_name = section.section_name;
  form.course_id = String(section.course_id);
  form.year_level = String(section.year_level);
  form.semester = String(section.semester);
  form.school_year = section.school_year;
  form.status = section.status;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  isEditing.value = false;
  selectedSection.value = null;
  form.reset();
};

const submitForm = () => {
  if (!form.section_name || !form.course_id || !form.year_level || !form.semester || !form.school_year) {
    alert('Please fill in all required fields.');
    return;
  }

  if (isEditing.value) {
    form.put(route('admin.sections.update', { id: selectedSection.value?.section_id }), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        window.location.reload();
      },
    });
  } else {
    form.post(route('admin.sections.store'), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        window.location.reload();
      },
    });
  }
};

const deleteSection = (section: Section) => {
  if (!confirm(`Are you sure you want to delete ${section.section_name}?`)) {
    return;
  }

  const deleteForm = useForm({});
  deleteForm.delete(route('admin.sections.destroy', { id: section.section_id }), {
    preserveState: true,
    onSuccess: () => window.location.reload(),
  });
};

const capitalizeFirst = (str: string) => {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
};

const getYearLabel = (year: string | number) => {
  const yearMap: Record<string | number, string> = {
    '1': '1st Year',
    '2': '2nd Year',
    '3': '3rd Year',
    '4': '4th Year',
  };
  return yearMap[year] || String(year);
};

const getSemesterLabel = (semester: string | number) => {
  const semesterMap: Record<string | number, string> = {
    '1': '1st',
    '2': '2nd',
  };
  return semesterMap[semester] || String(semester);
};
</script>
