<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import Barcode from '@/components/Icon/Barcode.vue';
import BarcodeScanner from './BarcodeScanner.vue';
import SearchBar from './SearchBar.vue';
import Search from '@/components/Icon/Search.vue';
import Item from '@/components/Icon/Item.vue';
import AddButton from '@/components/Buttons/AddButton.vue';
import skuIcon from '@/components/Icon/sku.vue';
import Description from '@/components/Icon/Description.vue';
import Swal from 'sweetalert2';
import EmptyTable from '@/components/Icon/EmptyTable.vue';
import EditInventory from '@/components/Modal/EditInventory.vue';
import { computed } from 'vue';

const props = defineProps({
    table_header: { type: String, required: true },
    rows: { type: Array, default: () => [] },
});

const search = ref('');
// SEARCH BAR
const filteredRows = computed(() => {
    if (!search.value) return props.rows;
    const q = search.value.toLowerCase();
    return props.rows.filter(
        (row) =>
            row.name?.toLowerCase().includes(q) ||
            row.barcode?.toLowerCase().includes(q) ||
            row.sku?.toLowerCase().includes(q) ||
            row.description?.toLowerCase().includes(q),
    );
});

const form = useForm({
    barcode: '',
    name: '',
    sku: '',
    description: '',
});

const onAddItem = () => {
    if (!form.barcode || !form.name) {
        Swal.fire({
            title: 'Missing required fields',
            text: !form.barcode
                ? 'Barcode is required.'
                : 'Item name is required.',
            icon: 'warning',
        });
        return;
    }

    const itemName = form.name;

    form.post(route('admin.items.store'), {
        preserveScroll: true,
        onSuccess: () => {
            Swal.fire({
                title: 'Item added',
                text: `${itemName} is added`,
                icon: 'success',
            });
            form.reset();
        },
        onError: () => {
            Swal.fire({
                title: 'Failed to add item',
                text: Object.values(form.errors)[0],
                icon: 'error',
            });
        },
    });
};

const formatDate = (date) => {
    if (!date) return '—';
    return new Intl.DateTimeFormat('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    }).format(new Date(date));
};

const selectedItem = ref(null);
const showEditModal = ref(false);

const openEdit = (row) => {
    selectedItem.value = row;
    showEditModal.value = true;
};

const onDelete = (row) => {
    Swal.fire({
        title: 'Are you sure?',
        text: `Delete "${row.name}"? This can be restored later.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!',
    }).then((result) => {
        if (result.isConfirmed) {
            router.delete(route('admin.items.destroy', row.item_id), {
                preserveScroll: true,
                onSuccess: () => {
                    Swal.fire({
                        title: 'Deleted',
                        text: `${row.name} has been deleted.`,
                        icon: 'success',
                    });
                },
            });
        }
    });
};
</script>

<template>
    <div class="h-[500px] w-full overflow-auto rounded-[10px] bg-white p-5">
        <header class="flex items-center justify-between gap-5 border-b-2">
            <div class="flex w-[30%] items-center space-x-5">
                <h1 class="pb-5 text-[20px] font-medium">
                    {{ table_header }}
                </h1>
                <SearchBar
                    :icon="Search"
                    placeholder="Quick Search..."
                    v-model="search"
                />
            </div>
            <div class="mb-5 h-10 w-px self-center bg-gray-300"></div>
            <div class="flex w-full items-center space-x-5">
                <BarcodeScanner
                    v-model="form.barcode"
                    :icon="Barcode"
                    placeholder="Scan or Type the barcode"
                />
                <SearchBar
                    v-model="form.name"
                    :icon="Item"
                    placeholder="Type the item name"
                />
                <SearchBar
                    v-model="form.sku"
                    :icon="skuIcon"
                    placeholder="SKU"
                />
                <SearchBar
                    v-model="form.description"
                    :icon="Description"
                    placeholder="Description"
                />
                <AddButton @click="onAddItem" />
            </div>
        </header>

        <!-- Column Headers -->
        <div class="mt-4 flex px-4 text-center text-sm text-gray-400">
            <span class="min-w-0 flex-1">Item Name</span>
            <span class="min-w-0 flex-1">ID</span>
            <span class="min-w-0 flex-1">Barcode</span>
            <span class="min-w-0 flex-1">Description</span>
            <span class="min-w-0 flex-1">SKU</span>
            <span class="min-w-0 flex-1">Date</span>
            <span class="min-w-0 flex-1">Status</span>
            <span class="min-w-0 flex-1">Action</span>
        </div>

        <!-- Rows -->
        <div class="mt-2 flex flex-col divide-y divide-gray-100">
            <div
                v-if="filteredRows.length === 0"
                class="flex flex-col items-center justify-center py-16 text-gray-400"
            >
                <EmptyTable />
                <p class="text-sm font-medium">No items found</p>
                <p class="text-xs">Add an item using the form above.</p>
            </div>
            <div
                v-for="(row, index) in filteredRows"
                :key="index"
                class="flex items-center px-4 py-3"
            >
                <span class="min-w-0 flex-1 pr-2 text-center font-medium">{{
                    row.name
                }}</span>
                <span class="min-w-0 flex-1 pr-2 text-center">{{
                    row.item_id
                }}</span>
                <span class="min-w-0 flex-1 pr-2 text-center text-blue-500">{{
                    row.barcode
                }}</span>
                <span
                    class="min-w-0 flex-1 pr-2 text-center text-sm text-gray-600"
                    >{{ row.description ?? 'N/A' }}</span
                >
                <span class="min-w-0 flex-1 pr-2 text-center">{{
                    row.sku ?? 'N/A'
                }}</span>
                <span class="min-w-0 flex-1 pr-2 text-center text-gray-500">{{
                    formatDate(row.created_at)
                }}</span>
                <span class="min-w-0 flex-1 pr-2">
                    <div class="flex items-center justify-center">
                        <span
                            class="rounded-md px-3 py-1 text-xs"
                            :class="{
                                'bg-green-100 text-green-600':
                                    row.status === 'Available',
                                'bg-red-100 text-red-600':
                                    row.status === 'Unavailable',
                                'bg-yellow-100 text-yellow-600':
                                    row.status === 'Maintenance',
                            }"
                        >
                            {{ row.status }}
                        </span>
                    </div>
                </span>
                <span class="min-w-0 flex-1 pr-2 text-center">
                    <button
                        class="cursor-pointer rounded-md bg-blue-100 px-3 py-1 text-xs text-blue-600 hover:bg-blue-200"
                        @click="openEdit(row)"
                    >
                        Edit
                    </button>
                    <button
                        class="ml-1 cursor-pointer rounded-md bg-red-100 px-3 py-1 text-xs text-red-600 hover:bg-red-200"
                        @click="onDelete(row)"
                    >
                        Delete
                    </button>
                </span>
            </div>
        </div>
    </div>

    <EditInventory
        :show="showEditModal"
        :item="selectedItem"
        @close="showEditModal = false"
    />
</template>
