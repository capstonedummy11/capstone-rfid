<template>
  <div class="flex h-[calc(100vh-64px)] overflow-hidden bg-slate-50">
    <!-- Left sidebar: Laboratory navigation -->
    <aside class="flex w-60 shrink-0 flex-col border-r border-slate-200 bg-white shadow-sm">
      <div class="border-b border-slate-100 px-4 py-4">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Laboratories</h2>
      </div>
      <nav class="flex-1 overflow-y-auto py-2">
        <button
          @click="selectLaboratory(null)"
          :class="[
            'flex w-full items-center gap-2 px-4 py-2.5 text-left text-sm transition-colors',
            selectedLaboratoryId === null
              ? 'bg-blue-50 font-semibold text-blue-700'
              : 'text-slate-700 hover:bg-slate-50',
          ]"
        >
          All Laboratories
        </button>
        <button
          v-for="lab in props.laboratories"
          :key="lab.laboratory_id"
          @click="selectLaboratory(lab.laboratory_id)"
          :class="[
            'flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left text-sm transition-colors',
            selectedLaboratoryId === lab.laboratory_id
              ? 'bg-blue-50 font-semibold text-blue-700'
              : 'text-slate-700 hover:bg-slate-50',
          ]"
        >
          <span class="truncate">{{ lab.name }}</span>
          <span
            v-if="lab.status"
            :class="[
              'shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium',
              lab.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500',
            ]"
          >{{ lab.status }}</span>
        </button>
        <div v-if="props.laboratories.length === 0" class="px-4 py-4 text-xs text-slate-400">No laboratories found.</div>
      </nav>
    </aside>

    <!-- Right main: timetable -->
    <div class="flex flex-1 flex-col overflow-hidden">
      <!-- Top bar -->
      <div class="flex items-center justify-between border-b border-slate-200 bg-white px-6 py-4 shadow-sm">
        <div>
          <h1 class="text-xl font-bold text-slate-800">{{ selectedLaboratory ? selectedLaboratory.name : 'All Laboratories' }}</h1>
          <p class="text-xs text-slate-400">Weekly schedule overview</p>
        </div>
        <button
          @click="openAddModal"
          :disabled="selectedLaboratoryId === null"
          :title="selectedLaboratoryId === null ? 'Select a laboratory first' : 'Add schedule'"
          class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white transition hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-40"
        >+ Add Schedule</button>
      </div>

      <!-- Timetable -->
      <div class="flex-1 overflow-auto p-4">
        <div class="min-w-[760px] overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
          <table class="w-full table-fixed border-collapse text-sm">
            <colgroup>
              <col class="w-24" />
              <col v-for="d in days" :key="d.key" />
            </colgroup>
            <thead>
              <tr class="bg-slate-50">
                <th class="border-b border-r border-slate-200 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-500">Time</th>
                <th
                  v-for="d in days"
                  :key="d.key"
                  class="border-b border-r border-slate-200 px-3 py-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-600 last:border-r-0"
                >{{ d.label }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in grid" :key="row.slot" class="group">
                <td class="border-b border-r border-slate-100 bg-slate-50 px-3 py-2 text-center text-xs font-medium text-slate-500 align-top">{{ formatSlot(row.slot) }}</td>
                <template v-for="(cell, dayIdx) in row.cells" :key="dayIdx">
                  <td
                    v-if="cell.type !== 'occupied'"
                    :rowspan="cell.rowspan || 1"
                    :class="[
                      'border-b border-r border-slate-100 p-1 align-top last:border-r-0',
                      cell.type === 'empty' ? 'bg-white group-hover:bg-slate-50/60' : '',
                    ]"
                  >
                    <div
                      v-if="cell.type === 'start' && cell.schedule"
                      class="flex h-full w-full cursor-pointer flex-col gap-0.5 overflow-hidden rounded-md border border-blue-200 bg-blue-50 px-2 py-1.5 transition hover:border-blue-400 hover:bg-blue-100"
                      @click="openEditModal(cell.schedule)"
                    >
                      <span class="truncate text-[11px] font-semibold text-blue-800 leading-tight">{{ cell.schedule.subject_code || 'No subject' }}</span>
                      <span class="truncate text-[10px] text-blue-600 leading-tight">{{ cell.schedule.section_name || '-' }}</span>
                      <span class="truncate text-[10px] text-slate-500 leading-tight">{{ cell.schedule.instructor_name || '-' }}</span>
                      <span class="mt-auto text-[9px] text-slate-400 leading-tight">{{ normalizeTime(cell.schedule.time_start) }} - {{ normalizeTime(cell.schedule.time_end) }}</span>
                    </div>
                  </td>
                </template>
              </tr>
            </tbody>
          </table>
          <div v-if="filteredSchedules.length === 0" class="py-12 text-center text-slate-400">
            {{ selectedLaboratoryId ? 'No schedules for this laboratory.' : 'Select a laboratory to view its schedule.' }}
          </div>
        </div>
      </div>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="closeModal">
      <div class="w-full max-w-lg overflow-hidden rounded-xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-slate-800">{{ isEditing ? 'Edit Schedule' : 'Add Schedule' }}</h2>
            <p class="text-xs text-slate-400">Laboratory: <span class="font-medium text-slate-600">{{ selectedLaboratory?.name ?? 'â€”' }}</span></p>
          </div>
          <button @click="closeModal" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
          </button>
        </div>
        <form @submit.prevent="submitForm" class="space-y-4 px-6 py-5">
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Instructor</label>
            <select v-model="form.instructor_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100">
              <option value="">Select instructor</option>
              <option v-for="inst in props.instructorOptions" :key="inst.instructor_id" :value="String(inst.instructor_id)">{{ inst.name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Subject <span class="text-rose-500">*</span></label>
            <select v-model="form.subject_code" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>
              <option value="">Select subject</option>
              <option v-for="sub in props.subjectOptions" :key="sub.subject_code" :value="sub.subject_code">{{ sub.subject_code }} - {{ sub.subject_name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Section <span class="text-rose-500">*</span></label>
            <select v-model="form.section_id" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required>
              <option value="">Select section</option>
              <option v-for="sec in props.sectionOptions" :key="sec.section_id" :value="String(sec.section_id)">{{ sec.section_name }}</option>
            </select>
          </div>
          <div>
            <label class="mb-2 block text-sm font-medium text-slate-700">Weekdays <span class="text-rose-500">*</span></label>
            <div class="flex flex-wrap gap-2">
              <label
                v-for="d in days"
                :key="d.key"
                :class="[
                  'flex cursor-pointer select-none items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition',
                  selectedWeekdays.includes(d.key)
                    ? 'border-blue-500 bg-blue-600 text-white'
                    : 'border-slate-300 bg-white text-slate-600 hover:border-blue-400',
                ]"
              >
                <input type="checkbox" :value="d.key" v-model="selectedWeekdays" class="sr-only" />
                {{ d.label }}
              </label>
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">Start Time <span class="text-rose-500">*</span></label>
              <input v-model="form.time_start" type="time" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required />
            </div>
            <div>
              <label class="mb-1 block text-sm font-medium text-slate-700">End Time <span class="text-rose-500">*</span></label>
              <input v-model="form.time_end" type="time" class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100" required />
            </div>
          </div>
          <div class="flex justify-end gap-3 border-t border-slate-100 pt-4">
            <button v-if="isEditing" type="button" @click="deleteSchedule(selectedSchedule!)" class="mr-auto rounded-md border border-rose-200 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50">Delete</button>
            <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="submit" class="rounded-md bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50" :disabled="form.processing">{{ isEditing ? 'Update' : 'Save Schedule' }}</button>
          </div>
        </form>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

// â”€â”€â”€ Types â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

interface Schedule {
  scheduled_id: string | number;
  laboratory_id: string | number | null;
  laboratory_name: string | null;
  instructor_id: string | number | null;
  instructor_name: string | null;
  section_id: string | number;
  section_name: string | null;
  subject_code: string;
  subject_name: string | null;
  weekdays: string;
  time_start: string;
  time_end: string;
  room: string | null;
  timestamp: string | null;
}

interface Laboratory {
  laboratory_id: number;
  name: string;
  status: string | null;
}

interface SectionOption {
  section_id: string | number;
  section_name: string;
}

interface SubjectOption {
  subject_code: string;
  subject_name: string;
}

interface InstructorOption {
  instructor_id: string | number;
  name: string;
}

declare function route(name: string, params?: Record<string, unknown>): string;

// â”€â”€â”€ Props â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const props = defineProps({
  schedules: { type: Array as () => Schedule[], default: () => [] },
  filters: { type: Object, default: () => ({ laboratory_id: null }) },
  laboratories: { type: Array as () => Laboratory[], default: () => [] },
  sectionOptions: { type: Array as () => SectionOption[], default: () => [] },
  subjectOptions: { type: Array as () => SubjectOption[], default: () => [] },
  instructorOptions: { type: Array as () => InstructorOption[], default: () => [] },
});

// â”€â”€â”€ Days / time config â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const days = [
  { key: 'Mon', label: 'Monday' },
  { key: 'Tue', label: 'Tuesday' },
  { key: 'Wed', label: 'Wednesday' },
  { key: 'Thu', label: 'Thursday' },
  { key: 'Fri', label: 'Friday' },
  { key: 'Sat', label: 'Saturday' },
];

const timeSlots: string[] = Array.from({ length: 14 }, (_, i) => {
  const h = 7 + i;
  return `${String(h).padStart(2, '0')}:00`;
});

// â”€â”€â”€ Sidebar state â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const selectedLaboratoryId = ref<number | null>(props.filters.laboratory_id ?? null);

const selectedLaboratory = computed(() =>
  selectedLaboratoryId.value === null
    ? null
    : (props.laboratories.find((l) => l.laboratory_id === selectedLaboratoryId.value) ?? null),
);

const selectLaboratory = (id: number | null) => {
  selectedLaboratoryId.value = id;
  router.get(
    route('admin.schedules.index'),
    id !== null ? { laboratory_id: id } : {},
    { preserveState: true, preserveScroll: true, replace: true },
  );
};

// â”€â”€â”€ Filtered schedules â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const filteredSchedules = computed(() =>
  selectedLaboratoryId.value === null
    ? props.schedules
    : props.schedules.filter((s) => Number(s.laboratory_id) === selectedLaboratoryId.value),
);

// â”€â”€â”€ Timetable grid â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const parseMinutes = (t: string): number => {
  const parts = t.split(':');
  return parseInt(parts[0] ?? '0') * 60 + parseInt(parts[1] ?? '0');
};

const normalizeTime = (t: string | null | undefined): string => String(t ?? '').slice(0, 5);

const formatSlot = (slot: string): string => {
  const [h] = slot.split(':');
  const hour = parseInt(h ?? '0');
  const ampm = hour < 12 ? 'AM' : 'PM';
  const display = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour;
  return `${display}:00 ${ampm}`;
};

type Cell =
  | { type: 'empty' }
  | { type: 'start'; schedule: Schedule; rowspan: number }
  | { type: 'occupied' };

const grid = computed(() => {
  return timeSlots.map((slot) => {
    const slotMins = parseMinutes(slot);
    const cells: Cell[] = days.map((day) => {
      const schedule = filteredSchedules.value.find((s) => {
        const wDays = s.weekdays.split(/[,\-\/\s]+/).map((d) => d.trim()).filter(Boolean);
        const startMins = parseMinutes(normalizeTime(s.time_start));
        const endMins = parseMinutes(normalizeTime(s.time_end));
        return (
          wDays.some((d) => d.toLowerCase() === day.key.toLowerCase()) &&
          startMins <= slotMins &&
          endMins > slotMins
        );
      });
      if (!schedule) return { type: 'empty' as const };
      const startMins = parseMinutes(normalizeTime(schedule.time_start));
      if (startMins === slotMins) {
        const endMins = parseMinutes(normalizeTime(schedule.time_end));
        const rowspan = Math.max(1, Math.ceil((endMins - startMins) / 60));
        return { type: 'start' as const, schedule, rowspan };
      }
      return { type: 'occupied' as const };
    });
    return { slot, cells };
  });
});

// â”€â”€â”€ Modal state â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

const showModal = ref(false);
const isEditing = ref(false);
const selectedSchedule = ref<Schedule | null>(null);
const selectedWeekdays = ref<string[]>([]);

const form = useForm({
  laboratory_id: '' as string | number,
  instructor_id: '' as string,
  section_id: '' as string,
  subject_code: '' as string,
  weekdays: '' as string,
  time_start: '' as string,
  time_end: '' as string,
  room: '' as string,
});

const openAddModal = () => {
  if (selectedLaboratoryId.value === null) return;
  isEditing.value = false;
  selectedSchedule.value = null;
  selectedWeekdays.value = [];
  form.reset();
  form.laboratory_id = selectedLaboratoryId.value;
  showModal.value = true;
};

const openEditModal = (schedule: Schedule) => {
  isEditing.value = true;
  selectedSchedule.value = schedule;
  selectedWeekdays.value = schedule.weekdays.split(/[,\-\/\s]+/).map((d) => d.trim()).filter(Boolean);
  form.reset();
  form.laboratory_id = schedule.laboratory_id ?? '';
  form.instructor_id = schedule.instructor_id ? String(schedule.instructor_id) : '';
  form.section_id = String(schedule.section_id);
  form.subject_code = schedule.subject_code;
  form.weekdays = schedule.weekdays;
  form.time_start = normalizeTime(schedule.time_start);
  form.time_end = normalizeTime(schedule.time_end);
  form.room = schedule.room ?? '';
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  isEditing.value = false;
  selectedSchedule.value = null;
  selectedWeekdays.value = [];
  form.reset();
};

const submitForm = () => {
  if (selectedWeekdays.value.length === 0) {
    alert('Please select at least one weekday.');
    return;
  }
  if (!form.subject_code || !form.section_id) {
    alert('Please fill in all required fields.');
    return;
  }
  if (form.time_end <= form.time_start) {
    alert('End time must be later than start time.');
    return;
  }

  const payload = {
    laboratory_id: form.laboratory_id || null,
    instructor_id: form.instructor_id ? Number(form.instructor_id) : null,
    section_id: Number(form.section_id),
    subject_code: form.subject_code,
    weekdays: selectedWeekdays.value.join(','),
    time_start: form.time_start,
    time_end: form.time_end,
    room: selectedLaboratory.value?.name ?? form.room,
  };

  if (isEditing.value && selectedSchedule.value) {
    form.transform(() => payload).put(route('admin.schedules.update', { id: selectedSchedule.value!.scheduled_id }), {
      preserveState: true,
      onSuccess: () => { closeModal(); router.reload({ only: ['schedules'] }); },
    });
    return;
  }

  form.transform(() => payload).post(route('admin.schedules.store'), {
    preserveState: true,
    onSuccess: () => { closeModal(); router.reload({ only: ['schedules'] }); },
  });
};

const deleteSchedule = (schedule: Schedule) => {
  if (!confirm('Delete this schedule? This cannot be undone.')) return;
  const deleteForm = useForm({});
  deleteForm.delete(route('admin.schedules.destroy', { id: schedule.scheduled_id }), {
    preserveState: true,
    onSuccess: () => { closeModal(); router.reload({ only: ['schedules'] }); },
  });
};
</script>

