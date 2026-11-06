<template>
    <div class="w-full">
        <div class="mx-auto max-w-[1400px] px-4 py-6">
            <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <h1 class="text-3xl font-bold">RFID System</h1>
                        <p class="text-sm text-slate-500">
                            Assign, clear and audit RFID tags for students and
                            instructors.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="resetFilters"
                            class="rounded-md border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            Reset
                        </button>
                        <button
                            @click="openRegisterModal"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm text-white hover:bg-blue-700"
                        >
                            Register RFID
                        </button>
                    </div>
                </div>
            </section>

            <section class="mb-6 rounded-lg bg-white p-6 shadow-lg">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Search</label
                        >
                        <input
                            v-model="search"
                            type="text"
                            placeholder="Name | ID | RFID"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            @input="onFilterChange"
                        />
                    </div>
                    <div>
                        <label
                            class="mb-1 block text-xs font-medium text-slate-600"
                            >Owner Type</label
                        >
                        <select
                            v-model="selectedType"
                            @change="onFilterChange"
                            class="w-full rounded-md border border-slate-300 px-3 py-2"
                        >
                            <option value="all">All</option>
                            <option value="students">Students</option>
                            <option value="instructors">Instructors</option>
                        </select>
                    </div>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow-lg">
                <div class="overflow-x-auto">
                    <table class="w-full table-auto border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Name
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Role
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Owner ID
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    RFID Tag
                                </th>
                                <th
                                    class="border border-gray-300 px-4 py-3 text-left"
                                >
                                    Strand / Section
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
                                v-for="row in filteredRows"
                                :key="`${row.type}-${row.id}`"
                                class="hover:bg-gray-50"
                            >
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ row.name }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ row.role }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ row.ownerId }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <span
                                        v-if="row.rfid"
                                        class="text-green-600"
                                        >{{ row.rfid }}</span
                                    >
                                    <span v-else class="text-red-600"
                                        >Not assigned</span
                                    >
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    {{ row.strand }} / {{ row.section }}
                                </td>
                                <td class="border border-gray-300 px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            @click="openEditModal(row)"
                                            class="rounded-md bg-indigo-600 px-3 py-1 text-sm text-white hover:bg-indigo-700"
                                        >
                                            {{ row.rfid ? 'Edit' : 'Assign' }}
                                        </button>
                                        <button
                                            @click="clearRfid(row)"
                                            class="rounded-md bg-rose-500 px-3 py-1 text-sm text-white hover:bg-rose-600"
                                            :disabled="!row.rfid"
                                        >
                                            Clear
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>

                    <div
                        v-if="filteredRows.length === 0"
                        class="py-8 text-center text-gray-500"
                    >
                        No records found.
                    </div>
                </div>
            </section>

            <div
                v-if="showModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            >
                <div class="w-full max-w-md rounded-lg bg-white p-6">
                    <h2 class="mb-4 text-xl font-semibold">Edit RFID Tag</h2>
                    <p class="mb-4 text-sm text-slate-500">
                        {{ selectedRow?.name }} ({{ selectedRow?.role }})
                    </p>

                    <form @submit.prevent="submitRfid" class="space-y-4">
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >RFID Tag</label
                            >
                            <input
                                v-model="form.rfid_tag"
                                type="text"
                                placeholder="Enter RFID value"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                @click="closeModal"
                                class="rounded-md border border-slate-300 px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700"
                                :disabled="form.processing"
                            >
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div
                v-if="showRegisterModal"
                class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
            >
                <div class="w-full max-w-md rounded-lg bg-white p-6">
                    <h2 class="mb-4 text-xl font-semibold">
                        Register RFID Tag
                    </h2>
                    <form @submit.prevent="submitNewRfid" class="space-y-4">
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >Search owner</label
                            >
                            <input
                                type="text"
                                v-model="ownerSearch"
                                placeholder="Search by name, ID"
                                class="mb-2 w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >Owner</label
                            >
                            <select
                                v-model="registerForm.ownerKey"
                                class="w-full rounded-md border border-slate-300 px-3 py-2"
                                size="6"
                            >
                                <option value="">Select owner</option>
                                <option
                                    v-for="owner in filteredOwners"
                                    :key="`${owner.type}-${owner.id}`"
                                    :value="`${owner.type}|${owner.id}`"
                                >
                                    {{
                                        owner.type === 'student'
                                            ? 'Student'
                                            : 'Instructor'
                                    }}
                                    - {{ owner.name }} ({{ owner.ownerId }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label
                                class="mb-1 block text-sm font-medium text-slate-700"
                                >RFID Tag</label
                            >
                            <input
                                v-model="registerForm.rfid_tag"
                                type="text"
                                placeholder="Enter RFID value"
                                class="w-full rounded-md border border-slate-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-100"
                            />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button
                                type="button"
                                @click="closeRegisterModal"
                                class="rounded-md border border-slate-300 px-4 py-2 text-sm"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="rounded-md bg-emerald-600 px-4 py-2 text-sm text-white hover:bg-emerald-700"
                                :disabled="registerForm.processing"
                            >
                                Register
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
import { confirmActionModal, showAlertModal } from '@/lib/feedbackModal';

interface RfidRow {
    id: string | number;
    ownerId: string;
    name: string;
    role: string;
    strand: string;
    section: string;
    year: string;
    rfid: string;
    type: 'student' | 'instructor';
}

interface UnassignedOwner {
    id: string | number;
    ownerId: string;
    name: string;
    type: 'student' | 'instructor';
}

declare function route(name: string, params?: Record<string, unknown>): string;

const props = defineProps({
    rfidRows: {
        type: Array as () => RfidRow[],
        default: () => [],
    },
    filters: {
        type: Object,
        default: () => ({ search: '', type: 'all' }),
    },
    unassignedOwners: {
        type: Array as () => UnassignedOwner[],
        default: () => [],
    },
});

const search = ref(props.filters.search ?? '');
const selectedType = ref(props.filters.type ?? 'all');
const showModal = ref(false);
const showRegisterModal = ref(false);
const selectedRow = ref<RfidRow | null>(null);

const form = useForm({ rfid_tag: '' });
const registerForm = useForm({ ownerKey: '', rfid_tag: '' });
const clearForm = useForm({});

const filteredRows = computed<RfidRow[]>(() => {
    return (props.rfidRows as RfidRow[]).filter((row) => {
        const matchesSearch =
            search.value === '' ||
            [row.name, row.ownerId, row.rfid].some((v) =>
                String(v ?? '')
                    .toLowerCase()
                    .includes(search.value.toLowerCase()),
            );

        const matchesType =
            selectedType.value === 'all' ||
            (selectedType.value === 'students' && row.type === 'student') ||
            (selectedType.value === 'instructors' && row.type === 'instructor');

        return matchesSearch && matchesType;
    });
});

// @function onFilterChange: Hinahandle ang filter change sa Rfid flow.
// @useIn onFilterChange: resources/js/pages/Admin/Rfid/RfidPage.vue template @input
const onFilterChange = () => {
    const query = {
        search: search.value,
        type: selectedType.value,
    };
    // Sync with backend route state for reload
    window.history.replaceState(
        {},
        '',
        `${window.location.pathname}?search=${encodeURIComponent(query.search)}&type=${encodeURIComponent(query.type)}`,
    );
};

const ownerSearch = ref('');

const availableOwners = computed<UnassignedOwner[]>(() => {
    const unassigned = (props.unassignedOwners as UnassignedOwner[]) || [];
    if (unassigned.length > 0) {
        return unassigned;
    }

    // if no explicit unassigned owners are returned, fall back to all rows as owner candidates
    return (props.rfidRows as RfidRow[]).map((row) => ({
        id: row.id,
        ownerId: row.ownerId,
        name: row.name,
        type: row.type,
    }));
});

const filteredOwners = computed<UnassignedOwner[]>(() => {
    const query = ownerSearch.value.trim().toLowerCase();
    if (!query) return availableOwners.value;

    return availableOwners.value.filter(
        (owner) =>
            owner.name.toLowerCase().includes(query) ||
            owner.ownerId.toString().toLowerCase().includes(query) ||
            owner.type.toLowerCase().includes(query),
    );
});

// @function resetFilters: Nire-reset ang filters sa Rfid flow.
// @useIn resetFilters: resources/js/pages/Admin/Rfid/RfidPage.vue template @click
const resetFilters = () => {
    search.value = '';
    selectedType.value = 'all';
    onFilterChange();
};

// @function openEditModal: Binubuksan ang edit modal sa Rfid flow.
// @useIn openEditModal: resources/js/pages/Admin/Rfid/RfidPage.vue template @click
const openEditModal = (row: RfidRow) => {
    selectedRow.value = row;
    form.reset();
    form.rfid_tag = row.rfid ?? '';
    showModal.value = true;
};

// @function closeModal: Isinasara ang modal sa Rfid flow.
// @useIn closeModal: resources/js/pages/Admin/Rfid/RfidPage.vue template @click
const closeModal = () => {
    showModal.value = false;
    selectedRow.value = null;
    form.reset();
};

// @function openRegisterModal: Binubuksan ang register modal sa Rfid flow.
// @useIn openRegisterModal: resources/js/pages/Admin/Rfid/RfidPage.vue template @click
const openRegisterModal = () => {
    registerForm.reset();
    showRegisterModal.value = true;
};

// @function closeRegisterModal: Isinasara ang register modal sa Rfid flow.
// @useIn closeRegisterModal: resources/js/pages/Admin/Rfid/RfidPage.vue template @click
const closeRegisterModal = () => {
    showRegisterModal.value = false;
    registerForm.reset();
};

// @function submitNewRfid: Isinusumite ang new rfid sa Rfid flow.
// @useIn submitNewRfid: resources/js/pages/Admin/Rfid/RfidPage.vue template
const submitNewRfid = () => {
    if (!registerForm.ownerKey || !registerForm.rfid_tag) {
        showAlertModal(
            'Missing required fields',
            'Please select an owner and provide an RFID tag.',
            'warning',
        );
        return;
    }

    const [ownerType, ownerId] = (registerForm.ownerKey as string).split('|');
    if (!ownerType || !ownerId) {
        showAlertModal(
            'Invalid owner',
            'Please select a valid RFID owner.',
            'error',
        );
        return;
    }

    registerForm.put(
        route('admin.rfid.update', { type: ownerType, id: ownerId }),
        {
            preserveState: true,
            onSuccess: () => {
                closeRegisterModal();
                window.location.reload();
            },
        },
    );
};

// @function submitRfid: Isinusumite ang rfid sa Rfid flow.
// @useIn submitRfid: resources/js/pages/Admin/Rfid/RfidPage.vue template
const submitRfid = () => {
    if (!selectedRow.value) return;

    form.put(
        route('admin.rfid.update', {
            type: selectedRow.value.type,
            id: selectedRow.value.id,
        }),
        {
            preserveState: true,
            onSuccess: () => {
                closeModal();
                window.location.reload();
            },
        },
    );
};

// @function clearRfid: Nililinis ang rfid sa Rfid flow.
// @useIn clearRfid: resources/js/pages/Admin/Rfid/RfidPage.vue template @click
const clearRfid = async (row: RfidRow) => {
    const confirmed = await confirmActionModal({
        title: 'Clear RFID assignment?',
        text: `Clear RFID for ${row.name}?`,
        confirmButtonText: 'Clear RFID',
    });
    if (!confirmed) return;

    clearForm.delete(
        route('admin.rfid.destroy', { type: row.type, id: row.id }),
        {
            preserveState: true,
            onSuccess: () => window.location.reload(),
        },
    );
};
</script>
