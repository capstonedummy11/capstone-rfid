<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Barcode from '@/components/Icon/Barcode.vue';
import BarcodeScanner from './BarcodeScanner.vue';
import SearchBar from './SearchBar.vue';
import Search from '@/components/Icon/Search.vue';
import Item from '@/components/Icon/Item.vue';
import AddButton from '@/components/Buttons/AddButton.vue';
import skuIcon from '@/components/Icon/sku.vue';
import Description from '@/components/Icon/Description.vue';
import Swal from 'sweetalert2';

defineProps({
    table_header: { type: String, required: true },
    rows: { type: Array, default: () => [] },
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
</script>

<template>
    <div class="h-[500px] w-full overflow-auto rounded-[10px] bg-white p-5">
        <header class="flex items-center justify-between gap-5 border-b-2">
            <div class="flex w-[30%] items-center space-x-5">
                <h1 class="pb-5 text-[20px] font-medium">
                    {{ table_header }}
                </h1>
                <SearchBar :icon="Search" placeholder="Quick Search..." />
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
        </div>

        <!-- Rows -->
        <div class="mt-2 flex flex-col divide-y divide-gray-100">
            <div
                v-for="(row, index) in rows"
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
                    >{{ row.description }}</span
                >
                <span class="min-w-0 flex-1 pr-2 text-center">{{
                    row.sku
                }}</span>
                <span class="min-w-0 flex-1 pr-2 text-center text-gray-500">{{
                    formatDate(row.created_at)
                }}</span>
                <span class="min-w-0 flex-1 pr-2">
                    <div class="flex items-center justify-center">
                        <span
                            class="rounded-md bg-green-100 px-3 py-1 text-xs text-green-600"
                            >{{ row.status }}</span
                        >
                    </div>
                </span>
            </div>
        </div>
    </div>
</template>