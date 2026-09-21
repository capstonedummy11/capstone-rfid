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
            <label class="block text-xs font-medium text-slate-600 mb-1">Strand</label>
            <select v-model="selectedStrand" @change="onFilterChange" class="w-full rounded-md border border-slate-300 px-3 py-2">
              <option value="">All Strands</option>
              <option v-for="strand in props.strands" :key="strand.strand_id" :value="strand.strand_code">
                {{ strand.strand_code }}
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
                <th class="border border-gray-300 px-4 py-3 text-left">Strand</th>
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
                <td class="border border-gray-300 px-4 py-3">{{ instructor.strand_code }}</td>
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
                    <div class="group relative">
                      <button
                        type="button"
                        @click="resetInstructorPassword(instructor)"
                        aria-label="Reset password"
                        class="rounded-md bg-amber-500 p-2 text-white hover:bg-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-300"
                      >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                          <path d="M15.5 7.5a4.5 4.5 0 1 0-2.8 4.15L15 14h2v2h2v2h2v-3.17l-5.02-5.02A4.5 4.5 0 0 0 15.5 7.5Z" stroke-linecap="round" stroke-linejoin="round" />
                          <path d="M8 7.5h.01" stroke-linecap="round" />
                        </svg>
                      </button>
                      <span
                        role="tooltip"
                        class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100"
                      >
                        Reset password
                      </span>
                    </div>
                    <div class="group relative">
                      <button
                        type="button"
                        @click="resetInstructorSecurityQuestions(instructor)"
                        aria-label="Reset security questions"
                        class="rounded-md bg-sky-600 p-2 text-white hover:bg-sky-700 focus:outline-none focus:ring-2 focus:ring-sky-300"
                      >
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                          <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z" stroke-linecap="round" stroke-linejoin="round" />
                          <path d="M9.8 9a2.25 2.25 0 1 1 3.42 1.92c-.75.45-1.22.87-1.22 1.83" stroke-linecap="round" />
                          <path d="M12 16h.01" stroke-linecap="round" />
                        </svg>
                      </button>
                      <span
                        role="tooltip"
                        class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded bg-slate-900 px-2 py-1 text-[11px] font-medium text-white opacity-0 transition-opacity duration-150 group-hover:opacity-100 group-focus-within:opacity-100"
                      >
                        Reset security questions
                      </span>
                    </div>
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
                <label class="block text-sm font-medium text-slate-700 mb-1">Strand *</label>
                <select v-model="form.strand_id" class="w-full rounded-md border border-slate-300 px-3 py-2" required>
                  <option value="">Select Strand</option>
                  <option v-for="strand in props.strands" :key="strand.strand_id" :value="String(strand.strand_id)">
                    {{ strand.strand_code }} - {{ strand.strand_name }}
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

      <!-- Reset password confirmation modal -->
      <div
        v-if="resetConfirmationInstructor"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4"
        role="presentation"
        @click.self="closeResetConfirmation"
      >
        <div
          class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="reset-password-title"
        >
          <h2 id="reset-password-title" class="text-xl font-semibold text-slate-900">Reset Instructor password?</h2>
          <p class="mt-3 text-sm leading-6 text-slate-600">
            Reset {{ resetConfirmationInstructorName }}'s password to <strong>password</strong>? Their active sessions will end,
            and they must create a private password at the next login.
          </p>
          <p v-if="resetForm.errors.reset" class="mt-3 rounded-md bg-rose-50 p-3 text-sm text-rose-700" role="alert">
            {{ resetForm.errors.reset }}
          </p>
          <div class="mt-6 flex justify-end gap-3">
            <button
              type="button"
              class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="resetForm.processing"
              @click="closeResetConfirmation"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-md bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="resetForm.processing"
              @click="confirmResetInstructorPassword"
            >
              {{ resetForm.processing ? 'Resetting...' : 'Reset password' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Reset password success modal -->
      <div v-if="resetSuccessInstructorName" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4" role="presentation">
        <div
          class="w-full max-w-md rounded-lg bg-white p-6 text-center shadow-xl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="reset-password-success-title"
        >
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
          <h2 id="reset-password-success-title" class="mt-4 text-xl font-semibold text-slate-900">Password reset successfully</h2>
          <p class="mt-3 text-sm leading-6 text-slate-600">
            {{ resetSuccessInstructorName }} must create a private password at the next login. Their active sessions have ended.
          </p>
          <button
            type="button"
            class="mt-6 rounded-md bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700"
            @click="resetSuccessInstructorName = ''"
          >
            OK
          </button>
        </div>
      </div>

      <!-- Reset security questions confirmation modal -->
      <div
        v-if="securityQuestionResetInstructor"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4"
        role="presentation"
        @click.self="closeSecurityQuestionResetConfirmation"
      >
        <div
          class="w-full max-w-md rounded-lg bg-white p-6 shadow-xl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="reset-security-questions-title"
        >
          <h2 id="reset-security-questions-title" class="text-xl font-semibold text-slate-900">Reset security questions?</h2>
          <p class="mt-3 text-sm leading-6 text-slate-600">
            Reset {{ securityQuestionResetInstructorName }}'s saved security questions? Their active sessions will end, and they
            must create three new questions at the next verification. Their password will not change.
          </p>
          <p
            v-if="securityQuestionResetForm.errors.reset"
            class="mt-3 rounded-md bg-rose-50 p-3 text-sm text-rose-700"
            role="alert"
          >
            {{ securityQuestionResetForm.errors.reset }}
          </p>
          <div class="mt-6 flex justify-end gap-3">
            <button
              type="button"
              class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="securityQuestionResetForm.processing"
              @click="closeSecurityQuestionResetConfirmation"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-md bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-60"
              :disabled="securityQuestionResetForm.processing"
              @click="confirmResetInstructorSecurityQuestions"
            >
              {{ securityQuestionResetForm.processing ? 'Resetting...' : 'Reset questions' }}
            </button>
          </div>
        </div>
      </div>

      <!-- Reset security questions success modal -->
      <div
        v-if="securityQuestionResetSuccessName"
        class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4"
        role="presentation"
      >
        <div
          class="w-full max-w-md rounded-lg bg-white p-6 text-center shadow-xl"
          role="dialog"
          aria-modal="true"
          aria-labelledby="reset-security-questions-success-title"
        >
          <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true">
            <svg viewBox="0 0 24 24" class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="2.5">
              <path d="m5 12 4 4L19 6" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
          </div>
          <h2 id="reset-security-questions-success-title" class="mt-4 text-xl font-semibold text-slate-900">
            Security questions reset
          </h2>
          <p class="mt-3 text-sm leading-6 text-slate-600">
            {{ securityQuestionResetSuccessName }} must create three new security questions at the next verification. Their active
            sessions have ended.
          </p>
          <button
            type="button"
            class="mt-6 rounded-md bg-emerald-600 px-5 py-2 text-sm font-medium text-white hover:bg-emerald-700"
            @click="securityQuestionResetSuccessName = ''"
          >
            OK
          </button>
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
  strand_id: string | number;
  strand_code: string;
  rfid_tag: string;
  status: 'active' | 'inactive' | 'on_leave';
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
  instructors: {
    type: Array as () => Instructor[],
    default: () => [],
  },
  strands: {
    type: Array as () => Array<{ strand_id: number; strand_code: string; strand_name: string }>,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({ search: '', strand: '', status: '' }),
  },
});

const search = ref(props.filters.search ?? '');
const selectedStrand = ref(props.filters.strand ?? '');
const selectedStatus = ref(props.filters.status ?? '');
const showModal = ref(false);
const isEditing = ref(false);
const selectedInstructor = ref<Instructor | null>(null);
const resetConfirmationInstructor = ref<Instructor | null>(null);
const resetSuccessInstructorName = ref('');
const securityQuestionResetInstructor = ref<Instructor | null>(null);
const securityQuestionResetSuccessName = ref('');

const form = useForm({
  instructor_id: '',
  instructor_number: '',
  first_name: '',
  middle_name: '',
  last_name: '',
  email: '',
  phone: '',
  gender: '',
  strand_id: '',
  rfid_tag: '',
  status: 'active',
});

const resetForm = useForm({});
const securityQuestionResetForm = useForm({});

const resetConfirmationInstructorName = computed(() => {
  const instructor = resetConfirmationInstructor.value;
  return instructor ? `${instructor.first_name} ${instructor.last_name}`.trim() : '';
});

const securityQuestionResetInstructorName = computed(() => {
  const instructor = securityQuestionResetInstructor.value;
  return instructor ? `${instructor.first_name} ${instructor.last_name}`.trim() : '';
});

const filteredInstructors = computed<Instructor[]>(() => {
  return (props.instructors as Instructor[]).filter((instructor) => {
    const matchesSearch = search.value === '' ||
      [instructor.first_name, instructor.last_name, instructor.instructor_number, instructor.email].some(
        (v) => String(v ?? '').toLowerCase().includes(search.value.toLowerCase())
      );

    const matchesStrand = selectedStrand.value === '' ||
      (instructor.strand_code ?? '').toLowerCase() === selectedStrand.value.toLowerCase();

    const matchesStatus = selectedStatus.value === '' ||
      instructor.status === selectedStatus.value;

    return matchesSearch && matchesStrand && matchesStatus;
  });
});

const onFilterChange = () => {
  const query = {
    search: search.value,
    strand: selectedStrand.value,
    status: selectedStatus.value,
  };
  // Sync with backend route state for reload
  window.history.replaceState(
    {},
    '',
    `${window.location.pathname}?search=${encodeURIComponent(query.search)}&strand=${encodeURIComponent(query.strand)}&status=${encodeURIComponent(query.status)}`
  );
};

const resetFilters = () => {
  search.value = '';
  selectedStrand.value = '';
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
  form.strand_id = String(instructor.strand_id);
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
  if (!form.first_name || !form.last_name || !form.email || !form.strand_id || !form.instructor_number) {
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

const resetInstructorPassword = (instructor: Instructor) => {
  resetForm.clearErrors();
  resetConfirmationInstructor.value = instructor;
};

const closeResetConfirmation = () => {
  if (resetForm.processing) return;
  resetConfirmationInstructor.value = null;
};

const confirmResetInstructorPassword = () => {
  const instructor = resetConfirmationInstructor.value;
  if (!instructor || resetForm.processing) return;

  resetForm.put(route('admin.instructors.password.reset-default', { id: instructor.instructor_id }), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      resetSuccessInstructorName.value = `${instructor.first_name} ${instructor.last_name}`.trim();
      resetConfirmationInstructor.value = null;
    },
    onError: (errors) => {
      resetForm.setError('reset', Object.values(errors).flat().join(', ') || 'The password could not be reset. Please try again.');
    },
  });
};

const resetInstructorSecurityQuestions = (instructor: Instructor) => {
  securityQuestionResetForm.clearErrors();
  securityQuestionResetInstructor.value = instructor;
};

const closeSecurityQuestionResetConfirmation = () => {
  if (securityQuestionResetForm.processing) return;
  securityQuestionResetInstructor.value = null;
};

const confirmResetInstructorSecurityQuestions = () => {
  const instructor = securityQuestionResetInstructor.value;
  if (!instructor || securityQuestionResetForm.processing) return;

  securityQuestionResetForm.put(route('admin.instructors.security-questions.reset', { id: instructor.instructor_id }), {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      securityQuestionResetSuccessName.value = `${instructor.first_name} ${instructor.last_name}`.trim();
      securityQuestionResetInstructor.value = null;
    },
    onError: (errors) => {
      securityQuestionResetForm.setError(
        'reset',
        Object.values(errors).flat().join(', ') || 'The security questions could not be reset. Please try again.',
      );
    },
  });
};

const capitalizeFirst = (str: string) => {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1).replace('_', ' ');
};
</script>
