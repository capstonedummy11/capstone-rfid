<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4 py-6">
      <section class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold">RFID Management</h1>
            <p class="text-sm text-slate-500">Assign, clear and audit RFID tags for students and instructors.</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="resetFilters" class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Reset</button>
          </div>
        </div>
      </section>

      <section class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Search</label>
            <input
              v-model="search" type="text" placeholder="Name | ID | RFID"
              class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
              @input="onFilterChange"
            />
          </div>
          <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">Owner Type</label>
            <select v-model="selectedType" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="all">All</option>
              <option value="students">Students</option>
              <option value="instructors">Instructors</option>
            </select>
          </div>
        </div>
      </section>

      <section class="bg-white shadow-lg rounded-lg p-6">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left">Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Role</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Owner ID</th>
                <th class="border border-gray-300 px-4 py-3 text-left">RFID Tag</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Course / Section</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in filteredRows" :key="`${row.type}-${row.id}`" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3">{{ row.name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ row.role }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ row.ownerId }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span v-if="row.rfid" class="text-green-600">{{ row.rfid }}</span>
                  <span v-else class="text-red-600">Not assigned</span>
                </td>
                <td class="border border-gray-300 px-4 py-3">{{ row.course }} / {{ row.section }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="openEditModal(row)" class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700">Edit</button>
                    <button @click="clearRfid(row)" class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600" :disabled="!row.rfid">Clear</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="filteredRows.length === 0" class="text-center py-8 text-gray-500">No records found.</div>
        </div>
      </section>

      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-md rounded-lg bg-white p-6">
          <h2 class="text-xl font-semibold mb-4">Edit RFID Tag</h2>
          <p class="text-sm text-slate-500 mb-4">{{ selectedRow?.name }} ({{ selectedRow?.role }})</p>

          <form @submit.prevent="submitRfid" class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">RFID Tag</label>
              <input v-model="form.rfid_tag" type="text" placeholder="Enter RFID value" class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100" />
            </div>
            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm">Cancel</button>
              <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700" :disabled="form.processing">Save</button>
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

interface RfidRow {
  id: string | number;
  ownerId: string;
  name: string;
  role: string;
  course: string;
  section: string;
  year: string;
  rfid: string;
  type: 'student' | 'instructor';
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
  rfidRows: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({ search: '', type: 'all' }),
  },
});

const search = ref(props.filters.search ?? '');
const selectedType = ref(props.filters.type ?? 'all');
const showModal = ref(false);
const selectedRow = ref<RfidRow | null>(null);

const form = useForm({ rfid_tag: '' });
const clearForm = useForm({});

const filteredRows = computed<RfidRow[]>(() => {
  return (props.rfidRows as RfidRow[]).filter((row) => {
    const matchesSearch = search.value === '' ||
      [row.name, row.ownerId, row.rfid].some(v => String(v ?? '').toLowerCase().includes(search.value.toLowerCase()));

    const matchesType = selectedType.value === 'all' ||
      (selectedType.value === 'students' && row.type === 'student') ||
      (selectedType.value === 'instructors' && row.type === 'instructor');

    return matchesSearch && matchesType;
  });
});

const onFilterChange = () => {
  const query = {
    search: search.value,
    type: selectedType.value,
  };
  // Sync with backend route state for reload
  window.history.replaceState({}, '', `${window.location.pathname}?search=${encodeURIComponent(query.search)}&type=${encodeURIComponent(query.type)}`);
};

const resetFilters = () => {
  search.value = '';
  selectedType.value = 'all';
  onFilterChange();
};

const openEditModal = (row: RfidRow) => {
  selectedRow.value = row;
  form.reset();
  form.rfid_tag = row.rfid ?? '';
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  selectedRow.value = null;
  form.reset();
};

const submitRfid = () => {
  if (!selectedRow.value) return;

  form.put(route('admin.rfid.update', { type: selectedRow.value.type, id: selectedRow.value.id }), {
    preserveState: true,
    onSuccess: () => {
      closeModal();
      window.location.reload();
    },
  });
};

const clearRfid = (row: RfidRow) => {
  if (!confirm(`Clear RFID for ${row.name}?`)) {
    return;
  }

  clearForm.delete(route('admin.rfid.destroy', { type: row.type, id: row.id }), {
    preserveState: true,
    onSuccess: () => window.location.reload(),
  });
};
</script>
