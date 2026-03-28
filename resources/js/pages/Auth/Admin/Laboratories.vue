<template>
  <div class="w-full">
    <div class="max-w-[1400px] mx-auto px-4 py-6">
      <!-- Header Section -->
      <section class="bg-white shadow-lg rounded-lg p-6 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
          <div>
            <h1 class="text-3xl font-bold">Laboratories Management</h1>
            <p class="text-sm text-slate-500">View, manage, and organize laboratory records and information.</p>
          </div>
          <div class="flex items-center gap-2">
            <button @click="openAddModal" class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700">Add Laboratory</button>
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
              placeholder="Laboratory name | Description | Location"
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

      <!-- Laboratories Table Section -->
      <section class="bg-white shadow-lg rounded-lg p-6">
        <div class="overflow-x-auto">
          <table class="w-full table-auto border-collapse">
            <thead>
              <tr class="bg-gray-50">
                <th class="border border-gray-300 px-4 py-3 text-left">Name</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Description</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Location</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Status</th>
                <th class="border border-gray-300 px-4 py-3 text-left">Actions</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="laboratory in filteredLaboratories" :key="laboratory.laboratory_id" class="hover:bg-gray-50">
                <td class="border border-gray-300 px-4 py-3 font-medium">{{ laboratory.name }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ laboratory.description || '-' }}</td>
                <td class="border border-gray-300 px-4 py-3">{{ laboratory.location }}</td>
                <td class="border border-gray-300 px-4 py-3">
                  <span
                    :class="[
                      'px-2 py-1 rounded-md text-xs font-medium',
                      laboratory.status === 'active'
                        ? 'bg-green-100 text-green-800'
                        : 'bg-yellow-100 text-yellow-800',
                    ]"
                  >
                    {{ capitalizeFirst(laboratory.status) }}
                  </span>
                </td>
                <td class="border border-gray-300 px-4 py-3">
                  <div class="flex items-center gap-2">
                    <button @click="openEditModal(laboratory)" class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700">Edit</button>
                    <button @click="deleteLaboratory(laboratory)" class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600">Delete</button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>

          <div v-if="filteredLaboratories.length === 0" class="text-center py-8 text-gray-500">No laboratory records found.</div>
        </div>
      </section>

      <!-- Edit/Add Laboratory Modal -->
      <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
        <div class="w-full max-w-2xl rounded-lg bg-white p-6 max-h-[90vh] overflow-y-auto">
          <h2 class="text-xl font-semibold mb-4">{{ isEditing ? 'Edit Laboratory' : 'Add New Laboratory' }}</h2>

          <form @submit.prevent="submitForm" class="space-y-4">
            <div v-if="Object.keys(form.errors).length > 0" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
              Please review the form fields below.
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Laboratory Name *</label>
              <input
                v-model="form.name"
                type="text"
                placeholder="e.g., Computer Lab 1"
                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                required
              />
              <p v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Description</label>
              <textarea
                v-model="form.description"
                placeholder="e.g., Main laboratory for general IT strands"
                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                rows="3"
              ></textarea>
              <p v-if="form.errors.description" class="mt-1 text-xs text-red-600">{{ form.errors.description }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Location *</label>
              <input
                v-model="form.location"
                type="text"
                placeholder="e.g., Building A, 2nd Floor, Room 201"
                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                required
              />
              <p v-if="form.errors.location" class="mt-1 text-xs text-red-600">{{ form.errors.location }}</p>
            </div>

            <div>
              <label class="block text-sm font-medium text-slate-700 mb-1">Status *</label>
              <select v-model="form.status" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
              <p v-if="form.errors.status" class="mt-1 text-xs text-red-600">{{ form.errors.status }}</p>
            </div>

            <div class="flex justify-end gap-2 pt-4 border-t">
              <button type="button" @click="closeModal" class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancel</button>
              <button type="submit" class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700" :disabled="form.processing">
                {{ isEditing ? 'Update Laboratory' : 'Add Laboratory' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

interface Laboratory {
  laboratory_id: string | number;
  name: string;
  description: string | null;
  location: string;
  status: 'active' | 'inactive';
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
  laboratories: {
    type: Array as () => Laboratory[],
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
const selectedLaboratory = ref<Laboratory | null>(null);

const form = useForm({
  name: '',
  description: '',
  location: '',
  status: 'active',
});

const filteredLaboratories = computed<Laboratory[]>(() => {
  return (props.laboratories as Laboratory[]).filter((laboratory) => {
    const matchesSearch = search.value === '' ||
      [laboratory.name, laboratory.description, laboratory.location].some(
        (v) => String(v ?? '').toLowerCase().includes(search.value.toLowerCase())
      );

    const matchesStatus = selectedStatus.value === '' ||
      laboratory.status === selectedStatus.value;

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
  selectedLaboratory.value = null;
  form.reset();
  form.status = 'active';
  showModal.value = true;
};

const openEditModal = (laboratory: Laboratory) => {
  isEditing.value = true;
  selectedLaboratory.value = laboratory;
  form.reset();
  form.name = laboratory.name;
  form.description = laboratory.description || '';
  form.location = laboratory.location;
  form.status = laboratory.status;
  showModal.value = true;
};

const closeModal = () => {
  showModal.value = false;
  isEditing.value = false;
  selectedLaboratory.value = null;
  form.reset();
};

const submitForm = () => {
  if (!form.name || !form.location) {
    alert('Please fill in all required fields.');
    return;
  }

  if (isEditing.value) {
    form.put(route('admin.laboratories.update', { id: selectedLaboratory.value?.laboratory_id }), {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        closeModal();
        router.reload({ only: ['laboratories'] });
      },
    });
  } else {
    form.post(route('admin.laboratories.store'), {
      preserveState: true,
      preserveScroll: true,
      onSuccess: () => {
        closeModal();
        router.reload({ only: ['laboratories'] });
      },
    });
  }
};

const deleteLaboratory = (laboratory: Laboratory) => {
  if (!confirm(`Are you sure you want to delete ${laboratory.name}?`)) {
    return;
  }

  const deleteForm = useForm({});
  deleteForm.delete(route('admin.laboratories.destroy', { id: laboratory.laboratory_id }), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => router.reload({ only: ['laboratories'] }),
  });
};

const capitalizeFirst = (str: string) => {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
};
</script>

