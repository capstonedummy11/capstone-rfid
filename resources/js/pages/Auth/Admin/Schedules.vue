<template>
  <div class="w-full">
    <div class="mx-auto max-w-[1400px] px-4 py-6">
      <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
          <div>
            <h1 class="text-3xl font-bold">Schedules Management</h1>
            <p class="text-sm text-slate-500">Manage section schedules, rooms, weekdays, and subject assignments.</p>
          </div>
          <button @click="openAddModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Add Schedule</button>
        </div>
      </section>

      <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Search</label>
            <input
              v-model="search"
              type="text"
              placeholder="Weekdays | room | subject code"
              class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              @input="onFilterChange"
            />
          </div>
          <div>
            <label class="mb-1 block text-xs font-medium text-slate-600">Room</label>
            <select v-model="selectedRoom" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Rooms</option>
              <option v-for="room in props.roomOptions" :key="room" :value="room">{{ room }}</option>
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
                <th class="border border-gray-300 px-4 py-3 text-left">Section</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Subject Code</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Subject Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Weekdays</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Time</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Room</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Updated</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="schedule in props.schedules" :key="schedule.scheduled_id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ schedule.section_name || 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ schedule.subject_code }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ schedule.subject_name || 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ schedule.weekdays }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ formatTimeRange(schedule.time_start, schedule.time_end) }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ schedule.room }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ schedule.timestamp || 'N/A' }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="openEditModal(schedule)" class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700">Edit</button>
                    <button @click="deleteSchedule(schedule)" class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="props.schedules.length === 0" class="py-8 text-center text-gray-500">No schedule records found.</div>
        </div>
      </section>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-lg bg-white p-6">
          <h2 class="mb-4 text-xl font-semibold">{{ isEditing ? 'Edit Schedule' : 'Add New Schedule' }}</h2>

          <form @submit.prevent="submitForm" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Section *</label>
                <select v-model="form.section_id" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Section</option>
                  <option v-for="section in props.sectionOptions" :key="section.section_id" :value="String(section.section_id)">{{ section.section_name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Subject *</label>
                <select v-model="form.subject_code" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Subject</option>
                  <option v-for="subject in props.subjectOptions" :key="subject.subject_code" :value="subject.subject_code">
                    {{ subject.subject_code }} - {{ subject.subject_name }}
                  </option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Weekdays *</label>
                <input v-model="form.weekdays" type="text" placeholder="e.g., Mon-Wed-Fri" class="w-full rounded-md border border-slate-300 px-3 py-2" required />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Start Time *</label>
                <input v-model="form.time_start" type="time" class="w-full rounded-md border border-slate-300 px-3 py-2" required />
              </div>
              <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">End Time *</label>
                <input v-model="form.time_end" type="time" class="w-full rounded-md border border-slate-300 px-3 py-2" required />
              </div>
            </div>

            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Room *</label>
              <input v-model="form.room" type="text" placeholder="e.g., Computer Lab 1" class="w-full rounded-md border border-slate-300 px-3 py-2" required />
            </div>

            <div class="flex justify-end gap-2 border-t pt-4">
              <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
              <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700" :disabled="form.processing">{{ isEditing ? 'Update Schedule' : 'Add Schedule' }}</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Schedule {
  scheduled_id: string | number;
  section_id: string | number;
  section_name: string | null;
  subject_code: string;
  subject_name: string | null;
  weekdays: string;
  time_start: string;
  time_end: string;
  room: string;
  timestamp: string | null;
}

interface SectionOption {
  section_id: string | number;
  section_name: string;
}

interface SubjectOption {
  subject_code: string;
  subject_name: string;
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
  schedules: {
    type: Array as () => Schedule[],
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({ search: '', room: '' }),
  },
  sectionOptions: {
    type: Array as () => SectionOption[],
    default: () => [],
  },
  subjectOptions: {
    type: Array as () => SubjectOption[],
    default: () => [],
  },
  roomOptions: {
    type: Array as () => string[],
    default: () => [],
  },
});

const search = ref(props.filters.search ?? '');
const selectedRoom = ref(props.filters.room ?? '');
const showModal = ref(false);
const isEditing = ref(false);
const selectedSchedule = ref<Schedule | null>(null);

const form = useForm({
  section_id: '',
  subject_code: '',
  weekdays: '',
  time_start: '',
  time_end: '',
  room: '',
});

const normalizeTime = (value: string | null | undefined) => {
  if (!value) {
    return '';
  }

  return String(value).slice(0, 5);
};

const formatTimeRange = (start: string, end: string) => {
  const normalizedStart = normalizeTime(start);
  const normalizedEnd = normalizeTime(end);
  return normalizedStart && normalizedEnd ? `${normalizedStart} - ${normalizedEnd}` : 'N/A';
};

const onFilterChange = () => {
  router.get(route('admin.schedules.index'), {
    search: search.value,
    room: selectedRoom.value,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  });
};

const resetFilters = () => {
  search.value = '';
  selectedRoom.value = '';
  onFilterChange();
};

const openAddModal = () => {
  isEditing.value = false;
  selectedSchedule.value = null;
  form.reset();
  showModal.value = true;
};

const openEditModal = (schedule: Schedule) => {
  isEditing.value = true;
  selectedSchedule.value = schedule;
  form.reset();
  form.section_id = String(schedule.section_id);
  form.subject_code = schedule.subject_code;
  form.weekdays = schedule.weekdays;
  form.time_start = normalizeTime(schedule.time_start);
  form.time_end = normalizeTime(schedule.time_end);
  form.room = schedule.room;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  isEditing.value = false;
  selectedSchedule.value = null;
  form.reset();
};

const submitForm = () => {
  if (!form.section_id || !form.subject_code || !form.weekdays || !form.time_start || !form.time_end || !form.room) {
    alert('Please fill in all required fields.');
    return;
  }

  if (form.time_end <= form.time_start) {
    alert('End time must be later than start time.');
    return;
  }

  const payload = {
    ...form.data(),
    section_id: Number(form.section_id),
  };

  if (isEditing.value && selectedSchedule.value) {
    form.transform(() => payload).put(route('admin.schedules.update', { id: selectedSchedule.value.scheduled_id }), {
      preserveState: true,
      onSuccess: () => {
        closeModal();
        router.reload({ only: ['schedules'] });
      },
    });
    return;
  }

  form.transform(() => payload).post(route('admin.schedules.store'), {
    preserveState: true,
    onSuccess: () => {
      closeModal();
      router.reload({ only: ['schedules'] });
    },
  });
};

const deleteSchedule = (schedule: Schedule) => {
  if (!confirm(`Are you sure you want to delete schedule ${schedule.scheduled_id}?`)) {
    return;
  }

  const deleteForm = useForm({});
  deleteForm.delete(route('admin.schedules.destroy', { id: schedule.scheduled_id }), {
    preserveState: true,
    onSuccess: () => router.reload({ only: ['schedules'] }),
  });
};
</script>