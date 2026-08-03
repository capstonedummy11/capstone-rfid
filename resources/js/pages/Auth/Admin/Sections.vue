<template>
  <div class="w-full">
    <div class="mx-auto max-w-[1400px] px-4 py-6">
      <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-3xl font-bold">Sections Management</h1>
            <p class="text-sm text-slate-500">Manage sections with strand alignment.</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="openAddModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Add Section</button>
          </div>
        </div>
      </section>

      <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-5">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Academic Year</label>
            <select v-model="selectedAcademicYear" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Academic Years</option>
              <option v-for="year in academicYearOptions" :key="year.academic_year_id" :value="String(year.academic_year_id)">{{ year.name }} ({{ year.status }})</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Search</label>
            <input v-model="search" type="text" placeholder="Section name" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" @input="onFilterChange" />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Strand</label>
            <select v-model="selectedStrand" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Strands</option>
              <option v-for="strand in strandOptions" :key="strand.strand_id" :value="String(strand.strand_id)">{{ strand.strand_code }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Grade</label>
            <select v-model="selectedYear" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Grades</option>
              <option value="11">Grade 11</option>
              <option value="12">Grade 12</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Status</label>
            <select v-model="selectedStatus" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Status</option>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
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
                <th class="border border-gray-300 px-4 py-3 text-left">Section Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Strand</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Grade</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Semester</th>
                <th class="border border-gray-300 px-4 py-3 text-left">School Year</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Status</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="section in filteredSections" :key="section.section_id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ section.section_name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ section.strand_code ?? 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ getYearLabel(section.year_level) }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ section.semester }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ section.school_year }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span :class="statusClasses(section.status)">{{ capitalizeFirst(section.status) }}</span>
                </td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button v-if="section.is_writable" @click="openEditModal(section)" class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700">Edit</button>
                    <button v-if="section.is_writable" @click="deleteSection(section)" class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600">Delete</button>
                    <span v-if="!section.is_writable" class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-500">Locked: {{ section.academic_year_status }}</span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="filteredSections.length === 0" class="py-8 text-center text-gray-500">No section records found.</div>
        </div>
      </section>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-lg bg-white p-6">
          <h2 class="mb-4 text-xl font-semibold">{{ isEditing ? 'Edit Section' : 'Add New Section' }}</h2>

          <form @submit.prevent="submitForm" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Section ID</label>
                <input v-model="form.section_id" type="text" disabled class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Section Name *</label>
                <input v-model="form.section_name" type="text" placeholder="e.g., Section A" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required />
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Strand *</label>
                <select v-model="form.strand_id" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Strand</option>
                  <option v-for="strand in strandOptions" :key="strand.strand_id" :value="String(strand.strand_id)">{{ strand.strand_code }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Grade *</label>
                <select v-model="form.year_level" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Grade</option>
                  <option value="11">Grade 11</option>
                  <option value="12">Grade 12</option>
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

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">School Year *</label>
                <select v-model="form.school_year" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select School Year</option>
                  <option v-for="schoolYear in schoolYearOptions" :key="schoolYear" :value="schoolYear">{{ schoolYear }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Status *</label>
                <select v-model="form.status" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>
            </div>

            <div class="flex justify-end gap-2 border-t pt-4">
              <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
              <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700" :disabled="form.processing">{{ isEditing ? 'Update Section' : 'Add Section' }}</button>
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

interface Section {
  section_id: string | number;
  section_name: string;
  strand_id: string | number;
  strand_code: string;
  year_level: string | number;
  semester: string;
  school_year: string;
  status: 'active' | 'inactive';
  academic_year_id?: string | number | null;
  academic_year_status?: string | null;
  is_writable: boolean;
}

interface StrandOption {
  strand_id: string | number;
  strand_code: string;
  strand_name: string;
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
  sections: {
    type: Array as () => Section[],
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({ search: '', strand: '', year: '', status: '', academic_year: '' }),
  },
  strandOptions: {
    type: Array as () => StrandOption[],
    default: () => [],
  },
  academicYearOptions: {
    type: Array as () => Array<{ academic_year_id: string | number; name: string; status: string }>,
    default: () => [],
  },
  activeAcademicYearId: {
    type: Number,
    default: null,
  },
});

const search = ref(props.filters.search ?? '');
const selectedStrand = ref(props.filters.strand ?? '');
const selectedYear = ref(props.filters.year ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const selectedAcademicYear = ref(props.filters.academic_year ?? (props.activeAcademicYearId ? String(props.activeAcademicYearId) : ''));
const showModal = ref(false);
const isEditing = ref(false);
const selectedSection = ref<Section | null>(null);
const defaultSchoolYearOptions = Array.from({ length: 6 }, (_, index) => {
  const startYear = 2025 + index;
  return `${startYear}-${startYear + 1}`;
});

const form = useForm({
  section_id: '',
  section_name: '',
  strand_id: '',
  year_level: '',
  semester: '1st Semester',
  school_year: '',
  status: 'active',
});

const filteredSections = computed<Section[]>(() => {
  return (props.sections as Section[]).filter((section) => {
    const matchesSearch = search.value === '' || String(section.section_name ?? '').toLowerCase().includes(search.value.toLowerCase());
    const matchesStrand = selectedStrand.value === '' || String(section.strand_id) === selectedStrand.value;
    const matchesYear = selectedYear.value === '' || String(section.year_level) === selectedYear.value;
    const matchesStatus = selectedStatus.value === '' || section.status === selectedStatus.value;
    const matchesAcademicYear = selectedAcademicYear.value === '' || String(section.academic_year_id ?? '') === selectedAcademicYear.value;

    return matchesSearch && matchesStrand && matchesYear && matchesStatus && matchesAcademicYear;
  });
});

const schoolYearOptions = computed(() => {
  return Array.from(
    new Set([
      ...defaultSchoolYearOptions,
      ...props.academicYearOptions.map((year) => year.name),
      ...(props.sections as Section[]).map((section) => section.school_year),
    ].filter(Boolean)),
  ).sort();
});

const onFilterChange = () => {
  router.get(route('admin.sections.index'), {
    search: search.value,
    strand: selectedStrand.value,
    year: selectedYear.value,
    status: selectedStatus.value,
    academic_year: selectedAcademicYear.value,
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
  selectedAcademicYear.value = '';
  onFilterChange();
};

const openAddModal = () => {
  isEditing.value = false;
  selectedSection.value = null;
  form.reset();
  form.status = 'active';
  form.semester = '1st Semester';
  showModal.value = true;
};

const openEditModal = (section: Section) => {
  isEditing.value = true;
  selectedSection.value = section;
  form.reset();
  form.section_id = String(section.section_id);
  form.section_name = section.section_name;
  form.strand_id = String(section.strand_id ?? '');
  form.year_level = String(section.year_level);
  form.semester = section.semester;
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
  if (!form.section_name || !form.strand_id || !form.year_level || !form.semester || !form.school_year) {
    alert('Please fill in all required fields.');
    return;
  }

  if (isEditing.value) {
    form.put(route('admin.sections.update', { id: selectedSection.value?.section_id }), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        router.reload({ only: ['sections'] });
      },
    });
  } else {
    form.post(route('admin.sections.store'), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        router.reload({ only: ['sections'] });
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
    onSuccess: () => router.reload({ only: ['sections'] }),
  });
};

const capitalizeFirst = (str: string) => str ? str.charAt(0).toUpperCase() + str.slice(1) : '';

const getYearLabel = (year: string | number) => {
  const yearMap: Record<string | number, string> = {
    '11': 'Grade 11',
    '12': 'Grade 12',
  };
  return yearMap[year] || String(year);
};

const statusClasses = (status: string) => [
  'rounded-md px-2 py-1 text-xs font-medium',
  status === 'active' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800',
];
</script>
