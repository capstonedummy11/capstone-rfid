<template>
    <div class="w-full">
        <div class="mx-auto max-w-[1400px] px-4 py-6">
            <!-- Header Section -->
            <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h1 class="text-3xl font-bold">Strands Management</h1>
                        <p class="text-sm text-slate-500">
                            View, manage, and organize strand records and
                            information.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="openAddModal"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
                        >
                            Add Strand
                        </button>
                    </div>
                </div>
            </section>

            <!-- Filter Section -->
            <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Search</label
                        >
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Strand code | Strand name | Department"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            @input="onFilterChange"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Status</label
                        >
                        <select
                            v-model="selectedStatus"
                            @change="onFilterChange"
                            class="w-full rounded-md border border-slate-300 px-3 py-2"
                        >
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button
                            @click="resetFilters"
                            class="w-full rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            Reset Filters
                        </button>
                    </div>
                </div>
            </section>

            <!-- Strands Table Section -->
            <section class="rounded-lg bg-white p-6 shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Strand Code
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Strand Name
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Department
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Status
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="strand in filteredStrands"
                                :key="strand.strand_id"
                                class="hover:bg-gray-50"
                            >
                                <td
                                    class="border border-gray-300 px-4 py-3 font-medium"
                                >
                                    {{ strand.strand_code }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ strand.strand_name }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ strand.department }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <span
                                        :class="[
                                            'rounded-md px-2 py-1 text-xs font-medium',
                                            strand.status === 'active'
                                                ? 'bg-green-100 text-green-800'
                                                : 'bg-yellow-100 text-yellow-800',
                                        ]"
                                    >
                                        {{ capitalizeFirst(strand.status) }}
                                    </span>
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="openEditModal(strand)"
                                            class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            @click="deleteStrand(strand)"
                                            class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="filteredStrands.length === 0"
                        class="py-8 text-center text-gray-500"
                    >
                        No strand records found.
                    </div>
                </div>
            </section>

            <!-- Edit/Add Strand Modal -->
            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            >
                <div
                    class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-lg bg-white p-6"
                >
                    <h2 class="mb-4 text-xl font-semibold">
                        {{ isEditing ? 'Edit Strand' : 'Add New Strand' }}
                    </h2>

                    <form @submit.prevent="submitForm" class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Strand ID</label
                                >
                                <input
                                    v-model="form.strand_id"
                                    type="text"
                                    disabled
                                    class="w-full rounded-md border border-slate-300 bg-slate-50 px-3 py-2"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-1 block text-sm font-medium text-slate-700"
                                    >Strand Code *</label
                                >
                                <input
                                    v-model="form.strand_code"
                                    type="text"
                                    placeholder="e.g., ICT"
                                    class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                    required
                                />
                            </div>
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >Strand Name *</label
                            >
                            <input
                                v-model="form.strand_name"
                                type="text"
                                placeholder="e.g., Information and Communications Technology"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                required
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >Department *</label
                            >
                            <input
                                v-model="form.department"
                                type="text"
                                placeholder="e.g., TVL - ICT Department"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                                required
                            />
                        </div>

                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >Status *</label
                            >
                            <select
                                v-model="form.status"
                                class="w-full rounded-md border border-slate-300 px-3 py-2"
                                required
                            >
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="flex justify-end gap-2 border-t pt-4">
                            <button
                                type="button"
                                @click="closeModal"
                                class="rounded-md border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700"
                                :disabled="form.processing"
                            >
                                {{ isEditing ? 'Update Strand' : 'Add Strand' }}
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

interface Strand {
    strand_id: string | number;
    strand_code: string;
    strand_name: string;
    department: string;
    status: 'active' | 'inactive';
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
    strands: {
        type: Array as () => Strand[],
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
const selectedStrand = ref<Strand | null>(null);

const form = useForm({
    strand_id: '',
    strand_code: '',
    strand_name: '',
    department: '',
    status: 'active',
});

const filteredStrands = computed<Strand[]>(() => {
    return (props.strands as Strand[]).filter((strand) => {
        const matchesSearch =
            search.value === '' ||
            [strand.strand_code, strand.strand_name, strand.department].some(
                (v) =>
                    String(v ?? '')
                        .toLowerCase()
                        .includes(search.value.toLowerCase()),
            );

        const matchesStatus =
            selectedStatus.value === '' ||
            strand.status === selectedStatus.value;

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
        `${window.location.pathname}?search=${encodeURIComponent(query.search)}&status=${encodeURIComponent(query.status)}`,
    );
};

const resetFilters = () => {
    search.value = '';
    selectedStatus.value = '';
    window.location.href = window.location.pathname;
};

const openAddModal = () => {
    isEditing.value = false;
    selectedStrand.value = null;
    form.reset();
    form.status = 'active';
    showModal.value = true;
};

const openEditModal = (strand: Strand) => {
    isEditing.value = true;
    selectedStrand.value = strand;
    form.reset();
    form.strand_id = String(strand.strand_id);
    form.strand_code = strand.strand_code;
    form.strand_name = strand.strand_name;
    form.department = strand.department;
    form.status = strand.status;
    showModal.value = true;
};

const closeModal = () => {
    showModal.value = false;
    isEditing.value = false;
    selectedStrand.value = null;
    form.reset();
};

const submitForm = () => {
    if (!form.strand_code || !form.strand_name || !form.department) {
        alert('Please fill in all required fields.');
        return;
    }

    if (isEditing.value) {
        form.put(
            route('admin.strands.update', {
                id: selectedStrand.value?.strand_id,
            }),
            {
                preserveState: true,
                onSuccess: () => {
                    closeModal();
                    window.location.reload();
                },
            },
        );
    } else {
        form.post(route('admin.strands.store'), {
            preserveState: true,
            onSuccess: () => {
                closeModal();
                window.location.reload();
            },
        });
    }
};

const deleteStrand = (strand: Strand) => {
    if (
        !confirm(
            `Are you sure you want to delete ${strand.strand_code} - ${strand.strand_name}?`,
        )
    ) {
        return;
    }

    const deleteForm = useForm({});
    deleteForm.delete(
        route('admin.strands.destroy', { id: strand.strand_id }),
        {
            preserveState: true,
            onSuccess: () => window.location.reload(),
        },
    );
};

const capitalizeFirst = (str: string) => {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1);
};
</script>
