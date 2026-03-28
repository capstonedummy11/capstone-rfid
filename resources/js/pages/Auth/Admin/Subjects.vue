<template>
  <div class="w-full">
    <div class="mx-auto max-w-[1400px] px-4 py-6">
      <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-3xl font-bold">Subjects Management</h1>
            <p class="text-sm text-slate-500">Manage subjects, assigned section, instructor, and semester.</p>
          </div>
          <button @click="openAddModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Add Subject</button>
        </div>
      </section>

      <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Search</label>
            <input
              v-model="search"
              type="text"
              placeholder="Subject name | code | department"
              class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              @input="onFilterChange"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Semester</label>
            <select v-model="selectedSemester" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Semesters</option>
              <option value="1st Semester">1st Semester</option>
              <option value="2nd Semester">2nd Semester</option>
            </select>
          </div>
          <div class="flex items-end justify-end">
            <button @click="resetFilters" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset Filters</button>
          </div>
        </div>
      </section>

      <section class="rounded-lg bg-white p-6 shadow-lg">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left">Subject Code</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Subject Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Description</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Department</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Unit</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Semester</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Section</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Instructor</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="subject in props.subjects" :key="subject.subject_id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ subject.subject_code }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ subject.subject_name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ subject.subject_description || 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ subject.department || 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ subject.unit }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ subject.semester || 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ subject.section_name || 'Unassigned' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ subject.user_name || 'Unassigned' }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="openEditModal(subject)" class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700">Edit</button>
                    <button @click="deleteSubject(subject)" class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="props.subjects.length === 0" class="py-8 text-center text-gray-500">No subject records found.</div>
        </div>
      </section>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-6">
          <h2 class="mb-4 text-xl font-semibold">{{ isEditing ? 'Edit Subject' : 'Add New Subject' }}</h2>

          <form @submit.prevent="submitForm" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Subject Code *</label>
                <input v-model="form.subject_code" type="text" placeholder="e.g., CP1" class="w-full rounded-md border border-slate-300 px-3 py-2" required />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Subject Name *</label>
                <input v-model="form.subject_name" type="text" placeholder="e.g., Computer Programming 1" class="w-full rounded-md border border-slate-300 px-3 py-2" required />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
              <textarea v-model="form.subject_description" rows="3" placeholder="e.g., Introduction to programming concepts using variables, conditions, loops, and functions." class="w-full rounded-md border border-slate-300 px-3 py-2" />
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Department</label>
                <input v-model="form.department" type="text" placeholder="e.g., ICT" class="w-full rounded-md border border-slate-300 px-3 py-2" />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Unit *</label>
                <input v-model.number="form.unit" type="number" min="0" placeholder="e.g., 3" class="w-full rounded-md border border-slate-300 px-3 py-2" required />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Semester</label>
                <select v-model="form.semester" class="w-full rounded-md border border-slate-300 px-3 py-2">
                  <option value="">Select Semester</option>
                  <option value="1st Semester">1st Semester</option>
                  <option value="2nd Semester">2nd Semester</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Section</label>
                <select v-model="form.section_id" class="w-full rounded-md border border-slate-300 px-3 py-2">
                  <option value="">Unassigned</option>
                  <option v-for="section in props.sectionOptions" :key="section.section_id" :value="String(section.section_id)">{{ section.section_name }}</option>
                </select>
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Instructor</label>
              <select v-model="form.user_id" class="w-full rounded-md border border-slate-300 px-3 py-2">
                <option value="">Unassigned</option>
                <option v-for="instructor in instructors" :key="instructor.user_id" :value="String(instructor.user_id)">{{ instructor.name }}</option>
              </select>
            </div>

            <div class="flex justify-end gap-2 border-t pt-4">
              <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
              <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700" :disabled="form.processing">{{ isEditing ? 'Update Subject' : 'Add Subject' }}</button>
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

interface Subject {
  subject_id: string | number;
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
}

interface SectionOption {
  section_id: string | number;
  section_name: string;
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
});

const search = ref(props.filters.search ?? '');
const selectedSemester = ref(props.filters.semester ?? '');
const showModal = ref(false);
const isEditing = ref(false);
const selectedSubject = ref<Subject | null>(null);

const instructors = computed(() => {
  return (props.instructorOptions as InstructorOption[]).filter((instructor) => {
    const role = String(instructor.role ?? '').toLowerCase();
    return role === '' || role === 'instructor' || role === 'teacher';
  });
});

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

const onFilterChange = () => {
  router.get(route('admin.subjects.index'), {
    search: search.value,
    semester: selectedSemester.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  search.value = '';
  selectedSemester.value = '';
  onFilterChange();
};

const openAddModal = () => {
  isEditing.value = false;
  selectedSubject.value = null;
  form.reset();
  form.unit = 0;
  showModal.value = true;
};

const openEditModal = (subject: Subject) => {
  isEditing.value = true;
  selectedSubject.value = subject;
  form.reset();
  form.section_id = subject.section_id ? String(subject.section_id) : '';
  form.user_id = subject.user_id ? String(subject.user_id) : '';
  form.subject_name = subject.subject_name;
  form.subject_code = subject.subject_code;
  form.subject_description = subject.subject_description ?? '';
  form.department = subject.department ?? '';
  form.unit = Number(subject.unit ?? 0);
  form.semester = subject.semester ?? '';
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  isEditing.value = false;
  selectedSubject.value = null;
  form.reset();
};

const submitForm = () => {
  if (!form.subject_name || !form.subject_code || form.unit === null || form.unit === undefined || Number(form.unit) < 0) {
    alert('Please fill in all required fields and provide a valid unit value.');
    return;
  }

  const payload = {
    ...form.data(),
    section_id: form.section_id === '' ? null : Number(form.section_id),
    user_id: form.user_id === '' ? null : Number(form.user_id),
    unit: Number(form.unit),
    semester: form.semester === '' ? null : form.semester,
    department: form.department === '' ? null : form.department,
    subject_description: form.subject_description === '' ? null : form.subject_description,
  };

  if (isEditing.value && selectedSubject.value) {
    form.transform(() => payload).put(route('admin.subjects.update', { id: selectedSubject.value.subject_id }), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        router.reload({ only: ['subjects'] });
      },
    });
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

const deleteSubject = (subject: Subject) => {
  if (!confirm(`Are you sure you want to delete ${subject.subject_code}?`)) {
    return;
  }

  const deleteForm = useForm({});
  deleteForm.delete(route('admin.subjects.destroy', { id: subject.subject_id }), {
    preserveState: true,
    onSuccess: () => router.reload({ only: ['subjects'] }),
  });
};
</script>